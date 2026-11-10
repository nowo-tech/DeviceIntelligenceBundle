<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\DependencyInjection;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\Device\DefaultDeviceLabeler;
use Nowo\DeviceIntelligence\Device\DeviceLabelerInterface;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Matching\Candidate\DeviceCandidateProviderInterface;
use Nowo\DeviceIntelligence\Matching\Candidate\RepositoryCandidateProvider;
use Nowo\DeviceIntelligence\Port\GeoIpProviderInterface;
use Nowo\DeviceIntelligence\Port\GeoIpResult;
use Nowo\DeviceIntelligence\Port\MetricsRecorderInterface;
use Nowo\DeviceIntelligence\Port\NullGeoIpProvider;
use Nowo\DeviceIntelligence\Port\NullMetricsRecorder;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessor;
use Nowo\DeviceIntelligence\Privacy\PrivacyProcessorInterface;
use Nowo\DeviceIntelligence\Signal\Server\DefaultServerSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\NetworkSignalProviderInterface;
use Nowo\DeviceIntelligence\Signal\Server\NullNetworkSignalProvider;
use Nowo\DeviceIntelligence\Signal\Server\ServerSignalProviderInterface;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The core ports DeviceIntelligence uses are service aliases: the defaults reproduce DeviceIntelligence::create()
 * and a host replaces a single adapter by overriding its alias.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class CorePortsWiringTest extends TestCase
{
    /**
     * @return array<string, array{class-string, string, class-string}>
     */
    public static function defaultPorts(): array
    {
        return [
            'candidates' => [DeviceCandidateProviderInterface::class, 'candidates', RepositoryCandidateProvider::class],
            'privacy' => [PrivacyProcessorInterface::class, 'privacy', PrivacyProcessor::class],
            'labeler' => [DeviceLabelerInterface::class, 'labeler', DefaultDeviceLabeler::class],
            'server signals' => [ServerSignalProviderInterface::class, 'serverSignals', DefaultServerSignalProvider::class],
            'network signals' => [NetworkSignalProviderInterface::class, 'networkSignals', NullNetworkSignalProvider::class],
            'geoip' => [GeoIpProviderInterface::class, 'geoIp', NullGeoIpProvider::class],
            'metrics' => [MetricsRecorderInterface::class, 'metrics', NullMetricsRecorder::class],
        ];
    }

    public function testDefaultsAreTheImplementationsCreateUses(): void
    {
        $container = $this->container();
        foreach (self::defaultPorts() as [$port, , $implementation]) {
            self::assertSame($implementation, (string) $container->getAlias($port), $port);
        }

        $container->compile();
        $engine = $container->get(DeviceIntelligence::class);
        self::assertInstanceOf(DeviceIntelligence::class, $engine);

        $reference = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        foreach (self::defaultPorts() as [, $property, $implementation]) {
            $actual = (new ReflectionProperty(DeviceIntelligence::class, $property))->getValue($engine);
            self::assertInstanceOf($implementation, $actual, $property);
            self::assertSame(
                (new ReflectionProperty(DeviceIntelligence::class, $property))->getValue($reference)::class,
                $actual::class,
                $property,
            );
        }

        $analysis = $engine->analyze(new AnalysisInput(new DateTimeImmutable('2026-11-10T10:00:00Z'), SignalBag::empty(), '10.0.0.1', 'Mozilla/5.0 Firefox/128.0'));
        self::assertNull($analysis->observation()->country);
    }

    public function testHostAdaptersReplaceSinglePorts(): void
    {
        $container = $this->container();
        $container->register('app.geoip', HostGeoIpProvider::class);
        $container->setAlias(GeoIpProviderInterface::class, 'app.geoip');
        $container->register('app.metrics', HostMetricsRecorder::class)->setPublic(true);
        $container->setAlias(MetricsRecorderInterface::class, 'app.metrics');
        $container->register('app.labeler', HostDeviceLabeler::class);
        $container->setAlias(DeviceLabelerInterface::class, 'app.labeler');
        $container->compile();

        $engine = $container->get(DeviceIntelligence::class);
        self::assertInstanceOf(DeviceIntelligence::class, $engine);
        $analysis = $engine->analyze(new AnalysisInput(new DateTimeImmutable('2026-11-10T10:00:00Z'), SignalBag::empty(), '10.0.0.1', 'Mozilla/5.0 Firefox/128.0'));

        self::assertSame('ES', $analysis->observation()->country);
        self::assertSame('Host label', $analysis->device()->label);
        $metrics = $container->get('app.metrics');
        self::assertInstanceOf(HostMetricsRecorder::class, $metrics);
        self::assertContains('device_intelligence.observations', $metrics->increments);
        // Ports the host did not override keep their defaults.
        self::assertInstanceOf(NullNetworkSignalProvider::class, (new ReflectionProperty(DeviceIntelligence::class, 'networkSignals'))->getValue($engine));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 's');
        $container->setParameter('kernel.environment', 'test');
        $container->register('cache.app', ArrayAdapter::class);
        (new NowoDeviceIntelligenceExtension())->load([['doctrine' => ['enabled' => false]]], $container);

        // Only DeviceIntelligence is under test: the rest of the bundle graph (HTTP, security, Messenger...) is removed
        // at compile time instead of needing the framework services it references.
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->isSynthetic()) {
                $definition->setPublic(DeviceIntelligence::class === $id);
            }
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(false);
        }

        return $container;
    }
}

/**
 * @internal
 */
final class HostGeoIpProvider implements GeoIpProviderInterface
{
    public function locate(string $ip): GeoIpResult
    {
        return new GeoIpResult('ES');
    }
}

/**
 * @internal
 */
final class HostMetricsRecorder implements MetricsRecorderInterface
{
    /** @var list<string> */
    public array $increments = [];

    public function increment(string $name, array $tags = [], int $by = 1): void
    {
        $this->increments[] = $name;
    }

    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
    }
}

/**
 * @internal
 */
final class HostDeviceLabeler implements DeviceLabelerInterface
{
    public function label(SignalBag $signals): string
    {
        return 'Host label';
    }
}
