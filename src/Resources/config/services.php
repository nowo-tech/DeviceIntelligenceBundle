<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Nowo\DeviceIntelligenceBundle\Command\CleanupCommand;
use Nowo\DeviceIntelligenceBundle\Command\DeviceShowCommand;
use Nowo\DeviceIntelligenceBundle\Command\RecalculateCommand;
use Nowo\DeviceIntelligenceBundle\Command\RiskTestCommand;
use Nowo\DeviceIntelligenceBundle\Command\StatsCommand;
use Nowo\DeviceIntelligenceBundle\Command\UserDevicesCommand;
use Nowo\DeviceIntelligenceBundle\Controller\CollectController;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceMapper;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineDeviceUserRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineObservationRepository;
use Nowo\DeviceIntelligenceBundle\Doctrine\DoctrineTrustedDeviceRepository;
use Nowo\DeviceIntelligenceBundle\Event\AnalyzeService;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\AnalyzeSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\ControllerAttributeSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\DeviceRequestSubscriber;
use Nowo\DeviceIntelligenceBundle\EventSubscriber\SecurityDeviceSubscriber;
use Nowo\DeviceIntelligenceBundle\Http\AnalysisInputFactory;
use Nowo\DeviceIntelligenceBundle\Http\CollectRequestValidator;
use Nowo\DeviceIntelligenceBundle\Http\ObservationTokenIssuer;
use Nowo\DeviceIntelligenceBundle\Http\OriginValidator;
use Nowo\DeviceIntelligenceBundle\Infrastructure\SystemClock;
use Nowo\DeviceIntelligenceBundle\Messenger\CleanupHandler;
use Nowo\DeviceIntelligenceBundle\Messenger\RecalculateStabilityHandler;
use Nowo\DeviceIntelligenceBundle\Profiler\DeviceIntelligenceDataCollector;
use Nowo\DeviceIntelligenceBundle\RateLimiter\DeviceRateLimiterInterface;
use Nowo\DeviceIntelligenceBundle\RateLimiter\SymfonyDeviceRateLimiter;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContextValueResolver;
use Nowo\DeviceIntelligenceBundle\Request\TokenDeviceContextFactory;
use Nowo\DeviceIntelligenceBundle\Trust\DeviceTrustService;
use Nowo\DeviceIntelligenceBundle\User\SecurityUserIdentifierResolver;
use Symfony\Component\Cache\Psr16Cache;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(SystemClock::class);

    $services->set(DeviceMapper::class);
    $services->set(DoctrineDeviceRepository::class);
    $services->set(DoctrineObservationRepository::class);
    $services->set(DoctrineDeviceUserRepository::class);
    $services->set(DoctrineTrustedDeviceRepository::class);

    $services->set('nowo_device_intelligence.simple_cache', Psr16Cache::class)
        ->args([service('cache.app')]);

    $services->set(OriginValidator::class);
    $services->set(CollectRequestValidator::class)
        ->arg('$cache', service('nowo_device_intelligence.simple_cache'));
    $services->set(AnalysisInputFactory::class)
        ->arg('$kernelSecret', param('kernel.secret'));
    $services->set(ObservationTokenIssuer::class)
        ->arg('$secret', param('kernel.secret'));
    $services->set(CollectController::class)
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'device_intelligence'])
        ->public();

    $services->set(AnalyzeService::class)->share(false);
    $services->set(DeviceTrustService::class);
    $services->set(TokenDeviceContextFactory::class);

    $services->set(DeviceContextValueResolver::class)
        ->tag('controller.argument_value_resolver');
    $services->set(DeviceRequestSubscriber::class);
    $services->set(SecurityDeviceSubscriber::class);
    $services->set(ControllerAttributeSubscriber::class);
    $services->set(AnalyzeSubscriber::class);

    $services->set(SecurityUserIdentifierResolver::class);

    $services->set(SymfonyDeviceRateLimiter::class)
        ->arg('$cache', service('nowo_device_intelligence.simple_cache'));
    $services->alias(DeviceRateLimiterInterface::class, SymfonyDeviceRateLimiter::class);

    $services->set(DeviceIntelligenceDataCollector::class)
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CleanupHandler::class);
    $services->set(RecalculateStabilityHandler::class);

    $services->set(DeviceShowCommand::class);
    $services->set(UserDevicesCommand::class);
    $services->set(RiskTestCommand::class);
    $services->set(CleanupCommand::class)
        ->arg('$bus', service('messenger.default_bus')->nullOnInvalid());
    $services->set(StatsCommand::class);
    $services->set(RecalculateCommand::class)
        ->arg('$bus', service('messenger.default_bus')->nullOnInvalid());
};
