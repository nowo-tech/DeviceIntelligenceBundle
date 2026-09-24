<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;

/**
 * Doctrine implementation of the core trusted-device port.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineTrustedDeviceRepository implements TrustedDeviceRepositoryInterface
{
    use ResolvesEntityManagerTrait;

    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private DeviceMapper $mapper,
    ) {
    }

    public function save(TrustedDevice $trust): void
    {
        $em = $this->entityManager(DeviceTrustEntity::class);
        $existing = $this->findEntity($em, $trust->deviceId, $trust->userIdentifier);
        $entity = $this->mapper->toTrustEntity($trust, $existing);
        $em->persist($entity);
        $em->flush();
        $this->detachAll($em, [$entity]);
    }

    public function findActive(DeviceId $deviceId, UserIdentifier $user, \DateTimeImmutable $now): ?TrustedDevice
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

    public function forUser(UserIdentifier $user, \DateTimeImmutable $now): array
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
            if (!$entity instanceof DeviceTrustEntity) {
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

    public function countAll(): int
    {
        return (int) $this->entityManager(DeviceTrustEntity::class)->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(DeviceTrustEntity::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findEntity(EntityManagerInterface $em, DeviceId $deviceId, UserIdentifier $user): ?DeviceTrustEntity
    {
        $entity = $this->withRefresh($em->createQueryBuilder()
            ->select('t')
            ->from(DeviceTrustEntity::class, 't')
            ->where('t.deviceId = :device')
            ->andWhere('t.userIdentifier = :user')
            ->setParameter('device', $deviceId->value)
            ->setParameter('user', $user->value)
            ->setMaxResults(1)
            ->getQuery())->getOneOrNullResult();
        $entity = $this->fresh($em, $entity instanceof DeviceTrustEntity ? $entity : null);

        return $entity instanceof DeviceTrustEntity ? $entity : null;
    }
}
