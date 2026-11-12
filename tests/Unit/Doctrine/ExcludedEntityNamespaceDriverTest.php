<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Compiler\DeviceIntelligenceEntityManagerPass;
use Nowo\DeviceIntelligenceBundle\Doctrine\ExcludedEntityNamespaceDriver;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use PHPUnit\Framework\TestCase;

final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    public function testMapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(DeviceEntity::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(DeviceEntity::class);
        $driver->loadMetadataForClass(DeviceEntity::class, new ClassMetadata(DeviceEntity::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the bundle entities.
     */
    public function testWinsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(DeviceEntity::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), DeviceIntelligenceEntityManagerPass::ENTITY_NAMESPACE);

        self::assertTrue($chain->isTransient(DeviceEntity::class));
        self::assertTrue($chain->isTransient(DeviceTrustEntity::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
