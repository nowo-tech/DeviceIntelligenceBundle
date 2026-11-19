<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\Port\AtomicTrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;

/**
 * Doctrine implementation of the core trusted-device port.
 *
 * Rows are matched byte for byte (REQ-SEC-006, 1.9.2): every lookup re-checks `device_id` and `user_identifier` with
 * `===` after the SQL filter, which may fold on a MySQL / MariaDB collation (`josé@` = `Jose@` = `jose@ ` = `jose@`),
 * and no lookup is cut by a LIMIT before that re-check. A write never updates a look-alike's row; when a folding unique
 * key rejects the new row, {@see LookAlikeIdentifierException} is thrown (fail closed).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineTrustedDeviceRepository implements AtomicTrustedDeviceRepositoryInterface
{
    use ResolvesEntityManagerTrait;

    private const LOOK_ALIKE_MESSAGE = 'A trusted-device row of a look-alike user identifier blocks this one (folding collation on the unique key): run the ALTER of UPGRADING 1.9.2.';

    /**
     * Creates the doctrine trusted device repository.
     */
    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private DeviceMapper $mapper,
    ) {
    }

    /**
     * Persists the trusted-device record: the whole record is written over the row of exactly this device and user, or
     * inserted (1.10, REQ-RUNTIME-003 rule 4: one UPDATE by id or one INSERT, never a row read and written back; a
     * concurrent save of the same device and user is not an error, the last one wins).
     */
    public function save(TrustedDevice $trust): void
    {
        $em = $this->entityManager(DeviceTrustEntity::class);
        // 1.7 (rule 6): only this row is written; the entity manager (maybe the host's) is never flushed.
        DbalEntityWriter::upsert($em, DeviceTrustEntity::class, $this->keys($trust->deviceId, $trust->userIdentifier), $this->mapper->toTrustEntity($trust), [
            'trustedAt' => $trust->trustedAt,
            'expiresAt' => $trust->expiresAt,
            'revokedAt' => $trust->revokedAt,
            'label' => $trust->label,
            'grantedBy' => $trust->grantedBy,
        ], [], self::LOOK_ALIKE_MESSAGE);
    }

    /**
     * Sets the revocation time of the trust in one write (1.10, see
     * {@see AtomicTrustedDeviceRepositoryInterface::revoke()}): the expiry, label and grant of the row are not written.
     */
    public function revoke(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $at, string $label): void
    {
        $em = $this->entityManager(DeviceTrustEntity::class);
        DbalEntityWriter::upsert(
            $em,
            DeviceTrustEntity::class,
            $this->keys($deviceId, $user),
            $this->mapper->toTrustEntity(new TrustedDevice($deviceId, $user, $at, null, $at, $label)),
            ['revokedAt' => $at],
            [],
            self::LOOK_ALIKE_MESSAGE,
        );
    }

    /**
     * Active (not expired, not revoked) trust of the user on the device at the given time.
     */
    public function findActive(DeviceId $deviceId, UserIdentifier $user, DateTimeImmutable $now): ?TrustedDevice
    {
        $em = $this->entityManager(DeviceTrustEntity::class);
        $entity = $this->findEntity($em, $deviceId, $user);
        if (!$entity instanceof DeviceTrustEntity) {
            return null;
        }
        $trust = $this->mapper->toTrustedDevice($entity);
        $this->detachAll($em, [$entity]);
        if (!$trust->isActive($now)) {
            return null;
        }

        return $trust;
    }

    /**
     * Active trusted devices of the user at the given time.
     */
    public function forUser(UserIdentifier $user, DateTimeImmutable $now): array
    {
        $em = $this->entityManager(DeviceTrustEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('t')
            ->from(DeviceTrustEntity::class, 't')
            ->where('t.userIdentifier = :user')
            ->setParameter('user', $user->value)
            ->getQuery())->getResult();

        $out = [];
        foreach ($rows as $entity) {
            if (!$entity instanceof DeviceTrustEntity || $entity->getUserIdentifier() !== $user->value) {
                continue;
            }
            $trust = $this->mapper->toTrustedDevice($entity);
            if ($trust->isActive($now)) {
                $out[] = $trust;
            }
        }
        $this->detachAll($em, $rows);

        return $out;
    }

    /**
     * Number of stored trusted-device records.
     */
    public function countAll(): int
    {
        return (int) $this->entityManager(DeviceTrustEntity::class)->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(DeviceTrustEntity::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array{deviceId: string, userIdentifier: string}
     */
    private function keys(DeviceId $deviceId, UserIdentifier $user): array
    {
        return ['deviceId' => $deviceId->value, 'userIdentifier' => $user->value];
    }

    /**
     * The row of exactly this device and user. Every row the (maybe folding) SQL filter returns is read, without LIMIT,
     * and the one whose device and user are byte-identical is kept, so a look-alike's row is never returned or updated.
     */
    private function findEntity(EntityManagerInterface $em, DeviceId $deviceId, UserIdentifier $user): ?DeviceTrustEntity
    {
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('t')
            ->from(DeviceTrustEntity::class, 't')
            ->where('t.deviceId = :device')
            ->andWhere('t.userIdentifier = :user')
            ->setParameter('device', $deviceId->value)
            ->setParameter('user', $user->value)
            ->getQuery())->getResult();

        $found = null;
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceTrustEntity && null === $found && $entity->getDeviceId() === $deviceId->value && $entity->getUserIdentifier() === $user->value) {
                $found = $entity;

                continue;
            }
            $this->detachAll($em, [$entity]);
        }

        return $this->fresh($em, $found);
    }
}
