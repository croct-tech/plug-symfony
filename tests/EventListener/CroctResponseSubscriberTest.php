<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\EventListener;

use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Symfony\PrivateResponseMarker;
use Croct\Plug\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(CroctResponseSubscriber::class)]
#[TestDox('The response subscriber')]
final class CroctResponseSubscriberTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Subscribes to the kernel response event, running late.')]
    public function testSubscribesToResponseEvent(): void
    {
        self::assertSame(
            [
                KernelEvents::RESPONSE => [
                    'onResponse',
                    -1024,
                ],
            ],
            CroctResponseSubscriber::getSubscribedEvents(),
        );
    }

    #[TestDox('Leaves a response that was not flagged as personalized untouched.')]
    public function testIgnoresUnflaggedResponse(): void
    {
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(3600);

        // A valid token is already present, so reconcile re-issues nothing and the response is left
        // shareable.
        $token = Token::issue(appId: self::APP_ID, now: 1000)->toString();

        $this->createSubscriber($token)->onResponse($this->createEvent($response, flagged: false));

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertSame([], $response->headers->getCookies());
    }

    #[TestDox('Writes the session cookies and marks the response private when the token is reissued.')]
    public function testWritesCookiesWhenTokenReissued(): void
    {
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(3600);

        // No token is present, so reconcile issues one; the subscriber must write it and go private
        // even though the response was never flagged as personalized.
        $this->createSubscriber()->onResponse($this->createEvent($response, flagged: false, main: true));

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));

        $names = \array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $response->headers->getCookies(),
        );

        self::assertContains('ct_user_token', $names);
    }

    #[TestDox('Marks a flagged main-request response private and writes the session cookies.')]
    public function testMarksFlaggedMainRequestPrivateWithCookies(): void
    {
        $response = new Response();

        $this->createSubscriber()->onResponse(
            $this->createEvent($response, flagged: true, main: true),
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        $names = \array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $response->headers->getCookies(),
        );

        self::assertContains('ct_client_id', $names);
        self::assertContains('ct_user_token', $names);

        $httpOnly = \array_map(
            static fn (Cookie $cookie): bool => $cookie->isHttpOnly(),
            $response->headers->getCookies(),
        );

        // The client SDK reads these cookies, so they must never be HTTP-only.
        self::assertNotContains(true, $httpOnly);
    }

    #[TestDox('Marks a flagged sub-request response private without writing cookies.')]
    public function testMarksFlaggedSubRequestPrivateWithoutCookies(): void
    {
        $response = new Response();

        $this->createSubscriber()->onResponse(
            $this->createEvent($response, flagged: true, main: false),
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertSame([], $response->headers->getCookies());
    }

    private function createSubscriber(?string $token = null): CroctResponseSubscriber
    {
        $stack = new RequestStack();

        if ($token !== null) {
            $stack->push(Request::create('/', cookies: ['ct_user_token' => $token]));
        }

        return new CroctResponseSubscriber(
            new CroctManager($stack, self::APP_ID, self::API_KEY),
            new PrivateResponseMarker(),
        );
    }

    private function createEvent(Response $response, bool $flagged, bool $main = true): ResponseEvent
    {
        $request = new Request();

        if ($flagged) {
            $request->attributes->set(CroctResponseSubscriber::PERSONALIZED_ATTRIBUTE, true);
        }

        return new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response,
        );
    }
}
