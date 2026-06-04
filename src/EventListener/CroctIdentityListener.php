<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\EventListener;

use Croct\Plug\Symfony\CroctFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Keeps the Croct user token in sync with the authenticated Symfony user.
 *
 * On every main request it compares the firewall's user with the visitor token and, only when they
 * differ, re-identifies (on login) or anonymizes (on logout).
 *
 * It is wired only when Symfony Security is installed and {@see croct.identity.enabled} is true.
 */
final class CroctIdentityListener
{
    private CroctFactory $factory;

    private Security $security;

    public function __construct(CroctFactory $factory, Security $security)
    {
        $this->factory = $factory;
        $this->security = $security;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $userId = $this->security->getUser()?->getUserIdentifier();
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
