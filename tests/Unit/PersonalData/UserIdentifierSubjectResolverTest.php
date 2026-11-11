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
     * @param list<DeviceIntelligencePersonalDataSubject> $subjects
     *
     * @return list<string>
     */
    private static function values(array $subjects): array
    {
        return array_map(static fn (DeviceIntelligencePersonalDataSubject $subject): string => $subject->userIdentifier, $subjects);
    }
}
