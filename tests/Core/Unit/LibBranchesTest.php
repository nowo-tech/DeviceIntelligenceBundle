<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DefaultDeviceLabeler;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceManager;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\MutationReport;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligence\Infrastructure\FrozenClock;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Matching\Candidate\RepositoryCandidateProvider;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Comparator\DefaultSignalComparator;
use Nowo\DeviceIntelligence\Matching\MatchingConfig;
use Nowo\DeviceIntelligence\Matching\MatchingWeights;
use Nowo\DeviceIntelligence\Matching\Similarity;
use Nowo\DeviceIntelligence\Matching\WeightedDeviceMatcher;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Port\DeviceRepositoryInterface;
use Nowo\DeviceIntelligence\Port\GeoIpResult;
use Nowo\DeviceIntelligence\Port\IpReputation;
use Nowo\DeviceIntelligence\Port\NullGeoIpProvider;
use Nowo\DeviceIntelligence\Port\NullIpReputationProvider;
use Nowo\DeviceIntelligence\Privacy\AllowAllConsentGate;
use Nowo\DeviceIntelligence\Privacy\ConsentContext;
use Nowo\DeviceIntelligence\Privacy\IpHash;
use Nowo\DeviceIntelligence\Privacy\PrivacyMode;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessor;
use Nowo\DeviceIntelligence\Risk\RiskContext;
use Nowo\DeviceIntelligence\Risk\RiskEngine;
use Nowo\DeviceIntelligence\Risk\RiskScore;
use Nowo\DeviceIntelligence\Risk\Rule\AutomationRule;
use Nowo\DeviceIntelligence\Risk\Rule\CountryChangeRule;
use Nowo\DeviceIntelligence\Risk\Rule\DeviceVelocityRule;
use Nowo\DeviceIntelligence\Risk\Rule\FingerprintMutationRule;
use Nowo\DeviceIntelligence\Risk\Rule\ImpossibleTravelRule;
use Nowo\DeviceIntelligence\Risk\Rule\IpChangeRule;
use Nowo\DeviceIntelligence\Risk\Rule\MultipleAccountsRule;
use Nowo\DeviceIntelligence\Risk\Rule\RapidAccountCreationRule;
use Nowo\DeviceIntelligence\Risk\Rule\SessionChangeRule;
use Nowo\DeviceIntelligence\Risk\Rule\SuspiciousLoginRule;
use Nowo\DeviceIntelligence\Risk\Rule\TrustedDeviceRule;
use Nowo\DeviceIntelligence\Signal\ClientHintPlatformBridge;
use Nowo\DeviceIntelligence\Signal\EnhancementLevel;
use Nowo\DeviceIntelligence\Signal\Normalizer\BrowserVersionNormalizer;
use Nowo\DeviceIntelligence\Signal\Normalizer\CompactDigestNormalizer;
use Nowo\DeviceIntelligence\Signal\Normalizer\IdentityNormalizer;
use Nowo\DeviceIntelligence\Signal\Normalizer\PlatformNormalizer;
use Nowo\DeviceIntelligence\Signal\Normalizer\ScreenNormalizer;
use Nowo\DeviceIntelligence\Signal\Normalizer\SignalNormalizerRegistry;
use Nowo\DeviceIntelligence\Signal\Normalizer\TimezoneNormalizer;
use Nowo\DeviceIntelligence\Signal\Normalizer\WebGlNormalizer;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Server\DefaultServerSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\NetworkSignalProviderInterface;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;
use Nowo\DeviceIntelligence\Trust\TrustedDevice;
use Nowo\DeviceIntelligence\User\DeviceUserRelation;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Nowo\DeviceIntelligence\Velocity\CacheVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\InMemoryVelocityEngine;
use Nowo\DeviceIntelligence\Velocity\TimeWindow;
use Nowo\DeviceIntelligenceBundle\Tests\Support\Scenario;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * Remaining core branches for ≥99% line coverage.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class LibBranchesTest extends TestCase
{
    public function testValueObjectEdges(): void
    {
        $this->expectException(InvalidValueException::class);
        new Similarity(2.0);
    }

    public function testMoreValueObjectEdges(): void
    {
        self::assertSame(0.0, Similarity::clamp(-1)->value);
        $this->expectException(InvalidValueException::class);
        new Stability(2.0);
    }

    public function testQualityRiskScoreUserId(): void
    {
        try {
            new Quality(2.0);
            self::fail('q');
        } catch (InvalidValueException) {
            $this->addToAssertionCount(1);
        }
        try {
            new RiskScore(101);
            self::fail('r');
        } catch (InvalidValueException) {
            $this->addToAssertionCount(1);
        }
        try {
            new UserIdentifier('');
            self::fail('u');
        } catch (InvalidValueException) {
            $this->addToAssertionCount(1);
        }
        $id = ObservationId::generate(Scenario::now());
        self::assertSame($id->value, (string) $id);
        try {
            new ObservationId('not-a-valid-ulid-value!!!!');
            self::fail('oid');
        } catch (InvalidValueException) {
            $this->addToAssertionCount(1);
        }
        try {
            new MatchingWeights(['a' => 1.5]);
            self::fail('w');
        } catch (InvalidValueException) {
            $this->addToAssertionCount(1);
        }
        try {
            new MatchingWeights(['a' => 0.2, 'b' => 0.2]);
            self::fail('sum');
        } catch (InvalidValueException) {
            $this->addToAssertionCount(1);
        }
        self::assertSame(0.75, MatchingConfig::defaults()->minimumConfidence);
        $report = new MutationReport([SignalName::Canvas], [], 0.4);
        self::assertSame([SignalName::Canvas], $report->changedSignals());
        self::assertSame(2, EnhancementLevel::of(Scenario::bag(Scenario::signal(SignalName::Canvas, 'aa'))));
        self::assertSame(1, EnhancementLevel::of(Scenario::bag(Scenario::signal(SignalName::Platform, 'linux'))));
        $geo = new GeoIpResult('ES', 1.0, 2.0, 'AS1');
        self::assertSame('ES', $geo->country);
        $rep = new IpReputation(true, true, true, true, false, 9);
        self::assertTrue($rep->vpn);
        self::assertNull((new NullIpReputationProvider())->inspect('1.1.1.1'));
        $gate = new AllowAllConsentGate();
        self::assertTrue($gate->allows('timezone', new ConsentContext(PrivacyMode::Balanced, false)));
        self::assertFalse($gate->allows('canvas', new ConsentContext(PrivacyMode::Strict, false)));
        self::assertTrue($gate->allows('canvas', new ConsentContext(PrivacyMode::Balanced, true)));
    }

    public function testInMemoryRepositoriesAndDeviceManager(): void
    {
        $now = Scenario::now();
        $devices = new InMemoryDeviceRepository();
        $users = new InMemoryDeviceUserRepository();
        $trusts = new InMemoryTrustedDeviceRepository();
        $active = Scenario::device($now);
        $revoked = Scenario::device($now->modify('-1 day'), status: DeviceStatus::Revoked, os: 'linux');
        $old = new Device(
            DeviceId::generate($now),
            $now->modify('-400 days'),
            $now->modify('-400 days'),
            1,
            $active->confidence,
            $active->stability,
            DeviceStatus::Active,
            new CandidateIndexKey('macos', 'chrome', 'nvidia', 'desktop', 'UTC', 'x'),
            'old',
            [],
            SignalBag::empty(),
        );
        $tzMiss = Scenario::device($now, os: 'macos', browser: 'chrome', gpu: 'apple', tz: 'UTC');
        $gpuMiss = Scenario::device($now, os: 'macos', browser: 'chrome', gpu: 'nvidia', tz: 'Europe/Madrid');
        $devices->save($revoked);
        $devices->save($old);
        $devices->save($tzMiss);
        $devices->save($gpuMiss);
        $devices->save(Scenario::device($now, os: 'macos', browser: 'firefox', gpu: 'apple', tz: 'Europe/Madrid'));
        $devices->save($active);
        $devices->save(Scenario::device($now, os: 'macos', browser: 'chrome', gpu: 'apple', tz: 'Europe/Madrid'));

        $hits = $devices->findCandidates('macos', 'chrome', 'Europe/Madrid', 'apple', 1, $now->modify('-1 day'));
        self::assertCount(1, $hits);

        $manager = new DeviceManager($devices, $users, $trusts, new FrozenClock($now));
        self::assertSame($active, $manager->get($active->id));
        $alice = new UserIdentifier('alice');
        $rel1 = $manager->associate($active, $alice);
        $rel2 = $manager->associate($active, $alice);
        self::assertSame(2, $rel2->loginCount);
        self::assertSame(1, $manager->accountCount($active));
        self::assertNotSame([], iterator_to_array($manager->usersForDevice($active)));
        self::assertNotSame([], iterator_to_array($manager->devicesForUser($alice)));
        $orphan = new DeviceUserRelation(DeviceId::generate($now), new UserIdentifier('ghost'), $now, $now, 1);
        $users->save($orphan);
        self::assertSame([], iterator_to_array($manager->devicesForUser(new UserIdentifier('ghost'))));

        $manager->trust($active, $alice, $now->modify('+1 day'), 'laptop');
        self::assertTrue($manager->isTrusted($active, $alice));
        self::assertNotSame([], $trusts->forUser($alice, $now));
        $manager->revoke($active, $alice);
        self::assertFalse($manager->isTrusted($active, $alice));
        $manager->revoke($active, new UserIdentifier('nobody'));
        $expired = new TrustedDevice($active->id, new UserIdentifier('eve'), $now, $now->modify('-1 hour'), null, 'x');
        $trusts->save($expired);
        self::assertSame([], $trusts->forUser(new UserIdentifier('eve'), $now));
    }

    public function testDeviceCompareLabelSignalsAndBags(): void
    {
        $now = Scenario::now();
        $bag = Scenario::bag(
            Scenario::signal(SignalName::Timezone, 'Europe/Madrid', stability: 0.9),
            Scenario::signal(SignalName::Canvas, 'aaa', stability: 0.5),
        );
        $dev = Scenario::device($now, $bag);
        $same = Scenario::observation($dev, $bag, $now);
        $mut = Scenario::observation($dev, Scenario::bag(
            Scenario::signal(SignalName::Timezone, 'UTC', 'UTC', stability: 0.9),
            Scenario::signal(SignalName::Canvas, 'bbb', stability: 0.5),
        ), $now);
        $extra = Scenario::observation($dev, Scenario::bag(Scenario::signal(SignalName::Language, 'es')), $now);
        self::assertSame(0.0, $dev->compare($extra)->mutationScore);
        $stable = $dev->compare($same);
        self::assertSame(0.0, $stable->mutationScore);
        self::assertContains(SignalName::Timezone, $dev->historicallyStableSignals());

        $merged = $dev->withObservation(
            $now,
            $dev->confidence,
            $dev->stability,
            new CandidateIndexKey('other', 'other', 'other', 'other', 'UTC', ''),
            $bag,
            '',
        );
        self::assertSame('macos', $merged->indexKey->osFamily);

        $hints = Scenario::bag(Scenario::signal(SignalName::ClientHints, ['browser' => 'Firefox', 'platform' => 'linux'], ['browser' => 'Firefox', 'platform' => 'linux']));
        self::assertStringContainsString('Firefox', (new DefaultDeviceLabeler())->label($hints));

        $s = Scenario::signal(SignalName::Timezone, 'x');
        self::assertSame('x', $s->summary(48));
        $long = Scenario::signal(SignalName::Timezone, str_repeat('z', 80));
        self::assertStringEndsWith('…', $long->summary(8));
        $arr = new Signal(SignalName::Screen, ['w' => 1], ['w' => 1], new Quality(1), 0.7, SignalName::Screen->entropyCategory(), $now);
        self::assertNotSame('', $arr->summary(4));

        $mergedBag = SignalBag::empty()->merge([$s]);
        self::assertTrue($mergedBag->has(SignalName::Timezone));
        self::assertCount(1, $mergedBag);
        self::assertArrayHasKey('timezone', $mergedBag->all());
        self::assertFalse($mergedBag->without(SignalName::Timezone)->has(SignalName::Timezone));

        $fromClient = SignalFactory::bagFromClient([
            'unknown' => 'x',
            'timezone' => ['value' => 'UTC', 'quality' => 1, 'collectedAt' => time() * 1000],
            'platform' => 'linux',
        ], $now);
        self::assertTrue($fromClient->has(SignalName::Timezone));
        self::assertTrue($fromClient->has(SignalName::Platform));
    }

    public function testRiskRulesAndEngineWeights(): void
    {
        $now = Scenario::now();
        $device = Scenario::device($now);
        $autoBag = Scenario::bag(Scenario::signal(SignalName::AutomationIndicators, ['confidence' => 0.9, 'indicators' => ['webdriver']], ['confidence' => 0.9, 'indicators' => ['webdriver']]));
        $obs = Scenario::observation($device, $autoBag, $now, 'FR', 'sess-2', null, new UserIdentifier('a'));
        $ctx = Scenario::context(
            $device,
            $obs,
            false,
            [
                new DeviceUserRelation($device->id, new UserIdentifier('a'), $now, $now, 1),
                new DeviceUserRelation($device->id, new UserIdentifier('b'), $now, $now, 1),
                new DeviceUserRelation($device->id, new UserIdentifier('c'), $now, $now, 1),
            ],
            ['request' => 200, 'registration' => 5, 'login_failure' => 8],
            true,
            'ES',
            'oldhash',
            'sess-1',
            new GeoIpResult('FR'),
        );
        $obsIp = Scenario::observation($device, $autoBag, $now, 'FR', 'sess-2', IpHash::hmac('1.1.1.1', 's'));
        $ctxIp = Scenario::context($device, $obsIp, false, [], [], false, 'ES', 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef', 'sess-1', new GeoIpResult('FR'));

        self::assertGreaterThan(0, (new AutomationRule())->evaluate($ctx)->scoreContribution);
        self::assertSame(0, (new AutomationRule())->evaluate(Scenario::context($device, Scenario::observation($device, Scenario::bag(Scenario::signal(SignalName::AutomationIndicators, ['confidence' => 0.1], ['confidence' => 0.1])))))->scoreContribution);
        self::assertGreaterThan(0, (new MultipleAccountsRule())->evaluate($ctx)->scoreContribution);
        self::assertGreaterThan(0, (new DeviceVelocityRule())->evaluate($ctx)->scoreContribution);
        self::assertGreaterThan(0, (new RapidAccountCreationRule())->evaluate($ctx)->scoreContribution);
        self::assertGreaterThan(0, (new SuspiciousLoginRule())->evaluate($ctx)->scoreContribution);
        self::assertGreaterThan(0, (new CountryChangeRule())->evaluate($ctx)->scoreContribution);
        self::assertGreaterThan(0, (new SessionChangeRule())->evaluate($ctx)->scoreContribution);
        self::assertSame(-25, (new TrustedDeviceRule())->evaluate($ctx)->scoreContribution);
        self::assertGreaterThan(0, (new IpChangeRule())->evaluate($ctxIp)->scoreContribution);

        $mutDevice = Scenario::device($now, Scenario::bag(Scenario::signal(SignalName::Timezone, 'Europe/Madrid', stability: 0.9)));
        $mutObs = Scenario::observation($mutDevice, Scenario::bag(Scenario::signal(SignalName::Timezone, 'UTC', 'UTC', stability: 0.9)));
        self::assertGreaterThan(0, (new FingerprintMutationRule())->evaluate(Scenario::context($mutDevice, $mutObs))->scoreContribution);

        $travelSoon = Scenario::observation($device, country: 'US', now: $now);
        $travelCtx = Scenario::context($device, $travelSoon, previousCountry: 'ES', geo: new GeoIpResult('US'));
        $sameCountry = Scenario::context($device, $travelSoon, previousCountry: 'US', geo: new GeoIpResult('US'));
        self::assertSame(0, (new ImpossibleTravelRule())->evaluate($sameCountry)->scoreContribution);
        self::assertGreaterThan(0, (new ImpossibleTravelRule())->evaluate($travelCtx)->scoreContribution);
        $laterObs = Scenario::observation($device, now: $now->modify('+10 hours'), country: 'US');
        $laterCtx = new RiskContext(
            $laterObs,
            $device,
            $travelCtx->match,
            [],
            [],
            false,
            new GeoIpResult('US'),
            null,
            'ES',
        );
        self::assertSame(15, (new ImpossibleTravelRule())->evaluate($laterCtx)->scoreContribution);

        $engine = new RiskEngine([new TrustedDeviceRule(), new CountryChangeRule()], [
            'trusted_device' => ['enabled' => false],
            'country_change' => ['enabled' => true, 'weight' => 12],
        ]);
        $assessment = $engine->assess($ctx);
        self::assertSame(12, $assessment->score());
    }

    public function testNormalizersComparatorsMatcherAndServerSignals(): void
    {
        $now = Scenario::now();
        $ua = Scenario::signal(SignalName::UserAgent, 'Mozilla/5.0 Edg/120.0');
        self::assertSame('Edge 120', (new BrowserVersionNormalizer())->normalize($ua)->normalizedValue);
        $opr = Scenario::signal(SignalName::UserAgent, 'OPR/90.0');
        self::assertSame('Opera 90', (new BrowserVersionNormalizer())->normalize($opr)->normalizedValue);
        $sam = Scenario::signal(SignalName::UserAgent, 'SamsungBrowser/25.0');
        self::assertSame('Samsung 25', (new BrowserVersionNormalizer())->normalize($sam)->normalizedValue);
        $hints = Scenario::signal(SignalName::ClientHints, [
            'brands' => ['skip', ['brand' => 'Not A Brand', 'version' => '99'], ['brand' => 'Chromium', 'version' => '143']],
            'uaFullVersion' => '143.0.0',
            'platform' => 'Windows',
            'mobile' => true,
        ]);
        $normHints = (new BrowserVersionNormalizer())->normalize($hints);
        self::assertSame('windows', $normHints->normalizedValue['platform']);
        foreach (['macOS', 'iPhone', 'Android', 'Linux', 'FreeBSD'] as $p) {
            $h = Scenario::signal(SignalName::ClientHints, ['brands' => [['brand' => 'Firefox', 'version' => 'x']], 'platform' => $p]);
            (new BrowserVersionNormalizer())->normalize($h);
        }
        $emptyBrands = Scenario::signal(SignalName::ClientHints, ['brands' => 'nope', 'uaFullVersion' => ['x']]);
        (new BrowserVersionNormalizer())->normalize($emptyBrands);
        $otherBrands = Scenario::signal(SignalName::ClientHints, ['brands' => [['brand' => 'Unknown Browser', 'version' => '1']]]);
        self::assertSame('other', (new BrowserVersionNormalizer())->normalize($otherBrands)->normalizedValue['browser']);

        $plat = new PlatformNormalizer();
        self::assertSame('macos', $plat->normalize(Scenario::signal(SignalName::Platform, 'MacIntel'))->normalizedValue);
        self::assertSame('ios', $plat->normalize(Scenario::signal(SignalName::Platform, 'iPhone'))->normalizedValue);
        self::assertSame('chromeos', $plat->normalize(Scenario::signal(SignalName::Platform, 'CrOS'))->normalizedValue);

        $screen = new ScreenNormalizer();
        self::assertSame('mobile-s', $screen->normalize(Scenario::signal(SignalName::Screen, '400x600'))->normalizedValue['class']);
        self::assertSame('qhd', $screen->normalize(Scenario::signal(SignalName::Screen, ['w' => 2500, 'h' => 1400]))->normalizedValue['class']);
        self::assertSame('uhd', $screen->normalize(Scenario::signal(SignalName::Screen, ['width' => 4000, 'height' => 2000]))->normalizedValue['class']);

        $tz = new TimezoneNormalizer();
        self::assertSame('UTC', $tz->normalize(Scenario::signal(SignalName::Timezone, ['id' => '']))->normalizedValue);
        self::assertSame('Europe/Madrid', $tz->normalize(Scenario::signal(SignalName::Timezone, ['timezone' => 'Europe/Madrid']))->normalizedValue);

        $webgl = new WebGlNormalizer();
        self::assertSame('nvidia', $webgl->normalize(Scenario::signal(SignalName::Webgl, 'GeForce RTX'))->normalizedValue['vendor']);
        foreach (['amd radeon', 'intel uhd', 'mali-g78', 'swiftshader'] as $gpu) {
            $webgl->normalize(Scenario::signal(SignalName::Gpu, $gpu));
        }
        self::assertSame('other', $webgl->normalize(Scenario::signal(SignalName::Webgl, ['vendor' => 'xx', 'renderer' => 'yy']))->normalizedValue['vendor']);

        $digest = new CompactDigestNormalizer();
        self::assertSame('aabbccdd', $digest->normalize(Scenario::signal(SignalName::Canvas, ['digest' => 'aabbccdd']))->normalizedValue);
        $long = $digest->normalize(Scenario::signal(SignalName::Audio, str_repeat('ab', 40)));
        self::assertSame(16, \strlen((string) $long->normalizedValue));
        self::assertLessThan(0.3, $digest->normalize(Scenario::signal(SignalName::Fonts, 'zz'))->quality->value);

        $id = new IdentityNormalizer();
        $already = Scenario::signal(SignalName::Language, 'raw', 'norm');
        self::assertSame($already, $id->normalize($already));
        self::assertSame('raw', $id->normalize(Scenario::signal(SignalName::Language, 'raw', 'raw'))->normalizedValue);

        $emptyReg = new SignalNormalizerRegistry([]);
        $sig = Scenario::signal(SignalName::Language, 'es');
        self::assertSame($sig, $emptyReg->normalize($sig));

        $cmp = new DefaultSignalComparator();
        self::assertSame(-1.0, $cmp->similarity(null, $sig));
        $hw1 = Scenario::signal(SignalName::HardwareConcurrency, 8, 8);
        $hw2 = Scenario::signal(SignalName::HardwareConcurrency, 9, 9);
        $hw3 = Scenario::signal(SignalName::HardwareConcurrency, 10, 10);
        $hw4 = Scenario::signal(SignalName::HardwareConcurrency, 20, 20);
        self::assertSame(0.7, $cmp->similarity($hw1, $hw2));
        self::assertSame(0.4, $cmp->similarity($hw1, $hw3));
        self::assertSame(0.15, $cmp->similarity($hw1, $hw4));
        $scrA = Scenario::signal(SignalName::Screen, ['class' => 'hd'], ['class' => 'hd']);
        $scrB = Scenario::signal(SignalName::Screen, 'other', 'other');
        self::assertSame(0.15, $cmp->similarity($scrA, $scrB));
        $glA = Scenario::signal(SignalName::Webgl, ['vendor' => 'apple', 'renderer' => 'a'], ['vendor' => 'apple', 'renderer' => 'a']);
        $glB = Scenario::signal(SignalName::Webgl, ['vendor' => 'apple', 'renderer' => 'b'], ['vendor' => 'apple', 'renderer' => 'b']);
        $glC = Scenario::signal(SignalName::Webgl, ['vendor' => 'nvidia'], ['vendor' => 'nvidia']);
        self::assertSame(0.7, $cmp->similarity($glA, $glB));
        self::assertSame(0.0, $cmp->similarity($glA, $glC));
        $capA = Scenario::signal(SignalName::BrowserCapabilities, ['webp' => true, 'avif' => false], ['webp' => true, 'avif' => false]);
        $capB = Scenario::signal(SignalName::BrowserCapabilities, ['webp' => true, 'k' => 'v'], ['webp' => true, 'k' => 'v']);
        self::assertGreaterThan(0, $cmp->similarity($capA, $capB));
        self::assertSame(1.0, $cmp->similarity(
            Scenario::signal(SignalName::BrowserCapabilities, [], []),
            Scenario::signal(SignalName::BrowserCapabilities, [], []),
        ));
        $strCap = Scenario::signal(SignalName::BrowserCapabilities, 'x', 'x');
        self::assertSame(1.0, $cmp->similarity($strCap, $strCap));
        $brA = Scenario::signal(SignalName::UserAgent, 'Chrome 143', 'Chrome 143');
        $brB = Scenario::signal(SignalName::UserAgent, 'Chrome 150', 'Chrome 150');
        $brC = Scenario::signal(SignalName::UserAgent, 'Firefox 120', 'Firefox 120');
        $brD = Scenario::signal(SignalName::UserAgent, 'weird', 'weird');
        self::assertSame(0.55, $cmp->similarity($brA, $brB));
        self::assertSame(0.2, $cmp->similarity($brA, $brC));
        self::assertSame(0.0, $cmp->similarity($brA, $brD));
        $arrBr = Scenario::signal(SignalName::ClientHints, ['browser' => 'Chrome 143'], ['browser' => 'Chrome 143']);
        self::assertSame(1.0, $cmp->similarity($arrBr, $arrBr));
        $lang = Scenario::signal(SignalName::Language, 'es', 'es');
        self::assertSame(1.0, $cmp->similarity($lang, $lang));

        $storedSignals = Scenario::bag(
            Scenario::signal(SignalName::Canvas, 'aaa', quality: 0.9),
            Scenario::signal(SignalName::Audio, 'bbb', quality: 0.9),
            Scenario::signal(SignalName::Webgl, ['vendor' => 'apple'], ['vendor' => 'apple', 'renderer' => 'a'], 0.9),
            Scenario::signal(SignalName::Platform, 'macos', 'macos'),
        );
        $incoming = Scenario::bag(
            Scenario::signal(SignalName::Canvas, 'zzz', quality: 0.9),
            Scenario::signal(SignalName::Audio, 'yyy', quality: 0.9),
            Scenario::signal(SignalName::Webgl, ['vendor' => 'nvidia'], ['vendor' => 'nvidia', 'renderer' => 'n'], 0.9),
            Scenario::signal(SignalName::Platform, 'macos', 'macos'),
        );
        $storedDev = Scenario::device($now, $storedSignals);
        $obs = Scenario::observation($storedDev, $incoming, $now);
        $match = (new WeightedDeviceMatcher())->match($obs, [$storedDev]);
        self::assertGreaterThanOrEqual(0.0, $match->confidence());

        $mixedQ = Scenario::observation($storedDev, Scenario::bag(
            Scenario::signal(SignalName::Canvas, 'zzz', quality: 0.9),
            Scenario::signal(SignalName::Audio, 'yyy', quality: 0.2),
            Scenario::signal(SignalName::Platform, 'linux', 'linux', 0.9),
        ), $now);
        (new WeightedDeviceMatcher())->match($mixedQ, [$storedDev]);

        $secondEmpty = 0;
        $secondRepo = $this->createMock(DeviceRepositoryInterface::class);
        $secondRepo->method('findCandidates')->willReturnCallback(static function () use (&$secondEmpty, $storedDev): array {
            ++$secondEmpty;

            return 1 === $secondEmpty ? [] : [$storedDev];
        });
        self::assertNotSame([], iterator_to_array((new RepositoryCandidateProvider($secondRepo))->candidates($obs)));

        $epochCalls = 0;
        $epochRepo = $this->createMock(DeviceRepositoryInterface::class);
        $epochRepo->method('findCandidates')->willReturnCallback(static function () use (&$epochCalls, $storedDev): array {
            ++$epochCalls;

            return $epochCalls < 3 ? [] : [$storedDev];
        });
        self::assertNotSame([], iterator_to_array((new RepositoryCandidateProvider($epochRepo))->candidates($obs)));

        $server = new DefaultServerSignalProvider();
        $names = [];
        foreach ($server->collect(new AnalysisInput($now, SignalBag::empty(), '1.1.1.1', 'Mozilla/5.0', [
            'accept-language' => 'es',
            'sec-ch-ua' => '"Chromium"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"macOS"',
        ], 'session-1')) as $signal) {
            $names[] = $signal->name;
        }
        self::assertContains(SignalName::Session, $names);
        self::assertContains(SignalName::ClientHints, $names);

        $bridged = ClientHintPlatformBridge::platformFromHints(
            Scenario::bag(Scenario::signal(SignalName::ClientHints, ['platform' => 'macOS'], ['platform' => 'macOS'])),
            $now,
        );
        self::assertTrue($bridged->has(SignalName::Platform));
        $fromHeader = ClientHintPlatformBridge::platformFromHints(
            Scenario::bag(Scenario::signal(SignalName::ClientHints, ['sec-ch-ua-platform' => '"Windows"'])),
            $now,
        );
        self::assertTrue($fromHeader->has(SignalName::Platform));
        $emptyHints = ClientHintPlatformBridge::platformFromHints(
            Scenario::bag(Scenario::signal(SignalName::ClientHints, [])),
            $now,
        );
        self::assertFalse($emptyHints->has(SignalName::Platform));
    }

    public function testAnalyzeNetworkTrustAndHighRisk(): void
    {
        $now = Scenario::now();
        $devices = new InMemoryDeviceRepository();
        $obs = new InMemoryObservationRepository();
        $users = new InMemoryDeviceUserRepository();
        $trusts = new InMemoryTrustedDeviceRepository();
        $network = new class implements NetworkSignalProviderInterface {
            public function collect(AnalysisInput $input): iterable
            {
                unset($input);
                yield new Signal(
                    SignalName::IpAsn,
                    'AS1',
                    'AS1',
                    new Quality(1),
                    0.4,
                    SignalName::IpAsn->entropyCategory(),
                    new \DateTimeImmutable(),
                    SignalSource::Server,
                );
            }
        };
        $engine = new DeviceIntelligence(
            $devices,
            $obs,
            $users,
            $trusts,
            new WeightedDeviceMatcher(),
            new RepositoryCandidateProvider($devices),
            SignalNormalizerRegistry::defaults(),
            new PrivacyProcessor(),
            RiskEngine::defaults(),
            new DefaultDeviceLabeler(),
            new DefaultServerSignalProvider(),
            $network,
            new NullGeoIpProvider(),
            new InMemoryVelocityEngine(),
        );
        $user = new UserIdentifier('dana');
        $first = $engine->analyze(new AnalysisInput($now, SignalBag::empty(), '8.8.8.8', userIdentifier: $user));
        $trusts->save(new TrustedDevice($first->device()->id, $user, $now, null, null, 'p'));
        $second = $engine->analyze(new AnalysisInput($now->modify('+1 minute'), SignalBag::empty(), '8.8.8.8', userIdentifier: $user));
        self::assertNotSame('', $second->device()->id->value);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn('nope');
        $cache->method('set')->willReturn(true);
        $vel = new CacheVelocityEngine($cache);
        $vel->increment('k', $first->device(), 1);
        self::assertSame(0, $vel->count('k', $first->device(), TimeWindow::parse('1 hours')));
    }
}
