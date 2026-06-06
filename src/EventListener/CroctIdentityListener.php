<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\IdentityResolver;
use Croct\Plug\Symfony\CroctFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface as EventSubscriber;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the Croct user token in sync with the authenticated user.
 *
 * On every main request it compares the resolved user with the visitor token and, only when they
 * differ, re-identifies (on login) or anonymizes (on logout).
 *
 * The user comes from an {@see IdentityResolver}, so the listener works with any host.
 */
final class CroctIdentityListener implements EventSubscriber
{
    private CroctFactory $factory;

    private IdentityResolver $identity;

    public function __construct(CroctFactory $factory, IdentityResolver $identity)
    {
        $this->factory = $factory;
        $this->identity = $identity;
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        // After the firewall authenticates the user (Symfony priority 8), before the controller runs.
        return [KernelEvents::REQUEST => ['onKernelRequest', 6]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $userId = $this->identity->getUserId();
        $token = $this->factory->getStoredUserToken();

        $matches = $userId === null
            ? ($token?->isAnonymous() ?? true)
            : ($token?->isSubject($userId) ?? false);

        if ($matches) {
            return;
        }

        $plug = $this->factory->getPlug();

        if ($userId === null) {
            $plug->anonymize();

            return;
        }

        $plug->identify($userId);
    }
}
