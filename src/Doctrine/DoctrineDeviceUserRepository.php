<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;

/**
 * Doctrine implementation of the core device-user port.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineDeviceUserRepository implements DeviceUserRepositoryInterface
{
    use ResolvesEntityManagerTrait;

    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private DeviceMapper $mapper,
    ) {
    }

    public function save(DeviceUserRelation $relation): void
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $existing = $this->findEntity($em, $relation->deviceId, $relation->userIdentifier);
        $entity = $this->mapper->toUserEntity($relation, $existing);
        $em->persist($entity);
        $em->flush();
        $this->detachAll($em, [$entity]);
    }

    public function forDevice(DeviceId $deviceId): array
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.deviceId = :device')
            ->setParameter('device', $deviceId->value)
            ->getQuery())->getResult();

        return $this->mapRows($em, $rows);
    }

    public function forUser(UserIdentifier $user): array
    {
        $em = $this->entityManager(DeviceUserEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.userIdentifier = :user')
            ->setParameter('user', $user->value)
            ->getQuery())->getResult();

        return $this->mapRows($em, $rows);
    }

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

    public function countAll(): int
    {
        return (int) $this->entityManager(DeviceUserEntity::class)->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(DeviceUserEntity::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findEntity(EntityManagerInterface $em, DeviceId $deviceId, UserIdentifier $user): ?DeviceUserEntity
    {
        $entity = $this->withRefresh($em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.deviceId = :device')
            ->andWhere('r.userIdentifier = :user')
            ->setParameter('device', $deviceId->value)
            ->setParameter('user', $user->value)
            ->setMaxResults(1)
            ->getQuery())->getOneOrNullResult();
        $entity = $this->fresh($em, $entity instanceof DeviceUserEntity ? $entity : null);

        return $entity instanceof DeviceUserEntity ? $entity : null;
    }

    /**
     * @param list<mixed> $rows
     *
     * @return list<DeviceUserRelation>
     */
    private function mapRows(EntityManagerInterface $em, array $rows): array
    {
        $out = [];
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceUserEntity) {
                $out[] = $this->mapper->toUserRelation($entity);
            }
        }
        $this->detachAll($em, $rows);

        return $out;
    }
}
