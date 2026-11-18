<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\Port\AtomicDeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;

/**
 * Doctrine implementation of the core device-user port.
 *
 * Rows are matched byte for byte (REQ-SEC-006, 1.9.2): every lookup re-checks `device_id` / `user_identifier` with
 * `===` after the SQL filter, which may fold on a MySQL / MariaDB collation, and no lookup is cut by a LIMIT before
 * that re-check. A write never updates a look-alike's row; when a folding unique key rejects the new row,
 * {@see LookAlikeIdentifierException} is thrown (fail closed). Stored identifiers that are no longer usable
 * ({@see UserIdentifier}) are skipped.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineDeviceUserRepository implements AtomicDeviceUserRepositoryInterface
{
    use ResolvesEntityManagerTrait;

    private const LOOK_ALIKE_MESSAGE = 'A device-user row of a look-alike user identifier blocks this one (folding collation on the unique key): run the ALTER of UPGRADING 1.9.2.';

    /**
     * Creates the doctrine device user repository.
     */
    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private DeviceMapper $mapper,
    ) {
    }

    /**
     * Persists the device/user relation: written over the row of exactly this device and user, or inserted (1.10,
     * REQ-RUNTIME-003 rule 4: one UPDATE by id or one INSERT; a concurrent save of the same pair is not an error).
     * Logins go through {@see self::recordLogin()}, which counts in storage.
     */
    public function save(DeviceUserRelation $relation): void
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        // 1.7 (rule 6): only this row is written; the entity manager (maybe the host's) is never flushed.
        DbalEntityWriter::upsert($em, DeviceUserEntity::class, $this->keys($relation->deviceId, $relation->userIdentifier), $this->mapper->toUserEntity($relation), [
            'firstSeenAt' => $relation->firstSeenAt,
            'lastSeenAt' => $relation->lastSeenAt,
            'loginCount' => $relation->loginCount,
        ], [], self::LOOK_ALIKE_MESSAGE);
    }

    /**
     * Records one login (1.10, see {@see AtomicDeviceUserRepositoryInterface::recordLogin()}): `login_count + 1` and
     * the last-seen time in one UPDATE, or the first link; two logins at once both count.
     */
    public function recordLogin(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $at): DeviceUserRelation
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $first = new DeviceUserRelation($deviceId, $user, $at, $at, 1);
        DbalEntityWriter::upsert($em, DeviceUserEntity::class, $this->keys($deviceId, $user), $this->mapper->toUserEntity($first), [
            'lastSeenAt' => $at,
        ], ['loginCount' => 1], self::LOOK_ALIKE_MESSAGE);

        return $this->find($deviceId, $user) ?? $first;
    }

    /**
     * Relations of the given device.
     */
    public function forDevice(DeviceId $deviceId): array
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.deviceId = :device')
            ->setParameter('device', $deviceId->value)
            ->getQuery())->getResult();

        return $this->mapRows($em, $rows, $deviceId->value, null);
    }

    /**
     * Relations of the given user.
     */
    public function forUser(UserIdentifier $user): array
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.userIdentifier = :user')
            ->setParameter('user', $user->value)
            ->getQuery())->getResult();

        return $this->mapRows($em, $rows, null, $user->value);
    }

    /**
     * Loads the relation between a device and a user.
     */
    public function find(DeviceId $deviceId, UserIdentifier $user): ?DeviceUserRelation
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $entity = $this->findEntity($em, $deviceId, $user);
        if (!$entity instanceof DeviceUserEntity) {
            return null;
        }
        $relation = $this->mapper->toUserRelation($entity);
        $this->detachAll($em, [$entity]);

        return $relation;
    }

    /**
     * @return array{deviceId: string, userIdentifier: string}
     */
    private function keys(DeviceId $deviceId, UserIdentifier $user): array
    {
        return ['deviceId' => $deviceId->value, 'userIdentifier' => $user->value];
    }

    /**
     * Number of stored device/user relations.
     */
    public function countAll(): int
    {
        return (int) $this->entityManager(DeviceUserEntity::class)->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(DeviceUserEntity::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * The row of exactly this device and user. Every row the (maybe folding) SQL filter returns is read, without LIMIT,
     * and the one whose device and user are byte-identical is kept, so a look-alike's row is never returned or updated.
     */
    private function findEntity(EntityManagerInterface $em, DeviceId $deviceId, UserIdentifier $user): ?DeviceUserEntity
    {
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.deviceId = :device')
            ->andWhere('r.userIdentifier = :user')
            ->setParameter('device', $deviceId->value)
            ->setParameter('user', $user->value)
            ->getQuery())->getResult();

        $found = null;
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceUserEntity && null === $found && $entity->getDeviceId() === $deviceId->value && $entity->getUserIdentifier() === $user->value) {
                $found = $entity;

                continue;
            }
            $this->detachAll($em, [$entity]);
        }

        return $this->fresh($em, $found);
    }

    /**
     * Maps the rows whose device / user are byte-identical to the requested ones (null: not filtered).
     *
     * @param list<mixed> $rows
     *
     * @return list<DeviceUserRelation>
     */
    private function mapRows(EntityManagerInterface $em, array $rows, ?string $deviceId, ?string $user): array
    {
        $out = [];
        foreach ($rows as $entity) {
            if (!$entity instanceof DeviceUserEntity
                || (null !== $deviceId && $entity->getDeviceId() !== $deviceId)
                || (null !== $user && $entity->getUserIdentifier() !== $user)) {
                continue;
            }

            try {
                $out[] = $this->mapper->toUserRelation($entity);
            } catch (InvalidValueException) {
                // a legacy identifier that is no longer usable (item 1): unreachable, never mapped onto another user
            }
        }
        $this->detachAll($em, $rows);

        return $out;
    }
}
