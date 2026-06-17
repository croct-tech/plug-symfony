<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\IdentityResolver;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Resolves the user identity from the Symfony Security firewall.
 */
final class SecurityIdentityResolver implements IdentityResolver
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function getUserId(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
