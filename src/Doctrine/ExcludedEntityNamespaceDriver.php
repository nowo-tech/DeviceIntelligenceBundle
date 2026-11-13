<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Doctrine;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\DeviceIntelligenceEntityManagerPass;

/**
 * Metadata driver that maps nothing: registered for the bundle entity namespace on every entity manager other than
 * `doctrine.entity_manager` (on every manager with `doctrine.enabled: false`) by
 * {@see DeviceIntelligenceEntityManagerPass}.
 *
 * A {@see MappingDriverChain} asks its namespace drivers before its default driver, so this driver makes the bundle
 * entities transient on those managers even when their chain has a default driver that claims every class carrying
 * `#[ORM\Entity]` (DoctrineBundle 3.4 `auto_discover_entities`, enabled by default on a manager without `mappings`
 * and `auto_mapping`). `ManagerRegistry::getManagerForClass()` then answers the configured manager on DoctrineBundle
 * 2.x and 3.x alike. Stateless (worker-safe).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ExcludedEntityNamespaceDriver implements MappingDriver
{
    public function loadMetadataForClass(string $className, ClassMetadata $metadata): void
    {
        throw new MappingException(\sprintf('Class "%s" is not mapped on this entity manager (nowo_device_intelligence.doctrine.entity_manager maps it on another one, or nowo_device_intelligence.doctrine.enabled is false).', $className));
    }

    /**
     * @return list<class-string>
     */
    public function getAllClassNames(): array
    {
        return [];
    }

    public function isTransient(string $className): bool
    {
        return true;
    }
}
