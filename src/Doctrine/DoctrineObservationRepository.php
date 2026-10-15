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
    /** Rows per purge statement ({@see deleteOlderThan()}). */
    public const PURGE_BATCH_SIZE = 1000;

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
            ->orderBy('o.createdAt', \SortDirection::Descending)
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

    /**
     * Deletes observations older than $cutoff in keyset batches (`id > :last ORDER BY id LIMIT n`), one
     * short statement per batch instead of one unbounded DELETE that locks the table on big instances.
     */
    public function deleteOlderThan(\DateTimeImmutable $cutoff, int $batchSize = self::PURGE_BATCH_SIZE): int
    {
        $em = $this->entityManager(DeviceObservationEntity::class);
        $batchSize = max(1, $batchSize);
        $deleted = 0;
        $last = null;
        do {
            $select = $em->createQueryBuilder()
                ->select('o.id')
                ->from(DeviceObservationEntity::class, 'o')
                ->where('o.createdAt < :cutoff')
                ->setParameter('cutoff', $cutoff)
                ->orderBy('o.id', 'ASC')
                ->setMaxResults($batchSize);
            if (null !== $last) {
                $select->andWhere('o.id > :last')->setParameter('last', $last);
            }
            /** @var list<string> $ids */
            $ids = $select->getQuery()->getSingleColumnResult();
            if ([] === $ids) {
                break;
            }
            $deleted += (int) $em->createQueryBuilder()
                ->delete(DeviceObservationEntity::class, 'o')
                ->where('o.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();
            $last = $ids[\count($ids) - 1];
        } while (\count($ids) === $batchSize);

        return $deleted;
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
