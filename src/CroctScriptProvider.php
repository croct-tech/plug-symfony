<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Psr\Cache\CacheItemPoolInterface as CacheItemPool;
use Symfony\Contracts\HttpClient\HttpClientInterface as HttpClient;

/**
 * Fetches the client-side SDK from its upstream source and caches it for first-party serving.
 */
final class CroctScriptProvider
{
    private const TTL = 3600;

    private HttpClient $httpClient;

    private CacheItemPool $cache;

    private string $loaderUrl;

    public function __construct(HttpClient $httpClient, CacheItemPool $cache, string $loaderUrl)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->loaderUrl = $loaderUrl;
    }

    public function load(string $acceptEncoding): CroctScriptContent
    {
        $item = $this->cache->getItem('croct.plug_script.' . \hash('xxh128', $this->loaderUrl . '|' . $acceptEncoding));
        $cached = $item->get();

        if ($cached instanceof CroctScriptContent) {
            return $cached;
        }

        // Forwarding Accept-Encoding disables transparent decompression, so the bytes are kept as sent.
        $response = $this->httpClient->request('GET', $this->loaderUrl, [
            'headers' => ['Accept-Encoding' => $acceptEncoding],
        ]);

        $content = new CroctScriptContent(
            $response->getContent(),
            $response->getHeaders()['content-encoding'][0] ?? null,
        );

        $item->set($content);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);

        return $content;
    }
}
