<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\DependencyInjection\Compiler;

use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\DependencyInjection\Compiler\IdentityIntegrationPass;
use Croct\Plug\Symfony\EventListener\CroctIdentityListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelEvents;

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

        $definition = $container->getDefinition(CroctIdentityListener::class);
        $tags = $definition->getTag('kernel.event_listener');

        self::assertCount(1, $tags);

        $tag = $tags[0];

        self::assertIsArray($tag);
        self::assertSame(KernelEvents::REQUEST, $tag['event']);
        self::assertSame('onKernelRequest', $tag['method']);
        self::assertSame(6, $tag['priority']);

        $arguments = $definition->getArguments();

        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(CroctFactory::class, (string) $arguments[0]);
        self::assertSame(Security::class, (string) $arguments[1]);
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
