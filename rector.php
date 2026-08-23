<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanNot\NegatedAndsToPositiveOrsRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\Concat\RemoveConcatAutocastRector;
use Rector\Symfony\Symfony61\Rector\Class_\CommandConfigureToAttributeRector;
use Rector\Symfony\Symfony72\Rector\StmtsAwareInterface\PushRequestToRequestStackConstructorRector;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\EarlyReturn\Rector\StmtsAwareInterface\ReturnEarlyIfVariableRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/lib',
    ])
    ->withPhpVersion(PhpVersion::PHP_81)
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
        FlipTypeControlToUseExclusiveTypeRector::class,
        NegatedAndsToPositiveOrsRector::class,
        NarrowObjectReturnTypeRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,
        ReturnEarlyIfVariableRector::class,
        RecastingRemovalRector::class,
        RemoveConcatAutocastRector::class,
        CommandConfigureToAttributeRector::class,
        RemoveEmptyClassMethodRector::class,
        PushRequestToRequestStackConstructorRector::class,
    ]);
