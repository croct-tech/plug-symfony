<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\DependencyInjection\Compiler;

use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\EventListener\CroctIdentityListener;
use Croct\Plug\Symfony\SecurityIdentityResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the user-identity listener only when Symfony Security is installed and identity is enabled.
 *
 * It binds the listener to the Security-backed {@see SecurityIdentityResolver}.
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

        $listener = new Definition(CroctIdentityListener::class);

        $listener->setArguments([
            new Reference(CroctFactory::class),
            new Reference(SecurityIdentityResolver::class),
        ]);

        // The listener is an event subscriber, so its event and priority come from getSubscribedEvents.
        $listener->addTag('kernel.event_subscriber');

        $listener->setAutowired(false);
        $listener->setAutoconfigured(false);
        $container->setDefinition(CroctIdentityListener::class, $listener);
    }
}
