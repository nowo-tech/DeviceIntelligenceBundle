<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\DeviceIntelligence\Device\DeviceId;
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
        $query  = $this->queryMock();
        $query->method('getResult')->willReturn([$entity, new stdClass()]);
        $query->method('getSingleScalarResult')->willReturn('4');
        $em    = $this->em($query);
        $finds = 0;
        $em->method('find')->willReturnCallback(static function () use (&$finds, $entity): ?object {
            ++$finds;

            return $finds <= 2 ? null : $entity;
        });
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::exactly(2))->method('flush');

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
        $query  = $this->queryMock();
        $query->method('getResult')->willReturn([]);
        $em = $this->em($query);

        $repo = new DoctrineDeviceRepository($em, $mapper);
        self::assertSame([], $repo->findCandidates('linux', 'firefox', null, null, 5, Scenario::now()));
    }

    public function testObservationRepositoryRoundtrip(): void
    {
        $mapper      = new DeviceMapper();
        $device      = Scenario::device();
        $observation = Scenario::observation($device, ipHash: IpHash::hmac('1.1.1.1', 'salt'));
        $entity      = $mapper->toObservationEntity($observation);
        $query       = $this->queryMock();
        $query->method('getResult')->willReturn([$entity, 'skip']);
        $query->method('getSingleScalarResult')->willReturn(2);
        $query->method('execute')->willReturn(3);
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

    public function testUserRepositorySaveFindAndLists(): void
    {
        $mapper   = new DeviceMapper();
        $device   = Scenario::device();
        $user     = new UserIdentifier('alice');
        $relation = new DeviceUserRelation($device->id, $user, Scenario::now(), Scenario::now(), 1);
        $entity   = $mapper->toUserEntity($relation);
        $query    = $this->queryMock();
        $query->method('getOneOrNullResult')->willReturnOnConsecutiveCalls(null, $entity, $entity, null);
        $query->method('getResult')->willReturn([$entity, 0]);
        $query->method('getSingleScalarResult')->willReturn(1);
        $em = $this->em($query);

        $repo = new DoctrineDeviceUserRepository($em, $mapper);
        $repo->save($relation);
        $repo->save($relation);
        self::assertSame('alice', $repo->find($device->id, $user)?->userIdentifier->value);
        self::assertNull($repo->find(DeviceId::generate(Scenario::now()), new UserIdentifier('bob')));
        self::assertCount(1, $repo->forDevice($device->id));
        self::assertCount(1, $repo->forUser($user));
        self::assertSame(1, $repo->countAll());
    }

    public function testTrustRepositoryActiveExpiredAndForUser(): void
    {
        $mapper  = new DeviceMapper();
        $device  = Scenario::device();
        $user    = new UserIdentifier('alice');
        $now     = Scenario::now();
        $active  = new TrustedDevice($device->id, $user, $now, $now->modify('+1 day'), null, 'laptop');
        $expired = new TrustedDevice($device->id, $user, $now, $now->modify('-1 day'), null, 'old');
        $activeE = $mapper->toTrustEntity($active);
        $expE    = $mapper->toTrustEntity($expired);
        $query   = $this->queryMock();
        $query->method('getOneOrNullResult')->willReturnOnConsecutiveCalls(null, $activeE, $expE, new stdClass());
        $query->method('getResult')->willReturn([$activeE, $expE, 'nope']);
        $query->method('getSingleScalarResult')->willReturn(2);
        $em = $this->em($query);

        $repo = new DoctrineTrustedDeviceRepository($em, $mapper);
        $repo->save($active);
        self::assertNotNull($repo->findActive($device->id, $user, $now));
        self::assertNull($repo->findActive($device->id, $user, $now));
        self::assertNull($repo->findActive($device->id, $user, $now));
        $forUser = $repo->forUser($user, $now);
        self::assertCount(1, $forUser);
        self::assertSame(2, $repo->countAll());
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
            'timezone'     => ['value' => 'UTC', 'normalizedValue' => 'UTC'],
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
            ->onlyMethods(['getResult', 'getSingleScalarResult', 'getOneOrNullResult', 'execute'])
            ->getMock();

        return $query;
    }

    /**
     * @return EntityManagerInterface&MockObject
     */
    private function em(Query $query): EntityManagerInterface
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

        return $em;
    }
}
