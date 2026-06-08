<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\DependencyInjection\Compiler;

use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\DependencyInjection\Compiler\IdentityIntegrationPass;
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
    #[TestDox('Leaves the manager untouched when identity is disabled.')]
    public function testSkipsWhenDisabled(): void
    {
        $container = self::createContainer(enabled: false, withSecurity: true);

        (new IdentityIntegrationPass())->process($container);

        self::assertFalse($container->hasDefinition(SecurityIdentityResolver::class));
        self::assertSame([], $container->getDefinition(CroctManager::class)->getArguments());
    }

    #[TestDox('Leaves the manager untouched when Symfony Security is absent.')]
    public function testSkipsWhenSecurityAbsent(): void
    {
        $container = self::createContainer(enabled: true, withSecurity: false);

        (new IdentityIntegrationPass())->process($container);

        self::assertFalse($container->hasDefinition(SecurityIdentityResolver::class));
        self::assertSame([], $container->getDefinition(CroctManager::class)->getArguments());
    }

    #[TestDox('Injects the resolver into the manager when identity is enabled and Security is present.')]
    public function testInjectsResolverIntoManager(): void
    {
        $container = self::createContainer(enabled: true, withSecurity: true);

        (new IdentityIntegrationPass())->process($container);

        self::assertTrue($container->hasDefinition(SecurityIdentityResolver::class));

        // The resolver wraps the Security service.
        $resolver = $container->getDefinition(SecurityIdentityResolver::class);

        self::assertInstanceOf(Reference::class, $resolver->getArgument(0));
        self::assertSame(Security::class, (string) $resolver->getArgument(0));

        // The manager reconciles the visitor token against the resolver as the session is resolved.
        $argument = $container->getDefinition(CroctManager::class)->getArgument('$identity');

        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame(SecurityIdentityResolver::class, (string) $argument);
    }

    private static function createContainer(bool $enabled, bool $withSecurity): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('croct.identity.enabled', $enabled);
        $container->register(CroctManager::class);

        if ($withSecurity) {
            $container->register(Security::class);
        }

        return $container;
    }
}
