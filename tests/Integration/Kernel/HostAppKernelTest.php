<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DeviceIntelligenceBundle\DependencyInjection\NowoDeviceIntelligenceExtension;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceObservationEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceTrustEntity;
use Nowo\DeviceIntelligenceBundle\Entity\DeviceUserEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Translation\Translator;

/**
 * A real host boots and warms its cache with the bundle: Doctrine proxies of the bundle entities are generated on
 * every supported PHP version (legacy lazy ghosts on PHP 8.3, native lazy objects on 8.4+), and the framework prepend
 * does not require symfony/asset or symfony/translation.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HostAppKernelTest extends TestCase
{
    private ?HostAppTestKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(HostAppTestKernel::baseDir());
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove(HostAppTestKernel::baseDir());
    }

    public function testHostKernelBootsAndWarmsUpItsCache(): void
    {
        $kernel = $this->bootKernel();

        $services = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $services);
        $warmer = $services->get('cache_warmer');
        self::assertInstanceOf(CacheWarmerInterface::class, $warmer);
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertDirectoryExists($kernel->getCacheDir());
    }

    /**
     * @param class-string $entityClass
     */
    #[DataProvider('entityClasses')]
    public function testEntityProxiesCanBeCreated(string $entityClass): void
    {
        $em = $this->bootKernel()->getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $metadata = $em->getClassMetadata($entityClass);
        $identifier = [];
        foreach ($metadata->getIdentifierFieldNames() as $field) {
            $identifier[$field] = 'integer' === $metadata->getTypeOfField($field) ? 1 : '01HZZZZZZZZZZZZZZZZZZZZZZZ';
        }

        $reference = $em->getReference($entityClass, $identifier);

        self::assertInstanceOf($entityClass, $reference);
        self::assertTrue($em->getUnitOfWork()->isUninitializedObject($reference));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function entityClasses(): iterable
    {
        yield 'device' => [DeviceEntity::class];
        yield 'observation' => [DeviceObservationEntity::class];
        yield 'trust' => [DeviceTrustEntity::class];
        yield 'user' => [DeviceUserEntity::class];
    }

    public function testFrameworkPrependFollowsTheInstalledComponents(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        (new NowoDeviceIntelligenceExtension())->prepend($container);

        $framework = array_merge(...$container->getExtensionConfig('framework'));
        self::assertSame(class_exists(Package::class), isset($framework['assets']));
        self::assertSame(class_exists(Translator::class), isset($framework['translator']));
    }

    private function bootKernel(): HostAppTestKernel
    {
        $this->kernel = new HostAppTestKernel();
        $this->kernel->boot();

        return $this->kernel;
    }
}
