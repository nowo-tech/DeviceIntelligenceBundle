<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\Request;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryDeviceUserRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryObservationRepository;
use Nowo\DeviceIntelligence\Infrastructure\InMemoryTrustedDeviceRepository;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContext;
use Nowo\DeviceIntelligenceBundle\Request\DeviceContextValueResolver;
use Nowo\DeviceIntelligenceBundle\Request\TokenDeviceContextFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TokenAndValueResolverTest extends TestCase
{
    public function testFromStoredAndResolver(): void
    {
        $engine = DeviceIntelligence::create(
            new InMemoryDeviceRepository(),
            new InMemoryObservationRepository(),
            new InMemoryDeviceUserRepository(),
            new InMemoryTrustedDeviceRepository(),
        );
        $analysis = $engine->analyze(new AnalysisInput(new DateTimeImmutable(), SignalBag::empty(), '9.9.9.9'));
        $context  = (new TokenDeviceContextFactory())->fromStored($analysis->device(), $analysis->observation(), true);

        self::assertTrue($context->isTrusted());
        self::assertFalse($context->isNew());
        self::assertSame($analysis->device()->id->value, $context->device()->id->value);

        $request = Request::create('/');
        $request->attributes->set('_device', $context);
        $resolver = new DeviceContextValueResolver();
        $typed    = new ArgumentMetadata('device', DeviceContext::class, false, false, null);
        self::assertSame([$context], iterator_to_array($resolver->resolve($request, $typed)));

        $other = new ArgumentMetadata('foo', 'string', false, false, null);
        self::assertSame([], iterator_to_array($resolver->resolve($request, $other)));

        $empty    = Request::create('/');
        $nullable = new ArgumentMetadata('device', DeviceContext::class, false, false, null, true);
        self::assertSame([null], iterator_to_array($resolver->resolve($empty, $nullable)));
        $required = new ArgumentMetadata('device', DeviceContext::class, false, false, null, false);
        self::assertSame([], iterator_to_array($resolver->resolve($empty, $required)));
    }
}
