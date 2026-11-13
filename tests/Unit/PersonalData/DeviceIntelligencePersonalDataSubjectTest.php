<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\PersonalData;

use InvalidArgumentException;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligenceErasureResult;
use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceIntelligencePersonalDataSubjectTest extends TestCase
{
    public function testKeepsTheValueAsTheBundleStoresIt(): void
    {
        self::assertSame('Alice@Example.com', DeviceIntelligencePersonalDataSubject::fromUserIdentifier('Alice@Example.com')->userIdentifier, 'no case folding');
        self::assertSame(str_repeat('a', 191), DeviceIntelligencePersonalDataSubject::fromUserIdentifier(str_repeat('a', 191))->userIdentifier);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('a', 192)];
        yield 'NUL byte' => ["alice\0bob"];
        yield 'edge whitespace (REQ-SEC-006, never trimmed)' => ["  alice\n"];
        yield 'trailing space' => ['alice '];
        yield 'NBSP' => ["alice\u{00A0}"];
        yield 'ZWSP' => ["ali\u{200B}ce"];
    }

    #[DataProvider('invalid')]
    public function testRejectsValuesTheBundleCannotStore(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        DeviceIntelligencePersonalDataSubject::fromUserIdentifier($value);
    }

    public function testErasureResultCounts(): void
    {
        $result = new DeviceIntelligenceErasureResult(3, 2, 1, 4);

        self::assertSame(['observations' => 3, 'device_users' => 2, 'device_trusts' => 1, 'devices' => 4], $result->toArray());
        self::assertSame(10, $result->total());
        self::assertSame(0, (new DeviceIntelligenceErasureResult())->total());
    }
}
