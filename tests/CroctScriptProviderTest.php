<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Symfony\CroctScriptProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(CroctScriptProvider::class)]
#[TestDox('The first-party script provider')]
final class CroctScriptProviderTest extends TestCase
{
    private const LOADER = 'https://cdn.example/plug.js';

    #[TestDox('Fetches the script and keeps the upstream content encoding.')]
    public function testFetchesUpstreamScript(): void
    {
        $client = new MockHttpClient([
            new MockResponse('compressed', ['response_headers' => ['content-encoding' => 'br']]),
        ]);
        $provider = new CroctScriptProvider($client, new ArrayAdapter(), self::LOADER);

        $script = $provider->load('br');

        self::assertSame('compressed', $script->getContent());
        self::assertSame('br', $script->getEncoding());
        self::assertSame(1, $client->getRequestsCount());
    }

    #[TestDox('Serves the cached script on later loads without fetching again.')]
    public function testCachesTheScript(): void
    {
        $client = new MockHttpClient([new MockResponse('// plug')]);
        $provider = new CroctScriptProvider($client, new ArrayAdapter(), self::LOADER);

        $first = $provider->load('br');

        self::assertSame('// plug', $first->getContent());
        self::assertNull($first->getEncoding());
        self::assertSame('// plug', $provider->load('br')->getContent());
        self::assertSame(1, $client->getRequestsCount());
    }

    #[TestDox('Caches each accepted encoding separately.')]
    public function testCachesEachEncodingSeparately(): void
    {
        $client = new MockHttpClient([
            new MockResponse('brotli', ['response_headers' => ['content-encoding' => 'br']]),
            new MockResponse('gzipped', ['response_headers' => ['content-encoding' => 'gzip']]),
        ]);
        $provider = new CroctScriptProvider($client, new ArrayAdapter(), self::LOADER);

        self::assertSame('brotli', $provider->load('br')->getContent());
        self::assertSame('gzipped', $provider->load('gzip')->getContent());
        self::assertSame(2, $client->getRequestsCount());
    }
}
