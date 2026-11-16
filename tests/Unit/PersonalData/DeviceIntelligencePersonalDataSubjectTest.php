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

    /**
     * RE1-L1: GDPR requests still reach what releases before 1.9.2 stored (raw, and trimmed with PHP trim()), byte for
     * byte; a usable identifier gives exactly itself.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function personalDataRequests(): iterable
    {
        yield 'usable: itself only' => ['alice', ['alice']];
        yield 'internal NBSP (stored as is)' => ["ana\u{00A0}lopez", ["ana\u{00A0}lopez"]];
        yield 'internal ZWSP (stored as is)' => ["ali\u{200B}ce", ["ali\u{200B}ce"]];
        yield 'edge space: raw and the trimmed value stored' => ['bob ', ['bob ', 'bob']];
        yield 'edge tab and newline' => ["\tcarol\n", ["\tcarol\n", 'carol']];
        yield 'edge NBSP is not trimmed by trim()' => ["dave\u{00A0}", ["dave\u{00A0}"]];
        yield 'blank: nothing' => ['   ', []];
        yield 'empty: nothing' => ['', []];
        yield 'NUL inside: nothing' => ["a\0b", []];
        yield 'trailing NUL: only the trimmed value' => ["erin\0", ['erin']];
        yield 'too long raw, storable trimmed' => [str_repeat('a', 191).' ', [str_repeat('a', 191)]];
        yield 'too long' => [str_repeat('a', 192), []];
        yield 'invalid UTF-8' => ["al\xFFice", []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('personalDataRequests')]
    public function testForPersonalDataRequestMatchesLegacyStoredValuesExactly(string $value, array $expected): void
    {
        self::assertSame($expected, array_map(
            static fn (DeviceIntelligencePersonalDataSubject $subject): string => $subject->userIdentifier,
            DeviceIntelligencePersonalDataSubject::forPersonalDataRequest($value),
        ));
    }

    public function testErasureResultCounts(): void
    {
        $result = new DeviceIntelligenceErasureResult(3, 2, 1, 4);

        self::assertSame(['observations' => 3, 'device_users' => 2, 'device_trusts' => 1, 'devices' => 4], $result->toArray());
        self::assertSame(10, $result->total());
        self::assertSame(0, (new DeviceIntelligenceErasureResult())->total());
    }
}
