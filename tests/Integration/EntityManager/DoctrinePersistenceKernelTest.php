<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\EntityManager;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Analysis;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligenceInterface;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Tests\Integration\EntityManager\Entity\HostNote;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustService;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 1.7, architecture bar rules 6 and 8, through a real kernel: the analysis writes its rows with DBAL and never flushes
 * the host's entity manager (pending host changes stay pending, also inside a host transaction); `entity_manager` maps
 * the entities on a dedicated manager only; `doctrine.enabled: false` maps them nowhere and works without
 * DoctrineBundle; an unknown manager fails the build.
 */
final class DoctrinePersistenceKernelTest extends TestCase
{
    private ?EntityManagerTestKernel $kernel = null;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testStandardHostAnalysisNeverFlushesTheHostManager(): void
    {
        $this->boot([
            'dbal' => ['url' => 'sqlite:///'.$this->database('standard')],
            'orm' => ['auto_mapping' => true, 'mappings' => self::hostMapping()],
        ]);
        $host = $this->manager('default');
        (new SchemaTool($host))->createSchema($host->getMetadataFactory()->getAllMetadata());
        self::assertSame($host, $this->registry()->getManagerForClass(DeviceEntity::class));

        $note = new HostNote('pending host change');
        $host->persist($note);

        $analysis = $this->analyze('alice');

        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_device'));
        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_observation'));
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'), 'the host change is not flushed by the analysis');
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($note), 'and stays pending for the host');

        // Second visit, then trust rows inserted and updated (DBAL INSERT / UPDATE).
        $this->analyze('alice');
        self::assertSame(2, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_observation'));
        $trust = $this->container()->get(DeviceTrustServiceInterface::class);
        self::assertInstanceOf(DeviceTrustService::class, $trust);
        $trust->trust($analysis->device, new UserIdentifier('alice'));
        $trust->trust($analysis->device, new UserIdentifier('alice'), label: 'Laptop');
        self::assertTrue($trust->isTrusted($analysis->device, new UserIdentifier('alice')));
        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_device_trust'));
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'));
    }

    public function testInsideAHostTransactionTheRowsFollowTheHost(): void
    {
        $this->boot([
            'dbal' => ['url' => 'sqlite:///'.$this->database('tx')],
            'orm' => ['auto_mapping' => true],
        ]);
        $host = $this->manager('default');
        (new SchemaTool($host))->createSchema($host->getMetadataFactory()->getAllMetadata());

        $host->getConnection()->beginTransaction();
        $this->analyze(null);
        self::assertTrue($host->getConnection()->isTransactionActive(), 'the savepoint left the host transaction open');
        self::assertSame(1, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_device'));
        $host->getConnection()->rollBack();

        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_device'));
    }

    public function testDedicatedManagerMapsTheEntitiesThereOnly(): void
    {
        $this->boot([
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => ['url' => 'sqlite:///'.$this->database('host')],
                    'device' => ['url' => 'sqlite:///'.$this->database('device')],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers' => [
                    'default' => ['connection' => 'default', 'auto_mapping' => true],
                    'device_intelligence' => ['connection' => 'device'],
                ],
            ],
        ], ['doctrine' => ['entity_manager' => 'device_intelligence']]);
        $host = $this->manager('default');
        $device = $this->manager('device_intelligence');
        self::assertTrue($host->getMetadataFactory()->isTransient(DeviceEntity::class), 'not mapped on the auto-mapped host manager');
        self::assertSame($device, $this->registry()->getManagerForClass(DeviceEntity::class));
        (new SchemaTool($device))->createSchema($device->getMetadataFactory()->getAllMetadata());

        $this->analyze('bob');

        self::assertSame(1, (int) $device->getConnection()->fetchOne('SELECT COUNT(*) FROM device_intelligence_device'));
        self::assertSame([], $host->getConnection()->createSchemaManager()->listTableNames(), 'nothing on the host database');
    }

    public function testDisabledDoctrineMapsNothingEvenWithAutoMapping(): void
    {
        $this->boot([
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['auto_mapping' => true],
        ], ['doctrine' => ['enabled' => false, 'entity_manager' => 'ignored']]);

        $default = $this->manager('default');
        self::assertTrue($default->getMetadataFactory()->isTransient(DeviceEntity::class));
        self::assertInstanceOf(InMemoryDeviceRepository::class, $this->container()->get(DeviceRepositoryInterface::class));
        self::assertSame(1, $this->analyze('carol')->device->observationCount);
    }

    public function testWithoutDoctrineBundleTheCoreRunsInMemory(): void
    {
        $this->boot(null, ['doctrine' => ['enabled' => false]]);

        self::assertFalse($this->container()->has('doctrine'));
        self::assertInstanceOf(InMemoryDeviceRepository::class, $this->container()->get(DeviceRepositoryInterface::class));
        self::assertSame(1, $this->analyze('dave')->device->observationCount);
    }

    public function testUnknownManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_device_intelligence.doctrine.entity_manager is "reports"');

        $this->boot(['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []], ['doctrine' => ['entity_manager' => 'reports']]);
    }

    private function analyze(?string $user): Analysis
    {
        $engine = $this->container()->get(DeviceIntelligenceInterface::class);
        self::assertInstanceOf(DeviceIntelligenceInterface::class, $engine);

        return $engine->analyze(new AnalysisInput(
            new DateTimeImmutable(),
            SignalBag::empty(),
            '203.0.113.7',
            'Mozilla/5.0',
            userIdentifier: null === $user ? null : new UserIdentifier($user),
        ));
    }

    /**
     * @param array<string, mixed>|null $doctrine
     * @param array<string, mixed>      $bundle
     */
    private function boot(?array $doctrine, array $bundle = []): void
    {
        $this->kernel = new EntityManagerTestKernel($doctrine, $bundle);
        $this->kernel->boot();
    }

    private function container(): ContainerInterface
    {
        self::assertInstanceOf(EntityManagerTestKernel::class, $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function registry(): ManagerRegistry
    {
        $registry = $this->container()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        return $registry;
    }

    private function manager(string $name): EntityManagerInterface
    {
        $manager = $this->registry()->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function hostMapping(): array
    {
        return ['HostApp' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Entity',
            'prefix' => 'Nowo\\DeviceIntelligenceBundle\\Tests\\Integration\\EntityManager\\Entity',
        ]];
    }

    private function database(string $name): string
    {
        $file = \sprintf('%s/nowo_device_intelligence_em_%s_%d.sqlite', sys_get_temp_dir(), $name, (int) getmypid());
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;

        return $file;
    }
}
