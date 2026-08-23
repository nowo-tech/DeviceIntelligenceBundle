<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligence\Tests\Unit;

use DateTimeImmutable;
use Nowo\DeviceIntelligence\Device\Device;
use Nowo\DeviceIntelligence\Device\DeviceId;
use Nowo\DeviceIntelligence\Device\DeviceStatus;
use Nowo\DeviceIntelligence\Device\Stability;
use Nowo\DeviceIntelligence\Matching\CandidateIndexKey;
use Nowo\DeviceIntelligence\Matching\Confidence;
use Nowo\DeviceIntelligence\Matching\WeightedDeviceMatcher;
use Nowo\DeviceIntelligence\Observation\DeviceObservation;
use Nowo\DeviceIntelligence\Observation\ObservationId;
use Nowo\DeviceIntelligence\Signal\Quality;
use Nowo\DeviceIntelligence\Signal\Signal;
use Nowo\DeviceIntelligence\Signal\SignalBag;
use Nowo\DeviceIntelligence\Signal\SignalName;
use Nowo\DeviceIntelligence\Signal\SignalSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WeightedDeviceMatcherTest extends TestCase
{
    #[Test]
    public function minorBrowserUpdateDoesNotCreateNewDevice(): void
    {
        $stored   = $this->observation('Chrome 143', 'macos', 'a' . str_repeat('1', 15), 'apple');
        $device   = $this->device($stored);
        $incoming = $this->observation('Chrome 144', 'macos', 'a' . str_repeat('1', 15), 'apple');

        $match = (new WeightedDeviceMatcher())->match($incoming, [$device]);

        self::assertFalse($match->isNewDevice());
        self::assertNotNull($match->device());
        self::assertGreaterThan(0.75, $match->confidence());
    }

    #[Test]
    public function osSwapCreatesNewDevice(): void
    {
        $stored   = $this->observation('Chrome 143', 'macos', 'a' . str_repeat('1', 15), 'apple');
        $device   = $this->device($stored);
        $incoming = $this->observation('Chrome 143', 'windows', 'b' . str_repeat('2', 15), 'nvidia');

        $match = (new WeightedDeviceMatcher())->match($incoming, [$device]);

        self::assertTrue($match->isNewDevice());
    }

    #[Test]
    public function missingAudioStillMatches(): void
    {
        $stored   = $this->observation('Chrome 143', 'linux', 'c' . str_repeat('3', 15), 'intel', true);
        $device   = $this->device($stored);
        $incoming = $this->observation('Chrome 143', 'linux', 'c' . str_repeat('3', 15), 'intel', false);

        $match = (new WeightedDeviceMatcher())->match($incoming, [$device]);

        self::assertFalse($match->isNewDevice());
    }

    #[Test]
    public function emptyCandidatesIsNewDevice(): void
    {
        $incoming = $this->observation('Firefox 120', 'linux', 'd' . str_repeat('4', 15), 'amd');
        $match    = (new WeightedDeviceMatcher())->match($incoming, []);

        self::assertTrue($match->isNewDevice());
        self::assertNull($match->device());
    }

    private function observation(string $browser, string $os, string $canvas, string $gpu, bool $audio = true): DeviceObservation
    {
        $now = new DateTimeImmutable('2026-08-22T12:00:00Z');
        $bag = SignalBag::empty()
            ->with($this->signal(SignalName::Platform, $os, $now))
            ->with($this->signal(SignalName::ClientHints, ['browser' => $browser, 'platform' => $os], $now))
            ->with($this->signal(SignalName::Canvas, $canvas, $now, 0.95))
            ->with($this->signal(SignalName::Webgl, ['vendor' => $gpu, 'renderer' => $gpu . '-gpu'], $now, 0.9))
            ->with($this->signal(SignalName::Screen, ['class' => 'hd', 'width' => 1920, 'height' => 1080], $now))
            ->with($this->signal(SignalName::Timezone, 'Europe/Madrid', $now))
            ->with($this->signal(SignalName::HardwareConcurrency, 8, $now))
            ->with($this->signal(SignalName::BrowserCapabilities, ['webp' => true, 'av1' => true], $now));
        if ($audio) {
            $bag = $bag->with($this->signal(SignalName::Audio, 'e' . str_repeat('5', 15), $now, 0.9));
        }

        return new DeviceObservation(
            ObservationId::generate($now),
            DeviceId::generate($now),
            $now,
            1,
            '1.0.0',
            null,
            null,
            $browser,
            null,
            null,
            null,
            $bag,
            0,
            false,
            $audio ? 3 : 2,
        );
    }

    private function device(DeviceObservation $observation): Device
    {
        return new Device(
            $observation->deviceId,
            $observation->createdAt,
            $observation->createdAt,
            5,
            new Confidence(0.9),
            new Stability(0.9),
            DeviceStatus::Active,
            new CandidateIndexKey('macos', 'chrome', 'apple', 'hd', 'Europe/Madrid', 'abcd'),
            'Chrome on macos',
            [],
            $observation->signals,
        );
    }

    private function signal(SignalName $name, mixed $value, DateTimeImmutable $now, float $q = 0.9): Signal
    {
        return new Signal(
            $name,
            $value,
            $value,
            new Quality($q),
            $name->expectedStability(),
            $name->entropyCategory(),
            $now,
            SignalSource::Client,
        );
    }
}
