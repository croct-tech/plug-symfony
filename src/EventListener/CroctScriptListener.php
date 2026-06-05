<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\Symfony\CroctScriptProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves the client-side SDK from a first-party path instead of a third-party CDN.
 *
 * It runs before the router so the configured path needs no route, and short-circuits the request
 * with a cacheable JavaScript response that keeps the upstream content encoding.
 */
final class CroctScriptListener implements EventSubscriber
{
    private const TTL = 3600;

    private CroctScriptProvider $provider;

    private string $path;

    public function __construct(CroctScriptProvider $provider, string $path)
    {
        $this->provider = $provider;
        $this->path = $path;
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        // Above the router (priority 32) so the path is served without a registered route.
        return [KernelEvents::REQUEST => ['onRequest', 33]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->getPathInfo() !== $this->path) {
            return;
        }

        $script = $this->provider->load(self::negotiateEncoding($request));

        $response = new Response($script->getContent(), Response::HTTP_OK, ['Content-Type' => 'text/javascript']);
        $response->headers->set('Vary', 'Accept-Encoding');

        if ($script->getEncoding() !== null) {
            $response->headers->set('Content-Encoding', $script->getEncoding());
        }

        $response->setPublic();
        $response->setMaxAge(self::TTL);
        $response->setEtag(\hash('xxh128', $script->getContent()));
        $response->isNotModified($request);

        $event->setResponse($response);
        $event->stopPropagation();
    }

    private static function negotiateEncoding(Request $request): string
    {
        $accepted = $request->getEncodings();

        if (\in_array('br', $accepted, true)) {
            return 'br';
        }

        if (\in_array('gzip', $accepted, true)) {
            return 'gzip';
        }

        return '';
    }
}
