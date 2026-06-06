<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\DependencyInjection\Compiler;

use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\DependencyInjection\Compiler\IdentityIntegrationPass;
use Croct\Plug\Symfony\EventListener\CroctIdentityListener;
use Croct\Plug\Symfony\SecurityIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(IdentityIntegrationPass::class)]
#[TestDox('The identity integration pass')]
final class IdentityIntegrationPassTest extends TestCase
{
    #[TestDox('Does not register the listener when identity is disabled.')]
    public function testSkipsWhenDisabled(): void
    {
        $container = self::createContainer(enabled: false, withSecurity: true);

        (new IdentityIntegrationPass())->process($container);

        self::assertFalse($container->hasDefinition(CroctIdentityListener::class));
    }

    #[TestDox('Does not register the listener when Symfony Security is absent.')]
    public function testSkipsWhenSecurityAbsent(): void
    {
        $container = self::createContainer(enabled: true, withSecurity: false);

        (new IdentityIntegrationPass())->process($container);

        self::assertFalse($container->hasDefinition(CroctIdentityListener::class));
    }

    #[TestDox('Registers the listener after the firewall when identity is enabled and Security is present.')]
    public function testRegistersListener(): void
    {
        $container = self::createContainer(enabled: true, withSecurity: true);

        (new IdentityIntegrationPass())->process($container);

        self::assertTrue($container->hasDefinition(CroctIdentityListener::class));
        self::assertTrue($container->hasDefinition(SecurityIdentityResolver::class));

        // The resolver wraps the Security service.
        $resolver = $container->getDefinition(SecurityIdentityResolver::class);

        self::assertInstanceOf(Reference::class, $resolver->getArgument(0));
        self::assertSame(Security::class, (string) $resolver->getArgument(0));

        $definition = $container->getDefinition(CroctIdentityListener::class);

        // Registered as an event subscriber so it works in both Symfony and Drupal.
        self::assertCount(1, $definition->getTag('kernel.event_subscriber'));

        $arguments = $definition->getArguments();

        // The listener depends on the resolver, not on Security directly.
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(CroctFactory::class, (string) $arguments[0]);
        self::assertSame(SecurityIdentityResolver::class, (string) $arguments[1]);
        self::assertFalse($definition->isAutowired());
    }

    private static function createContainer(bool $enabled, bool $withSecurity): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('croct.identity.enabled', $enabled);
        $container->register(CroctFactory::class);

        if ($withSecurity) {
            $container->register(Security::class);
        }

        return $container;
    }
}
