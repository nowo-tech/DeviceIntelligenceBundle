<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\DeviceIntelligenceBundle\Doctrine\TablePrefixSubscriber;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TablePrefixSubscriberTest extends TestCase
{
    public function testPrefixesBundleEntityTable(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(DeviceEntity::class);
        $metadata->setPrimaryTable(['name' => 'device', 'indexes' => ['idx_os' => ['columns' => ['os_family']]]]);
        $subscriber = new TablePrefixSubscriber('device_intelligence_');
        $subscriber->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $em));

        self::assertSame('device_intelligence_device', $metadata->getTableName());
    }

    public function testIgnoresOtherEntities(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(\stdClass::class);
        $metadata->setPrimaryTable(['name' => 'users']);
        $subscriber = new TablePrefixSubscriber('device_intelligence_');
        $subscriber->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $em));

        self::assertSame('users', $metadata->getTableName());
    }
}
