<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;

/**
 * Doctrine implementation of the core device port.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineDeviceRepository implements DeviceRepositoryInterface
{
    use ResolvesEntityManagerTrait;

    /**
     * Creates the doctrine device repository.
     */
    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private DeviceMapper $mapper,
    ) {
    }

    /**
     * Loads a device by id.
     */
    public function find(DeviceId $id): ?Device
    {
        $em = $this->entityManager(DeviceEntity::class);
        $entity = $this->fresh($em, $em->find(DeviceEntity::class, $id->value));
        if (!$entity instanceof DeviceEntity) {
            return null;
        }
        $device = $this->mapper->toDevice($entity);
        $this->detachAll($em, [$entity]);

        return $device;
    }

    /**
     * Persists the device.
     */
    public function save(Device $device): void
    {
        $em = $this->entityManager(DeviceEntity::class);
        $entity = $em->find(DeviceEntity::class, $device->id->value);
        $entity = $this->mapper->toDeviceEntity($device, $entity instanceof DeviceEntity ? $entity : null);
        $em->persist($entity);
        $em->flush();
        $this->detachAll($em, [$entity]);
    }

    /**
     * Returns stored devices that may match the given fingerprint signals.
     */
    public function findCandidates(
        string $osFamily,
        string $browserFamily,
        ?string $timezone,
        ?string $gpuFamily,
        int $limit,
        DateTimeImmutable $since,
    ): array {
        $em = $this->entityManager(DeviceEntity::class);
        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(DeviceEntity::class, 'd')
            ->where('d.status = :status')
            ->andWhere('d.lastSeenAt >= :since')
            ->andWhere('d.osFamily = :os')
            ->andWhere('d.browserFamily = :browser')
            ->setParameter('status', DeviceStatus::Active->value)
            ->setParameter('since', $since)
            ->setParameter('os', $osFamily)
            ->setParameter('browser', $browserFamily)
            ->setMaxResults($limit);

        if (null !== $timezone) {
            $qb->andWhere('d.timezone = :tz')->setParameter('tz', $timezone);
        }
        if (null !== $gpuFamily) {
            $qb->andWhere('d.gpuFamily = :gpu')->setParameter('gpu', $gpuFamily);
        }

        $rows = $this->withRefresh($qb->getQuery())->getResult();
        $out = [];
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceEntity) {
                $out[] = $this->mapper->toDevice($entity);
            }
        }
        $this->detachAll($em, $rows);

        return $out;
    }

    /**
     * Number of stored devices.
     */
    public function countAll(): int
    {
        return (int) $this->entityManager(DeviceEntity::class)->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(DeviceEntity::class, 'd')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Device>
     */
    public function all(): array
    {
        $em = $this->entityManager(DeviceEntity::class);
        $rows = $this->withRefresh($em->createQueryBuilder()
            ->select('d')
            ->from(DeviceEntity::class, 'd')
            ->getQuery())->getResult();

        $out = [];
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceEntity) {
                $out[] = $this->mapper->toDevice($entity);
            }
        }
        $this->detachAll($em, $rows);

        return $out;
    }
}
