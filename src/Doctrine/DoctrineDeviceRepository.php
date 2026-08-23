<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(
        private EntityManagerInterface $em,
        private DeviceMapper $mapper,
    ) {
    }

    public function find(DeviceId $id): ?Device
    {
        $entity = $this->em->find(DeviceEntity::class, $id->value);
        if (!$entity instanceof DeviceEntity) {
            return null;
        }

        return $this->mapper->toDevice($entity);
    }

    public function save(Device $device): void
    {
        $entity = $this->em->find(DeviceEntity::class, $device->id->value);
        $entity = $this->mapper->toDeviceEntity($device, $entity instanceof DeviceEntity ? $entity : null);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findCandidates(
        string $osFamily,
        string $browserFamily,
        ?string $timezone,
        ?string $gpuFamily,
        int $limit,
        DateTimeImmutable $since,
    ): array {
        $qb = $this->em->createQueryBuilder()
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

        if ($timezone !== null) {
            $qb->andWhere('d.timezone = :tz')->setParameter('tz', $timezone);
        }
        if ($gpuFamily !== null) {
            $qb->andWhere('d.gpuFamily = :gpu')->setParameter('gpu', $gpuFamily);
        }

        $out = [];
        foreach ($qb->getQuery()->getResult() as $entity) {
            if ($entity instanceof DeviceEntity) {
                $out[] = $this->mapper->toDevice($entity);
            }
        }

        return $out;
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
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
        $rows = $this->em->createQueryBuilder()
            ->select('d')
            ->from(DeviceEntity::class, 'd')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $entity) {
            if ($entity instanceof DeviceEntity) {
                $out[] = $this->mapper->toDevice($entity);
            }
        }

        return $out;
    }
}
