<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\PersonalData;

use Nowo\DeviceIntelligenceBundle\PersonalData\DeviceIntelligencePersonalDataSubject;
use Nowo\DeviceIntelligenceBundle\PersonalData\UserIdentifierSubjectResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserIdentifierSubjectResolverTest extends TestCase
{
    public function testOriginalAndCurrentIdentifierOnceEach(): void
    {
        $resolver = new UserIdentifierSubjectResolver();

        self::assertSame(['old@example.com', 'anon-1'], self::values($resolver->subjectsFor(new InMemoryUser('anon-1', null), 'old@example.com')));
        self::assertSame(['alice'], self::values($resolver->subjectsFor(new InMemoryUser('alice', null), 'alice')));
        self::assertSame(['alice'], self::values($resolver->subjectsFor(new InMemoryUser('alice', null), null)));
    }

    public function testSkipsValuesThatCannotBeStoredAndNonSecurityUsers(): void
    {
        $resolver = new UserIdentifierSubjectResolver();

        self::assertSame([], $resolver->subjectsFor(new stdClass(), null));
        self::assertSame(['bob'], self::values($resolver->subjectsFor(new stdClass(), 'bob')));
        self::assertSame(['carol'], self::values($resolver->subjectsFor(new InMemoryUser('carol', null), str_repeat('x', 300))));
    }

    /**
     * RE1-L1: identifiers 1.9.2 refuses still reach the rows earlier releases stored (raw and trimmed, byte for byte).
     */
    public function testLegacyIdentifiersStillResolveForGdprRequests(): void
    {
        $resolver = new UserIdentifierSubjectResolver();

        self::assertSame(["ana\u{00A0}lopez"], self::values($resolver->subjectsFor(new InMemoryUser("ana\u{00A0}lopez", null), null)));
        self::assertSame(['bob ', 'bob'], self::values($resolver->subjectsFor(new InMemoryUser('bob ', null), null)));
        self::assertSame(["ali\u{200B}ce", 'anon-2'], self::values($resolver->subjectsFor(new InMemoryUser('anon-2', null), "ali\u{200B}ce")));
        self::assertSame(['bob ', 'bob'], self::values($resolver->subjectsFor(new InMemoryUser('bob', null), 'bob ')), 'each value once');
    }

    /**
     * @param list<DeviceIntelligencePersonalDataSubject> $subjects
     *
     * @return list<string>
     */
    private static function values(array $subjects): array
    {
        return array_map(static fn (DeviceIntelligencePersonalDataSubject $subject): string => $subject->userIdentifier, $subjects);
    }
}
