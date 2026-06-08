<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\EventListener;

use Croct\Plug\CroctScript;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[CoversClass(CroctScriptSubscriber::class)]
#[TestDox('The script injector')]
final class CroctScriptSubscriberTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    private const LOADER = 'https://cdn.example/plug.js';

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
            CroctScriptSubscriber::getSubscribedEvents(),
        );
    }

    #[TestDox('Injects the bootstrap before the closing body tag.')]
    public function testInjectsBeforeBody(): void
    {
        $response = new Response('<html><body>Hi</body></html>');
        $this->dispatch(Request::create('/'), $response);

        self::assertSame(
            '<html><body>Hi' . $this->expectedScript() . '</body></html>',
            (string) $response->getContent(),
        );
    }

    #[TestDox('Injects before the closing head tag with head placement.')]
    public function testInjectsBeforeHead(): void
    {
        $response = new Response('<html><head></head><body></body></html>');
        $this->dispatch(Request::create('/'), $response, placement: 'head');

        self::assertSame(
            '<html><head>' . $this->expectedScript() . '</head><body></body></html>',
            (string) $response->getContent(),
        );
    }

    #[TestDox('Adds the CSP nonce from the request attributes.')]
    public function testUsesCspNonce(): void
    {
        $request = Request::create('/');
        $request->attributes->set('csp_nonce', 'n0nce');

        $response = new Response('<html><body></body></html>');
        $this->dispatch($request, $response);

        self::assertSame(
            '<html><body>' . $this->expectedScript('n0nce') . '</body></html>',
            (string) $response->getContent(),
        );
    }

    #[TestDox('Never injects twice into the same request.')]
    public function testInjectsOnlyOnce(): void
    {
        $request = Request::create('/');
        $response = new Response('<html><body></body></html>');

        $this->dispatch($request, $response);
        $this->dispatch($request, $response);

        self::assertSame(1, \substr_count((string) $response->getContent(), 'croct.plug('));
    }

    #[TestDox('Ignores ESI fragment sub-requests.')]
    public function testIgnoresSubRequests(): void
    {
        $response = new Response('<html><body></body></html>');
        $this->dispatch(Request::create('/'), $response, main: false);

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Ignores XML HTTP requests.')]
    public function testIgnoresXmlHttpRequests(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = new Response('<html><body></body></html>');
        $this->dispatch($request, $response);

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Ignores streamed and binary responses.')]
    public function testIgnoresStreamedAndBinaryResponses(): void
    {
        $streamed = new StreamedResponse(static function (): void {
            echo '<html><body></body></html>';
        });
        $this->dispatch(Request::create('/'), $streamed);
        self::assertStringNotContainsString('croct.plug(', (string) $streamed->getContent());

        $binary = new BinaryFileResponse(__FILE__);
        $this->dispatch(Request::create('/'), $binary);
        self::assertStringNotContainsString('croct.plug(', (string) $binary->getContent());
    }

    #[TestDox('Ignores redirects.')]
    public function testIgnoresRedirects(): void
    {
        $response = new RedirectResponse('/elsewhere');
        $this->dispatch(Request::create('/'), $response);

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Ignores non-HTML responses.')]
    public function testIgnoresNonHtmlResponses(): void
    {
        $response = new JsonResponse(['ok' => true]);
        $this->dispatch(Request::create('/'), $response);

        self::assertStringNotContainsString('croct.plug(', (string) $response->getContent());
    }

    #[TestDox('Leaves HTML without the anchor untouched.')]
    public function testLeavesContentWithoutAnchorUntouched(): void
    {
        $response = new Response('just a fragment');
        $this->dispatch(Request::create('/'), $response);

        self::assertSame('just a fragment', $response->getContent());
    }

    private function dispatch(Request $request, Response $response, bool $main = true, string $placement = 'body'): void
    {
        $subscriber = new CroctScriptSubscriber($this->manager(), self::LOADER, $placement);

        $subscriber->onResponse(
            new ResponseEvent(
                $this->createMock(HttpKernelInterface::class),
                $request,
                $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
                $response,
            ),
        );
    }

    private function expectedScript(?string $nonce = null): string
    {
        return (string) new CroctScript(self::LOADER, $this->manager()->getPlugOptions(), $nonce);
    }

    private function manager(): CroctManager
    {
        return new CroctManager(new RequestStack(), self::APP_ID, self::API_KEY);
    }
}
