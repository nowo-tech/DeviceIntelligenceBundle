<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\TransactionContract\TransactionRunnerInterface;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Throwable;

/**
 * The bundle's write-unit runner (1.10, REQ-RUNTIME-003 rules 1, 3 and 9): runs the analysis write unit (device and
 * observation rows) in one transaction on the connection of the manager that maps the bundle entities, resolved per
 * call (a manager closed and reset in an earlier request is picked up).
 *
 * The work is DBAL-only ({@see DbalEntityWriter}): no flush and no clear or reset of any entity manager (the host's
 * default one may map the bundle entities). On its own the unit runs in transaction-doctrine's connection runner, which
 * repairs the nesting level after a failure; inside a host transaction it runs in an explicit SAVEPOINT (also on DBAL 3
 * without nested savepoints), rolled back alone on failure. A deadlock, lock-wait timeout or serialization failure is
 * rethrown as the contract's {@see TransientStorageFailure} (nothing of the unit is committed); one attempt, no retry.
 *
 * Its own transaction (not nested in the host's) bounds the wait for a row lock held elsewhere
 * ({@see self::LOCK_TIMEOUT_MS}), so it cannot hang the request:
 * - PostgreSQL: `SET LOCAL lock_timeout`, which ends with the transaction;
 * - MySQL and MariaDB (1.10.1, audit UE-DI-3): the session `innodb_lock_wait_timeout` is lowered to the bound, in whole
 *   seconds rounded up (at least 1), for the transaction only, and set back to the session's previous value afterwards,
 *   also on failure (worker mode: the next request on the connection gets the host's value). A session value already
 *   at or below the bound is left alone.
 * Inside the host's transaction the host's settings apply.
 *
 * Stateless (FrankenPHP worker mode, kernel reset off).
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DeviceIntelligenceTransactionRunner implements TransactionRunnerInterface
{
    use ResolvesEntityManagerTrait;

    /** Lock-wait bound of the bundle's own transactions, in milliseconds (PostgreSQL `lock_timeout`, MySQL / MariaDB `innodb_lock_wait_timeout` rounded up to seconds). */
    public const LOCK_TIMEOUT_MS = 5000;

    /** Retry-After hint of the TransientStorageFailure the runner throws. */
    public const RETRY_AFTER_SECONDS = 1;

    private const SAVEPOINT = 'nowo_device_intelligence_unit';

    private TransientFailureClassifier $classifier;

    /**
     * @param int $lockTimeoutMs lock-wait bound of the bundle's own transactions, in milliseconds (0 = the server's)
     */
    public function __construct(
        private EntityManagerInterface|ManagerRegistry $em,
        private int $lockTimeoutMs = self::LOCK_TIMEOUT_MS,
    ) {
        $this->classifier = new TransientFailureClassifier();
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $connection = $this->entityManager(DeviceEntity::class)->getConnection();
        if ($connection->isTransactionActive()) {
            // Inside the host's transaction: an explicit SAVEPOINT, rolled back alone on failure (also on DBAL 3 without
            // nested savepoints, where a nested beginTransaction() would doom the host's transaction).
            try {
                return DbalEntityWriter::inSavepoint($connection, $operation, self::SAVEPOINT);
            } catch (TransientStorageFailure $failure) {
                throw $failure;
            } catch (Throwable $failure) {
                if ($this->classifier->isTransient($failure)) {
                    throw TransientStorageFailure::fromPrevious($failure, self::RETRY_AFTER_SECONDS);
                }

                throw $failure;
            }
        }

        $platform = $connection->getDatabasePlatform();
        $timeout = $this->lockTimeoutMs > 0 && $platform instanceof PostgreSQLPlatform;
        $restore = $this->lockTimeoutMs > 0 && $platform instanceof AbstractMySQLPlatform ? $this->lowerMySqlLockWait($connection) : null;

        try {
            return DoctrineTransactionRunner::forConnection($connection, self::RETRY_AFTER_SECONDS)->run(function () use ($connection, $operation, $timeout): mixed {
                if ($timeout) {
                    $connection->executeStatement(\sprintf("SET LOCAL lock_timeout = '%dms'", $this->lockTimeoutMs));
                }

                return $operation();
            });
        } finally {
            if (null !== $restore) {
                try {
                    $connection->executeStatement(\sprintf('SET SESSION innodb_lock_wait_timeout = %d', $restore));
                } catch (Throwable) {
                    // A lost connection takes the session value with it; the unit's own outcome is the one that matters.
                }
            }
        }
    }

    /**
     * Lowers the session `innodb_lock_wait_timeout` to the bound (whole seconds, rounded up) and returns the value to set
     * back, or null when the session value is already at or below the bound.
     */
    private function lowerMySqlLockWait(Connection $connection): ?int
    {
        $bound = max(1, (int) ceil($this->lockTimeoutMs / 1000));
        $current = (int) $connection->fetchOne('SELECT @@SESSION.innodb_lock_wait_timeout');
        if ($current <= $bound) {
            return null;
        }
        $connection->executeStatement(\sprintf('SET SESSION innodb_lock_wait_timeout = %d', $bound));

        return $current;
    }
}
