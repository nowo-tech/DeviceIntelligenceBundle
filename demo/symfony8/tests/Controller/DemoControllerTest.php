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
