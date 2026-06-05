<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\EventListener;

use Croct\Plug\Symfony\CroctScriptProvider;
use Croct\Plug\Symfony\EventListener\CroctScriptListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(CroctScriptListener::class)]
#[TestDox('The first-party script listener')]
final class CroctScriptListenerTest extends TestCase
{
    private const PATH = '/_croct/plug.js';

    #[TestDox('Subscribes to the request event, ahead of the router.')]
    public function testSubscribesAheadOfRouter(): void
    {
        self::assertSame(
            [
                KernelEvents::REQUEST => [
                    'onRequest',
                    33,
                ],
            ],
            CroctScriptListener::getSubscribedEvents(),
        );
    }

    #[TestDox('Serves brotli, with Vary, when the client accepts it.')]
    public function testServesBrotliWhenAccepted(): void
    {
        $request = Request::create(self::PATH);
        $request->headers->set('Accept-Encoding', 'br, gzip');

        $response = $this->dispatch($request, 'br')->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('// plug', $response->getContent());
        self::assertStringContainsString('javascript', (string) $response->headers->get('Content-Type'));
        self::assertSame('br', $response->headers->get('Content-Encoding'));
        self::assertSame('Accept-Encoding', $response->headers->get('Vary'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertNotNull($response->getEtag());
    }

    #[TestDox('Falls back to gzip when brotli is not accepted.')]
    public function testServesGzipWhenBrotliUnavailable(): void
    {
        $request = Request::create(self::PATH);
        $request->headers->set('Accept-Encoding', 'gzip, deflate');

        self::assertSame('gzip', $this->dispatch($request, 'gzip')->getResponse()?->headers->get('Content-Encoding'));
    }

    #[TestDox('Serves the script uncompressed when no encoding is accepted.')]
    public function testServesUncompressedWithoutAcceptEncoding(): void
    {
        $response = $this->dispatch(Request::create(self::PATH))->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertFalse($response->headers->has('Content-Encoding'));
        self::assertSame('Accept-Encoding', $response->headers->get('Vary'));
    }

    #[TestDox('Returns 304 when the client already has the current script.')]
    public function testReturnsNotModified(): void
    {
        $request = Request::create(self::PATH);
        $request->headers->set('If-None-Match', '"' . \hash('xxh128', '// plug') . '"');

        self::assertSame(304, $this->dispatch($request)->getResponse()?->getStatusCode());
    }

    #[TestDox('Leaves other paths to the router.')]
    public function testIgnoresOtherPaths(): void
    {
        self::assertFalse($this->dispatch(Request::create('/something-else'))->hasResponse());
    }

    #[TestDox('Ignores sub-requests.')]
    public function testIgnoresSubRequests(): void
    {
        self::assertFalse($this->dispatch(Request::create(self::PATH), main: false)->hasResponse());
    }

    private function dispatch(Request $request, ?string $contentEncoding = null, bool $main = true): RequestEvent
    {
        $info = $contentEncoding === null ? [] : ['response_headers' => ['content-encoding' => $contentEncoding]];

        $provider = new CroctScriptProvider(
            new MockHttpClient([new MockResponse('// plug', $info)]),
            new ArrayAdapter(),
            'https://cdn.example/plug.js',
        );

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );

        (new CroctScriptListener($provider, self::PATH))->onRequest($event);

        return $event;
    }
}
