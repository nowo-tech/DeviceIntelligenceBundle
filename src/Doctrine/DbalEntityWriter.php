<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
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
 * Only scalar field mappings are written (the bundle entities have no associations); a generated identifier is left
 * to the database on insert.
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
        $generatedId = $metadata->isIdGeneratorIdentity() || $metadata->isIdGeneratorSequence();

        $data = [];
        $types = [];
        $idColumn = $quotes->getColumnName($idField, $metadata, $platform);
        foreach ($metadata->getFieldNames() as $field) {
            if ($field === $idField && ($exists || $generatedId)) {
                continue;
            }

            $column = $quotes->getColumnName($field, $metadata, $platform);
            $data[$column] = $metadata->getFieldValue($entity, $field);
            $types[$column] = $metadata->getTypeOfField($field) ?? 'string';
        }

        $table = $quotes->getTableName($metadata, $platform);
        $savepoint = $connection->isTransactionActive();
        if ($savepoint) {
            $connection->createSavepoint(self::SAVEPOINT);
        }

        try {
            if ($exists) {
                $connection->update($table, $data, [$idColumn => $metadata->getFieldValue($entity, $idField)], $types + [$idColumn => $metadata->getTypeOfField($idField) ?? 'string']);
            } else {
                $connection->insert($table, $data, $types);
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
}
