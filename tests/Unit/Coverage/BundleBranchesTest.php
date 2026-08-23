<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Coverage;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\DeviceUserRepositoryInterface;
use Nowo\DeviceIntelligence\Port\ObservationRepositoryInterface;
use Nowo\DeviceIntelligence\Port\TrustedDeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Privacy\PrivacyContext;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskResult;
use Nowo\DeviceIntelligence\Risk\RiskRuleInterface;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligence\User\UserIdentifierResolverInterface;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligenceBundle\Attribute\AsDeviceRiskRule;
use Nowo\DeviceIntelligenceBundle\Command\DeviceShowCommand;
use Nowo\DeviceIntelligenceBundle\Command\RiskTestCommand;
use Nowo\DeviceIntelligenceBundle\Command\StatsCommand;
use Nowo\DeviceIntelligenceBundle\Command\UserDevicesCommand;
use Nowo\DeviceIntelligenceBundle\Config\DeviceIntelligenceConfig;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\Configuration;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\TablePrefixSubscriber;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\Event\SuspiciousDeviceEvent;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\DeviceRequestSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\SecurityDeviceSubscriber;
use Nowo\DeviceIntelligenceBundle\Http\AnalysisInputFactory;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\Exception\CollectValidationException;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityHandler;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityMessage;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\RateLimiter\SymfonyDeviceRateLimiter;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Request\TokenDeviceContextFactory;
use Nowo\DeviceIntelligenceBundle\Tests\Support\ProcessedConfig;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use Nowo\DeviceIntelligenceBundle\User\SecurityUserIdentifierResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

use const JSON_THROW_ON_ERROR;

/**
 * Remaining bundle branches for ≥99% line coverage.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class BundleBranchesTest extends TestCase
{
    public function testCommandsSuccessPathsAndStatsDoctrine(): void
    {
        $now     = Scenario::now();
        $devices = new InMemoryDeviceRepository();
        $obs     = new InMemoryObservationRepository();
        $users   = new InMemoryDeviceUserRepository();
        $trusts  = new InMemoryTrustedDeviceRepository();
        $engine  = DeviceIntelligence::create($devices, $obs, $users, $trusts);
        $signals = SignalFactory::bagFromClient([
            'platform'             => ['value' => 'MacIntel', 'quality' => 1],
            'canvas'               => ['value' => 'aabbccddeeff0011', 'quality' => 0.95],
            'webgl'                => ['value' => ['vendor' => 'Apple', 'renderer' => 'Apple GPU'], 'quality' => 0.9],
            'screen'               => ['value' => ['width' => 1440, 'height' => 900], 'quality' => 1],
            'timezone'             => ['value' => 'Europe/Madrid', 'quality' => 1],
            'client_hints'         => ['value' => ['brands' => [['brand' => 'Google Chrome', 'version' => '143']], 'platform' => 'macOS'], 'quality' => 0.9],
            'hardware_concurrency' => ['value' => 8, 'quality' => 1],
            'browser_capabilities' => ['value' => ['webp' => true], 'quality' => 1],
            'audio'                => ['value' => '1122334455667788', 'quality' => 0.9],
        ], $now);
        $analysis = $engine->analyze(new AnalysisInput($now, $signals, '1.2.3.4', 'Mozilla/5.0 Chrome/143.0.0.0'));
        $manager  = new DeviceManager($devices, $users, $trusts, new FrozenClock($now));
        $manager->associate($analysis->device(), new UserIdentifier('alice'));

        $show = new CommandTester(new DeviceShowCommand($manager, $obs));
        self::assertSame(0, $show->execute(['deviceId' => $analysis->device()->id->value]));
        self::assertStringContainsString($analysis->device()->id->value, $show->getDisplay());

        $list = new CommandTester(new UserDevicesCommand($manager));
        self::assertSame(0, $list->execute(['user' => 'alice']));
        self::assertStringContainsString($analysis->device()->id->value, $list->getDisplay());

        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->onlyMethods(['getSingleScalarResult'])->getMock();
        $query->method('getSingleScalarResult')->willReturnOnConsecutiveCalls(3, 4, 5, 6);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $mapper          = new DeviceMapper();
        $doctrineDevices = new DoctrineDeviceRepository($em, $mapper);
        $doctrineObs     = new DoctrineObservationRepository($em, $mapper);
        $doctrineUsers   = new DoctrineDeviceUserRepository($em, $mapper);
        $doctrineTrusts  = new DoctrineTrustedDeviceRepository($em, $mapper);
        $stats           = new CommandTester(new StatsCommand($doctrineDevices, $doctrineObs, $doctrineUsers, $doctrineTrusts));
        self::assertSame(0, $stats->execute([]));
        self::assertStringContainsString('3', $stats->getDisplay());

        $unknown      = $this->createMock(DeviceRepositoryInterface::class);
        $unknownStats = new CommandTester(new StatsCommand(
            $unknown,
            $this->createMock(ObservationRepositoryInterface::class),
            $this->createMock(DeviceUserRepositoryInterface::class),
            $this->createMock(TrustedDeviceRepositoryInterface::class),
        ));
        self::assertSame(0, $unknownStats->execute([]));
    }

    public function testRiskTestInvalidJsonAndEmptyStdin(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $service = new AnalyzeService($engine, new EventDispatcher());
        $file    = tempnam(sys_get_temp_dir(), 'di');
        self::assertIsString($file);
        file_put_contents($file, 'not-json');
        $tester = new CommandTester(new RiskTestCommand($service));
        self::assertSame(1, $tester->execute(['--file' => $file]));
        unlink($file);
        self::assertStringContainsString('Invalid JSON', $tester->getDisplay());

        $empty = tempnam(sys_get_temp_dir(), 'di');
        self::assertIsString($empty);
        file_put_contents($empty, '');
        $ok = new CommandTester(new RiskTestCommand($service));
        self::assertSame(0, $ok->execute(['--file' => $empty]));
        unlink($empty);
    }

    public function testDeviceRequestSubscriberBranches(): void
    {
        $now          = Scenario::now();
        $devices      = new InMemoryDeviceRepository();
        $observations = new InMemoryObservationRepository();
        $users        = new InMemoryDeviceUserRepository();
        $trusts       = new InMemoryTrustedDeviceRepository();
        $engine       = DeviceIntelligence::create($devices, $observations, $users, $trusts);
        $analysis     = $engine->analyze(new AnalysisInput($now, SignalBag::empty(), '1.2.3.4'));
        $manager      = new DeviceManager($devices, $users, $trusts, new FrozenClock($now));
        $kernel       = $this->createMock(HttpKernelInterface::class);

        $disabledObserve = ProcessedConfig::object(['enabled' => true, 'observe_on_every_request' => false]);
        $tokens          = new ObservationTokenIssuer($disabledObserve, new FrozenClock($now), 's');
        $sub             = new DeviceRequestSubscriber($disabledObserve, $tokens, $observations, $manager, new TokenDeviceContextFactory());
        $event           = new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
        $sub->onRequest($event);
        self::assertNull($event->getRequest()->attributes->get('_device'));

        $cookieReq = Request::create('/');
        $cookieReq->cookies->set((string) $disabledObserve->tokenCookie()['name'], 'x');
        $sub->onRequest(new RequestEvent($kernel, $cookieReq, HttpKernelInterface::MAIN_REQUEST));

        $already = Request::create('/');
        $already->attributes->set('_device', new DeviceContext($analysis));
        $sub->onRequest(new RequestEvent($kernel, $already, HttpKernelInterface::MAIN_REQUEST));
        self::assertInstanceOf(DeviceContext::class, $already->attributes->get('_device'));

        $observe = ProcessedConfig::object(['observe_on_every_request' => true]);
        $issuer  = new ObservationTokenIssuer($observe, new FrozenClock($now), 's');
        $full    = new DeviceRequestSubscriber(
            $observe,
            $issuer,
            $observations,
            $manager,
            new TokenDeviceContextFactory(),
            new SecurityUserIdentifierResolver(),
            null,
            new NullLogger(),
        );
        $noToken = new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
        $full->onRequest($noToken);

        $bad = Request::create('/');
        $bad->cookies->set((string) $observe->tokenCookie()['name'], 'a.b');
        $full->onRequest(new RequestEvent($kernel, $bad, HttpKernelInterface::MAIN_REQUEST));

        $missingObs = Request::create('/');
        $cookie     = $issuer->issue(ObservationId::generate($now), 'n', true);
        $missingObs->cookies->set($cookie->getName(), (string) $cookie->getValue());
        $full->onRequest(new RequestEvent($kernel, $missingObs, HttpKernelInterface::MAIN_REQUEST));

        $ts       = $now->getTimestamp();
        $payload  = 'not-a-ulid|' . $ts . '|' . ($ts + 3600) . '|n';
        $bogus    = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=') . '.' . hash_hmac('sha256', $payload, 's');
        $throwReq = Request::create('/');
        $throwReq->cookies->set((string) $observe->tokenCookie()['name'], $bogus);
        $full->onRequest(new RequestEvent($kernel, $throwReq, HttpKernelInterface::MAIN_REQUEST));

        $ghostDev = Scenario::device($now);
        $ghostObs = Scenario::observation($ghostDev, now: $now);
        $observations->save($ghostObs);
        $ghostReq = Request::create('/');
        $ghostC   = $issuer->issue($ghostObs->id, 'g', true);
        $ghostReq->cookies->set($ghostC->getName(), (string) $ghostC->getValue());
        $full->onRequest(new RequestEvent($kernel, $ghostReq, HttpKernelInterface::MAIN_REQUEST));
        self::assertNull($ghostReq->attributes->get('_device'));

        $okReq = Request::create('/');
        $okC   = $issuer->issue($analysis->observation()->id, 'n2', true);
        $okReq->cookies->set($okC->getName(), (string) $okC->getValue());
        $full->onRequest(new RequestEvent($kernel, $okReq, HttpKernelInterface::MAIN_REQUEST));
        self::assertInstanceOf(DeviceContext::class, $okReq->attributes->get('_device'));

        $user    = new InMemoryUser('alice', null);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $withUser = new DeviceRequestSubscriber(
            $observe,
            $issuer,
            $observations,
            $manager,
            new TokenDeviceContextFactory(),
            new class implements UserIdentifierResolverInterface {
                public function resolve(object $user): UserIdentifier
                {
                    throw new InvalidValueException('nope');
                }
            },
            $storage,
            new NullLogger(),
        );
        $trustedReq = Request::create('/');
        $trustedReq->cookies->set($okC->getName(), (string) $okC->getValue());
        $withUser->onRequest(new RequestEvent($kernel, $trustedReq, HttpKernelInterface::MAIN_REQUEST));
        self::assertInstanceOf(DeviceContext::class, $trustedReq->attributes->get('_device'));

        $okResolver = new DeviceRequestSubscriber(
            $observe,
            $issuer,
            $observations,
            $manager,
            new TokenDeviceContextFactory(),
            new SecurityUserIdentifierResolver(),
            $storage,
            new NullLogger(),
        );
        $okUser = Request::create('/');
        $okUser->cookies->set($okC->getName(), (string) $okC->getValue());
        $okResolver->onRequest(new RequestEvent($kernel, $okUser, HttpKernelInterface::MAIN_REQUEST));
        self::assertInstanceOf(DeviceContext::class, $okUser->attributes->get('_device'));
    }

    public function testSecuritySubscriberAssociatesOnLogin(): void
    {
        $now      = Scenario::now();
        $devices  = new InMemoryDeviceRepository();
        $users    = new InMemoryDeviceUserRepository();
        $trusts   = new InMemoryTrustedDeviceRepository();
        $obs      = new InMemoryObservationRepository();
        $engine   = DeviceIntelligence::create($devices, $obs, $users, $trusts);
        $analysis = $engine->analyze(new AnalysisInput($now, SignalBag::empty()));
        $manager  = new DeviceManager($devices, $users, $trusts, new FrozenClock($now));
        $stack    = new RequestStack();
        $request  = Request::create('/');
        $request->attributes->set('_device', new DeviceContext($analysis));
        $stack->push($request);

        $subscriber = new SecurityDeviceSubscriber(
            $manager,
            new SecurityUserIdentifierResolver(),
            new InMemoryVelocityEngine(),
            $stack,
            new NullLogger(),
        );

        $user  = new InMemoryUser('bob', null);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $login = $this->createMock(LoginSuccessEvent::class);
        $login->method('getUser')->willReturn($user);
        $subscriber->onLoginSuccess($login);
        self::assertNotSame([], iterator_to_array($manager->devicesForUser(new UserIdentifier('bob'))));

        $interactive = new InteractiveLoginEvent($request, $token);
        $subscriber->onInteractiveLogin($interactive);

        $noContext = new RequestStack();
        $noContext->push(Request::create('/'));
        $lonely = new SecurityDeviceSubscriber(
            $manager,
            new SecurityUserIdentifierResolver(),
            new InMemoryVelocityEngine(),
            $noContext,
            new NullLogger(),
        );
        $lonely->onLoginSuccess($login);

        $failing = new SecurityDeviceSubscriber(
            $manager,
            new class implements UserIdentifierResolverInterface {
                public function resolve(object $user): UserIdentifier
                {
                    throw new InvalidValueException('x');
                }
            },
            new InMemoryVelocityEngine(),
            $stack,
            new NullLogger(),
        );
        $failing->onLoginSuccess($login);
        $this->addToAssertionCount(1);
    }

    public function testHttpBranches(): void
    {
        $config    = ProcessedConfig::object(['endpoint' => ['csrf' => 'none', 'max_payload_bytes' => 1024, 'replay_protection' => true]]);
        $validator = new CollectRequestValidator($config, new OriginValidator($config), new Psr16Cache(new ArrayAdapter()));
        $big       = Request::create('/_device/collect', 'POST', content: str_repeat('a', 1100));
        $big->headers->remove('Content-Length');
        try {
            $validator->validate($big);
            self::fail('size body');
        } catch (CollectValidationException $e) {
            self::assertSame(413, $e->statusCode());
        }

        try {
            $validator->validate(Request::create('/_device/collect', 'POST', content: 'true'));
            self::fail('json type');
        } catch (CollectValidationException) {
            $this->addToAssertionCount(1);
        }

        try {
            $validator->validate(Request::create('/_device/collect', 'POST', content: '{"v":1}'));
            self::fail('ts');
        } catch (CollectValidationException) {
            $this->addToAssertionCount(1);
        }

        $wide = ProcessedConfig::object(['endpoint' => ['csrf' => 'none', 'replay_protection' => true]]);
        $v2   = new CollectRequestValidator($wide, new OriginValidator($wide), new Psr16Cache(new ArrayAdapter()));
        try {
            $v2->validate(Request::create('/_device/collect', 'POST', content: json_encode([
                'v'         => 1,
                'timestamp' => time() * 1000,
            ], JSON_THROW_ON_ERROR)));
            self::fail('nonce');
        } catch (CollectValidationException) {
            $this->addToAssertionCount(1);
        }

        $msOk = json_encode([
            'v'         => 1,
            'timestamp' => time() * 1000,
            'nonce'     => 'n-ms',
            'signals'   => [],
        ], JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('v', $v2->validate(Request::create('/_device/collect', 'POST', content: $msOk)));

        $originCfg                                = ProcessedConfig::array();
        $originCfg['endpoint']['allowed_origins'] = ['', 1, 'cdn.internal'];
        $origins                                  = new OriginValidator(new DeviceIntelligenceConfig($originCfg));
        try {
            $origins->validate(Request::create('/_device/collect', 'POST', server: [
                'HTTP_HOST'   => 'app.test',
                'HTTP_ORIGIN' => 'http://',
            ]));
            self::fail('bad origin');
        } catch (CollectValidationException) {
            $this->addToAssertionCount(1);
        }
        $ok = Request::create('https://app.test/_device/collect', 'POST', server: [
            'HTTP_HOST'   => 'app.test',
            'HTTP_ORIGIN' => 'https://cdn.internal',
        ]);
        $origins->validate($ok);

        $now                                 = Scenario::now();
        $cookieA                             = ProcessedConfig::array();
        $cookieA['token_cookie']['secure']   = 'true';
        $cookieA['token_cookie']['samesite'] = 'weird';
        $cfg                                 = new DeviceIntelligenceConfig($cookieA);
        $issuer                              = new ObservationTokenIssuer($cfg, new FrozenClock($now), 'secret');
        $cookie                              = $issuer->issue(ObservationId::generate($now), 'n', false);
        self::assertTrue($cookie->isSecure());
        self::assertSame('lax', $cookie->getSameSite());

        $off = ProcessedConfig::object(['token_cookie' => ['secure' => '0']]);
        $c2  = (new ObservationTokenIssuer($off, new FrozenClock($now), 'secret'))->issue(ObservationId::generate($now), 'n', true);
        self::assertFalse($c2->isSecure());

        $badSig = Request::create('/');
        $parts  = explode('.', (string) $cookie->getValue());
        $badSig->cookies->set($cookie->getName(), $parts[0] . '.deadbeef');
        self::assertNull($issuer->read($badSig));

        $payload   = rtrim(strtr(base64_encode('only|three|parts'), '+/', '-_'), '=') . '.' . hash_hmac('sha256', 'only|three|parts', 'secret');
        $badChunks = Request::create('/');
        $badChunks->cookies->set($cookie->getName(), $payload);
        self::assertNull($issuer->read($badChunks));

        $factory = new AnalysisInputFactory(
            ProcessedConfig::object(['ip_salt' => '']),
            new FrozenClock($now),
            new PrivacyContext(PrivacyMode::Balanced),
            '',
            new class implements UserIdentifierResolverInterface {
                public function resolve(object $user): UserIdentifier
                {
                    throw new InvalidValueException('x');
                }
            },
            $this->tokenStorageWithUser(),
        );
        $input = $factory->fromRequest(Request::create('/'), ['highEntropyConsent' => false, 'schemaVersion' => 1]);
        self::assertFalse($input->highEntropyConsent);
        self::assertNull($input->userIdentifier);
    }

    public function testRateLimiterFactoryAndNonArrayCache(): void
    {
        $storage = new CacheStorage(new ArrayAdapter());
        $factory = new RateLimiterFactory(['id' => 'di', 'policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'], $storage);
        $limiter = new SymfonyDeviceRateLimiter(ProcessedConfig::object(), new Psr16Cache(new ArrayAdapter()), ['collect' => $factory]);
        self::assertTrue($limiter->consume('collect', 'ip', 'h', null, null));

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn('nope');
        $cache->method('set')->willReturn(true);
        $fallback = new SymfonyDeviceRateLimiter(ProcessedConfig::object(), $cache);
        self::assertTrue($fallback->consume('collect', 'ip', 'h', null, null, 10, '1 minute'));
    }

    public function testAnalyzeServiceHighRiskAndRecalculateSkip(): void
    {
        $rule = new class implements RiskRuleInterface {
            public function name(): string
            {
                return 'always_high';
            }

            public function evaluate(RiskContext $context): RiskResult
            {
                unset($context);

                return new RiskResult(80, 'always_high');
            }
        };
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
            null,
            new RiskEngine([$rule]),
        );
        $dispatcher = new EventDispatcher();
        $seen       = false;
        $dispatcher->addListener(SuspiciousDeviceEvent::class, static function () use (&$seen): void {
            $seen = true;
        });
        $analysis = (new AnalyzeService($engine, $dispatcher))->analyze(new AnalysisInput(Scenario::now(), SignalBag::empty()));
        self::assertTrue($analysis->risk()->isHigh());
        self::assertTrue($seen);
        self::assertSame($analysis, (new SuspiciousDeviceEvent($analysis))->analysis);

        $devices = new InMemoryDeviceRepository();
        $obs     = new InMemoryObservationRepository();
        $device  = Scenario::device();
        $devices->save($device);
        $handler = new RecalculateStabilityHandler($devices, $obs);
        self::assertSame(0, $handler(new RecalculateStabilityMessage($device->id->value)));
    }

    public function testProfilerCollectsSignalSummaries(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $now       = Scenario::now();
        $signals   = SignalFactory::bagFromClient(['timezone' => ['value' => 'UTC', 'quality' => 1]], $now);
        $analysis  = $engine->analyze(new AnalysisInput($now, $signals, '1.1.1.1'));
        $collector = new DeviceIntelligenceDataCollector();
        $collector->collectAnalysis($analysis);
        self::assertNotSame([], $collector->getSignals());
    }

    public function testConfigurationAndExtensionRemaining(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [[
            'default_profile' => 'missing',
        ]]);
    }

    public function testConfigurationLegacyMergeWeightRangeAndRiskOff(): void
    {
        $merged = (new Processor())->processConfiguration(new Configuration(), [[
            'matching' => ['minimum_confidence' => 0.77],
            'profiles' => ['default' => ['privacy' => ['mode' => 'strict']]],
        ]]);
        self::assertSame(0.77, $merged['profiles']['default']['matching']['minimum_confidence']);
        self::assertSame('strict', $merged['profiles']['default']['privacy']['mode']);

        try {
            (new Processor())->processConfiguration(new Configuration(), [[
                'profiles' => ['default' => ['matching' => ['weights' => [
                    'audio'    => 1.5, 'canvas' => 0, 'webgl' => 0, 'platform' => 0, 'screen' => 0,
                    'timezone' => 0, 'hardware' => 0, 'browser_capabilities' => 0, 'client_hints' => 0,
                ]]]],
            ]]);
            self::fail('weight');
        } catch (InvalidConfigurationException) {
            $this->addToAssertionCount(1);
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 's');
        $container->register('cache.app', ArrayAdapter::class);
        $extension = new NowoDeviceIntelligenceExtension();
        $extension->load([['profiles' => ['default' => ['risk' => ['enabled' => false]]]]], $container);
        $ref  = new ReflectionClass($container);
        $prop = null;
        foreach ($ref->getProperties() as $p) {
            if ($p->getName() === 'autoconfiguredAttributes') {
                $prop = $p;
                break;
            }
        }
        self::assertNotNull($prop);
        $cbs = $prop->getValue($container);
        self::assertArrayHasKey(AsDeviceRiskRule::class, $cbs);
        self::assertNotSame([], $cbs[AsDeviceRiskRule::class]);
        $def = new ChildDefinition('x');
        $cbs[AsDeviceRiskRule::class][0]($def, new AsDeviceRiskRule(9));
        self::assertTrue($def->hasTag('nowo.device_intelligence.risk_rule'));
    }

    public function testTablePrefixUniqueConstraintsAndMissingIndexes(): void
    {
        $em       = $this->createMock(EntityManagerInterface::class);
        $metadata = new ClassMetadata(DeviceEntity::class);
        $metadata->setPrimaryTable([
            'name'              => 'device_intelligence_device',
            'uniqueConstraints' => [
                'uniq_x'                     => ['columns' => ['id']],
                'device_intelligence_uniq_y' => ['columns' => ['label']],
            ],
        ]);
        $subscriber = new TablePrefixSubscriber('device_intelligence_');
        $subscriber->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $em));
        $constraints = $metadata->table['uniqueConstraints'] ?? [];
        self::assertArrayHasKey('device_intelligence_uniq_x', $constraints);
        self::assertArrayHasKey('device_intelligence_uniq_y', $constraints);
    }

    private function tokenStorageWithUser(): TokenStorage
    {
        $user    = new InMemoryUser('carol', null);
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return $storage;
    }
}
