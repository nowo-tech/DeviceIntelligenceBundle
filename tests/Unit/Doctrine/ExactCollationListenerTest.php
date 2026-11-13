<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQL84Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligenceBundle\Doctrine\ExactCollationListener;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * REQ-SEC-006 (1.9.2): the identity columns get the NO PAD binary collation of the platform in the generated schema.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ExactCollationListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{AbstractPlatform, string|null}>
     */
    public static function platforms(): iterable
    {
        yield 'mariadb' => [new MariaDBPlatform(), 'utf8mb4_nopad_bin'];
        yield 'mysql 8.4' => [new MySQL84Platform(), 'utf8mb4_0900_bin'];
        yield 'mysql 8.0' => [new MySQL80Platform(), 'utf8mb4_0900_bin'];
        yield 'mysql 5.7 or server_version without patch' => [new MySQLPlatform(), 'utf8mb4_bin'];
        yield 'postgresql' => [new PostgreSQLPlatform(), null];
        yield 'sqlite' => [new SQLitePlatform(), null];
    }

    #[DataProvider('platforms')]
    public function testCollationFor(AbstractPlatform $platform, ?string $expected): void
    {
        $this->assertSame($expected, ExactCollationListener::collationFor($platform));
    }

    #[DataProvider('platforms')]
    public function testTheGeneratedSchemaCarriesTheCollation(AbstractPlatform $platform, ?string $expected): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects('utf8mb4_bin' === $expected ? $this->exactly(3) : $this->never())->method('warning');

        $tables = $this->generatedTables($this->registryOn($platform), $logger);

        foreach (['device_trust', 'device_user', 'observation'] as $name) {
            foreach (['device_id', 'user_identifier'] as $column) {
                $this->assertSame($expected, $tables[$name]->getColumn($column)->getCollation(), $name.'.'.$column);
            }
        }
        $this->assertNull($tables['device_trust']->getColumn('label')->getCollation());
        $this->assertNull($tables['device']->getColumn('id')->getCollation(), 'other entities are left alone');
    }

    public function testWithoutAnOrmManagerNothingChanges(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $tables = $this->generatedTables($registry, null);

        $this->assertNull($tables['device_trust']->getColumn('user_identifier')->getCollation());
    }

    public function testCollateInPlaceOnOlderDbal(): void
    {
        $table = $this->generatedTables($this->registryOn(new SQLitePlatform()), null)['device_user'];

        $this->assertNull(ExactCollationListener::collate($table, 'user_identifier', 'utf8mb4_nopad_bin', false));
        $this->assertSame('utf8mb4_nopad_bin', $table->getColumn('user_identifier')->getCollation());
        $this->assertSame('utf8mb4', $table->getColumn('user_identifier')->getCharset());
    }

    /**
     * @return array<string, Table>
     */
    private function generatedTables(ManagerRegistry $registry, ?LoggerInterface $logger): array
    {
        $entityManager = $this->entityManager();
        $entityManager->getEventManager()->addEventListener([ToolEvents::postGenerateSchemaTable], new ExactCollationListener($registry, $logger));

        $schema = (new SchemaTool($entityManager))->getSchemaFromMetadata(array_map(
            $entityManager->getClassMetadata(...),
            [DeviceEntity::class, DeviceTrustEntity::class, DeviceUserEntity::class, DeviceObservationEntity::class],
        ));

        $tables = [];
        foreach ($schema->getTables() as $table) {
            $tables[$table->getName()] = $table;
        }

        return $tables;
    }

    private function registryOn(AbstractPlatform $platform): ManagerRegistry
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }

    private function entityManager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration, new EventManager());
    }
}
