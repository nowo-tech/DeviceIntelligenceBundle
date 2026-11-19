<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Throwable;

/**
 * Writes one bundle entity with a DBAL INSERT / UPDATE built from its class metadata (1.7, architecture bar rule 6).
 *
 * The analysis runs inside request listeners and on login; flushing the entity manager there (the host's default one
 * in a standard host) would also commit whatever the host had pending at that moment. This writer touches only the
 * bundle row: no flush, no unit of work, no identity map. When the host has a transaction open on the connection, the
 * write runs inside a SAVEPOINT so that a failure of the bundle never aborts the host's transaction; the original error
 * is rethrown even if rolling back the savepoint fails too.
 *
 * Only scalar field mappings are written (the bundle entities have no associations). On insert, the identifier follows
 * the entity's ORM generator, so the DBAL row matches what `persist()` + `flush()` would write on every platform:
 * - assigned ids (ULID strings) are written as they are;
 * - pre-insert generators (SEQUENCE on PostgreSQL with DBAL 3 or `identity_generation_preferences: sequence`, custom
 *   generators) produce the id first, which is set on the entity and written in the INSERT;
 * - post-insert generators (IDENTITY / auto-increment) leave the column out; the generated id is read with
 *   `lastInsertId()` right after the INSERT, before the savepoint is released (MySQL forgets it after `RELEASE`), and
 *   set on the entity.
 *
 * @internal
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DbalEntityWriter
{
    private const SAVEPOINT = 'nowo_device_intelligence_write';

    /** INSERTs an upsert tries when the row keeps being deleted between its INSERT and its UPDATE (erasure, cleanup). */
    private const UPSERT_ATTEMPTS = 3;

    /**
     * @param bool $exists true: UPDATE the row of the entity's identifier; false: INSERT it
     */
    public static function write(EntityManagerInterface $em, object $entity, bool $exists): void
    {
        $metadata = $em->getClassMetadata($entity::class);
        $connection = $em->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quotes = $em->getConfiguration()->getQuoteStrategy();
        $idField = $metadata->getSingleIdentifierFieldName();
        $idColumn = $quotes->getColumnName($idField, $metadata, $platform);
        $idType = $metadata->getTypeOfField($idField) ?? 'string';
        $postInsertId = !$exists && $metadata->idGenerator->isPostInsertGenerator();
        $preInsertId = !$exists && !$postInsertId && !$metadata->isIdentifierNatural();

        $table = $quotes->getTableName($metadata, $platform);
        self::inSavepoint($connection, static function () use ($em, $metadata, $quotes, $platform, $connection, $entity, $exists, $table, $idField, $idColumn, $idType, $postInsertId, $preInsertId): void {
            if ($preInsertId) {
                // SEQUENCE / custom generator: the id must be in the INSERT (the column has no default).
                $metadata->setFieldValue($entity, $idField, $metadata->idGenerator->generateId($em, $entity));
            }

            [$data, $types] = self::row($metadata, $quotes, $platform, $entity, $idField, $exists || $postInsertId);
            if ($exists) {
                $connection->update($table, $data, [$idColumn => $metadata->getFieldValue($entity, $idField)], $types + [$idColumn => $idType]);
            } else {
                $connection->insert($table, $data, $types);
                if ($postInsertId) {
                    // Before RELEASE SAVEPOINT: MySQL no longer reports the id after it.
                    $id = $connection->lastInsertId();
                    $metadata->setFieldValue($entity, $idField, \in_array($idType, ['integer', 'smallint'], true) ? (int) $id : $id);
                }
            }
        });
    }

    /**
     * UPDATEs the given fields of the row with identifier $id, and adds $increment[field] to counter columns in the same
     * statement (`n = n + :delta`, never a value read earlier). Returns the number of rows the server reports (MySQL and
     * MariaDB count changed rows only: 0 also means "found, nothing changed" unless an increment is given).
     *
     * @param class-string         $class
     * @param array<string, mixed> $set       field => value
     * @param array<string, int>   $increment field => delta
     */
    public static function update(EntityManagerInterface $em, string $class, int|string $id, array $set, array $increment = []): int
    {
        $metadata = $em->getClassMetadata($class);
        $connection = $em->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quotes = $em->getConfiguration()->getQuoteStrategy();
        $idField = $metadata->getSingleIdentifierFieldName();

        $assignments = [];
        $params = [];
        $types = [];
        foreach ($set as $field => $value) {
            $assignments[] = $quotes->getColumnName($field, $metadata, $platform).' = ?';
            $params[] = $value;
            $types[] = $metadata->getTypeOfField($field) ?? 'string';
        }
        foreach ($increment as $field => $delta) {
            $column = $quotes->getColumnName($field, $metadata, $platform);
            $assignments[] = $column.' = '.$column.' + ?';
            $params[] = $delta;
            $types[] = 'integer';
        }
        $params[] = $id;
        $types[] = $metadata->getTypeOfField($idField) ?? 'string';

        $sql = 'UPDATE '.$quotes->getTableName($metadata, $platform).' SET '.implode(', ', $assignments)
            .' WHERE '.$quotes->getColumnName($idField, $metadata, $platform).' = ?';

        return self::inSavepoint($connection, static fn (): int => (int) $connection->executeStatement($sql, $params, $types));
    }

    /**
     * Writes the row of business key $keys without a read-modify-write (REQ-RUNTIME-003 rule 4, 1.10): when the row
     * exists, an {@see self::update()} by its id with $set and $increment; otherwise an INSERT of $new. A concurrent
     * INSERT of the same key (unique violation) is not an error: the row it stored is found with a locking read and
     * updated. When the locking read finds no byte-identical row after a unique violation:
     * - a row that only the SQL filter matches holds the key (a look-alike on a folding collation):
     *   LookAlikeIdentifierException, nothing written (fail closed, REQ-SEC-006);
     * - no row at all: the conflicting row was deleted meanwhile (erasure, cleanup), so the INSERT is tried again while
     *   attempts remain (1.10.1, audit UE-DI-2), then the last unique violation is rethrown.
     *
     * @param class-string          $class
     * @param array<string, string> $keys      field => exact value of the unique business key
     * @param array<string, mixed>  $set       field => value written on an existing row
     * @param array<string, int>    $increment field => delta added on an existing row
     *
     * @throws LookAlikeIdentifierException
     */
    public static function upsert(EntityManagerInterface $em, string $class, array $keys, object $new, array $set, array $increment, string $lookAlikeMessage): void
    {
        $id = self::findExactId($em, $class, $keys);
        for ($attempt = 1;; ++$attempt) {
            // MySQL / MariaDB report 0 for a found row whose values do not change: confirmed with a locking read
            if (null !== $id && (self::update($em, $class, $id, $set, $increment) > 0 || $id === self::findExactId($em, $class, $keys, true))) {
                return;
            }

            try {
                self::write($em, $new, false);

                return;
            } catch (UniqueConstraintViolationException $error) {
                [$id, $lookAlike] = self::keyRows($em, $class, $keys, true);
                if (null === $id && $lookAlike) {
                    throw new LookAlikeIdentifierException($lookAlikeMessage, 0, $error);
                }
                if ($attempt >= self::UPSERT_ATTEMPTS) {
                    throw $error;
                }
            }
        }
    }

    /**
     * Identifier of the row whose $keys fields equal the given values byte for byte (REQ-SEC-006: the SQL filter may fold
     * on a MySQL / MariaDB collation, so every row it returns is re-checked with `===`, without a LIMIT before that).
     * With $forUpdate the read is a locking read (`FOR UPDATE`; SQLite has none and locks the whole database on write): it sees the latest committed
     * row, also inside a REPEATABLE READ snapshot, and waits for a concurrent writer of that row.
     *
     * @param class-string          $class
     * @param array<string, string> $keys  field => exact value
     */
    public static function findExactId(EntityManagerInterface $em, string $class, array $keys, bool $forUpdate = false): int|string|null
    {
        return self::keyRows($em, $class, $keys, $forUpdate)[0];
    }

    /**
     * One read of the rows the SQL filter matches for $keys: the identifier of the byte-identical one (or null), and
     * whether any other row matched (a look-alike on a folding collation).
     *
     * @param class-string          $class
     * @param array<string, string> $keys  field => exact value
     *
     * @return array{int|string|null, bool}
     */
    private static function keyRows(EntityManagerInterface $em, string $class, array $keys, bool $forUpdate): array
    {
        $metadata = $em->getClassMetadata($class);
        $connection = $em->getConnection();
        $platform = $connection->getDatabasePlatform();
        $quotes = $em->getConfiguration()->getQuoteStrategy();
        $idField = $metadata->getSingleIdentifierFieldName();

        $select = [$quotes->getColumnName($idField, $metadata, $platform).' AS di_id'];
        $where = [];
        $i = 0;
        foreach (array_keys($keys) as $field) {
            $column = $quotes->getColumnName($field, $metadata, $platform);
            $select[] = $column.' AS di_k'.$i++;
            $where[] = $column.' = ?';
        }
        $sql = 'SELECT '.implode(', ', $select).' FROM '.$quotes->getTableName($metadata, $platform)
            .' WHERE '.implode(' AND ', $where)
            .($forUpdate && !$platform instanceof SQLitePlatform ? ' FOR UPDATE' : '');
        $expected = array_values($keys);
        $integerId = \in_array($metadata->getTypeOfField($idField), ['integer', 'smallint', 'bigint'], true);

        // A locking read can time out (a lock error aborts a PostgreSQL transaction): inside the host's, in a savepoint.
        $rows = $forUpdate
            ? self::inSavepoint($connection, static fn (): array => $connection->fetchAllAssociative($sql, $expected))
            : $connection->fetchAllAssociative($sql, $expected);
        foreach ($rows as $row) {
            $row = array_change_key_case($row, \CASE_LOWER);
            foreach ($expected as $k => $value) {
                if ((string) $row['di_k'.$k] !== $value) {
                    continue 2;
                }
            }
            $id = $row['di_id'];

            return [$integerId ? (int) $id : (string) $id, false];
        }

        return [null, [] !== $rows];
    }

    /**
     * Runs $write inside a SAVEPOINT when a transaction is open on the connection (rolled back on failure, so a failure
     * of the bundle never aborts the host's transaction, PostgreSQL included); the original error is rethrown even if
     * rolling back the savepoint fails too.
     *
     * @template T
     *
     * Explicit savepoints (not a nested beginTransaction()): DBAL 3 without savepoints would only emulate the level, and a
     * failure would mark the whole host transaction rollback-only.
     *
     * @param callable(): T $write
     * @param string        $name  distinct per nesting level: MySQL replaces a savepoint of the same name
     *
     * @return T
     */
    public static function inSavepoint(Connection $connection, callable $write, string $name = self::SAVEPOINT): mixed
    {
        $savepoint = $connection->isTransactionActive();
        if ($savepoint) {
            $connection->createSavepoint($name);
        }

        try {
            $result = $write();
        } catch (Throwable $error) {
            if ($savepoint) {
                try {
                    $connection->rollbackSavepoint($name);
                } catch (Throwable) {
                    // The original error is the one that matters.
                }
            }

            throw $error;
        }

        if ($savepoint) {
            $connection->releaseSavepoint($name);
        }

        return $result;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array{array<string, mixed>, array<string, string>}
     */
    private static function row(ClassMetadata $metadata, QuoteStrategy $quotes, AbstractPlatform $platform, object $entity, string $idField, bool $withoutId): array
    {
        $data = [];
        $types = [];
        foreach ($metadata->getFieldNames() as $field) {
            if ($field === $idField && $withoutId) {
                continue;
            }

            $column = $quotes->getColumnName($field, $metadata, $platform);
            $data[$column] = $metadata->getFieldValue($entity, $field);
            $types[$column] = $metadata->getTypeOfField($field) ?? 'string';
        }

        return [$data, $types];
    }
}
