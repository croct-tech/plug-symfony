<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\Cookie;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\PersonalizationMarker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriber;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Writes the session cookies and headers to the response.
 */
final class CroctResponseSubscriber implements EventSubscriber
{
    public const PERSONALIZED_ATTRIBUTE = '_croct_personalized';

    private CroctManager $manager;

    private PersonalizationMarker $marker;

    public function __construct(CroctManager $manager, PersonalizationMarker $marker)
    {
        $this->manager = $manager;
        $this->marker = $marker;
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
        $response = $event->getResponse();

        // Reconcile the visitor token on the main request: it issues or refreshes the token and
        // reports whether it changed, so the new cookie is written and the response goes private.
        $reissued = $event->isMainRequest() && $this->manager->reconcile();

        $personalized = $event->getRequest()->attributes->get(self::PERSONALIZED_ATTRIBUTE) === true;

        if (!$personalized && !$reissued) {
            return;
        }

        // Cookies belong on the main response only. A fragment stays private without them.
        if ($event->isMainRequest()) {
            foreach ($this->manager->getResponseCookies() as $cookie) {
                $response->headers->setCookie(self::createCookie($cookie));
            }
        }

        // The response depends on the visitor (content or cookies): never shared-cache it.
        $this->marker->mark($response);
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
            // The client SDK reads these cookies, so they must never be HTTP-only.
            false,
            false,
            [
                'lax' => SymfonyCookie::SAMESITE_LAX,
                'strict' => SymfonyCookie::SAMESITE_STRICT,
                'none' => SymfonyCookie::SAMESITE_NONE,
            ][\strtolower((string) $cookie->getSameSite())] ?? null,
        );
    }
}
