<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceIntelligenceTransactionRunner;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * 1.10 (REQ-RUNTIME-003 rules 1, 3 and 9): the analysis write unit in one transaction on the bundle manager's
 * connection, a nested level inside the host's, transient failures as the contract's TransientStorageFailure, and a
 * lock-wait bound (PostgreSQL lock_timeout, MySQL / MariaDB innodb_lock_wait_timeout) on its own transactions only.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceIntelligenceTransactionRunnerTest extends TestCase
{
    public function testOwnTransactionCommitsAndRollsBackAsAWhole(): void
    {
        $connection = $this->sqlite()->getConnection();
        $connection->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $runner = new DeviceIntelligenceTransactionRunner($this->registry($connection));

        self::assertSame('done', $runner->run(static function () use ($connection): string {
            $connection->insert('t', ['id' => 1]);

            return 'done';
        }));

        $refused = new RuntimeException('refused');
        self::assertSame($refused, self::caught(static function () use ($runner, $connection, $refused): void {
            $runner->run(static function () use ($connection, $refused): void {
                $connection->insert('t', ['id' => 2]);

                throw $refused;
            });
        }));

        self::assertSame([1], array_map('intval', $connection->fetchFirstColumn('SELECT id FROM t')));
        self::assertSame(0, $connection->getTransactionNestingLevel());
    }

    public function testInsideTheHostTransactionOnlyItsLevelIsRolledBack(): void
    {
        $em = $this->sqlite();
        $connection = $em->getConnection();
        $connection->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $runner = new DeviceIntelligenceTransactionRunner($em);

        $connection->beginTransaction();
        $connection->insert('t', ['id' => 1]);
        $failure = self::caught(static function () use ($runner, $connection): void {
            $runner->run(static function () use ($connection): void {
                $connection->insert('t', ['id' => 2]);

                throw new DeadlockException(new class('Deadlock found', '40001', 1213) extends AbstractException {}, null);
            });
        });
        self::assertInstanceOf(TransientStorageFailure::class, $failure);
        self::assertInstanceOf(DeadlockException::class, $failure->getPrevious());
        self::assertSame(DeviceIntelligenceTransactionRunner::RETRY_AFTER_SECONDS, $failure->retryAfterSeconds());

        self::assertSame(1, $connection->getTransactionNestingLevel(), 'the host transaction is still open');
        $connection->commit();
        self::assertSame([1], array_map('intval', $connection->fetchFirstColumn('SELECT id FROM t')));
    }

    public function testInsideTheHostTransactionOtherFailuresPropagateUnchanged(): void
    {
        $em = $this->sqlite();
        $connection = $em->getConnection();
        $runner = new DeviceIntelligenceTransactionRunner($em);
        $transient = new TransientStorageFailure(5);
        $domain = new RuntimeException('refused');

        $connection->beginTransaction();
        self::assertSame($transient, self::caught(static fn () => $runner->run(static fn () => throw $transient)));
        self::assertSame($domain, self::caught(static fn () => $runner->run(static fn () => throw $domain)));
        self::assertSame(1, $connection->getTransactionNestingLevel());
        $connection->rollBack();
    }

    public function testPostgreSqlOwnTransactionsSetALockTimeout(): void
    {
        $statements = [];
        $runner = new DeviceIntelligenceTransactionRunner($this->em($this->postgres(false, $statements)), 2500);
        self::assertSame(1, $runner->run(static fn (): int => 1));
        self::assertSame(["SET LOCAL lock_timeout = '2500ms'"], $statements);

        $statements = [];
        (new DeviceIntelligenceTransactionRunner($this->em($this->postgres(true, $statements))))->run(static fn (): int => 1);
        self::assertSame([], $statements, 'never inside the host transaction (SET LOCAL would outlive the unit)');

        $statements = [];
        (new DeviceIntelligenceTransactionRunner($this->em($this->postgres(false, $statements)), 0))->run(static fn (): int => 1);
        self::assertSame([], $statements, '0 keeps the server setting');
    }

    /**
     * 1.10.1 (audit UE-DI-3): MySQL / MariaDB own transactions lower the session innodb_lock_wait_timeout to the bound
     * (seconds, rounded up) and set the previous value back afterwards, also when the unit fails; a lower session value,
     * 0 and the host's transaction leave the session alone.
     */
    public function testMySqlOwnTransactionsBoundTheLockWaitAndRestoreIt(): void
    {
        $statements = [];
        $runner = new DeviceIntelligenceTransactionRunner($this->em($this->mysql(false, 50, $statements)), 2500);
        self::assertSame(1, $runner->run(static fn (): int => 1));
        self::assertSame(['SET SESSION innodb_lock_wait_timeout = 3', 'SET SESSION innodb_lock_wait_timeout = 50'], $statements);

        $statements = [];
        $refused = new RuntimeException('refused');
        $runner = new DeviceIntelligenceTransactionRunner($this->em($this->mysql(false, 50, $statements)), 200);
        self::assertSame($refused, self::caught(static fn () => $runner->run(static fn () => throw $refused)));
        self::assertSame(['SET SESSION innodb_lock_wait_timeout = 1', 'SET SESSION innodb_lock_wait_timeout = 50'], $statements, 'restored on failure');

        $statements = [];
        (new DeviceIntelligenceTransactionRunner($this->em($this->mysql(false, 5, $statements))))->run(static fn (): int => 1);
        self::assertSame([], $statements, 'a session value at or below the bound is kept');

        $statements = [];
        (new DeviceIntelligenceTransactionRunner($this->em($this->mysql(true, 50, $statements))))->run(static fn (): int => 1);
        self::assertSame([], $statements, 'never inside the host transaction');

        $statements = [];
        (new DeviceIntelligenceTransactionRunner($this->em($this->mysql(false, 50, $statements)), 0))->run(static fn (): int => 1);
        self::assertSame([], $statements, '0 keeps the server setting');
    }

    public function testAFailedMySqlRestoreDoesNotHideTheResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('fetchOne')->willReturn('50');
        $calls = 0;
        $connection->method('executeStatement')->willReturnCallback(static function () use (&$calls): int {
            if (2 === ++$calls) {
                throw new RuntimeException('gone away');
            }

            return 0;
        });

        self::assertSame(1, (new DeviceIntelligenceTransactionRunner($this->em($connection)))->run(static fn (): int => 1));
        self::assertSame(2, $calls);
    }

    private static function caught(callable $call): ?Throwable
    {
        try {
            $call();
        } catch (Throwable $thrown) {
            return $thrown;
        }

        return null;
    }

    /**
     * @param list<string> $statements
     */
    private function postgres(bool $hostTransaction, array &$statements): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());
        $connection->method('isTransactionActive')->willReturn($hostTransaction);
        $connection->method('getTransactionNestingLevel')->willReturn($hostTransaction ? 1 : 0);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });

        return $connection;
    }

    /**
     * @param list<string> $statements
     */
    private function mysql(bool $hostTransaction, int $sessionLockWait, array &$statements): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('isTransactionActive')->willReturn($hostTransaction);
        $connection->method('getTransactionNestingLevel')->willReturn($hostTransaction ? 1 : 0);
        $connection->method('fetchOne')->with('SELECT @@SESSION.innodb_lock_wait_timeout')->willReturn((string) $sessionLockWait);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });

        return $connection;
    }

    /**
     * @return EntityManagerInterface&MockObject
     */
    private function em(Connection $connection): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }

    private function registry(Connection $connection): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $em = $this->em($connection);
        $em->method('isOpen')->willReturn(true);
        $registry->method('getManagerForClass')->willReturn($em);

        return $registry;
    }

    private function sqlite(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
    }
}
