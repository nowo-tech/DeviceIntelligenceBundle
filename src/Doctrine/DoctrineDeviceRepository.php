<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Port\AtomicDeviceRepositoryInterface;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;

/**
 * Doctrine implementation of the core device port.
 *
 * 1.10 (REQ-RUNTIME-003 rule 4): an observed device is written with `observation_count = observation_count + 1` and
 * without its status, first-seen time and metadata ({@see self::saveObserved()}), and the stability recalculation writes
 * the stability column alone ({@see self::saveStability()}), so concurrent analyses never lose a count and never revert
 * a status the host changed meanwhile.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineDeviceRepository implements AtomicDeviceRepositoryInterface
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
        $found = $em->find(DeviceEntity::class, $device->id->value);
        $entity = $this->mapper->toDeviceEntity($device, $found instanceof DeviceEntity ? $found : null);
        // 1.7 (rule 6): only this row is written; the entity manager (maybe the host's) is never flushed.
        $this->detachAll($em, [$entity]);
        DbalEntityWriter::write($em, $entity, $found instanceof DeviceEntity);
    }

    /**
     * Stores a device that has just gained one observation (see {@see AtomicDeviceRepositoryInterface::saveObserved()}).
     */
    public function saveObserved(Device $device): void
    {
        $em = $this->entityManager(DeviceEntity::class);
        $entity = $this->mapper->toDeviceEntity($device);
        $updated = DbalEntityWriter::update($em, DeviceEntity::class, $device->id->value, [
            'lastSeenAt' => $entity->getLastSeenAt(),
            'confidence' => $entity->getConfidence(),
            'stability' => $entity->getStability(),
            'osFamily' => $entity->getOsFamily(),
            'browserFamily' => $entity->getBrowserFamily(),
            'gpuFamily' => $entity->getGpuFamily(),
            'screenClass' => $entity->getScreenClass(),
            'timezone' => $entity->getTimezone(),
            'blockingKey' => $entity->getBlockingKey(),
            'label' => $entity->getLabel(),
            'lastSignals' => $entity->getLastSignals(),
        ], ['observationCount' => 1]);
        if (0 !== $updated) {
            return;
        }

        try {
            // erased or cleaned up since it was matched: stored again as observed now (as save() does)
            DbalEntityWriter::write($em, $entity, false);
        } catch (UniqueConstraintViolationException) {
            // a concurrent analysis stored it first: count this observation on its row
            DbalEntityWriter::update($em, DeviceEntity::class, $device->id->value, [], ['observationCount' => 1]);
        }
    }

    /**
     * Writes only the stability of a stored device.
     */
    public function saveStability(DeviceId $id, Stability $stability): void
    {
        DbalEntityWriter::update($this->entityManager(DeviceEntity::class), DeviceEntity::class, $id->value, ['stability' => $stability->value]);
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
