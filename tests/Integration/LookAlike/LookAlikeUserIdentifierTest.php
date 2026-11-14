<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\LookAlike;

use DateTimeImmutable;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\ExactCollationListener;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * REQ-SEC-006 (1.9.2): trusted-device and device-user rows are never resolved, listed or overwritten across look-alike
 * user identifiers (`Bob`, `bób`, `bob `, `bob\n`, NBSP, ZWSP).
 *
 * Runs on SQLite with `COLLATE NOCASE` columns always, and on MySQL / MariaDB when DEVICE_INTELLIGENCE_TEST_MYSQL_URL /
 * DEVICE_INTELLIGENCE_TEST_MARIADB_URL point at a throwaway database (the tables are dropped first). The "legacy"
 * variants keep the server's default folding collation, unique keys included (tables created before 1.9.2, without the
 * ALTER of UPGRADING): the repositories' `===` re-check alone must keep the users apart.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class LookAlikeUserIdentifierTest extends TestCase
{
    private const OWNER = 'bob@example.com';

    /** Look-alikes a UserIdentifier accepts (another, distinct account). */
    private const VALID_LOOK_ALIKES = ['Bob@example.com', 'bób@example.com'];

    /** Look-alikes 1.9.2 refuses, present as legacy rows. */
    private const LEGACY_LOOK_ALIKES = ['bob@example.com ', "bob@example.com\n", "bob@example.com\u{00A0}", "bob@example.com\u{200B}"];

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function platforms(): iterable
    {
        yield 'sqlite nocase' => ['sqlite', false];
        yield 'mysql legacy collation' => ['DEVICE_INTELLIGENCE_TEST_MYSQL_URL', false];
        yield 'mysql exact collation' => ['DEVICE_INTELLIGENCE_TEST_MYSQL_URL', true];
        yield 'mariadb legacy collation' => ['DEVICE_INTELLIGENCE_TEST_MARIADB_URL', false];
        yield 'mariadb exact collation' => ['DEVICE_INTELLIGENCE_TEST_MARIADB_URL', true];
    }

    /**
     * F3: the attacker trusts their own device as a look-alike of bob; bob is never trusted on it through that row,
     * and bob trusting or revoking that device never rewrites the attacker's row.
     */
    #[DataProvider('platforms')]
    public function testTrustIsNeverResolvedOrOverwrittenAcrossLookAlikes(string $source, bool $exactCollation): void
    {
        $em = $this->entityManager($source, $exactCollation);
        $trusts = new DoctrineTrustedDeviceRepository($this->registry($em), new DeviceMapper());
        $now = new DateTimeImmutable('2026-11-13 10:00:00');
        $owner = new UserIdentifier(self::OWNER);

        $devices = [];
        foreach ([...self::VALID_LOOK_ALIKES, ...self::LEGACY_LOOK_ALIKES] as $i => $lookAlike) {
            $device = DeviceId::generate($now->modify('+'.$i.' seconds'));
            $devices[$lookAlike] = $device;
            $this->insertTrust($em->getConnection(), $device, $lookAlike, 'attacker '.$i);
        }

        $this->assertSame([], $trusts->forUser($owner, $now));
        foreach ($devices as $lookAlike => $device) {
            $label = json_encode($lookAlike, \JSON_THROW_ON_ERROR);
            $this->assertNull($trusts->findActive($device, $owner, $now), $label);

            // bob trusts, then revokes, the attacker's device: either a row of his own or a fail-closed refusal
            foreach ([null, $now] as $revokedAt) {
                try {
                    $trusts->save(new TrustedDevice($device, $owner, $now, null, $revokedAt, 'bob'));
                    $this->assertSame(null === $revokedAt, null !== $trusts->findActive($device, $owner, $now), $label);
                } catch (LookAlikeIdentifierException) {
                    $this->assertFalse($exactCollation, 'the exact collation never folds the unique key');
                    $this->assertNull($trusts->findActive($device, $owner, $now), $label);
                }
            }

            $rows = $em->getConnection()->fetchAllAssociative('SELECT user_identifier, label, revoked_at FROM device_trust WHERE device_id = ? ORDER BY id', [$device->value]);
            $this->assertSame($lookAlike, $rows[0]['user_identifier'], $label);
            $this->assertStringStartsWith('attacker ', $rows[0]['label'], $label);
            $this->assertNull($rows[0]['revoked_at'], $label);
            if ($exactCollation) {
                $this->assertCount(2, $rows, $label);
                $this->assertSame(self::OWNER, $rows[1]['user_identifier']);
            }
        }

        foreach (self::VALID_LOOK_ALIKES as $lookAlike) {
            $own = $trusts->findActive($devices[$lookAlike], new UserIdentifier($lookAlike), $now);
            $this->assertNotNull($own);
            $this->assertSame($lookAlike, $own->userIdentifier->value);
            $this->assertCount(1, $trusts->forUser(new UserIdentifier($lookAlike), $now));
        }
    }

    #[DataProvider('platforms')]
    public function testDeviceUserRelationsAreNeverSharedAcrossLookAlikes(string $source, bool $exactCollation): void
    {
        $em = $this->entityManager($source, $exactCollation);
        $users = new DoctrineDeviceUserRepository($this->registry($em), new DeviceMapper());
        $now = new DateTimeImmutable('2026-11-13 10:00:00');
        $owner = new UserIdentifier(self::OWNER);
        $ownDevices = [];

        foreach ([...self::VALID_LOOK_ALIKES, ...self::LEGACY_LOOK_ALIKES] as $i => $lookAlike) {
            $label = json_encode($lookAlike, \JSON_THROW_ON_ERROR);
            $device = DeviceId::generate($now->modify('+'.$i.' seconds'));
            $this->insertRelation($em->getConnection(), $device, $lookAlike, 7);

            $this->assertNull($users->find($device, $owner), $label);
            // only the devices bob himself was linked to in the previous rounds, never a look-alike's
            $listedOwn = array_map(static fn (DeviceUserRelation $relation): string => $relation->deviceId->value, $users->forUser($owner));
            sort($listedOwn);
            $this->assertSame($ownDevices, $listedOwn, $label);
            $listed = array_map(static fn (DeviceUserRelation $relation): string => $relation->userIdentifier->value, $users->forDevice($device));
            $this->assertSame(\in_array($lookAlike, self::VALID_LOOK_ALIKES, true) ? [$lookAlike] : [], $listed, $label.' (legacy unusable identifiers are skipped)');

            try {
                $users->save(new DeviceUserRelation($device, $owner, $now, $now, 1));
                $this->assertSame(1, $users->find($device, $owner)?->loginCount, $label);
                $ownDevices[] = $device->value;
                sort($ownDevices);
            } catch (LookAlikeIdentifierException) {
                $this->assertFalse($exactCollation, 'the exact collation never folds the unique key');
                $this->assertNull($users->find($device, $owner), $label);
            }

            $rows = $em->getConnection()->fetchAllAssociative('SELECT user_identifier, login_count FROM device_user WHERE device_id = ? ORDER BY id', [$device->value]);
            $this->assertSame($lookAlike, $rows[0]['user_identifier'], $label);
            $this->assertSame(7, (int) $rows[0]['login_count'], $label.': the look-alike row is never updated');
        }
    }

    /**
     * The listener gives the identity columns of the three tables the platform's NO PAD binary collation.
     */
    #[DataProvider('platforms')]
    public function testTheListenerSetsTheExactCollation(string $source, bool $exactCollation): void
    {
        if (!$exactCollation) {
            $this->addToAssertionCount(1); // the legacy variants keep the server's default collation

            return;
        }
        $connection = $this->entityManager($source, true)->getConnection();
        $platform = $connection->getDatabasePlatform();
        $this->assertInstanceOf(AbstractMySQLPlatform::class, $platform);
        $expected = ExactCollationListener::collationFor($platform);
        $this->assertContains($expected, ['utf8mb4_0900_bin', 'utf8mb4_nopad_bin']);

        $collations = $connection->fetchAllNumeric(
            "SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('device_trust', 'device_user', 'observation') AND COLUMN_NAME IN ('device_id', 'user_identifier') ORDER BY TABLE_NAME, COLUMN_NAME",
        );
        $this->assertSame([
            ['device_trust', 'device_id', $expected],
            ['device_trust', 'user_identifier', $expected],
            ['device_user', 'device_id', $expected],
            ['device_user', 'user_identifier', $expected],
            ['observation', 'device_id', $expected],
            ['observation', 'user_identifier', $expected],
        ], $collations);
    }

    private function insertTrust(Connection $connection, DeviceId $device, string $user, string $label): void
    {
        $connection->insert('device_trust', [
            'device_id' => $device->value,
            'user_identifier' => $user,
            'trusted_at' => '2026-11-13 09:00:00',
            'expires_at' => null,
            'revoked_at' => null,
            'label' => $label,
            'granted_by' => 'user',
        ]);
    }

    private function insertRelation(Connection $connection, DeviceId $device, string $user, int $logins): void
    {
        $connection->insert('device_user', [
            'device_id' => $device->value,
            'user_identifier' => $user,
            'first_seen_at' => '2026-11-13 09:00:00',
            'last_seen_at' => '2026-11-13 09:00:00',
            'login_count' => $logins,
        ]);
    }

    private function registry(EntityManager $em): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        return $registry;
    }

    private function entityManager(string $source, bool $exactCollation): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        if ('sqlite' === $source) {
            $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
            // SQLite compares exactly unless told otherwise: NOCASE stands in for a folding MySQL collation (the
            // unique key folds too)
            $connection->executeStatement('CREATE TABLE device_trust (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, device_id CHAR(26) NOT NULL COLLATE NOCASE, user_identifier VARCHAR(191) NOT NULL COLLATE NOCASE, trusted_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, label VARCHAR(191) NOT NULL, granted_by VARCHAR(32) NOT NULL)');
            $connection->executeStatement('CREATE UNIQUE INDEX uniq_di_device_trust ON device_trust (device_id, user_identifier)');
            $connection->executeStatement('CREATE TABLE device_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, device_id CHAR(26) NOT NULL COLLATE NOCASE, user_identifier VARCHAR(191) NOT NULL COLLATE NOCASE, first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, login_count INTEGER NOT NULL)');
            $connection->executeStatement('CREATE UNIQUE INDEX uniq_di_device_user ON device_user (device_id, user_identifier)');

            return new EntityManager($connection, $configuration);
        }

        $url = getenv($source);
        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped($source.' is not set.');
        }
        $connection = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql']))->parse($url), $configuration);
        foreach (['device_trust', 'device_user', 'observation'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
        $em = new EntityManager($connection, $configuration, new EventManager());
        if ($exactCollation) {
            $em->getEventManager()->addEventListener([ToolEvents::postGenerateSchemaTable], new ExactCollationListener($this->registry($em)));
        }
        (new SchemaTool($em))->createSchema([
            $em->getClassMetadata(DeviceTrustEntity::class),
            $em->getClassMetadata(DeviceUserEntity::class),
            $em->getClassMetadata(DeviceObservationEntity::class),
        ]);

        return $em;
    }
}
