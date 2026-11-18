<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\ConnectionLost;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligenceBundle\Controller\CollectController;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\Http\AnalysisInputFactory;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * 1.10 (REQ-RUNTIME-003 rule 6): a lock conflict of the collect endpoint's write unit answers 409 + X-Conflict-Code
 * (CONFLICT_001 with Retry-After, CONFLICT_002 without), configurable to 503; other failures are not mapped.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CollectControllerConflictTest extends TestCase
{
    /**
     * @return iterable<string, array{Throwable, string}>
     */
    public static function conflicts(): iterable
    {
        yield 'mysql deadlock' => [new DeadlockException(self::driverError('Deadlock found', '40001', 1213), null), '1'];
        yield 'lock wait timeout' => [new LockWaitTimeoutException(self::driverError('Lock wait timeout exceeded', 'HY000', 1205), null), '1'];
        yield 'postgresql serialization failure' => [new DeadlockException(self::driverError('could not serialize access', '40001', 7), null), '1'];
        yield 'runner failure with its hint' => [TransientStorageFailure::fromPrevious(new DeadlockException(self::driverError('Deadlock found', '40001', 1213), null), 4), '4'];
    }

    #[DataProvider('conflicts')]
    public function testLockConflictAnswers409WithConflict001(Throwable $failure, string $retryAfter): void
    {
        $response = $this->controller($failure)($this->request());

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_001', $response->headers->get('X-Conflict-Code'));
        self::assertSame($retryAfter, $response->headers->get('Retry-After'));
        self::assertSame(['error' => 'conflict', 'code' => 'CONFLICT_001'], json_decode((string) $response->getContent(), true));
        self::assertNull($response->headers->getCookies()[0] ?? null, 'no observation token for an observation that was not stored');
    }

    public function testCommitEarlierInTheRequestAnswersConflict002WithoutRetryAfter(): void
    {
        $ledger = new CommitLedger();
        $request = $this->request();
        $request->attributes->set(CommitMark::REQUEST_ATTRIBUTE, new CommitMark($ledger));
        $ledger->record();

        $response = $this->controller(new DeadlockException(self::driverError('Deadlock found', '40001', 1213), null))($request);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_002', $response->headers->get('X-Conflict-Code'));
        self::assertFalse($response->headers->has('Retry-After'));
        self::assertSame(['error' => 'conflict', 'code' => 'CONFLICT_002'], json_decode((string) $response->getContent(), true));
    }

    public function testTheHostMayAnswer503(): void
    {
        $response = $this->controller(new LockWaitTimeoutException(self::driverError('Lock wait timeout exceeded', 'HY000', 1205), null), new ConflictResponsePolicy(503, 7))($this->request());

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('CONFLICT_001', $response->headers->get('X-Conflict-Code'));
        self::assertSame('7', $response->headers->get('Retry-After'));
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function notConflicts(): iterable
    {
        yield 'unique violation' => [new UniqueConstraintViolationException(self::driverError('Duplicate entry', '23000', 1062), null)];
        yield 'connection lost' => [new ConnectionLost(self::driverError('MySQL server has gone away', 'HY000', 2006), null)];
        yield 'domain failure' => [new RuntimeException('boom')];
    }

    #[DataProvider('notConflicts')]
    public function testOtherFailuresAreNotMapped(Throwable $failure): void
    {
        try {
            $this->controller($failure)($this->request());
            self::fail('The failure must propagate.');
        } catch (Throwable $thrown) {
            self::assertSame($failure, $thrown);
        }
    }

    private static function driverError(string $message, string $sqlState, int $code): AbstractException
    {
        return new class($message, $sqlState, $code) extends AbstractException {};
    }

    private function request(): Request
    {
        $payload = json_encode([
            'v' => 1,
            'sdkVersion' => '1.0.0',
            'timestamp' => (new DateTimeImmutable())->getTimestamp(),
            'nonce' => 'nonce-conflict-'.bin2hex(random_bytes(4)),
            'signals' => ['timezone' => ['value' => 'Europe/Madrid', 'quality' => 1]],
        ], \JSON_THROW_ON_ERROR);

        return Request::create('/_device/collect', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10'], content: $payload);
    }

    private function controller(Throwable $failure, ?ConflictResponsePolicy $policy = null): CollectController
    {
        $config = ProcessedConfig::object(['endpoint' => ['csrf' => 'none']]);
        $frozen = new FrozenClock(new DateTimeImmutable());
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new class($failure) implements ObservationRepositoryInterface {
                public function __construct(private Throwable $failure)
                {
                }

                public function save(DeviceObservation $observation): void
                {
                    throw $this->failure;
                }

                public function find(ObservationId $id): ?DeviceObservation
                {
                    return null;
                }

                public function latestForDevice(Device $device, int $limit = 10): array
                {
                    return [];
                }

                public function deleteOlderThan(DateTimeImmutable $cutoff): int
                {
                    return 0;
                }
            },
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $limiter = $this->createMock(DeviceRateLimiterInterface::class);
        $limiter->method('consume')->willReturn(true);

        return new CollectController(
            new CollectRequestValidator($config, new OriginValidator($config), new Psr16Cache(new ArrayAdapter())),
            new AnalysisInputFactory($config, $frozen, new PrivacyContext(PrivacyMode::Balanced), 'kernel-secret'),
            new AnalyzeService($engine, new EventDispatcher()),
            new ObservationTokenIssuer($config, $frozen, 'kernel-secret'),
            $config,
            $limiter,
            new NullLogger(),
            $policy,
        );
    }
}
