<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

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
 * On PostgreSQL its own transaction (not nested in the host's) sets `lock_timeout` ({@see self::LOCK_TIMEOUT_MS}) so a
 * row lock held elsewhere cannot hang the request; MySQL and MariaDB use the server's `innodb_lock_wait_timeout`.
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

    /** PostgreSQL `lock_timeout` of the bundle's own transactions, in milliseconds. */
    public const LOCK_TIMEOUT_MS = 5000;

    /** Retry-After hint of the TransientStorageFailure the runner throws. */
    public const RETRY_AFTER_SECONDS = 1;

    private const SAVEPOINT = 'nowo_device_intelligence_unit';

    private TransientFailureClassifier $classifier;

    /**
     * @param int $lockTimeoutMs PostgreSQL `lock_timeout` of the bundle's own transactions, in milliseconds (0 = the server's)
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

        $timeout = $this->lockTimeoutMs > 0 && $connection->getDatabasePlatform() instanceof PostgreSQLPlatform;

        return DoctrineTransactionRunner::forConnection($connection, self::RETRY_AFTER_SECONDS)->run(function () use ($connection, $operation, $timeout): mixed {
            if ($timeout) {
                $connection->executeStatement(\sprintf("SET LOCAL lock_timeout = '%dms'", $this->lockTimeoutMs));
            }

            return $operation();
        });
    }
}
