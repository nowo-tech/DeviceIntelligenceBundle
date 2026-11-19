<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\Concurrency;

use DateTimeImmutable;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceIntelligenceTransactionRunner;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use Nowo\TransactionDoctrine\WriteUnit\Dbal\CommitTrackingMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * REQ-RUNTIME-003 (1.10): two connections (two workers) on the same MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 database
 * race on the bundle's hot write paths: the trusted-device upsert and revocation, the login link counter, the observed
 * device counter, and the analysis write unit under a held row lock (409 + CONFLICT_001 / CONFLICT_002).
 *
 * Runs when DEVICE_INTELLIGENCE_TEST_MYSQL_URL / DEVICE_INTELLIGENCE_TEST_MARIADB_URL / DEVICE_INTELLIGENCE_TEST_PGSQL_URL
 * point at a throwaway database (the tables are dropped first); skipped otherwise. Both sessions get a 1 s lock wait
 * timeout, so a regression fails fast instead of hanging.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ConcurrentWritesDbTest extends TestCase
{
    /** @var list<Connection> */
    private array $connections = [];

    /**
     * @return iterable<string, array{string}>
     */
    public static function databases(): iterable
    {
        yield 'mysql 8.4' => ['DEVICE_INTELLIGENCE_TEST_MYSQL_URL'];
        yield 'mariadb 11.4' => ['DEVICE_INTELLIGENCE_TEST_MARIADB_URL'];
        yield 'postgresql 16' => ['DEVICE_INTELLIGENCE_TEST_PGSQL_URL'];
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->close();
        }
        $this->connections = [];
    }

    /**
     * The 1.9.3 revocation read the trust, then wrote the whole row back: a trust changed by another worker after that
     * read got its old expiry and label back. Now only revoked_at is written.
     */
    #[DataProvider('databases')]
    public function testRevokeNeverWritesBackAStaleTrust(string $env): void
    {
        [$a, $b] = $this->sessions($env);
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $device = DeviceId::generate($now);
        $alice = new UserIdentifier('alice@example.com');
        $trustsA = new DoctrineTrustedDeviceRepository($a, new DeviceMapper());
        $trustsB = new DoctrineTrustedDeviceRepository($b, new DeviceMapper());
        $trustsA->save(new TrustedDevice($device, $alice, $now, $now->modify('+1 day'), null, 'old label'));

        // B (a revocation request) has read the trust inside its transaction, in a snapshot before A's change
        $b->getConnection()->beginTransaction();
        self::assertSame('old label', $trustsB->findActive($device, $alice, $now)?->label);
        $trustsA->save(new TrustedDevice($device, $alice, $now, $now->modify('+30 days'), null, 'new label'));
        $trustsB->revoke($device, $alice, $now->modify('+1 minute'), 'device label');
        $b->getConnection()->commit();

        $row = $a->getConnection()->fetchAssociative('SELECT label, expires_at, revoked_at FROM device_trust');
        self::assertIsArray($row);
        self::assertSame('new label', $row['label'], 'the concurrent change is kept');
        self::assertStringStartsWith('2026-12-19', (string) $row['expires_at']);
        self::assertNotNull($row['revoked_at'], 'the revocation is applied');
        self::assertNull($trustsA->findActive($device, $alice, $now->modify('+2 minutes')), 'the device is not trusted after the revocation');
        self::assertSame(1, (int) $a->getConnection()->fetchOne('SELECT COUNT(*) FROM device_trust'));
    }

    /**
     * Two first trusts of the same device and user: the one that loses the unique key updates the winner's row (no
     * duplicate, no 500). B's snapshot predates A's insert, so B's plain read misses the row on MySQL / MariaDB.
     */
    #[DataProvider('databases')]
    public function testConcurrentFirstTrustUpdatesTheWinnersRow(string $env): void
    {
        [$a, $b] = $this->sessions($env);
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $device = DeviceId::generate($now);
        $alice = new UserIdentifier('alice@example.com');
        $trustsB = new DoctrineTrustedDeviceRepository($b, new DeviceMapper());

        $b->getConnection()->beginTransaction();
        self::assertNull($trustsB->findActive($device, $alice, $now));
        (new DoctrineTrustedDeviceRepository($a, new DeviceMapper()))->save(new TrustedDevice($device, $alice, $now, null, null, 'first'));
        $trustsB->save(new TrustedDevice($device, $alice, $now, null, null, 'second'));
        $b->getConnection()->commit();

        self::assertSame([['label' => 'second']], $a->getConnection()->fetchAllAssociative('SELECT label FROM device_trust'));
    }

    /**
     * Two logins of the same user on the same device: both count (login_count + 1 in SQL), even when one worker read
     * the link before the other's login committed.
     */
    #[DataProvider('databases')]
    public function testConcurrentLoginsBothCount(string $env): void
    {
        [$a, $b] = $this->sessions($env);
        $now = new DateTimeImmutable('2026-11-19 10:00:00');
        $device = DeviceId::generate($now);
        $alice = new UserIdentifier('alice@example.com');
        $usersA = new DoctrineDeviceUserRepository($a, new DeviceMapper());
        $usersB = new DoctrineDeviceUserRepository($b, new DeviceMapper());
        $usersA->recordLogin($device, $alice, $now);

        $b->getConnection()->beginTransaction();
        self::assertSame(1, $usersB->find($device, $alice)?->loginCount);
        $usersA->recordLogin($device, $alice, $now->modify('+1 second'));
        $usersB->recordLogin($device, $alice, $now->modify('+2 seconds'));
        $b->getConnection()->commit();

        self::assertSame(3, $usersA->find($device, $alice)?->loginCount);
        self::assertSame(1, (int) $a->getConnection()->fetchOne('SELECT COUNT(*) FROM device_user'));
    }

    /**
     * Two analyses of the same device: both observations count, and a status the host changed meanwhile is kept.
     */
    #[DataProvider('databases')]
    public function testConcurrentObservationsBothCountAndKeepTheStatus(string $env): void
    {
        [$a, $b] = $this->sessions($env);
        $device = Scenario::device();
        $devicesA = new DoctrineDeviceRepository($a, new DeviceMapper());
        $devicesB = new DoctrineDeviceRepository($b, new DeviceMapper());
        $devicesA->save($device);

        $b->getConnection()->beginTransaction();
        $read = $devicesB->find($device->id);
        self::assertNotNull($read);
        $devicesA->saveObserved($this->observed($device));
        $a->getConnection()->executeStatement("UPDATE device SET status = 'blocked'");
        $devicesB->saveObserved($this->observed($read));
        $b->getConnection()->commit();

        $row = $a->getConnection()->fetchAssociative('SELECT observation_count, status FROM device');
        self::assertIsArray($row);
        self::assertSame($device->observationCount + 2, (int) $row['observation_count']);
        self::assertSame('blocked', $row['status']);
    }

    /**
     * A row lock held by another worker: the analysis unit waits, times out, and fails as a TransientStorageFailure
     * that answers 409 + CONFLICT_001 + Retry-After; nothing of the unit is stored and the connection still works.
     * After an earlier commit in the same request, the same failure answers CONFLICT_002 without Retry-After.
     */
    #[DataProvider('databases')]
    public function testLockWaitInTheAnalysisUnitIsARetrySafeConflict(string $env): void
    {
        $ledger = new CommitLedger();
        [$a, $b] = $this->sessions($env, $ledger);
        $device = Scenario::device();
        (new DoctrineDeviceRepository($a, new DeviceMapper()))->save($device);
        $observation = Scenario::observation($device);
        $devicesB = new DoctrineDeviceRepository($b, new DeviceMapper());
        $observationsB = new DoctrineObservationRepository($b, new DeviceMapper());
        $runner = new DeviceIntelligenceTransactionRunner($b, 1000);
        $unit = static function () use ($devicesB, $observationsB, $device, $observation): void {
            $devicesB->saveObserved($device);
            $observationsB->save($observation);
        };
        $policy = new ConflictResponsePolicy();

        $a->getConnection()->beginTransaction();
        $a->getConnection()->executeStatement('UPDATE device SET label = ? WHERE id = ?', ['held', $device->id->value]);
        $mark = new CommitMark($ledger);
        try {
            $runner->run($unit);
            self::fail('The held row lock must end in a conflict.');
        } catch (TransientStorageFailure $failure) {
            $code = ConflictCode::forFailure($failure, $mark);
            self::assertSame(ConflictCode::RetrySafe, $code);
            self::assertSame(['X-Conflict-Code' => 'CONFLICT_001', 'Retry-After' => '1'], $policy->headersFor($code, $failure->retryAfterSeconds()));
        }
        self::assertSame(0, $b->getConnection()->getTransactionNestingLevel());
        self::assertSame(0, (int) $b->getConnection()->fetchOne('SELECT COUNT(*) FROM observation'), 'nothing of the unit is stored');

        // the same request had committed something before (here: a trust saved by B): CONFLICT_002
        $mark = new CommitMark($ledger);
        (new DoctrineTrustedDeviceRepository($b, new DeviceMapper()))->save(new TrustedDevice($device->id, new UserIdentifier('alice@example.com'), new DateTimeImmutable(), null, null, 'x'));
        try {
            $runner->run($unit);
            self::fail('The held row lock must end in a conflict.');
        } catch (TransientStorageFailure $failure) {
            $code = ConflictCode::forFailure($failure, $mark);
            self::assertSame(ConflictCode::Committed, $code);
            self::assertSame(['X-Conflict-Code' => 'CONFLICT_002'], $policy->headersFor($code, $failure->retryAfterSeconds()));
        }
        $a->getConnection()->rollBack();

        // the lock released, the retry stores the unit once
        $runner->run($unit);
        self::assertSame(1, (int) $b->getConnection()->fetchOne('SELECT COUNT(*) FROM observation'));
        self::assertSame($device->observationCount + 1, (int) $b->getConnection()->fetchOne('SELECT observation_count FROM device'));
    }

    private function observed(Device $device): Device
    {
        return $device->withObservation(new DateTimeImmutable(), $device->confidence, new Stability(0.6), $device->indexKey, $device->lastSignals, $device->label);
    }

    /**
     * Two entity managers on two connections to the database of $env, with fresh tables and a 1 s lock wait timeout.
     *
     * @return array{EntityManager, EntityManager}
     */
    private function sessions(string $env, ?CommitLedger $ledger = null): array
    {
        $url = getenv($env);
        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped($env.' is not set.');
        }
        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'pdo-mysql' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'pdo-pgsql' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql']))->parse($url);

        $managers = [];
        foreach ([null, $ledger] as $i => $sessionLedger) {
            $config = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
            if (\PHP_VERSION_ID >= 80400) {
                $config->enableNativeLazyObjects(true);
            }
            $dbal = new DbalConfiguration();
            if (null !== $sessionLedger) {
                $dbal->setMiddlewares([new CommitTrackingMiddleware($sessionLedger)]);
            }
            $connection = DriverManager::getConnection($params, $dbal);
            $this->connections[] = $connection;
            $platform = $connection->getDatabasePlatform();
            if ($platform instanceof AbstractMySQLPlatform) {
                $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $connection->executeStatement("SET lock_timeout = '1s'");
            }
            $em = new EntityManager($connection, $config);
            if (0 === $i) {
                $schema = new SchemaTool($em);
                $metadata = $em->getMetadataFactory()->getAllMetadata();
                $schema->dropSchema($metadata);
                $schema->createSchema($metadata);
            }
            $managers[] = $em;
        }

        return [$managers[0], $managers[1]];
    }
}
