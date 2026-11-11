<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligenceBundle\Doctrine\ResolvesEntityManagerTrait;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use Nowo\DeviceIntelligenceBundle\Event\DeviceIntelligencePersonalDataErasedEvent;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default {@see DeviceIntelligencePersonalDataEraserInterface} on the bundle's Doctrine entity manager (1.9).
 *
 * DQL bulk statements only, on the connection the bundle writes through (the manager of `doctrine.entity_manager`):
 * no flush, no unit of work, no identity map. The SQL `=` only pre-filters; the stored identifier is compared with
 * `===` in PHP (a case- or accent-insensitive MySQL collation also returns `UserA` for `usera`), and rows are deleted
 * by primary key in batches of {@see self::BATCH_SIZE}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligencePersonalDataEraser implements DeviceIntelligencePersonalDataEraserInterface
{
    use ResolvesEntityManagerTrait;

    public const BATCH_SIZE = 500;

    /**
     * Stores the Doctrine registry (or a manager) and the optional dispatcher and clock.
     */
    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private ?EventDispatcherInterface $dispatcher = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function erase(DeviceIntelligencePersonalDataSubject $subject): DeviceIntelligenceErasureResult
    {
        $em = $this->entityManager(DeviceEntity::class);
        $connection = $em->getConnection();
        if ($connection->isTransactionActive()) {
            // The caller's transaction: join it, no event (it could still roll back).
            return $this->eraseRows($em, $subject->userIdentifier);
        }

        /** @var DeviceIntelligenceErasureResult $result */
        $result = $connection->transactional(fn (): DeviceIntelligenceErasureResult => $this->eraseRows($em, $subject->userIdentifier));
        $this->dispatcher?->dispatch(new DeviceIntelligencePersonalDataErasedEvent(
            $subject->userIdentifier,
            $result->toArray(),
            $this->clock?->now() ?? new DateTimeImmutable(),
        ));

        return $result;
    }

    private function eraseRows(EntityManagerInterface $em, string $userIdentifier): DeviceIntelligenceErasureResult
    {
        $deviceIds = [];
        $deleted = [];
        foreach ([DeviceObservationEntity::class, DeviceUserEntity::class, DeviceTrustEntity::class] as $class) {
            $ids = [];
            foreach (PersonalDataRows::exact($em, $class, $userIdentifier, ['deviceId']) as $row) {
                $ids[] = \is_int($row['id']) ? $row['id'] : (string) $row['id'];
                $deviceIds[(string) $row['deviceId']] = true;
            }
            $deleted[$class] = $this->deleteByIds($em, $class, $ids);
        }

        return new DeviceIntelligenceErasureResult(
            $deleted[DeviceObservationEntity::class],
            $deleted[DeviceUserEntity::class],
            $deleted[DeviceTrustEntity::class],
            $this->deleteOrphanDevices($em, array_map(strval(...), array_keys($deviceIds))),
        );
    }

    /**
     * @param class-string     $class
     * @param list<int|string> $ids
     */
    private function deleteByIds(EntityManagerInterface $em, string $class, array $ids): int
    {
        $deleted = 0;
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $deleted += (int) $em->createQuery(\sprintf('DELETE FROM %s r WHERE r.id IN (:ids)', $class))
                ->setParameter('ids', $chunk)
                ->execute();
        }

        return $deleted;
    }

    /**
     * Deletes the given devices that no link, trust grant or observation (of anyone, anonymous included) references.
     *
     * @param list<string> $deviceIds
     */
    private function deleteOrphanDevices(EntityManagerInterface $em, array $deviceIds): int
    {
        $deleted = 0;
        foreach (array_chunk($deviceIds, self::BATCH_SIZE) as $chunk) {
            $deleted += (int) $em->createQuery(\sprintf(
                'DELETE FROM %s d WHERE d.id IN (:ids)
                 AND NOT EXISTS (SELECT 1 FROM %s u WHERE u.deviceId = d.id)
                 AND NOT EXISTS (SELECT 1 FROM %s t WHERE t.deviceId = d.id)
                 AND NOT EXISTS (SELECT 1 FROM %s o WHERE o.deviceId = d.id)',
                DeviceEntity::class,
                DeviceUserEntity::class,
                DeviceTrustEntity::class,
                DeviceObservationEntity::class,
            ))
                ->setParameter('ids', $chunk)
                ->execute();
        }

        return $deleted;
    }
}
