<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(
        private EntityManagerInterface $em,
        private DeviceMapper $mapper,
    ) {
    }

    public function save(DeviceUserRelation $relation): void
    {
        $existing = $this->findEntity($relation->deviceId, $relation->userIdentifier);
        $entity   = $this->mapper->toUserEntity($relation, $existing);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function forDevice(DeviceId $deviceId): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.deviceId = :device')
            ->setParameter('device', $deviceId->value)
            ->getQuery()
            ->getResult();

        return $this->mapRows($rows);
    }

    public function forUser(UserIdentifier $user): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.userIdentifier = :user')
            ->setParameter('user', $user->value)
            ->getQuery()
            ->getResult();

        return $this->mapRows($rows);
    }

    public function find(DeviceId $deviceId, UserIdentifier $user): ?DeviceUserRelation
    {
        $entity = $this->findEntity($deviceId, $user);
        if (!$entity instanceof DeviceUserEntity) {
            return null;
        }

        return $this->mapper->toUserRelation($entity);
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(DeviceUserEntity::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findEntity(DeviceId $deviceId, UserIdentifier $user): ?DeviceUserEntity
    {
        $entity = $this->em->createQueryBuilder()
            ->select('r')
            ->from(DeviceUserEntity::class, 'r')
            ->where('r.deviceId = :device')
            ->andWhere('r.userIdentifier = :user')
            ->setParameter('device', $deviceId->value)
            ->setParameter('user', $user->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof DeviceUserEntity ? $entity : null;
    }

    /**
     * @param list<mixed> $rows
     *
     * @return list<DeviceUserRelation>
     */
    private function mapRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceUserEntity) {
                $out[] = $this->mapper->toUserRelation($entity);
            }
        }

        return $out;
    }
}
