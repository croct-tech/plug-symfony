<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\EventListener;

use Croct\Plug\IdentityResolver;
use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\EventListener\CroctIdentityListener;
use Croct\Plug\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(CroctIdentityListener::class)]
#[TestDox('The identity listener')]
final class CroctIdentityListenerTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Subscribes to the kernel request event after authentication.')]
    public function testSubscribesToRequestEvent(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 6]],
            CroctIdentityListener::getSubscribedEvents(),
        );
    }

    #[TestDox('Does nothing on sub-requests.')]
    public function testIgnoresSubRequests(): void
    {
        $identity = $this->createMock(IdentityResolver::class);
        $identity->expects(self::never())->method('getUserId');

        $listener = new CroctIdentityListener($this->createFactory(), $identity);
        $listener->onKernelRequest($this->createRequestEvent(main: false));
    }

    #[TestDox('Leaves an anonymous visitor untouched when there is no authenticated user.')]
    public function testKeepsAnonymousWhenNoUser(): void
    {
        $factory = $this->createFactory();

        $listener = new CroctIdentityListener($factory, $this->createIdentity(null));
        $listener->onKernelRequest($this->createRequestEvent());

        self::assertNull($factory->getStoredUserToken());
    }

    #[TestDox('Identifies the visitor when a user logs in.')]
    public function testIdentifiesNewUser(): void
    {
        $factory = $this->createFactory();

        $listener = new CroctIdentityListener($factory, $this->createIdentity('alice'));
        $listener->onKernelRequest($this->createRequestEvent());

        self::assertTrue($factory->getStoredUserToken()?->isSubject('alice'));
    }

    #[TestDox('Anonymizes the visitor after the user logs out.')]
    public function testAnonymizesAfterLogout(): void
    {
        $factory = $this->createFactory(self::issueToken('alice'));

        $listener = new CroctIdentityListener($factory, $this->createIdentity(null));
        $listener->onKernelRequest($this->createRequestEvent());

        self::assertTrue($factory->getStoredUserToken()?->isAnonymous());
    }

    #[TestDox('Leaves the token untouched when the user already matches.')]
    public function testKeepsTokenWhenUserMatches(): void
    {
        $token = self::issueToken('alice');
        $factory = $this->createFactory($token);

        $listener = new CroctIdentityListener($factory, $this->createIdentity('alice'));
        $listener->onKernelRequest($this->createRequestEvent());

        self::assertSame($token, $factory->getStoredUserToken()?->toString());
    }

    private function createIdentity(?string $userId): IdentityResolver
    {
        $identity = $this->createMock(IdentityResolver::class);
        $identity->method('getUserId')->willReturn($userId);

        return $identity;
    }

    private function createFactory(?string $userToken = null): CroctFactory
    {
        $cookies = $userToken === null ? [] : [
            'ct.user_token' => $userToken,
        ];

        $stack = new RequestStack();
        $stack->push(Request::create('/', cookies: $cookies));

        return new CroctFactory($stack, self::APP_ID, self::API_KEY);
    }

    private static function issueToken(string $subject): string
    {
        return Token::issue(appId: self::APP_ID, subject: $subject, now: 1000)->toString();
    }

    private function createRequestEvent(bool $main = true): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
