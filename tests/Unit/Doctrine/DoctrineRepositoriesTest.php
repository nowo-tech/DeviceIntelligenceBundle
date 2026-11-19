<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Exception\LookAlikeIdentifierException;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DoctrineRepositoriesTest extends TestCase
{
    public function testDeviceRepositoryFindSaveCandidatesCountAndAll(): void
    {
        $mapper = new DeviceMapper();
        $device = Scenario::device();
        $entity = $mapper->toDeviceEntity($device);
        $query = $this->queryMock();
        $query->method('getResult')->willReturn([$entity, new stdClass()]);
        $query->method('getSingleScalarResult')->willReturn('4');
        // 1.7 (rule 6): the rows are written through DBAL; the entity manager is never persisted to nor flushed.
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(self::metadata()->getConnection()->getDatabasePlatform());
        $connection->expects(self::once())->method('insert');
        $connection->expects(self::once())->method('update');
        $em = $this->em($query, $connection);
        $finds = 0;
        $em->method('find')->willReturnCallback(static function () use (&$finds, $entity): ?object {
            ++$finds;

            return $finds <= 2 ? null : $entity;
        });
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $repo = new DoctrineDeviceRepository($em, $mapper);
        self::assertNull($repo->find($device->id));

        $repo->save($device);
        $repo->save($device);
        self::assertSame($device->id->value, $repo->find($device->id)?->id->value);

        $found = $repo->findCandidates('macos', 'chrome', 'Europe/Madrid', 'apple', 10, Scenario::now());
        self::assertCount(1, $found);
        self::assertSame($device->id->value, $found[0]->id->value);

        $all = $repo->all();
        self::assertCount(1, $all);
        self::assertSame(4, $repo->countAll());
    }

    public function testDeviceRepositoryCandidatesWithoutOptionalFilters(): void
    {
        $mapper = new DeviceMapper();
        $query = $this->queryMock();
        $query->method('getResult')->willReturn([]);
        $em = $this->em($query);

        $repo = new DoctrineDeviceRepository($em, $mapper);
        self::assertSame([], $repo->findCandidates('linux', 'firefox', null, null, 5, Scenario::now()));
    }

    public function testObservationRepositoryRoundtrip(): void
    {
        $mapper = new DeviceMapper();
        $device = Scenario::device();
        $observation = Scenario::observation($device, ipHash: IpHash::hmac('1.1.1.1', 'salt'));
        $entity = $mapper->toObservationEntity($observation);
        $query = $this->queryMock();
        $query->method('getResult')->willReturn([$entity, 'skip']);
        $query->method('getSingleScalarResult')->willReturn(2);
        $query->method('execute')->willReturn(3);
        $query->method('getSingleColumnResult')->willReturn(['01A', '01B', '01C'], []);
        $em = $this->em($query);
        $em->method('find')->willReturnOnConsecutiveCalls(null, $entity, null);

        $repo = new DoctrineObservationRepository($em, $mapper);
        $repo->save($observation);
        self::assertSame($observation->id->value, $repo->find($observation->id)?->id->value);
        self::assertNull($repo->find(ObservationId::generate(Scenario::now())));
        self::assertCount(1, $repo->latestForDevice($device, 5));
        self::assertSame(3, $repo->deleteOlderThan(Scenario::now()));
        self::assertSame(2, $repo->countAll());
    }

    public function testDeleteOlderThanRunsKeysetBatchesUntilAShortBatch(): void
    {
        $query = $this->queryMock();
        // Batch size 2: a full batch, then a short one ends the loop (no third SELECT).
        $query->expects(self::exactly(2))->method('getSingleColumnResult')->willReturn(['01A', '01B'], ['01C']);
        $query->expects(self::exactly(2))->method('execute')->willReturn(2, 1);

        $repo = new DoctrineObservationRepository($this->em($query), new DeviceMapper());

        self::assertSame(3, $repo->deleteOlderThan(Scenario::now(), 2));
    }

    public function testDeleteOlderThanStopsOnAnEmptyBatch(): void
    {
        $query = $this->queryMock();
        $query->method('getSingleColumnResult')->willReturn([]);
        $query->expects(self::never())->method('execute');

        self::assertSame(0, (new DoctrineObservationRepository($this->em($query), new DeviceMapper()))->deleteOlderThan(Scenario::now()));
    }

    public function testUserRepositorySaveFindAndLists(): void
    {
        $em = self::sqlite();
        $repo = new DoctrineDeviceUserRepository($em, new DeviceMapper());
        $device = Scenario::device();
        $user = new UserIdentifier('alice');
        $now = Scenario::now();
        // REQ-SEC-006: lookups read every SQL row (no LIMIT) and keep the byte-identical one; Alice is a distinct account
        $repo->save(new DeviceUserRelation($device->id, new UserIdentifier('Alice'), $now, $now, 9));
        $repo->save(new DeviceUserRelation($device->id, $user, $now, $now, 1));
        $repo->save(new DeviceUserRelation($device->id, $user, $now, $now->modify('+1 hour'), 4));

        self::assertSame(4, $repo->find($device->id, $user)?->loginCount, 'the second save updates the same row');
        self::assertSame(9, $repo->find($device->id, new UserIdentifier('Alice'))?->loginCount);
        self::assertNull($repo->find(DeviceId::generate($now), new UserIdentifier('bob')));
        self::assertSame(['Alice', 'alice'], array_map(static fn (DeviceUserRelation $r): string => $r->userIdentifier->value, $repo->forDevice($device->id)));
        self::assertSame(['alice'], array_map(static fn (DeviceUserRelation $r): string => $r->userIdentifier->value, $repo->forUser($user)));
        self::assertSame(2, $repo->countAll());
        self::assertSame([], array_filter($em->getUnitOfWork()->getIdentityMap()), 'nothing stays managed');
    }

    /**
     * 1.10 (REQ-RUNTIME-003 rule 4): a login adds one to the stored count and moves the last-seen time; the first one
     * creates the link.
     */
    public function testRecordLoginCountsInStorage(): void
    {
        $em = self::sqlite();
        $repo = new DoctrineDeviceUserRepository($em, new DeviceMapper());
        $device = Scenario::device();
        $user = new UserIdentifier('alice');
        $now = Scenario::now();

        $first = $repo->recordLogin($device->id, $user, $now);
        self::assertSame(1, $first->loginCount);
        self::assertEquals($now, $first->firstSeenAt);

        // a stale relation saved meanwhile by another adapter path does not matter: the count is added in SQL
        $em->getConnection()->executeStatement('UPDATE device_user SET login_count = 6');
        $later = $repo->recordLogin($device->id, $user, $now->modify('+1 hour'));
        self::assertSame(7, $later->loginCount);
        self::assertEquals($now, $later->firstSeenAt);
        self::assertEquals($now->modify('+1 hour'), $later->lastSeenAt);
        self::assertSame(1, $repo->countAll());
    }

    public function testTrustRepositoryActiveExpiredAndForUser(): void
    {
        $em = self::sqlite();
        $repo = new DoctrineTrustedDeviceRepository($em, new DeviceMapper());
        $device = Scenario::device();
        $other = DeviceId::generate(Scenario::now()->modify('+1 second'));
        $user = new UserIdentifier('alice');
        $now = Scenario::now();

        self::assertNull($repo->findActive($device->id, $user, $now));
        $repo->save(new TrustedDevice($device->id, new UserIdentifier('alicé'), $now, null, null, 'attacker'));
        self::assertNull($repo->findActive($device->id, $user, $now), 'a look-alike row is never the user\'s trust');

        $repo->save(new TrustedDevice($device->id, $user, $now, $now->modify('+1 day'), null, 'laptop'));
        self::assertSame('laptop', $repo->findActive($device->id, $user, $now)?->label);
        $repo->save(new TrustedDevice($other, $user, $now, $now->modify('-1 day'), null, 'old'));
        self::assertNull($repo->findActive($other, $user, $now), 'expired');

        $forUser = $repo->forUser($user, $now);
        self::assertCount(1, $forUser);
        self::assertSame('laptop', $forUser[0]->label);
        self::assertSame(3, $repo->countAll());

        // a save overwrites the whole record of exactly this device and user
        $repo->save(new TrustedDevice($device->id, $user, $now, null, $now, 'laptop 2', 'admin'));
        self::assertNull($repo->findActive($device->id, $user, $now));
        self::assertSame(3, $repo->countAll());
        self::assertSame(['laptop 2', 'admin'], array_values((array) $em->getConnection()->fetchAssociative('SELECT label, granted_by FROM device_trust WHERE device_id = ? AND user_identifier = ?', [$device->id->value, 'alice'])));
        self::assertSame([], array_filter($em->getUnitOfWork()->getIdentityMap()), 'nothing stays managed');
    }

    /**
     * 1.10 (REQ-RUNTIME-003 rule 4): revoke writes the revocation time alone, so a trust changed meanwhile keeps its
     * expiry and label; without a trust it stores a revoked one.
     */
    public function testRevokeWritesOnlyTheRevocationTime(): void
    {
        $em = self::sqlite();
        $repo = new DoctrineTrustedDeviceRepository($em, new DeviceMapper());
        $device = Scenario::device();
        $user = new UserIdentifier('alice');
        $now = Scenario::now();

        $repo->revoke($device->id, $user, $now, 'device label');
        self::assertNull($repo->findActive($device->id, $user, $now));
        $row = $em->getConnection()->fetchAssociative('SELECT label, expires_at, revoked_at FROM device_trust');
        self::assertIsArray($row);
        self::assertSame('device label', $row['label']);
        self::assertNull($row['expires_at']);
        self::assertNotNull($row['revoked_at']);

        $repo->save(new TrustedDevice($device->id, $user, $now, $now->modify('+3 days'), null, 'laptop'));
        self::assertNotNull($repo->findActive($device->id, $user, $now));
        $repo->revoke($device->id, $user, $now->modify('+1 hour'), 'ignored');
        self::assertNull($repo->findActive($device->id, $user, $now->modify('+2 hours')));
        $row = $em->getConnection()->fetchAssociative('SELECT label, expires_at FROM device_trust');
        self::assertIsArray($row);
        self::assertSame('laptop', $row['label'], 'the label of the trust is kept');
        self::assertNotNull($row['expires_at'], 'the expiry of the trust is kept');
        self::assertSame(1, $repo->countAll());
    }

    /**
     * Audit F-DI-2 / 1.10: after a unique violation, a row of exactly this device and user is a concurrent insert of the
     * same pair (it is updated, no error); a row that only the folding SQL filter matches is a look-alike's (fail
     * closed).
     */
    public function testUniqueViolationUpdatesTheConcurrentRowOrIsALookAlike(): void
    {
        $mapper = new DeviceMapper();
        $device = Scenario::device();
        $user = new UserIdentifier('alice');
        $now = Scenario::now();
        $trust = new TrustedDevice($device->id, $user, $now, null, null, 'laptop');
        $relation = new DeviceUserRelation($device->id, $user, $now, $now, 1);
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        $exact = ['di_id' => 7, 'di_k0' => $device->id->value, 'di_k1' => 'alice'];
        $lookAlike = ['di_id' => 8, 'di_k0' => $device->id->value, 'di_k1' => 'Alice'];
        $saves = [
            static fn (EntityManagerInterface $em) => (new DoctrineTrustedDeviceRepository($em, $mapper))->save($trust),
            static fn (EntityManagerInterface $em) => (new DoctrineDeviceUserRepository($em, $mapper))->save($relation),
        ];

        foreach ($saves as $save) {
            foreach ([[$lookAlike], [$exact]] as $afterwards) {
                $connection = $this->connectionMock([[], $afterwards]);
                $connection->method('insert')->willThrowException($duplicate);
                $connection->expects(self::exactly([$lookAlike] === $afterwards ? 0 : 1))->method('executeStatement')->willReturn(1);
                try {
                    $save($this->em($this->queryMock(), $connection));
                    self::assertSame([$exact], $afterwards, 'the concurrent row was updated');
                } catch (LookAlikeIdentifierException $error) {
                    self::assertSame([$lookAlike], $afterwards);
                    self::assertSame($duplicate, $error->getPrevious());
                }
            }
        }
    }

    /**
     * 1.10.1 (audit UE-DI-2): a unique violation whose row is gone by the locking read (deleted by an erasure or a
     * cleanup meanwhile) is not a look-alike: the INSERT is tried again.
     */
    public function testUniqueViolationOnARowDeletedMeanwhileInsertsAgain(): void
    {
        $device = Scenario::device();
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        $inserts = 0;
        $connection = $this->connectionMock([[], []]);
        $connection->expects(self::exactly(2))->method('insert')->willReturnCallback(static function () use (&$inserts, $duplicate): int {
            if (1 === ++$inserts) {
                throw $duplicate;
            }

            return 1;
        });
        $connection->expects(self::never())->method('executeStatement');

        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $connection), new DeviceMapper()))
            ->save(new TrustedDevice($device->id, new UserIdentifier('alice'), Scenario::now(), null, null, 'laptop'));
        self::assertSame(2, $inserts);
    }

    /**
     * A row that keeps being deleted before every locking read ends in the last unique violation after three INSERTs,
     * never in a look-alike error or an endless loop.
     */
    public function testUniqueViolationOnRowsThatKeepVanishingGivesUpAfterThreeInserts(): void
    {
        $device = Scenario::device();
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        $connection = $this->connectionMock([]);
        $connection->expects(self::exactly(3))->method('insert')->willThrowException($duplicate);

        $this->expectExceptionObject($duplicate);
        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $connection), new DeviceMapper()))
            ->save(new TrustedDevice($device->id, new UserIdentifier('alice'), Scenario::now(), null, null, 'laptop'));
    }

    /**
     * MySQL / MariaDB report 0 rows for an UPDATE that changes nothing: a locking read confirms the row is still there;
     * a row deleted between the read and the UPDATE is inserted again.
     */
    public function testUpdateOfZeroRowsIsConfirmedOrInsertedAgain(): void
    {
        $mapper = new DeviceMapper();
        $device = Scenario::device();
        $trust = new TrustedDevice($device->id, new UserIdentifier('alice'), Scenario::now(), null, null, 'laptop');
        $exact = ['di_id' => 7, 'di_k0' => $device->id->value, 'di_k1' => 'alice'];

        $unchanged = $this->connectionMock([[$exact], [$exact]]);
        $unchanged->method('executeStatement')->willReturn(0);
        $unchanged->expects(self::never())->method('insert');
        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $unchanged), $mapper))->save($trust);

        $deleted = $this->connectionMock([[$exact], []]);
        $deleted->method('executeStatement')->willReturn(0);
        $deleted->expects(self::once())->method('insert');
        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $deleted), $mapper))->save($trust);
    }

    /**
     * A row that keeps vanishing between INSERT and UPDATE (an erasure loop) ends in the last unique violation after
     * three INSERTs, never in an endless loop.
     */
    public function testUpsertGivesUpAfterThreeInserts(): void
    {
        $device = Scenario::device();
        $exact = ['di_id' => 7, 'di_k0' => $device->id->value, 'di_k1' => 'alice'];
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        // read: none; then per round: insert fails, locked read finds it, update 0 rows, locked read: gone; the third
        // locked read finds no row, so the upsert gives up
        $connection = $this->connectionMock([[], [$exact], [], [$exact], [], []]);
        $connection->method('executeStatement')->willReturn(0);
        $connection->expects(self::exactly(3))->method('insert')->willThrowException($duplicate);

        $this->expectExceptionObject($duplicate);
        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $connection), new DeviceMapper()))
            ->save(new TrustedDevice($device->id, new UserIdentifier('alice'), Scenario::now(), null, null, 'laptop'));
    }

    /**
     * 1.10.2 (audit UE2-DI-3): when the third locking read after a unique violation does find the row, one more UPDATE
     * pass is made instead of giving up, and it succeeds.
     */
    public function testUpsertDoesOneMoreUpdateWhenTheLastLockingReadFoundTheRow(): void
    {
        $device = Scenario::device();
        $exact = ['di_id' => 7, 'di_k0' => $device->id->value, 'di_k1' => 'alice'];
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        $connection = $this->connectionMock([[], [$exact], [], [$exact], [], [$exact]]);
        $updates = 0;
        $connection->method('executeStatement')->willReturnCallback(static function () use (&$updates): int {
            return ++$updates < 3 ? 0 : 1;
        });
        $connection->expects(self::exactly(3))->method('insert')->willThrowException($duplicate);

        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $connection), new DeviceMapper()))
            ->save(new TrustedDevice($device->id, new UserIdentifier('alice'), Scenario::now(), null, null, 'laptop'));
        self::assertSame(3, $updates);
    }

    /**
     * 1.10.2 (audit UE2-DI-3): the extra UPDATE pass is the only one, so a row that keeps vanishing while it keeps
     * being found still ends in the last unique violation after four INSERTs.
     */
    public function testUpsertStillEndsWhenTheRowIsFoundEveryTime(): void
    {
        $device = Scenario::device();
        $exact = ['di_id' => 7, 'di_k0' => $device->id->value, 'di_k1' => 'alice'];
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        $connection = $this->connectionMock([[], [$exact], [], [$exact], [], [$exact], [], [$exact]]);
        $connection->method('executeStatement')->willReturn(0);
        $connection->expects(self::exactly(4))->method('insert')->willThrowException($duplicate);

        $this->expectExceptionObject($duplicate);
        (new DoctrineTrustedDeviceRepository($this->em($this->queryMock(), $connection), new DeviceMapper()))
            ->save(new TrustedDevice($device->id, new UserIdentifier('alice'), Scenario::now(), null, null, 'laptop'));
    }

    /**
     * 1.10 (REQ-RUNTIME-003 rule 4): an observed device adds one to the stored count and keeps the stored status, first
     * seen time and metadata; the stability recalculation writes the stability alone.
     */
    public function testSaveObservedIncrementsAndKeepsTheStatus(): void
    {
        $em = self::sqlite();
        $repo = new DoctrineDeviceRepository($em, new DeviceMapper());
        $device = Scenario::device();
        $repo->save($device);
        $em->getConnection()->executeStatement("UPDATE device SET status = 'blocked', observation_count = 10");

        $repo->saveObserved($device->withObservation(Scenario::now()->modify('+1 hour'), $device->confidence, new Stability(0.25), $device->indexKey, $device->lastSignals, 'renamed'));
        $row = $em->getConnection()->fetchAssociative('SELECT status, observation_count, stability, label FROM device');
        self::assertIsArray($row);
        self::assertSame('blocked', $row['status'], 'a concurrent status change is not reverted');
        self::assertSame(11, (int) $row['observation_count']);
        self::assertEqualsWithDelta(0.25, (float) $row['stability'], 0.0001);
        self::assertSame('renamed', $row['label']);

        $repo->saveStability($device->id, new Stability(0.75));
        $row = $em->getConnection()->fetchAssociative('SELECT observation_count, stability FROM device');
        self::assertIsArray($row);
        self::assertSame(11, (int) $row['observation_count']);
        self::assertEqualsWithDelta(0.75, (float) $row['stability'], 0.0001);

        // erased meanwhile: stored again; a concurrent analysis storing it first: counted on its row
        $em->getConnection()->executeStatement('DELETE FROM device');
        $repo->saveObserved($device);
        self::assertSame($device->observationCount, (int) $em->getConnection()->fetchOne('SELECT observation_count FROM device'));
    }

    public function testSaveObservedCountsOnTheRowAConcurrentAnalysisInserted(): void
    {
        $device = Scenario::device();
        $duplicate = new UniqueConstraintViolationException(new class('duplicate') extends AbstractException {}, null);
        $connection = $this->connectionMock([]);
        $connection->expects(self::exactly(2))->method('executeStatement')->willReturn(0, 1);
        $connection->expects(self::once())->method('insert')->willThrowException($duplicate);

        (new DoctrineDeviceRepository($this->em($this->queryMock(), $connection), new DeviceMapper()))->saveObserved($device);
    }

    public function testEntityIdsAndMapperSkipsUnknownSignals(): void
    {
        $user = new DeviceUserEntity();
        $user->setId(9);
        self::assertSame(9, $user->getId());

        $trust = new DeviceTrustEntity();
        $trust->setId(3);
        self::assertSame(3, $trust->getId());

        $bag = (new DeviceMapper())->signalsFromArray([
            'timezone' => ['value' => 'UTC', 'normalizedValue' => 'UTC'],
            'not_a_signal' => ['value' => 'y'],
        ]);
        self::assertTrue($bag->has(SignalName::Timezone));
        self::assertCount(1, $bag);
    }

    /**
     * @return MockObject&Query
     */
    private function queryMock(): Query
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult', 'getSingleScalarResult', 'getOneOrNullResult', 'execute', 'getSingleColumnResult'])
            ->getMock();

        return $query;
    }

    /**
     * @return EntityManagerInterface&MockObject
     */
    private function em(Query $query, ?Connection $connection = null): EntityManagerInterface
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('delete')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        // Real class metadata (id generators included) for DbalEntityWriter.
        $metadata = self::metadata();
        $em->method('getClassMetadata')->willReturnCallback(static fn (string $class): ClassMetadata => $metadata->getClassMetadata($class));
        $em->method('getConfiguration')->willReturn($metadata->getConfiguration());
        if (null === $connection) {
            $connection = $this->createMock(Connection::class);
            $connection->method('getDatabasePlatform')->willReturn($metadata->getConnection()->getDatabasePlatform());
        }
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }

    /**
     * A DBAL connection mock whose fetchAllAssociative() answers $reads in order (empty afterwards).
     *
     * @param list<list<array<string, mixed>>> $reads
     *
     * @return Connection&MockObject
     */
    private function connectionMock(array $reads): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(self::metadata()->getConnection()->getDatabasePlatform());
        $connection->method('fetchAllAssociative')->willReturnCallback(static function () use (&$reads): array {
            return array_shift($reads) ?? [];
        });

        return $connection;
    }

    private static function sqlite(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }

    private static function metadata(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([\dirname(__DIR__, 3).'/src/Entity'], true);
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
    }
}
