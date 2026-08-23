<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Applies the configured table prefix to bundle entities on LoadClassMetadata.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TablePrefixSubscriber
{
    private const ENTITY_NS = 'Nowo\\DeviceIntelligenceBundle\\Entity\\';

    public function __construct(private string $prefix)
    {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        if (!str_starts_with($metadata->getName(), self::ENTITY_NS)) {
            return;
        }

        $table = $metadata->getTableName();
        if (!str_starts_with($table, $this->prefix)) {
            $metadata->setPrimaryTable(['name' => $this->prefix.$table]);
        }

        $this->prefixUniqueConstraints($metadata);
        $this->prefixIndexes($metadata);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function prefixUniqueConstraints(ClassMetadata $metadata): void
    {
        $table = $metadata->table;
        if (!isset($table['uniqueConstraints']) || !\is_array($table['uniqueConstraints'])) {
            return;
        }
        $prefixed = [];
        foreach ($table['uniqueConstraints'] as $name => $definition) {
            $newName = str_starts_with((string) $name, $this->prefix) ? (string) $name : $this->prefix.$name;
            $prefixed[$newName] = $definition;
        }
        $table['uniqueConstraints'] = $prefixed;
        $metadata->table = $table;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function prefixIndexes(ClassMetadata $metadata): void
    {
        $table = $metadata->table;
        if (!isset($table['indexes']) || !\is_array($table['indexes'])) {
            return;
        }
        $prefixed = [];
        foreach ($table['indexes'] as $name => $definition) {
            $newName = str_starts_with((string) $name, $this->prefix) ? (string) $name : $this->prefix.$name;
            $prefixed[$newName] = $definition;
        }
        $table['indexes'] = $prefixed;
        $metadata->table = $table;
    }
}
