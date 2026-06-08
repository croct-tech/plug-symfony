<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Plug;
use Croct\Plug\Symfony\CroctBundle;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Symfony\SecurityIdentityResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Boots the test application and asserts the bundle wires its services correctly.
 */
#[CoversClass(CroctBundle::class)]
#[TestDox('The Croct bundle')]
final class BundleBootTest extends KernelTestCase
{
    use RestoresExceptionHandler;

    #[TestDox('Registers the Plug facade and the response subscriber.')]
    public function testRegistersCoreServices(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        self::assertTrue($container->has(Plug::class));
        self::assertTrue($container->has(CroctResponseSubscriber::class));
        self::assertInstanceOf(ResetInterface::class, $container->get(CroctManager::class));
    }

    #[TestDox('Records the configured locale, identity, and Storyblok settings.')]
    public function testExposesConfiguredParameters(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        self::assertTrue($container->getParameter('croct.identity.enabled'));
        self::assertTrue($container->getParameter('croct.storyblok.enabled'));
    }

    #[TestDox('Skips the identity integration when Symfony Security is not installed.')]
    public function testSkipsIdentityIntegrationWithoutSecurity(): void
    {
        self::bootKernel();

        // The test app has no firewall, so the guarded pass must not register the resolver.
        self::assertFalse(self::getContainer()->has(SecurityIdentityResolver::class));
    }

    #[TestDox('Skips the Storyblok decorator when the integration package is not installed.')]
    public function testSkipsStoryblokDecoratorWithoutPackage(): void
    {
        self::bootKernel();

        self::assertFalse(self::getContainer()->has('Croct\Plug\Storyblok\CroctStoriesApi'));
    }
}
