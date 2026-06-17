<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Symfony\SecurityIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(SecurityIdentityResolver::class)]
#[TestDox('The Security identity resolver')]
final class SecurityIdentityResolverTest extends TestCase
{
    #[TestDox('Returns the identifier of the authenticated user.')]
    public function testReturnsUserIdentifier(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('alice');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        self::assertSame('alice', (new SecurityIdentityResolver($security))->getUserId());
    }

    #[TestDox('Returns null when there is no authenticated user.')]
    public function testReturnsNullWhenAnonymous(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        self::assertNull((new SecurityIdentityResolver($security))->getUserId());
    }
}
