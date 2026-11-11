<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\QuoteStrategy;
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
        $savepoint = $connection->isTransactionActive();
        if ($savepoint) {
            $connection->createSavepoint(self::SAVEPOINT);
        }

        try {
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
        } catch (Throwable $error) {
            if ($savepoint) {
                try {
                    $connection->rollbackSavepoint(self::SAVEPOINT);
                } catch (Throwable) {
                    // The original error is the one that matters.
                }
            }

            throw $error;
        }

        if ($savepoint) {
            $connection->releaseSavepoint(self::SAVEPOINT);
        }
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
