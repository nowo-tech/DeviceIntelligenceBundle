<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Unit\User;

use Nowo\DeviceIntelligence\Exception\InvalidValueException;
use Nowo\DeviceIntelligenceBundle\User\SecurityUserIdentifierResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class SecurityUserIdentifierResolverTest extends TestCase
{
    public function testResolvesUserInterface(): void
    {
        $resolver = new SecurityUserIdentifierResolver();
        $id = $resolver->resolve(new InMemoryUser('alice', null));

        self::assertSame('alice', $id->value);
    }

    public function testResolvesDuckTypedUser(): void
    {
        $resolver = new SecurityUserIdentifierResolver();
        $user = new class {
            public function getUserIdentifier(): string
            {
                return 'bob';
            }
        };

        self::assertSame('bob', $resolver->resolve($user)->value);
    }

    public function testRejectsUnknownObject(): void
    {
        $this->expectException(InvalidValueException::class);
        (new SecurityUserIdentifierResolver())->resolve(new \stdClass());
    }
}
