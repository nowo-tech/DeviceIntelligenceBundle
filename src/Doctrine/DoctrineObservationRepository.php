<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;

/**
 * Doctrine implementation of the core observation port.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineObservationRepository implements ObservationRepositoryInterface
{
    use ResolvesEntityManagerTrait;

    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private DeviceMapper $mapper,
    ) {
    }

    public function save(DeviceObservation $observation): void
    {
        $em = $this->entityManager(DeviceObservationEntity::class);
        $entity = $em->find(DeviceObservationEntity::class, $observation->id->value);
        $entity = $this->mapper->toObservationEntity(
            $observation,
            $entity instanceof DeviceObservationEntity ? $entity : null,
        );
        $em->persist($entity);
        $em->flush();
        $this->detachAll($em, [$entity]);
    }

    public function find(ObservationId $id): ?DeviceObservation
    {
        $em = $this->entityManager(DeviceObservationEntity::class);
        $entity = $this->fresh($em, $em->find(DeviceObservationEntity::class, $id->value));
        if (!$entity instanceof DeviceObservationEntity) {
            return null;
        }
        $observation = $this->mapper->toObservation($entity);
        $this->detachAll($em, [$entity]);

        return $observation;
    }

    public function latestForDevice(Device $device, int $limit = 10): array
    {
        $em = $this->entityManager(DeviceObservationEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('o')
            ->from(DeviceObservationEntity::class, 'o')
            ->where('o.deviceId = :device')
            ->setParameter('device', $device->id->value)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery())->getResult();

        $out = [];
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceObservationEntity) {
                $out[] = $this->mapper->toObservation($entity);
            }
        }
        $this->detachAll($em, $rows);

        return $out;
    }

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->entityManager(DeviceObservationEntity::class)->createQueryBuilder()
            ->delete(DeviceObservationEntity::class, 'o')
            ->where('o.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function countAll(): int
    {
        return (int) $this->entityManager(DeviceObservationEntity::class)->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(DeviceObservationEntity::class, 'o')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
