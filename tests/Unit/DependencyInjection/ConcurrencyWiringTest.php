<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\DependencyInjection;

use Nowo\DeviceIntelligence\DeviceIntelligence;
use Nowo\DeviceIntelligenceBundle\Controller\CollectController;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\Doctrine\DeviceIntelligenceTransactionRunner;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * 1.10 (REQ-RUNTIME-003): the analysis write unit gets the bundle runner when Doctrine is on, and the collect endpoint
 * gets the conflict policy (transaction-doctrine's when no option is set, else its own 409 / 503).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ConcurrencyWiringTest extends TestCase
{
    public function testDefaultsFollowTransactionDoctrineAndUseTheBundleRunner(): void
    {
        $container = $this->load([]);

        $policy = $container->getDefinition(CollectController::class)->getArgument('$conflictPolicy');
        self::assertInstanceOf(Reference::class, $policy);
        self::assertSame(ConflictResponsePolicy::class, (string) $policy);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $policy->getInvalidBehavior());

        $runner = $container->getDefinition(DeviceIntelligence::class)->getArgument(18);
        self::assertInstanceOf(Reference::class, $runner);
        self::assertSame(DeviceIntelligenceTransactionRunner::class, (string) $runner);
        self::assertSame(DeviceIntelligenceTransactionRunner::LOCK_TIMEOUT_MS, $container->getDefinition(DeviceIntelligenceTransactionRunner::class)->getArgument(1));
    }

    public function testHostOptions(): void
    {
        $container = $this->load(['conflict_response' => ['status' => 503], 'doctrine' => ['lock_timeout_ms' => 1500]]);

        $policy = $container->getDefinition(CollectController::class)->getArgument('$conflictPolicy');
        self::assertInstanceOf(Definition::class, $policy);
        self::assertSame([503, ConflictResponsePolicy::DEFAULT_RETRY_AFTER], $policy->getArguments());
        self::assertSame(1500, $container->getDefinition(DeviceIntelligenceTransactionRunner::class)->getArgument(1));

        $policy = $this->load(['conflict_response' => ['retry_after' => 9]])->getDefinition(CollectController::class)->getArgument('$conflictPolicy');
        self::assertInstanceOf(Definition::class, $policy);
        self::assertSame([409, 9], $policy->getArguments());
    }

    public function testWithoutDoctrineTheAnalysisHasNoRunner(): void
    {
        $container = $this->load(['doctrine' => ['enabled' => false]]);

        self::assertNull($container->getDefinition(DeviceIntelligence::class)->getArgument(18));
        self::assertFalse($container->hasDefinition(DeviceIntelligenceTransactionRunner::class));
    }

    public function testRejectsAnotherStatus(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('conflict_response.status must be 409 or 503');

        $this->load(['conflict_response' => ['status' => 500]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 's');
        $container->setParameter('kernel.environment', 'test');
        (new NowoDeviceIntelligenceExtension())->load([$config], $container);

        return $container;
    }
}
