<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
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
    public function __construct(
        private EntityManagerInterface $em,
        private DeviceMapper $mapper,
    ) {
    }

    public function save(TrustedDevice $trust): void
    {
        $existing = $this->findEntity($trust->deviceId, $trust->userIdentifier);
        $entity = $this->mapper->toTrustEntity($trust, $existing);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findActive(DeviceId $deviceId, UserIdentifier $user, \DateTimeImmutable $now): ?TrustedDevice
    {
        $entity = $this->findEntity($deviceId, $user);
        if (!$entity instanceof DeviceTrustEntity) {
            return null;
        }
        $trust = $this->mapper->toTrustedDevice($entity);
        if (!$trust->isActive($now)) {
            return null;
        }

        return $trust;
    }

    public function forUser(UserIdentifier $user, \DateTimeImmutable $now): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('t')
            ->from(DeviceTrustEntity::class, 't')
            ->where('t.userIdentifier = :user')
            ->setParameter('user', $user->value)
            ->getQuery()
            ->getResult();

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

        return $out;
    }

    public function countAll(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(DeviceTrustEntity::class, 't')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function findEntity(DeviceId $deviceId, UserIdentifier $user): ?DeviceTrustEntity
    {
        $entity = $this->em->createQueryBuilder()
            ->select('t')
            ->from(DeviceTrustEntity::class, 't')
            ->where('t.deviceId = :device')
            ->andWhere('t.userIdentifier = :user')
            ->setParameter('device', $deviceId->value)
            ->setParameter('user', $user->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof DeviceTrustEntity ? $entity : null;
    }
}
