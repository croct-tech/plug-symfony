<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\DependencyInjection\Compiler;

use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\SecurityIdentityResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Reconciles the visitor identity with the authenticated user.
 *
 * Active only when Symfony Security is installed and identity is enabled. It injects the
 * Security-backed identity resolver into the manager, which keeps the visitor token in sync with
 * the logged-in user as the session is resolved, the same way plug-next and plug-nuxt do.
 */
final class IdentityIntegrationPass implements CompilerPass
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('croct.identity.enabled') !== true) {
            return;
        }

        if (!$container->has(Security::class)) {
            return;
        }

        $resolver = new Definition(SecurityIdentityResolver::class);
        $resolver->setArguments([new Reference(Security::class)]);
        $resolver->setAutowired(false);
        $resolver->setAutoconfigured(false);
        $container->setDefinition(SecurityIdentityResolver::class, $resolver);

        $container->getDefinition(CroctManager::class)
            ->setArgument('$identity', new Reference(SecurityIdentityResolver::class));
    }
}
