<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\PersonalData;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Event\DeviceIntelligencePersonalDataErasedEvent;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;
use Nowo\DeviceIntelligenceBundle\Tests\Integration\EntityManager\EntityManagerTestKernel;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use Nowo\DeviceIntelligenceBundle\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * GDPR ports (1.9) through a real kernel, on SQLite and, in CI, on MySQL and PostgreSQL (`DI_TEST_DATABASE_URL`):
 * counts, idempotency, byte-exact matching (a MySQL `*_ai_ci` collation returns `Alice` and `alíce` for `alice`),
 * shared devices kept, export shape, own transaction + event after commit, the caller's transaction joined without
 * an event, and the dedicated entity manager's connection.
 */
final class PersonalDataKernelTest extends TestCase
{
    private ?EntityManagerTestKernel $kernel = null;

    /** @var list<DeviceIntelligencePersonalDataErasedEvent> */
    private array $events = [];

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

    public function testEraseDeletesOnlyTheSubjectsRowsAndUnreferencedDevices(): void
    {
        $connection = $this->bootStandard('erase');
        $d = $this->seed();
        $before = $this->counts($connection);

        $result = $this->eraser()->erase(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alice'));

        self::assertSame(['observations' => 3, 'device_users' => 3, 'device_trusts' => 2, 'devices' => 2], $result->toArray());
        self::assertSame(10, $result->total());
        self::assertSame([
            'device' => $before['device'] - 2,
            'observation' => $before['observation'] - 3,
            'device_user' => $before['device_user'] - 3,
            'device_trust' => $before['device_trust'] - 2,
        ], $this->counts($connection));

        $left = $this->column($connection, 'SELECT id FROM device_intelligence_device');
        self::assertNotContains($d['mine'], $left, 'only alice referenced it');
        self::assertNotContains($d['trustOnly'], $left, 'only alice trusted it');
        self::assertContains($d['shared'], $left, 'bob still uses it');
        self::assertContains($d['anonymous'], $left, 'an anonymous observation still references it');
        self::assertContains($d['caseVariant'], $left);
        self::assertContains($d['accentVariant'], $left);
        self::assertSame(['Alice', 'alíce', 'bob'], $this->users($connection, 'device_user'), 'look-alikes are never erased');
        self::assertSame(['Alice', 'bob'], $this->users($connection, 'observation'));
        self::assertSame(['Alice'], $this->users($connection, 'device_trust'));

        self::assertCount(1, $this->events, 'own transaction: one event after the commit');
        self::assertSame('alice', $this->events[0]->userIdentifier);
        self::assertSame($result->toArray(), $this->events[0]->counts);

        $again = $this->eraser()->erase(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alice'));
        self::assertSame(0, $again->total(), 'idempotent');
        self::assertCount(2, $this->events);
    }

    public function testExportListsTheSubjectsDevicesWithoutHashesOrSignals(): void
    {
        $this->bootStandard('export');
        $d = $this->seed();

        $rows = $this->exporter()->export(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alice'));

        self::assertCount(4, $rows);
        self::assertSame([
            'first_seen_at' => self::atom($d['at'] - 3 * 86400),
            'last_seen_at' => self::atom($d['at'] - 3600),
            'sign_ins' => 4,
            'os_family' => 'macos',
            'browser_family' => 'chrome',
            'trusted' => true,
            'trusted_at' => self::atom($d['at'] - 3 * 86400),
            'trust_expires_at' => self::atom($d['at'] + 30 * 86400),
        ], $rows[0]);
        self::assertSame(['windows', 'firefox', false, null], [$rows[1]['os_family'], $rows[1]['browser_family'], $rows[1]['trusted'], $rows[1]['trusted_at']]);
        self::assertSame('linux', $rows[2]['os_family']);
        self::assertSame([null, null, 0, 'ios', 'safari', false, self::atom($d['at'] - 3600), null], array_values($rows[3]), 'trust only, revoked');
        foreach ($rows as $row) {
            self::assertSame(['first_seen_at', 'last_seen_at', 'sign_ins', 'os_family', 'browser_family', 'trusted', 'trusted_at', 'trust_expires_at'], array_keys($row));
        }
        $json = json_encode($rows, \JSON_THROW_ON_ERROR);
        foreach ([$d['mine'], $d['shared'], 'Mozilla', 'sess-', 'abcd'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }

        self::assertCount(1, $this->exporter()->export(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('Alice')), 'case variant: its own device only');
        self::assertCount(1, $this->exporter()->export(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alíce')), 'accent variant: its own device only');
        self::assertSame([], $this->exporter()->export(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('nobody')));
    }

    public function testInsideTheCallersTransactionItJoinsWithoutAnEvent(): void
    {
        $connection = $this->bootStandard('tx');
        $this->seed();

        $connection->beginTransaction();
        $result = $this->eraser()->erase(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alice'));
        self::assertTrue($connection->isTransactionActive());
        self::assertSame(10, $result->total());
        self::assertSame([], $this->events, 'never inside the caller transaction');
        $connection->rollBack();

        self::assertSame(3, $this->linksOfAlice($connection), 'the caller rolled it back');
        self::assertSame([], $this->events);

        $connection->beginTransaction();
        $this->eraser()->erase(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alice'));
        $connection->commit();
        self::assertSame(0, $this->linksOfAlice($connection));
        self::assertSame([], $this->events);
    }

    public function testDedicatedManagerUsesItsOwnConnection(): void
    {
        if (null !== TestDatabase::url()) {
            self::markTestSkipped('Two SQLite databases; the layout does not depend on the platform.');
        }

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
        $device = $this->manager('device_intelligence');
        (new SchemaTool($device))->createSchema($device->getMetadataFactory()->getAllMetadata());
        $this->seed();

        $host = $this->manager('default')->getConnection();
        $host->beginTransaction();
        $result = $this->eraser()->erase(DeviceIntelligencePersonalDataSubject::fromUserIdentifier('alice'));
        self::assertSame(10, $result->total());
        self::assertCount(1, $this->events, 'a host transaction on another connection is not the bundle\'s');
        self::assertTrue($host->isTransactionActive());
        $host->rollBack();
        self::assertSame(0, $this->linksOfAlice($device->getConnection()));
    }

    public function testPortsAreAbsentWithoutDoctrine(): void
    {
        $this->boot(null, ['doctrine' => ['enabled' => false]]);

        self::assertFalse($this->container()->has(DeviceIntelligencePersonalDataEraserInterface::class));
        self::assertFalse($this->container()->has(DeviceIntelligencePersonalDataExporterInterface::class));
    }

    /**
     * Seeds six devices; returns their ids and the reference time.
     *
     * - mine: alice only (2 observations, link, trust);
     * - shared: alice and bob (link + observation each);
     * - anonymous: alice's link and an anonymous observation;
     * - caseVariant / accentVariant: `Alice` / `alíce` (link, and observation + trust for `Alice`);
     * - trustOnly: a revoked trust of alice, no link.
     *
     * @return array{mine: string, shared: string, anonymous: string, caseVariant: string, accentVariant: string, trustOnly: string, at: int}
     */
    private function seed(): array
    {
        $at = time();
        $now = self::date($at);
        $devices = $this->service(DeviceRepositoryInterface::class);
        $observations = $this->service(ObservationRepositoryInterface::class);
        $links = $this->service(DeviceUserRepositoryInterface::class);
        $trusts = $this->service(TrustedDeviceRepositoryInterface::class);
        \assert($devices instanceof DeviceRepositoryInterface && $observations instanceof ObservationRepositoryInterface && $links instanceof DeviceUserRepositoryInterface && $trusts instanceof TrustedDeviceRepositoryInterface);

        $device = static function (string $os, string $browser) use ($devices, $now): Device {
            $device = Scenario::device($now, os: $os, browser: $browser);
            $devices->save($device);

            return $device;
        };
        $link = static fn (Device $device, string $user, int $first, int $last, int $logins) => $links->save(new DeviceUserRelation($device->id, new UserIdentifier($user), self::date($first), self::date($last), $logins));
        $observe = static fn (Device $device, ?string $user) => $observations->save(Scenario::observation($device, now: $now, user: null === $user ? null : new UserIdentifier($user)));

        $mine = $device('macos', 'chrome');
        $observe($mine, 'alice');
        $observe($mine, 'alice');
        $link($mine, 'alice', $at - 3 * 86400, $at - 3600, 4);
        $trusts->save(new TrustedDevice($mine->id, new UserIdentifier('alice'), self::date($at - 3 * 86400), self::date($at + 30 * 86400), null, 'Laptop'));

        $shared = $device('windows', 'firefox');
        $link($shared, 'alice', $at - 2 * 86400, $at - 2 * 86400, 1);
        $link($shared, 'bob', $at - 2 * 86400, $at, 2);
        $observe($shared, 'alice');
        $observe($shared, 'bob');

        $anonymous = $device('linux', 'chrome');
        $link($anonymous, 'alice', $at - 86400, $at - 86400, 1);
        $observe($anonymous, null);

        $caseVariant = $device('android', 'chrome');
        $link($caseVariant, 'Alice', $at - 5 * 86400, $at, 1);
        $observe($caseVariant, 'Alice');
        $trusts->save(new TrustedDevice($caseVariant->id, new UserIdentifier('Alice'), $now, null, null, 'Phone'));

        $accentVariant = $device('macos', 'safari');
        $link($accentVariant, 'alíce', $at - 5 * 86400, $at, 1);

        $trustOnly = $device('ios', 'safari');
        $trusts->save(new TrustedDevice($trustOnly->id, new UserIdentifier('alice'), self::date($at - 3600), null, $now, 'Tablet'));

        return [
            'mine' => $mine->id->value,
            'shared' => $shared->id->value,
            'anonymous' => $anonymous->id->value,
            'caseVariant' => $caseVariant->id->value,
            'accentVariant' => $accentVariant->id->value,
            'trustOnly' => $trustOnly->id->value,
            'at' => $at,
        ];
    }

    private function bootStandard(string $name): Connection
    {
        $orm = ['auto_mapping' => true];
        if (TestDatabase::sequenceIds()) {
            $orm['identity_generation_preferences'] = [PostgreSQLPlatform::class => 'sequence'];
        }
        $this->boot(['dbal' => ['url' => TestDatabase::url() ?? 'sqlite:///'.$this->database($name)], 'orm' => $orm]);
        $manager = $this->manager('default');
        $schema = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::assertSame($manager, $this->registry()->getManagerForClass(DeviceEntity::class));

        return $manager->getConnection();
    }

    /**
     * @param array<string, mixed>|null $doctrine
     * @param array<string, mixed>      $bundle
     */
    private function boot(?array $doctrine, array $bundle = []): void
    {
        $this->kernel = new EntityManagerTestKernel($doctrine, $bundle);
        $this->kernel->boot();
        $dispatcher = $this->service('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(DeviceIntelligencePersonalDataErasedEvent::class, function (DeviceIntelligencePersonalDataErasedEvent $event): void {
            $this->events[] = $event;
        });
    }

    private function eraser(): DeviceIntelligencePersonalDataEraserInterface
    {
        $eraser = $this->service(DeviceIntelligencePersonalDataEraserInterface::class);
        self::assertInstanceOf(DeviceIntelligencePersonalDataEraserInterface::class, $eraser);

        return $eraser;
    }

    private function exporter(): DeviceIntelligencePersonalDataExporterInterface
    {
        $exporter = $this->service(DeviceIntelligencePersonalDataExporterInterface::class);
        self::assertInstanceOf(DeviceIntelligencePersonalDataExporterInterface::class, $exporter);

        return $exporter;
    }

    private function service(string $id): object
    {
        $service = $this->container()->get($id);
        self::assertIsObject($service);

        return $service;
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
        $registry = $this->service('doctrine');
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
     * @return array{device: int, observation: int, device_user: int, device_trust: int}
     */
    private function counts(Connection $connection): array
    {
        $counts = [];
        foreach (['device', 'observation', 'device_user', 'device_trust'] as $table) {
            $counts[$table] = (int) $connection->fetchOne('SELECT COUNT(*) FROM device_intelligence_'.$table);
        }

        return $counts;
    }

    /**
     * Links stored for `alice` byte for byte (a MySQL `*_ci` WHERE would also count the look-alikes).
     */
    private function linksOfAlice(Connection $connection): int
    {
        return \count(array_filter($this->column($connection, 'SELECT user_identifier FROM device_intelligence_device_user'), static fn (string $user): bool => 'alice' === $user));
    }

    /**
     * Distinct stored user identifiers of a table, sorted byte-wise.
     *
     * @return list<string>
     */
    private function users(Connection $connection, string $table): array
    {
        $users = array_values(array_unique(array_filter($this->column($connection, 'SELECT user_identifier FROM device_intelligence_'.$table.' WHERE user_identifier IS NOT NULL'))));
        sort($users, \SORT_STRING);

        return $users;
    }

    /**
     * @return list<string>
     */
    private function column(Connection $connection, string $sql): array
    {
        return array_map(static fn (mixed $value): string => trim((string) $value), $connection->fetchFirstColumn($sql));
    }

    private static function date(int $timestamp): DateTimeImmutable
    {
        return new DateTimeImmutable(date('Y-m-d H:i:s', $timestamp));
    }

    private static function atom(int $timestamp): string
    {
        return self::date($timestamp)->format(DateTimeInterface::ATOM);
    }

    private function database(string $name): string
    {
        $file = \sprintf('%s/nowo_device_intelligence_gdpr_%s_%d.sqlite', sys_get_temp_dir(), $name, (int) getmypid());
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;

        return $file;
    }
}
