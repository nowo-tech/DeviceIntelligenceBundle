<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DeviceIntelligenceBundle\Doctrine\DbalEntityWriter;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
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
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testInsertsAndUpdatesWithoutTheUnitOfWork(): void
    {
        $entity = $this->relation('dev-1', 'alice');
        DbalEntityWriter::write($this->em, $entity, false);

        $row = $this->em->getConnection()->fetchAssociative('SELECT id, device_id, user_identifier, login_count FROM device_user');
        self::assertIsArray($row);
        self::assertSame('alice', $row['user_identifier']);
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap());

        $entity->setId((int) $row['id']);
        $entity->setLoginCount(5);
        DbalEntityWriter::write($this->em, $entity, true);
        self::assertSame(5, (int) $this->em->getConnection()->fetchOne('SELECT login_count FROM device_user'));
    }

    public function testFailureInsideAHostTransactionRollsBackOnlyTheSavepoint(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        $connection->executeStatement("INSERT INTO device_user (device_id, user_identifier, first_seen_at, last_seen_at, login_count) VALUES ('host', 'row', '2026-01-01 00:00:00', '2026-01-01 00:00:00', 0)");
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
