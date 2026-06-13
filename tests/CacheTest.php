<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversNothing]
#[TestDox('The bundle cache behavior')]
final class CacheTest extends WebTestCase
{
    use RestoresExceptionHandler;

    #[TestDox('Marks a personalized response private and writes the session cookies.')]
    public function testPersonalizedResponseIsPrivate(): void
    {
        $client = self::createClient();
        $client->request('GET', '/personalized');

        $response = $client->getResponse();

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));

        $cookies = \array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            $response->headers->getCookies(),
        );

        self::assertContains('ct_client_id', $cookies);
        self::assertContains('ct_user_token', $cookies);
    }

    #[TestDox('Marks a personalized ESI fragment sub-request private without writing session cookies.')]
    public function testEsiFragmentResponseIsPrivateWithoutCookies(): void
    {
        $client = self::createClient();

        // An ESI gateway fetches each fragment as its own sub-request.
        $response = $client->getKernel()->handle(
            Request::create('/personalized'),
            HttpKernelInterface::SUB_REQUEST,
        );

        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        $croctCookies = \array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $cookie): bool => \str_starts_with($cookie->getName(), 'ct_'),
        );

        self::assertSame([], $croctCookies);
    }

    #[TestDox('Leaves a non-personalized response publicly cacheable on a return visit.')]
    public function testPublicResponseStaysCacheable(): void
    {
        $client = self::createClient();

        // The first visit issues the session token, so that response is private. A return visit
        // carrying the token re-issues nothing and stays publicly cacheable.
        $client->request('GET', '/public');
        $token = self::userToken($client->getResponse());

        $client->request('GET', '/public', server: ['HTTP_COOKIE' => 'ct_user_token=' . $token]);

        $response = $client->getResponse();

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));

        $croctCookies = \array_filter(
            $response->headers->getCookies(),
            static fn (Cookie $cookie): bool => \str_starts_with($cookie->getName(), 'ct_'),
        );

        self::assertSame([], $croctCookies);
    }

    private static function userToken(Response $response): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'ct_user_token') {
                return (string) $cookie->getValue();
            }
        }

        return '';
    }
}
