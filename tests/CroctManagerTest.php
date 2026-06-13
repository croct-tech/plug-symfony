<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Content\NullContentProvider;
use Croct\Plug\Cookie;
use Croct\Plug\Exception\MalformedTokenException;
use Croct\Plug\IdentityResolver;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CroctManager::class)]
#[TestDox('The Croct manager')]
final class CroctManagerTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Builds the Plug even when there is no current request.')]
    public function testBuildsPlugWithoutRequest(): void
    {
        $manager = new CroctManager(new RequestStack(), self::APP_ID, self::API_KEY);

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Builds the Plug from the current request signals.')]
    public function testBuildsPlugFromRequest(): void
    {
        $manager = new CroctManager($this->createRequestStack(), self::APP_ID, self::API_KEY);

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Builds the Plug with the preview token from the request.')]
    public function testBuildsPlugWithPreviewToken(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/?croct-preview=preview-token'));

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY);

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Builds the Plug with a content provider, logger and token duration.')]
    public function testBuildsPlugWithOptions(): void
    {
        $manager = new CroctManager(
            $this->createRequestStack(),
            self::APP_ID,
            self::API_KEY,
            contentProvider: new NullContentProvider(),
            logger: new NullLogger(),
            tokenDuration: 3600,
        );

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Exposes the session cookies for the response.')]
    public function testExposesResponseCookies(): void
    {
        $manager = new CroctManager(
            new RequestStack(),
            self::APP_ID,
            self::API_KEY,
            cookieDomain: 'example.com',
        );

        $names = \array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $manager->getResponseCookies(),
        );

        self::assertContains('ct_client_id', $names);
        self::assertContains('ct_user_token', $names);
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Issues a token for the authenticated user and reports the change.')]
    public function testReconcilesIdentityOnLogin(): void
    {
        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY, identity: $this->resolver('alice'));

        self::assertTrue($manager->reconcile());
        self::assertSame('alice', Token::parse(self::userToken($manager))->getSubject());
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Re-issues anonymously after the user logs out and reports the change.')]
    public function testReconcilesIdentityOnLogout(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'alice', now: 1000)->toString();
        $request = Request::create('/', cookies: ['ct_user_token' => $token]);
        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY, identity: $this->resolver(null));

        self::assertTrue($manager->reconcile());
        self::assertTrue(Token::parse(self::userToken($manager))->isAnonymous());
    }

    #[TestDox('Keeps a matching token and reports no change.')]
    public function testKeepsMatchingToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, subject: 'alice', now: 1000)->toString();
        $request = Request::create('/', cookies: ['ct_user_token' => $token]);
        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY, identity: $this->resolver('alice'));

        self::assertFalse($manager->reconcile());
    }

    /**
     * @throws MalformedTokenException
     */
    #[TestDox('Issues an anonymous token when none exists, even without an identity resolver.')]
    public function testIssuesTokenWithoutIdentity(): void
    {
        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY);

        self::assertTrue($manager->reconcile());
        self::assertTrue(Token::parse(self::userToken($manager))->isAnonymous());
    }

    #[TestDox('Keeps a valid anonymous token without an identity resolver.')]
    public function testKeepsValidTokenWithoutIdentity(): void
    {
        $token = Token::issue(appId: self::APP_ID, now: 1000)->toString();
        $request = Request::create('/', cookies: ['ct_user_token' => $token]);
        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY);

        self::assertFalse($manager->reconcile());
    }

    #[TestDox('Re-issues an expired token and reports the change.')]
    public function testReissuesExpiredToken(): void
    {
        $token = Token::issue(appId: self::APP_ID, now: 1000)->withDuration(3600, 1000)->toString();
        $request = Request::create('/', cookies: ['ct_user_token' => $token]);
        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY);

        self::assertTrue($manager->reconcile());
    }

    #[TestDox('Exposes the visitor-independent browser plug options.')]
    public function testExposesPlugOptions(): void
    {
        $manager = new CroctManager(
            new RequestStack(),
            self::APP_ID,
            self::API_KEY,
            cookieDomain: 'example.com',
        );

        $options = $manager->getPlugOptions();

        self::assertSame(self::APP_ID, $options['appId']);
        self::assertTrue($options['disableCidMirroring']);
        self::assertArrayHasKey('cookie', $options);
    }

    #[TestDox('Flags the current request as personalized when the visitor session is used.')]
    public function testFlagsRequestWhenSessionIsUsed(): void
    {
        $request = Request::create('/');

        $stack = new RequestStack();
        $stack->push($request);

        $manager = new CroctManager($stack, self::APP_ID, self::API_KEY);

        // Using the session fires the observer callback; the second call reuses the cached storage.
        $manager->getPlug()->getClientId();
        $manager->getResponseCookies();

        self::assertTrue($request->attributes->get(CroctResponseSubscriber::PERSONALIZED_ATTRIBUTE));
    }

    #[TestDox('Rebuilds the Plug after a reset, so workers do not leak state between requests.')]
    public function testResetRebuildsThePlug(): void
    {
        $manager = new CroctManager(new RequestStack(), self::APP_ID, self::API_KEY);

        $first = $manager->getPlug();
        $manager->reset();

        self::assertNotSame($first, $manager->getPlug());
    }

    #[TestDox('Builds the Plug with locale detection disabled.')]
    public function testBuildsPlugWithLocaleDisabled(): void
    {
        $manager = new CroctManager(
            $this->createRequestStack(),
            self::APP_ID,
            self::API_KEY,
            localeEnabled: false,
        );

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
    }

    #[TestDox('Builds the Plug with a configured default locale overriding detection.')]
    public function testBuildsPlugWithDefaultLocale(): void
    {
        $manager = new CroctManager(
            $this->createRequestStack(),
            self::APP_ID,
            self::API_KEY,
            defaultLocale: 'en-US',
        );

        self::assertInstanceOf(VaryingResponseObserver::class, $manager->getPlug());
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

    private function resolver(?string $userId): IdentityResolver
    {
        $resolver = $this->createMock(IdentityResolver::class);
        $resolver->method('getUserId')->willReturn($userId);

        return $resolver;
    }

    private static function userToken(CroctManager $manager): string
    {
        foreach ($manager->getResponseCookies() as $cookie) {
            if ($cookie->getName() === 'ct_user_token') {
                return $cookie->getValue();
            }
        }

        return '';
    }
}
