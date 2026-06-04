<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\DependencyInjection\Compiler;

use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\EventListener\CroctIdentityListener;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Wires the user-identity listener only when Symfony Security is installed and identity is enabled.
 */
final class IdentityIntegrationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('croct.identity.enabled') !== true) {
            return;
        }

        if (!$container->has(Security::class)) {
            return;
        }

        $definition = new Definition(CroctIdentityListener::class);

        $definition->setArguments([
            new Reference(CroctFactory::class),
            new Reference(Security::class),
        ]);

        $definition->addTag('kernel.event_listener', [
            'event' => KernelEvents::REQUEST,
            'method' => 'onKernelRequest',
            // After the firewall (priority 8) authenticates the user, before the controller runs.
            'priority' => 6,
        ]);

        $definition->setAutowired(false);
        $definition->setAutoconfigured(false);
        $container->setDefinition(CroctIdentityListener::class, $definition);
    }
}
