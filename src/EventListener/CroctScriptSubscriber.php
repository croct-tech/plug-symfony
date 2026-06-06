<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\CroctScript;
use Croct\Plug\Symfony\CroctFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriber;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Injects the client-side SDK bootstrap into HTML responses.
 *
 * The options are visitor-independent, so the markup is identical for everyone and keeps the page
 * shareable. Manual placement through the {@see \Croct\Plug\Symfony\Twig\CroctScriptRuntime} Twig
 * function flags the request so this subscriber does not inject a second copy.
 */
final class CroctScriptSubscriber implements EventSubscriber
{
    public const SCRIPT_ATTRIBUTE = '_croct_script_injected';

    private CroctFactory $factory;

    private string $scriptSrc;

    private string $placement;

    public function __construct(CroctFactory $factory, string $scriptSrc, string $placement)
    {
        $this->factory = $factory;
        $this->scriptSrc = $scriptSrc;
        $this->placement = $placement;
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -1024]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get(self::SCRIPT_ATTRIBUTE) === true || $request->isXmlHttpRequest()) {
            return;
        }

        $response = $event->getResponse();

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return;
        }

        if ($response->isRedirection()) {
            return;
        }

        if (!\str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }

        $content = (string) $response->getContent();
        $anchor = $this->placement === 'head' ? '</head>' : '</body>';
        $position = \strripos($content, $anchor);

        if ($position === false) {
            return;
        }

        $nonce = $request->attributes->get('csp_nonce');

        $script = (string) new CroctScript(
            $this->scriptSrc,
            $this->factory->getPlugOptions(),
            \is_string($nonce) ? $nonce : null,
        );

        $response->setContent(\substr_replace($content, $script, $position, 0));
        $request->attributes->set(self::SCRIPT_ATTRIBUTE, true);
    }
}
