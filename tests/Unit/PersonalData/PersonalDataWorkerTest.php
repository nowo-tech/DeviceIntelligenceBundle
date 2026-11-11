<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\PersonalData;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use Nowo\DeviceIntelligenceBundle\Event\DeviceIntelligencePersonalDataErasedEvent;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporter;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;
use Nowo\DeviceIntelligenceBundle\PersonalData\PersonalDataRows;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * FrankenPHP worker mode without kernel reset (1.9): one eraser and one exporter instance serve user A then user B
 * 1,000 times on the same entity manager; no row, result or identity-map entry leaks from one user to the other and
 * memory stays flat.
 */
final class PersonalDataWorkerTest extends TestCase
{
    public function testOneInstanceServesManyUsersWithoutLeaks(): void
    {
        $em = $this->entityManager();
        $dispatcher = new EventDispatcher();
        $events = 0;
        $dispatcher->addListener(DeviceIntelligencePersonalDataErasedEvent::class, static function () use (&$events): void {
            ++$events;
        });
        $eraser = new DeviceIntelligencePersonalDataEraser($em, $dispatcher, new MockClock('2026-08-23 12:00:00'));
        $exporter = new DeviceIntelligencePersonalDataExporter($em);
        $mapper = new DeviceMapper();
        $devices = new DoctrineDeviceRepository($em, $mapper);
        $observations = new DoctrineObservationRepository($em, $mapper);
        $links = new DoctrineDeviceUserRepository($em, $mapper);
        $trusts = new DoctrineTrustedDeviceRepository($em, $mapper);
        $a = DeviceIntelligencePersonalDataSubject::fromUserIdentifier('worker-a');
        $b = DeviceIntelligencePersonalDataSubject::fromUserIdentifier('worker-b');
        $now = Scenario::now();

        $baseline = 0;
        for ($i = 1; $i <= 1000; ++$i) {
            $device = Scenario::device($now);
            $devices->save($device);
            $observations->save(Scenario::observation($device, now: $now, user: new UserIdentifier('worker-a')));
            $links->save(new DeviceUserRelation($device->id, new UserIdentifier('worker-a'), $now, $now, 1));
            $links->save(new DeviceUserRelation($device->id, new UserIdentifier('worker-b'), $now, $now, $i));
            $trusts->save(new TrustedDevice($device->id, new UserIdentifier('worker-b'), $now, null, null, 'b'));

            self::assertSame(1, $exporter->export($a)[0]['sign_ins']);
            self::assertFalse($exporter->export($a)[0]['trusted'], 'B\'s trust never shows in A\'s export');
            self::assertSame($i, $exporter->export($b)[0]['sign_ins']);

            self::assertSame(['observations' => 1, 'device_users' => 1, 'device_trusts' => 0, 'devices' => 0], $eraser->erase($a)->toArray());
            self::assertSame([], $exporter->export($a));
            self::assertCount(1, $exporter->export($b), 'A\'s erasure leaves B\'s device');
            self::assertSame(['observations' => 0, 'device_users' => 1, 'device_trusts' => 1, 'devices' => 1], $eraser->erase($b)->toArray());
            self::assertSame(0, $eraser->erase($a)->total());
            self::assertSame(0, $em->getUnitOfWork()->size());

            if (100 === $i) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat');
        self::assertSame(3000, $events);
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM device'));
    }

    public function testKeysetBatchesCoverEveryRow(): void
    {
        $em = $this->entityManager();
        $links = new DoctrineDeviceUserRepository($em, new DeviceMapper());
        $now = Scenario::now();
        for ($i = 0; $i < 7; ++$i) {
            $links->save(new DeviceUserRelation(Scenario::device($now)->id, new UserIdentifier('batch'), $now, $now, 1));
        }

        self::assertCount(7, iterator_to_array(PersonalDataRows::exact($em, DeviceUserEntity::class, 'batch', [], 3), false));
        self::assertCount(7, iterator_to_array(PersonalDataRows::exact($em, DeviceUserEntity::class, 'batch', [], 7), false));
    }

    public function testRegistryWithoutOrmManagerFails(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(LogicException::class);
        (new DeviceIntelligencePersonalDataExporter($registry))->export(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('x'));
    }

    private function entityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [\dirname(__DIR__, 3).'/src/Entity'],
            true,
            sys_get_temp_dir().'/di_worker_proxies',
        );
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }
}
