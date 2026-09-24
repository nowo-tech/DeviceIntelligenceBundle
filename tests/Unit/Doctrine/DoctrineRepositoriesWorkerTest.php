<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\TestCase;

/**
 * Two entity managers on the same SQLite file stand in for two FrankenPHP worker threads
 * that are never reset between requests.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineRepositoriesWorkerTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $file = tempnam(sys_get_temp_dir(), 'di_worker_');
        self::assertIsString($file);
        $this->dbFile = $file;
    }

    protected function tearDown(): void
    {
        if (isset($this->dbFile) && is_file($this->dbFile)) {
            unlink($this->dbFile);
        }
    }

    public function testTrustRevokedByAnotherWorkerIsSeenOnNextRequest(): void
    {
        $workerA = $this->entityManager(true);
        $workerB = $this->entityManager();
        $trustsA = new DoctrineTrustedDeviceRepository($workerA, new DeviceMapper());
        $trustsB = new DoctrineTrustedDeviceRepository($workerB, new DeviceMapper());

        $now = Scenario::now();
        $device = Scenario::device($now);
        $user = new UserIdentifier('alice');

        // Request 1 on worker A grants trust; request 1 on worker B checks it.
        $trustsA->save(new TrustedDevice($device->id, $user, $now, null, null, 'laptop'));
        self::assertNotNull($trustsB->findActive($device->id, $user, $now));

        // Request 2 on worker A revokes; request 2 on worker B (no reset, no clear) must see it.
        $trustsA->save(new TrustedDevice($device->id, $user, $now, null, $now, 'laptop'));
        self::assertNull($trustsB->findActive($device->id, $user, $now));
        self::assertSame([], $trustsB->forUser($user, $now));

        self::assertSame(0, $workerA->getUnitOfWork()->size());
        self::assertSame(0, $workerB->getUnitOfWork()->size());
        self::assertSame(1, $trustsB->countAll());
    }

    public function testDeviceStatusAndRelationsAreReadFreshAndNotRetained(): void
    {
        $workerA = $this->entityManager(true);
        $workerB = $this->entityManager();
        $mapper = new DeviceMapper();
        $devicesA = new DoctrineDeviceRepository($workerA, $mapper);
        $devicesB = new DoctrineDeviceRepository($workerB, $mapper);
        $usersA = new DoctrineDeviceUserRepository($workerA, $mapper);
        $usersB = new DoctrineDeviceUserRepository($workerB, $mapper);
        $observationsA = new DoctrineObservationRepository($workerA, $mapper);

        $now = Scenario::now();
        $device = Scenario::device($now);
        $devicesA->save($device);
        $observationsA->save(Scenario::observation($device, null, $now));
        $usersA->save(new DeviceUserRelation($device->id, new UserIdentifier('alice'), $now, $now, 1));

        $before = $devicesB->find($device->id);
        self::assertNotNull($before);
        self::assertSame(3, $before->observationCount);
        self::assertCount(1, $usersB->forDevice($device->id));

        // Another worker updates the device and adds a second account on it.
        $workerA->getConnection()->executeStatement('UPDATE device SET observation_count = 9');
        $usersA->save(new DeviceUserRelation($device->id, new UserIdentifier('bob'), $now, $now, 1));

        $after = $devicesB->find($device->id);
        self::assertNotNull($after);
        self::assertSame(9, $after->observationCount);
        self::assertCount(2, $usersB->forDevice($device->id));
        self::assertCount(1, $usersB->forUser(new UserIdentifier('bob')));
        self::assertSame(1, $usersB->find($device->id, new UserIdentifier('bob'))?->loginCount);
        self::assertCount(1, $devicesB->findCandidates('macos', 'chrome', 'Europe/Madrid', 'apple', 10, $now->modify('-1 day')));
        self::assertCount(1, $devicesB->all());
        self::assertCount(1, (new DoctrineObservationRepository($workerB, $mapper))->latestForDevice($device));

        self::assertSame(0, $workerA->getUnitOfWork()->size());
        self::assertSame(0, $workerB->getUnitOfWork()->size());
    }

    public function testHostHeldManagedEntityIsRefreshedOnRead(): void
    {
        $em = $this->entityManager(true);
        $trusts = new DoctrineTrustedDeviceRepository($em, new DeviceMapper());
        $now = Scenario::now();
        $device = Scenario::device($now);
        $user = new UserIdentifier('alice');

        $trusts->save(new TrustedDevice($device->id, $user, $now, null, null, 'laptop'));
        // Host application keeps the row managed across the "request" boundary.
        $held = $em->createQueryBuilder()
            ->select('t')
            ->from(DeviceTrustEntity::class, 't')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(DeviceTrustEntity::class, $held);
        self::assertNull($held->getRevokedAt());

        // Concurrent worker revokes via SQL (identity map still has the old row).
        $em->getConnection()->executeStatement('UPDATE device_trust SET revoked_at = ?', [$now->format('Y-m-d H:i:s')]);

        self::assertNull($trusts->findActive($device->id, $user, $now));
        self::assertSame(0, $em->getUnitOfWork()->size());
    }

    public function testRegistryResetsManagerClosedByAPreviousRequest(): void
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $fresh = $this->entityManager(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DeviceTrustEntity::class)
            ->willReturnOnConsecutiveCalls($closed, $fresh, $fresh, $fresh);
        $registry->method('getManagerNames')->willReturn(['audit' => 'doctrine.orm.audit_entity_manager', 'default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManager')->willReturnCallback(fn (?string $name): EntityManagerInterface => 'default' === $name ? $closed : $this->createMock(EntityManagerInterface::class));
        $registry->expects(self::once())->method('resetManager')->with('default')->willReturn($fresh);

        $trusts = new DoctrineTrustedDeviceRepository($registry, new DeviceMapper());
        $now = Scenario::now();
        $device = Scenario::device($now);

        self::assertNull($trusts->findActive($device->id, new UserIdentifier('alice'), $now));
        self::assertSame(0, $trusts->countAll());
    }

    public function testRegistryWithoutOrmManagerFails(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(DeviceEntity::class);
        (new DoctrineDeviceRepository($registry, new DeviceMapper()))->countAll();
    }

    public function testRegistryFailsWhenResetYieldsNoOrmManager(): void
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnOnConsecutiveCalls($closed, null);
        $registry->method('getManagerNames')->willReturn([]);
        $registry->expects(self::never())->method('resetManager');

        $this->expectException(\LogicException::class);
        (new DoctrineDeviceRepository($registry, new DeviceMapper()))->countAll();
    }

    private function entityManager(bool $createSchema = false): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [\dirname(__DIR__, 3).'/src/Entity'],
            true,
            sys_get_temp_dir().'/di_worker_proxies',
        );
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->dbFile], $config);
        $em = new EntityManager($connection, $config);
        if ($createSchema) {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        }

        return $em;
    }
}
