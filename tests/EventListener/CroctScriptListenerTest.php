<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\EventListener;

use Croct\Plug\CroctScriptProvider;
use Croct\Plug\Symfony\EventListener\CroctScriptListener;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface as ClientException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
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

    private Psr17Factory $factory;

    private MockClient $httpClient;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->httpClient = new MockClient();
    }

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

    #[TestDox('Relays the upstream response verbatim, with a Vary header and without cookies.')]
    public function testRelaysUpstreamResponse(): void
    {
        $this->httpClient->addResponse(
            $this->factory->createResponse(200)
                ->withHeader('Content-Type', 'text/javascript')
                ->withHeader('Content-Encoding', 'br')
                ->withHeader('Cache-Control', 'public, max-age=600')
                ->withHeader('Set-Cookie', 'session=1')
                ->withBody($this->factory->createStream('// plug')),
        );

        $request = Request::create(self::PATH);
        $request->headers->set('Accept-Encoding', 'br, gzip');

        $response = $this->dispatch($request)->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('// plug', $response->getContent());
        self::assertSame('text/javascript', $response->headers->get('Content-Type'));
        self::assertSame('br', $response->headers->get('Content-Encoding'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('600', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('Accept-Encoding', $response->headers->get('Vary'));
        self::assertFalse($response->headers->has('Set-Cookie'));
    }

    #[TestDox('Returns 304 when the relayed validator matches the request.')]
    public function testReturnsNotModified(): void
    {
        $this->httpClient->addResponse(
            $this->factory->createResponse(200)
                ->withHeader('ETag', '"v1"')
                ->withBody($this->factory->createStream('// plug')),
        );

        $request = Request::create(self::PATH);
        $request->headers->set('If-None-Match', '"v1"');

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

    /**
     * @throws ClientException If the upstream request fails.
     */
    private function dispatch(Request $request, bool $main = true): RequestEvent
    {
        $provider = new CroctScriptProvider(
            $this->httpClient,
            $this->factory,
            new Psr16Cache(new ArrayAdapter()),
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
