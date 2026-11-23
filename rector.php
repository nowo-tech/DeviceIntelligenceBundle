<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanNot\NegatedAndsToPositiveOrsRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\Concat\RemoveConcatAutocastRector;
use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;
use Rector\Symfony\Symfony61\Rector\Class_\CommandConfigureToAttributeRector;
use Rector\Symfony\Symfony72\Rector\StmtsAwareInterface\PushRequestToRequestStackConstructorRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/lib',
    ])
    // Machine-wide cap: at most 2 worker processes; generous timeout for slow (WSL2 / CI) hosts.
    ->withParallel(300, 2, 10)
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withComposerBased(symfony: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSkip([
        __DIR__ . '/demo',
        __DIR__ . '/vendor',
        // 1.0.1: keep existing null-check style; do not strip attributed empty test methods
        // or convert Console configure() — those are a follow-up, not this patch.
        ExplicitNullableObjectInstanceRector::class,
        NegatedAndsToPositiveOrsRector::class,
        NarrowObjectReturnTypeRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,
        RecastingRemovalRector::class,
        RemoveConcatAutocastRector::class,
        CommandConfigureToAttributeRector::class,
        RemoveEmptyClassMethodRector::class,
        PushRequestToRequestStackConstructorRector::class,
        // Public API: these classes implement EventSubscriberInterface and expose a public static getSubscribedEvents();
        // converting them to #[AsEventListener] would remove that public method and interface (a BC break for hosts
        // that call or decorate them), which must not ship in a minor. Revisit at the next major.
        EventSubscriberToAttributeRector::class => [
            __DIR__ . '/src/EventSubscriber/AnalyzeSubscriber.php',
            __DIR__ . '/src/EventSubscriber/ControllerAttributeSubscriber.php',
            __DIR__ . '/src/EventSubscriber/RequestStateResetSubscriber.php',
            __DIR__ . '/src/EventSubscriber/DeviceRequestSubscriber.php',
            __DIR__ . '/src/EventSubscriber/ProfilerAjaxBridgeSubscriber.php',
        ],
    ]);
