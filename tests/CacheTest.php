<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * End-to-end check that personalized responses are never shared-cached, while plain pages keep
 * their public caching.
 */
#[CoversNothing]
#[TestDox('The bundle cache behavior')]
final class CacheTest extends WebTestCase
{
    use RestoresErrorHandlers;

    #[TestDox('Marks a personalized response private and writes the session cookies.')]
    public function testPersonalizedResponseIsPrivate(): void
    {
        $client = self::createClient();
        $client->request('GET', '/personalized');

        $response = $client->getResponse();

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));

        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[] = $cookie->getName();
        }

        self::assertContains('ct.client_id', $cookies);
        self::assertContains('ct.user_token', $cookies);
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

        foreach ($response->headers->getCookies() as $cookie) {
            self::assertStringStartsNotWith('ct.', $cookie->getName());
        }
    }

    #[TestDox('Leaves a non-personalized response publicly cacheable without session cookies.')]
    public function testPublicResponseStaysCacheable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/public');

        $response = $client->getResponse();

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));

        foreach ($response->headers->getCookies() as $cookie) {
            self::assertStringStartsNotWith('ct.', $cookie->getName());
        }
    }
}
