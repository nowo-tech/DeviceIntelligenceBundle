<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

/**
 * REQ-SF-004 rule 7: the collect endpoint exists only when the host imports routes.php; the host's default
 * `routing.controllers` resource (Symfony >= 7.3) must not pick the bundle controller up.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class HostControllersRoutesKernelTest extends TestCase
{
    private ?HostControllersRoutesTestKernel $kernel = null;

    protected function setUp(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove(HostControllersRoutesTestKernel::baseDir());
    }

    public function testRoutingControllersDoesNotRegisterTheCollectEndpoint(): void
    {
        $names = array_keys($this->router(false)->getRouteCollection()->all());

        self::assertSame([], array_values(array_filter($names, static fn (string $name): bool => str_starts_with($name, 'nowo_device_intelligence'))));
    }

    public function testImportedRoutesServeTheCollectEndpoint(): void
    {
        $router = $this->router(true);

        self::assertSame('/_device/collect', $router->getRouteCollection()->get('nowo_device_intelligence_collect')?->getPath());

        self::assertNotNull($this->kernel);
        // No Origin header: the controller is resolved with its explicit tags and rejects the payload.
        $response = $this->kernel->handle(Request::create('/_device/collect', 'POST', content: '{}'));

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
    }

    private function router(bool $importBundleRoutes): RouterInterface
    {
        (new Filesystem())->remove(HostControllersRoutesTestKernel::baseDir());
        $this->kernel = new HostControllersRoutesTestKernel($importBundleRoutes);
        $this->kernel->boot();
        $router = $this->kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router;
    }
}
