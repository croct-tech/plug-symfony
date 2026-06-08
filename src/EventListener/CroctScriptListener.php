<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\CroctScriptProvider;
use Psr\Http\Client\ClientExceptionInterface as ClientException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves the client-side SDK from a first-party path instead of a third-party CDN.
 *
 * It runs before the router so the configured path needs no route, and relays the upstream response
 * verbatim, handling conditional requests locally against the relayed validators.
 */
final class CroctScriptListener implements EventSubscriber
{
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

    /**
     * @throws ClientException If the upstream request fails.
     */
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->getPathInfo() !== $this->path) {
            return;
        }

        $script = $this->provider->load(self::collectHeaders($request));

        $response = new Response($script->getContent(), $script->getStatusCode());

        foreach ($script->getHeaders() as $name => $value) {
            $response->headers->set($name, $value);
        }

        // The cache varies on Accept-Encoding, so downstream caches must too.
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->isNotModified($request);

        $event->setResponse($response);
        $event->stopPropagation();
    }

    /**
     * @return array<string, string>
     */
    private static function collectHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->keys() as $name) {
            $value = $request->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
