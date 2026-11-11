<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\PersonalData;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligenceBundle\Doctrine\ResolvesEntityManagerTrait;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use Psr\Clock\ClockInterface;

/**
 * Default {@see DeviceIntelligencePersonalDataExporterInterface} on the bundle's Doctrine entity manager (1.9).
 *
 * Read-only array results (no entity is hydrated or kept managed); the stored identifier is compared with `===`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DeviceIntelligencePersonalDataExporter implements DeviceIntelligencePersonalDataExporterInterface
{
    use ResolvesEntityManagerTrait;

    /**
     * Stores the Doctrine registry (or a manager) and the optional clock (trust expiry).
     */
    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function export(DeviceIntelligencePersonalDataSubject $subject): array
    {
        $em = $this->entityManager(DeviceEntity::class);
        $user = $subject->userIdentifier;

        $links = [];
        foreach (PersonalDataRows::exact($em, DeviceUserEntity::class, $user, ['deviceId', 'firstSeenAt', 'lastSeenAt', 'loginCount']) as $row) {
            $links[(string) $row['deviceId']] = $row;
        }
        $trusts = [];
        foreach (PersonalDataRows::exact($em, DeviceTrustEntity::class, $user, ['deviceId', 'trustedAt', 'expiresAt', 'revokedAt']) as $row) {
            $trusts[(string) $row['deviceId']] = $row;
        }
        if ([] === $links && [] === $trusts) {
            return [];
        }

        $order = [];
        foreach ($links as $deviceId => $link) {
            $order[$deviceId] = [self::timestamp($link['firstSeenAt']), (string) $deviceId];
        }
        foreach ($trusts as $deviceId => $trust) {
            $order[$deviceId] ??= [self::timestamp($trust['trustedAt']), (string) $deviceId];
        }
        uasort($order, static fn (array $a, array $b): int => $a <=> $b);

        $devices = $this->devices($em, array_map(strval(...), array_keys($order)));
        $now = $this->clock?->now() ?? new DateTimeImmutable();
        $rows = [];
        foreach (array_keys($order) as $deviceId) {
            $deviceId = (string) $deviceId;
            $link = $links[$deviceId] ?? null;
            $trust = $trusts[$deviceId] ?? null;
            $device = $devices[$deviceId] ?? null;
            $expiresAt = $trust['expiresAt'] ?? null;
            $rows[] = [
                'first_seen_at' => self::atom($link['firstSeenAt'] ?? null),
                'last_seen_at' => self::atom($link['lastSeenAt'] ?? null),
                'sign_ins' => (int) ($link['loginCount'] ?? 0),
                'os_family' => $device['osFamily'] ?? null,
                'browser_family' => $device['browserFamily'] ?? null,
                'trusted' => null !== $trust && null === $trust['revokedAt'] && (!$expiresAt instanceof DateTimeInterface || $expiresAt > $now),
                'trusted_at' => self::atom($trust['trustedAt'] ?? null),
                'trust_expires_at' => self::atom($expiresAt),
            ];
        }

        return $rows;
    }

    /**
     * OS and browser family of the given devices, by id.
     *
     * @param list<string> $deviceIds
     *
     * @return array<string, array{osFamily: string, browserFamily: string}>
     */
    private function devices(EntityManagerInterface $em, array $deviceIds): array
    {
        $devices = [];
        foreach (array_chunk($deviceIds, DeviceIntelligencePersonalDataEraser::BATCH_SIZE) as $chunk) {
            /** @var list<array{id: string, osFamily: string, browserFamily: string}> $rows */
            $rows = $em->createQuery(\sprintf('SELECT d.id AS id, d.osFamily AS osFamily, d.browserFamily AS browserFamily FROM %s d WHERE d.id IN (:ids)', DeviceEntity::class))
                ->setParameter('ids', $chunk)
                ->getArrayResult();
            foreach ($rows as $row) {
                $devices[$row['id']] = ['osFamily' => $row['osFamily'], 'browserFamily' => $row['browserFamily']];
            }
        }

        return $devices;
    }

    private static function timestamp(mixed $value): int
    {
        return $value instanceof DateTimeInterface ? $value->getTimestamp() : \PHP_INT_MAX;
    }

    private static function atom(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : null;
    }
}
