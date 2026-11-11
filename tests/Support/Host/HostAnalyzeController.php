<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support\Host;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\AnalysisInput;
use Nowo\DeviceIntelligence\DeviceIntelligenceInterface;
use Nowo\DeviceIntelligence\Signal\SignalFactory;
use Nowo\DeviceIntelligence\User\UserIdentifier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * A host use case analysing the device of `/analyze/{user}` (one browser per user, fixed signals).
 */
final readonly class HostAnalyzeController
{
    public function __construct(
        private DeviceIntelligenceInterface $engine,
    ) {
    }

    public function __invoke(Request $request, string $user): JsonResponse
    {
        $now = new DateTimeImmutable();
        $alice = 'alice' === $user;
        $analysis = $this->engine->analyze(new AnalysisInput(
            $now,
            SignalFactory::bagFromClient([
                'platform' => ['value' => $alice ? 'MacIntel' : 'Win32', 'quality' => 1],
                'canvas' => ['value' => $alice ? 'aabbccddeeff0011' : '9988776655443322', 'quality' => 0.95],
                'webgl' => ['value' => $alice ? ['vendor' => 'Apple', 'renderer' => 'Apple GPU'] : ['vendor' => 'NVIDIA', 'renderer' => 'GeForce'], 'quality' => 0.9],
                'screen' => ['value' => $alice ? ['width' => 1440, 'height' => 900] : ['width' => 1920, 'height' => 1080], 'quality' => 1],
                'timezone' => ['value' => $alice ? 'Europe/Madrid' : 'Europe/Paris', 'quality' => 1],
                'hardware_concurrency' => ['value' => $alice ? 8 : 16, 'quality' => 1],
                'audio' => ['value' => $alice ? '1122334455667788' : '8877665544332211', 'quality' => 0.9],
            ], $now),
            $alice ? '203.0.113.7' : '198.51.100.9',
            $alice ? 'Mozilla/5.0 Chrome/143.0.0.0' : 'Mozilla/5.0 Firefox/140.0',
            userIdentifier: new UserIdentifier($user),
        ));

        return new JsonResponse(['device' => $analysis->device->id->value, 'observations' => $analysis->device->observationCount]);
    }
}
