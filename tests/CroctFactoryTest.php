<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Cookie;
use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CroctFactory::class)]
#[TestDox('The Croct factory')]
final class CroctFactoryTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Builds the Plug even when there is no current request.')]
    public function testBuildsPlugWithoutRequest(): void
    {
        $factory = new CroctFactory(new RequestStack(), self::APP_ID, self::API_KEY);

        self::assertInstanceOf(VaryingResponseObserver::class, $factory->getPlug());
    }

    #[TestDox('Builds the Plug from the current request signals.')]
    public function testBuildsPlugFromRequest(): void
    {
        $factory = new CroctFactory($this->createRequestStack(), self::APP_ID, self::API_KEY);

        self::assertInstanceOf(VaryingResponseObserver::class, $factory->getPlug());
    }

    #[TestDox('Exposes the session cookies for the response.')]
    public function testExposesResponseCookies(): void
    {
        $factory = new CroctFactory(
            new RequestStack(),
            self::APP_ID,
            self::API_KEY,
            cookieDomain: 'example.com',
        );

        $names = \array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $factory->getResponseCookies(),
        );

        self::assertContains('ct.client_id', $names);
        self::assertContains('ct.user_token', $names);
    }

    #[TestDox('Reads the stored user token straight from the request cookies.')]
    public function testReadsStoredUserToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'user-1', now: 1000)->toString();

        $stack = new RequestStack();
        $stack->push(Request::create(
            '/',
            cookies: [
                'ct.user_token' => $token,
            ],
        ));

        $factory = new CroctFactory($stack, self::APP_ID, self::API_KEY);

        self::assertSame('user-1', $factory->getStoredUserToken()?->getSubject());
    }

    #[TestDox('Flags the current request as personalized when the visitor session is used.')]
    public function testFlagsRequestWhenSessionIsUsed(): void
    {
        $request = Request::create('/');

        $stack = new RequestStack();
        $stack->push($request);

        $factory = new CroctFactory($stack, self::APP_ID, self::API_KEY);

        // Using the session fires the observer callback; the second call reuses the cached storage.
        $factory->getPlug()->getClientId();
        $factory->getResponseCookies();

        self::assertTrue($request->attributes->get(CroctResponseSubscriber::PERSONALIZED_ATTRIBUTE));
    }

    #[TestDox('Rebuilds the Plug after a reset, so workers do not leak state between requests.')]
    public function testResetRebuildsThePlug(): void
    {
        $factory = new CroctFactory(new RequestStack(), self::APP_ID, self::API_KEY);

        $first = $factory->getPlug();
        $factory->reset();

        self::assertNotSame($first, $factory->getPlug());
    }

    #[TestDox('Builds the Plug with locale detection disabled.')]
    public function testBuildsPlugWithLocaleDisabled(): void
    {
        $factory = new CroctFactory(
            $this->createRequestStack(),
            self::APP_ID,
            self::API_KEY,
            localeEnabled: false,
        );

        self::assertInstanceOf(VaryingResponseObserver::class, $factory->getPlug());
    }

    #[TestDox('Builds the Plug with a configured default locale overriding detection.')]
    public function testBuildsPlugWithDefaultLocale(): void
    {
        $factory = new CroctFactory(
            $this->createRequestStack(),
            self::APP_ID,
            self::API_KEY,
            defaultLocale: 'en-US',
        );

        self::assertInstanceOf(VaryingResponseObserver::class, $factory->getPlug());
    }

    private function createRequestStack(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create(
            'https://example.com/',
            server: [
                'HTTP_USER_AGENT' => 'Test/1.0',
                'HTTP_REFERER' => 'https://referrer.example/',
            ],
        ));

        return $stack;
    }
}
