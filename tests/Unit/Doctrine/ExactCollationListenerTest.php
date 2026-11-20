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
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use LogicException;
use Nowo\DeviceIntelligenceBundle\Doctrine\ExactCollationListener;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Throwable;

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
        if (class_exists(MySQL84Platform::class)) { // DBAL 4.1+
            yield 'mysql 8.4' => [new MySQL84Platform(), 'utf8mb4_0900_bin'];
        }
        yield 'mysql 8.0' => [new MySQL80Platform(), 'utf8mb4_0900_bin'];
        yield 'mysql 5.7 or server_version without patch' => [new MySQLPlatform(), 'utf8mb4_bin'];
        yield 'postgresql' => [new PostgreSQLPlatform(), null];
        yield 'sqlite' => [self::sqlitePlatform(), null];
    }

    #[DataProvider('platforms')]
    public function testCollationFor(AbstractPlatform $platform, ?string $expected): void
    {
        $this->assertSame($expected, ExactCollationListener::collationFor($platform));
    }

    /**
     * Lazy connections (nothing connects): the platform comes from `serverVersion`.
     *
     * @return iterable<string, array{array<string, mixed>, string|null}>
     */
    public static function connections(): iterable
    {
        yield 'mariadb' => [['driver' => 'pdo_mysql', 'serverVersion' => '11.4.0-MariaDB'], 'utf8mb4_nopad_bin'];
        yield 'mysql 8.4' => [['driver' => 'pdo_mysql', 'serverVersion' => '8.4.3'], 'utf8mb4_0900_bin'];
        yield 'mysql 5.7 or server_version without patch' => [['driver' => 'pdo_mysql', 'serverVersion' => '5.7.44'], 'utf8mb4_bin'];
        yield 'sqlite' => [['driver' => 'pdo_sqlite', 'memory' => true], null];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('connections')]
    public function testTheGeneratedSchemaCarriesTheCollation(array $params, ?string $expected): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects('utf8mb4_bin' === $expected ? $this->exactly(3) : $this->never())->method('warning');

        $tables = $this->generatedTables($params, new ExactCollationListener(null, $logger));

        foreach (['device_trust', 'device_user', 'observation'] as $name) {
            foreach (['device_id', 'user_identifier'] as $column) {
                $this->assertSame($expected, self::collationOf($tables[$name], $column), $name.'.'.$column);
            }
        }
        $this->assertNull(self::collationOf($tables['device_trust'], 'label'));
        $this->assertNull(self::collationOf($tables['device'], 'id'), 'other entities are left alone');
    }

    /**
     * Audit XI: the generated schema keeps every foreign key and index of every table, as without the listener (the real
     * schema tool flow, with a to-one association on `device_trust`: built by hand, the entity has no such property).
     *
     * @param array<string, mixed> $params
     */
    #[DataProvider('connections')]
    public function testEveryForeignKeyAndIndexIsKept(array $params, ?string $expected): void
    {
        $with = $this->generatedSchema($params, new ExactCollationListener(), true);
        $without = $this->generatedSchema($params, null, true);

        $this->assertContains('parent_id->device_trust(id) CASCADE', self::shape($without)['device_trust']['fks']);
        $this->assertSame(self::shape($without), self::shape($with));
        $this->assertSame($expected, self::collationOf($with->getTable('device_trust'), 'user_identifier'));
    }

    /**
     * A table that has foreign keys keeps them, and the tables that point to it too.
     */
    public function testForeignKeysOfTheEditedTablesSurviveTheSwap(): void
    {
        $schema = new Schema();
        $owner = $schema->createTable('host_owner');
        $owner->addColumn('id', 'integer');
        $owner->setPrimaryKey(['id']);
        $trust = $schema->createTable('device_trust');
        $trust->addColumn('id', 'integer');
        $trust->addColumn('device_id', 'string', ['length' => 26]);
        $trust->addColumn('user_identifier', 'string', ['length' => 191]);
        $trust->addColumn('owner_id', 'integer', ['notnull' => false]);
        $trust->setPrimaryKey(['id']);
        $trust->addIndex(['owner_id'], 'idx_trust_owner');
        $trust->addForeignKeyConstraint('host_owner', ['owner_id'], ['id'], ['onDelete' => 'SET NULL']);
        $pointer = $schema->createTable('host_pointer');
        $pointer->addColumn('trust_id', 'integer');
        $pointer->addForeignKeyConstraint('device_trust', ['trust_id'], ['id']);
        $before = self::shape($schema);

        $args = new GenerateSchemaEventArgs($this->entityManager(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.3']), $schema);
        (new ExactCollationListener())->postGenerateSchema($args);

        $this->assertSame($before, self::shape($args->getSchema()));
        $this->assertSame('utf8mb4_0900_bin', self::collationOf($args->getSchema()->getTable('device_trust'), 'user_identifier'));
        $this->assertSame('utf8mb4_0900_bin', self::collationOf($args->getSchema()->getTable('device_trust'), 'device_id'));
    }

    public function testAManagerWithoutTheBundleEntitiesIsLeftAlone(): void
    {
        $factory = $this->createStub(ClassMetadataFactory::class);
        $factory->method('isTransient')->willReturn(true);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getMetadataFactory')->willReturn($factory);
        $schema = new Schema();
        $schema->createTable('device_trust')->addColumn('user_identifier', 'string', ['length' => 191]);

        $args = new GenerateSchemaEventArgs($manager, $schema);
        (new ExactCollationListener())->postGenerateSchema($args);

        $this->assertSame($schema, $args->getSchema());
        $this->assertNull(self::collationOf($schema->getTable('device_trust'), 'user_identifier'));
    }

    public function testTheFormerTableEventDoesNothing(): void
    {
        $table = new Table('device_trust');
        $table->addColumn('user_identifier', 'string', ['length' => 191]);
        $args = new GenerateSchemaTableEventArgs(new ClassMetadata(DeviceTrustEntity::class), new Schema(), $table);

        (new ExactCollationListener())->postGenerateSchemaTable($args);

        $this->assertSame($table, $args->getClassTable());
        $this->assertNull(self::collationOf($table, 'user_identifier'));
    }

    public function testCollateInPlaceOnOlderDbal(): void
    {
        $table = $this->generatedTables(['driver' => 'pdo_sqlite', 'memory' => true], null)['device_user'];

        $this->assertNull(ExactCollationListener::collate($table, 'user_identifier', 'utf8mb4_nopad_bin', false));
        $this->assertSame('utf8mb4_nopad_bin', self::collationOf($table, 'user_identifier'));
        $this->assertSame('utf8mb4', self::charsetOf($table, 'user_identifier'));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, Table>
     */
    private function generatedTables(array $params, ?ExactCollationListener $listener): array
    {
        $tables = [];
        foreach ($this->generatedSchema($params, $listener)->getTables() as $table) {
            $tables[$table->getName()] = $table;
        }

        return $tables;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function generatedSchema(array $params, ?ExactCollationListener $listener, bool $association = false): Schema
    {
        $entityManager = $this->entityManager($params);
        if (null !== $listener) {
            $entityManager->getEventManager()->addEventListener([ToolEvents::postGenerateSchema], $listener);
        }

        $classes = array_map(
            $entityManager->getClassMetadata(...),
            [DeviceEntity::class, DeviceTrustEntity::class, DeviceUserEntity::class, DeviceObservationEntity::class],
        );
        if ($association) {
            $classes = array_map(static function (ClassMetadata $metadata): ClassMetadata {
                if (DeviceTrustEntity::class !== $metadata->getName()) {
                    return $metadata;
                }
                $metadata = clone $metadata;
                $metadata->mapManyToOne(['fieldName' => 'parent', 'targetEntity' => DeviceTrustEntity::class, 'joinColumns' => [['name' => 'parent_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']]]);

                return $metadata;
            }, $classes);
        }

        return (new SchemaTool($entityManager))->getSchemaFromMetadata($classes);
    }

    /**
     * Foreign keys (local columns, foreign table and columns, ON DELETE) and index names per table.
     *
     * @return array<string, array{fks: list<string>, indexes: list<string>}>
     */
    private static function shape(Schema $schema): array
    {
        $shape = [];
        foreach ($schema->getTables() as $table) {
            $fks = [];
            foreach ($table->getForeignKeys() as $fk) {
                $fks[] = implode(',', $fk->getLocalColumns()).'->'.$fk->getForeignTableName().'('.implode(',', $fk->getForeignColumns()).') '.($fk->getOption('onDelete') ?? '-');
            }
            $indexes = array_map(static fn (Index $index): string => $index->getName(), array_values($table->getIndexes()));
            sort($fks);
            sort($indexes);
            $shape[$table->getName()] = ['fks' => $fks, 'indexes' => $indexes];
        }
        ksort($shape);

        return $shape;
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string|Throwable, string}>
     */
    public static function serverVersions(): iterable
    {
        yield 'mysql 8.0.16 lacks utf8mb4_0900_bin' => [new MySQL80Platform(), '8.0.16', 'utf8mb4_bin'];
        yield 'mysql 8.0.17' => [new MySQL80Platform(), '8.0.17', 'utf8mb4_0900_bin'];
        yield 'mysql 8.0.40 with a suffix' => [new MySQL80Platform(), '8.0.40-0ubuntu0.24.04.1', 'utf8mb4_0900_bin'];
        yield 'unreadable version: the platform decides' => [new MySQL80Platform(), new RuntimeException('offline'), 'utf8mb4_0900_bin'];
        yield 'unparsable version: the platform decides' => [new MySQL80Platform(), 'unknown', 'utf8mb4_0900_bin'];
        if (class_exists(MySQL84Platform::class)) { // DBAL 4.1+
            yield 'mysql 8.4 platform is not queried' => [new MySQL84Platform(), new LogicException('must not be read'), 'utf8mb4_0900_bin'];
        }
        yield 'mariadb is not queried' => [new MariaDBPlatform(), new LogicException('must not be read'), 'utf8mb4_nopad_bin'];
    }

    #[DataProvider('serverVersions')]
    public function testCollationForConnectionChecksTheMySql80Patch(AbstractPlatform $platform, string|Throwable $version, string $expected): void
    {
        if (!(new ReflectionMethod(Connection::class, 'getServerVersion'))->isPublic()) {
            self::markTestSkipped('DBAL 3 keeps Connection::getServerVersion() private (covered by the native-connection test).');
        }
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        if ($version instanceof Throwable) {
            $connection->method('getServerVersion')->willThrowException($version);
        } else {
            $connection->method('getServerVersion')->willReturn($version);
        }

        $this->assertSame($expected, ExactCollationListener::collationForConnection($connection));
    }

    /**
     * DBAL 3 keeps Connection::getServerVersion() private: the native connection is asked instead.
     */
    public function testServerVersionFallsBackToTheNativeConnection(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $dbal3 = new class($pdo) {
            public function __construct(private readonly mixed $native)
            {
            }

            public function getNativeConnection(): mixed
            {
                return $this->native;
            }
        };
        $this->assertSame($pdo->getAttribute(PDO::ATTR_SERVER_VERSION), ExactCollationListener::serverVersion($dbal3));

        $unknownDriver = new $dbal3(new stdClass());
        $this->assertNull(ExactCollationListener::serverVersion($unknownDriver));
        $this->assertNull(ExactCollationListener::serverVersion(new stdClass()));
    }

    private static function collationOf(Table $table, string $column): ?string
    {
        $definition = $table->getColumn($column);
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 3 has no Column::getCollation())
        if (method_exists($definition, 'getCollation')) {
            return $definition->getCollation();
        }

        $options = $definition->getPlatformOptions(); // DBAL 3

        return isset($options['collation']) && \is_string($options['collation']) ? $options['collation'] : null;
    }

    private static function charsetOf(Table $table, string $column): ?string
    {
        $definition = $table->getColumn($column);
        // @phpstan-ignore function.alreadyNarrowedType (DBAL 3 has no Column::getCharset())
        if (method_exists($definition, 'getCharset')) {
            return $definition->getCharset();
        }

        $options = $definition->getPlatformOptions(); // DBAL 3

        return isset($options['charset']) && \is_string($options['charset']) ? $options['charset'] : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function entityManager(array $params): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $configuration->enableNativeLazyObjects(true);
        }

        return new EntityManager(DriverManager::getConnection($params, $configuration), $configuration, new EventManager());
    }

    /**
     * SQLite's platform on DBAL 3 (SqlitePlatform) and DBAL 4 (SQLitePlatform).
     */
    private static function sqlitePlatform(): AbstractPlatform
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->getDatabasePlatform();
    }
}
