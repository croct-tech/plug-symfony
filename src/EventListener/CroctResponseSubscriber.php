<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\Cookie;
use Croct\Plug\Symfony\CroctFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Writes the session cookies and headers to the response.
 */
final class CroctResponseSubscriber implements EventSubscriberInterface
{
    public const PERSONALIZED_ATTRIBUTE = '_croct_personalized';

    private CroctFactory $factory;

    public function __construct(CroctFactory $factory)
    {
        $this->factory = $factory;
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
        if ($event->getRequest()->attributes->get(self::PERSONALIZED_ATTRIBUTE) !== true) {
            return;
        }

        $response = $event->getResponse();

        // Cookies belong on the main response only; fragments must not set them.
        if ($event->isMainRequest()) {
            foreach ($this->factory->getResponseCookies() as $cookie) {
                $response->headers->setCookie(self::createCookie($cookie));
            }
        }

        // The response depends on the visitor (content and/or session cookies): never shared-cache it.
        $response->setPrivate();
    }

    private static function createCookie(Cookie $cookie): SymfonyCookie
    {
        return SymfonyCookie::create(
            $cookie->getName(),
            $cookie->getValue(),
            $cookie->getExpiration() ?? 0,
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            false,
            [
                'lax' => SymfonyCookie::SAMESITE_LAX,
                'strict' => SymfonyCookie::SAMESITE_STRICT,
                'none' => SymfonyCookie::SAMESITE_NONE,
            ][\strtolower((string) $cookie->getSameSite())] ?? null,
        );
    }
}
