<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DeviceIntelligenceBundle\Doctrine\DbalEntityWriter;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use Nowo\DeviceIntelligenceBundle\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 1.7 (rule 6): one row through DBAL, no flush, savepoint inside a host transaction.
 */
final class DbalEntityWriterTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        if (TestDatabase::sequenceIds()) {
            $config->setIdentityGenerationPreferences([PostgreSQLPlatform::class => ClassMetadata::GENERATOR_TYPE_SEQUENCE]);
        }
        $params = null === TestDatabase::url() ? ['driver' => 'pdo_sqlite', 'memory' => true] : TestDatabase::params();
        $this->em = new EntityManager(DriverManager::getConnection($params, $config), $config);
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testInsertsAndUpdatesWithoutTheUnitOfWork(): void
    {
        $entity = $this->relation('dev-1', 'alice');
        DbalEntityWriter::write($this->em, $entity, false);

        $row = $this->em->getConnection()->fetchAssociative('SELECT id, device_id, user_identifier, login_count FROM device_user');
        self::assertIsArray($row);
        self::assertSame('alice', $row['user_identifier']);
        self::assertSame((int) $row['id'], $entity->getId(), 'the generated id (IDENTITY or SEQUENCE) is set on the entity');
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap());

        $second = $this->relation('dev-2', 'alice');
        DbalEntityWriter::write($this->em, $second, false);
        self::assertNotSame($entity->getId(), $second->getId());

        $entity->setLoginCount(5);
        DbalEntityWriter::write($this->em, $entity, true);
        self::assertSame(5, (int) $this->em->getConnection()->fetchOne('SELECT login_count FROM device_user WHERE id = ?', [$entity->getId()]));
    }

    public function testIdentityIdIsReadBeforeTheSavepointIsReleased(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        $entity = $this->relation('dev-3', 'carol');
        DbalEntityWriter::write($this->em, $entity, false);
        $id = $entity->getId();
        $connection->commit();

        self::assertNotNull($id);
        self::assertSame('carol', $connection->fetchOne('SELECT user_identifier FROM device_user WHERE id = ?', [$id]));
    }

    public function testPreInsertGeneratorIdIsWrittenInTheInsert(): void
    {
        // A SEQUENCE-like generator on any platform: the id comes first and goes into the INSERT.
        $metadata = $this->em->getClassMetadata(DeviceUserEntity::class);
        $generatorType = $metadata->generatorType;
        $generator = $metadata->idGenerator;
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_CUSTOM);
        $metadata->setIdGenerator(new class extends AbstractIdGenerator {
            public function generateId(EntityManagerInterface $em, ?object $entity): mixed
            {
                return 4242;
            }
        });

        try {
            $entity = $this->relation('dev-4', 'dave');
            DbalEntityWriter::write($this->em, $entity, false);
        } finally {
            $metadata->setIdGeneratorType($generatorType);
            $metadata->setIdGenerator($generator);
        }

        self::assertSame(4242, $entity->getId());
        self::assertSame('dave', $this->em->getConnection()->fetchOne('SELECT user_identifier FROM device_user WHERE id = 4242'));
    }

    public function testFailureInsideAHostTransactionRollsBackOnlyTheSavepoint(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        DbalEntityWriter::write($this->em, $this->relation('host', 'row'), false);
        DbalEntityWriter::write($this->em, $this->relation('dev-1', 'alice'), false);

        try {
            DbalEntityWriter::write($this->em, $this->relation('dev-1', 'alice'), false);
            self::fail('duplicate device/user expected');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertTrue($connection->isTransactionActive(), 'the host transaction is still usable');
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM device_user'));
        $connection->commit();
    }

    public function testTheOriginalErrorWinsWhenTheSavepointRollbackFails(): void
    {
        $original = new RuntimeException('insert failed');
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->em->getConnection()->getDatabasePlatform());
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('createSavepoint');
        $connection->method('insert')->willThrowException($original);
        $connection->method('rollbackSavepoint')->willThrowException(new RuntimeException('savepoint gone'));
        $connection->method('fetchOne')->willReturn('1');
        $connection->expects(self::never())->method('releaseSavepoint');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($this->em->getClassMetadata(DeviceUserEntity::class));
        $em->method('getConnection')->willReturn($connection);
        $em->method('getConfiguration')->willReturn($this->em->getConfiguration());

        try {
            DbalEntityWriter::write($em, $this->relation('dev-2', 'bob'), false);
            self::fail('exception expected');
        } catch (RuntimeException $error) {
            self::assertSame($original, $error);
        }
    }

    private function relation(string $device, string $user): DeviceUserEntity
    {
        $now = new DateTimeImmutable('2026-11-10 10:00:00');

        $entity = new DeviceUserEntity();
        $entity->setDeviceId($device);
        $entity->setUserIdentifier($user);
        $entity->setFirstSeenAt($now);
        $entity->setLastSeenAt($now);
        $entity->setLoginCount(1);

        return $entity;
    }
}
