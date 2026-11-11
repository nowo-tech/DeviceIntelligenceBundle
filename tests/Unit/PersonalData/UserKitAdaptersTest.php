<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\PersonalData;

use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligenceErasureResult;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataEraserInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataExporterInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubjectResolverInterface;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserIdentifierSubjectResolver;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitEraser;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserKit\DeviceIntelligenceUserKitExportProvider;
use Nowo\UserKitBundle\PersonalData\ErasureRequest;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserKitAdaptersTest extends TestCase
{
    public function testEraserKeysAndSubjects(): void
    {
        $eraser = new RecordingEraser();
        $adapter = new DeviceIntelligenceUserKitEraser($eraser, new UserIdentifierSubjectResolver());

        self::assertSame('device_intelligence', $adapter->getKey());
        self::assertGreaterThanOrEqual(100, DeviceIntelligenceUserKitEraser::PRIORITY);
        self::assertTrue($adapter->supports(new stdClass()));

        // Original identifier, current identifier and Beacon's previous_email, each once.
        $adapter->erase(new ErasureRequest(new InMemoryUser('anon-7', null), context: ['previous_email' => 'old@example.com'], originalIdentifier: 'alice@example.com'));
        self::assertSame(['alice@example.com', 'anon-7', 'old@example.com'], $eraser->erased);

        $eraser->erased = [];
        $adapter->erase(new ErasureRequest(new InMemoryUser('alice', null), context: ['previous_email' => 'alice'], originalIdentifier: 'alice'));
        self::assertSame(['alice'], $eraser->erased);

        $eraser->erased = [];
        foreach (['', "a\0b", 42, str_repeat('x', 300)] as $previous) {
            $adapter->erase(new ErasureRequest(new stdClass(), context: ['previous_email' => $previous]));
        }
        self::assertSame([], $eraser->erased, 'values the bundle cannot have stored are ignored');
    }

    public function testExportProviderSectionAndRows(): void
    {
        $exporter = new class implements DeviceIntelligencePersonalDataExporterInterface {
            public function export(DeviceIntelligencePersonalDataSubject $subject): array
            {
                return [['first_seen_at' => null, 'last_seen_at' => null, 'sign_ins' => \strlen($subject->userIdentifier), 'os_family' => null, 'browser_family' => null, 'trusted' => false, 'trusted_at' => null, 'trust_expires_at' => null]];
            }
        };
        $resolver = new class implements DeviceIntelligencePersonalDataSubjectResolverInterface {
            public function subjectsFor(object $user, ?string $originalIdentifier): array
            {
                return [DeviceIntelligencePersonalDataSubject::fromUserIdentifier('ab'), DeviceIntelligencePersonalDataSubject::fromUserIdentifier('abc')];
            }
        };
        $provider = new DeviceIntelligenceUserKitExportProvider($exporter, $resolver);

        self::assertSame('devices', $provider->getSectionKey());
        self::assertTrue($provider->supports(new stdClass()));
        self::assertSame([2, 3], array_column($provider->export(new stdClass()), 'sign_ins'));
    }
}

/**
 * @internal
 */
final class RecordingEraser implements DeviceIntelligencePersonalDataEraserInterface
{
    /** @var list<string> */
    public array $erased = [];

    public function erase(DeviceIntelligencePersonalDataSubject $subject): DeviceIntelligenceErasureResult
    {
        $this->erased[] = $subject->userIdentifier;

        return new DeviceIntelligenceErasureResult();
    }
}
