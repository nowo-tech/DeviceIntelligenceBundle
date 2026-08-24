<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoControllerTest extends WebTestCase
{
    public function testRootRedirectsToEnglish(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/en');
    }

    public function testEnglishHomePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Device Intelligence Bundle');
        self::assertSelectorTextContains('html', 'A Device ID is not a credential');
        self::assertSelectorExists('script[src*="/build/"]');
        self::assertSelectorExists('#collect-result');
        self::assertSelectorExists('.nav-pills');
    }

    public function testSpanishHomePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/es');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bundle de inteligencia de dispositivo');
        self::assertSelectorTextContains('html', 'Un Device ID no es una credencial');
        self::assertSelectorExists('script[src*="/build/"]');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function caseProvider(): iterable
    {
        yield 'overview' => ['/en', '/en'];
        yield 'checkout' => ['/en/checkout', '/en/checkout'];
        yield 'login' => ['/en/login', '/en/login'];
        yield 'trust' => ['/en/trust', '/en/trust'];
        yield 'privileged' => ['/en/privileged', '/en/privileged'];
        yield 'coupon' => ['/en/coupon', '/en/coupon'];
        yield 'export' => ['/en/export', '/en/export'];
        yield 'alerts' => ['/en/alerts', '/en/alerts'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('caseProvider')]
    public function testEachUseCasePage(string $path, string $activeHref): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.nav-pills .nav-link.active');
        self::assertSame($activeHref, $crawler->filter('.nav-pills .nav-link.active')->attr('href'));
        self::assertSelectorExists(sprintf('a.dropdown-item[href="%s"]', str_replace('/en', '/es', $path)));
    }

    public function testUnknownPathIsNotACase(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/not-a-real-case');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLegacyCaseQueryRedirectsToPath(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en?case=checkout');

        self::assertResponseRedirects('/en/checkout');
    }

    public function testLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
        self::assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testSignInAlice(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'alice',
            '_password' => 'password',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('html', 'alice');
        self::assertSelectorTextContains('html', 'Signed in');
    }

    public function testCheckoutWithoutObservationStepsUp(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/checkout');
        $form = $crawler->selectButton('Pay €42')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorExists('.alert-warning');
    }

    public function testCouponRateLimit(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/coupon');

        $sawBlock = false;
        for ($i = 0; $i < 4; ++$i) {
            $form = $crawler->selectButton('Redeem coupon')->form();
            $client->submit($form);
            self::assertResponseRedirects();
            $crawler = $client->followRedirect();
            if ($crawler->filter('.alert-danger')->count() > 0) {
                $sawBlock = true;
            }
        }

        self::assertTrue($sawBlock, 'The fourth coupon redeem should hit the device rate limit.');
    }

    public function testPrivilegedWithoutLoginAsksToSignIn(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/en/privileged');
        $form = $crawler->selectButton('Create token')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorExists('input[name="_username"]');
    }

    public function testTwigTemplatesDoNotUseRawHtmlForms(): void
    {
        $templates = dirname(__DIR__, 2).'/templates';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templates));
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            self::assertDoesNotMatchRegularExpression('/<form[\s>]/', $contents, $file->getPathname());
            self::assertDoesNotMatchRegularExpression('/<input[\s>]/', $contents, $file->getPathname());
        }
    }

    public function testViteEntrypointsAreBuilt(): void
    {
        self::bootKernel();
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        self::assertFileExists($projectDir.'/public/build/entrypoints.json');
    }

    public function testBrowserClientIifeIsPublished(): void
    {
        self::bootKernel();
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        self::assertFileExists($projectDir.'/public/bundles/nowodeviceintelligence/js/device-intelligence.min.js');
    }
}
