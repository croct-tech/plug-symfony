<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Plug;
use Croct\Plug\Symfony\CroctBundle;
use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(CroctBundle::class)]
#[TestDox('The Croct bundle configuration')]
final class CroctBundleTest extends TestCase
{
    #[TestDox('Wires every configured option to the factory and the container parameters.')]
    public function testWiresEveryConfiguredOption(): void
    {
        $container = $this->load([
            'app_id' => 'app-123',
            'api_key' => 'key-456',
            'base_endpoint_url' => 'https://api.example.test',
            'locale' => [
                'enabled' => false,
                'default' => 'en-GB',
            ],
            'identity' => [
                'enabled' => false,
            ],
            'cookie' => [
                'domain' => 'example.com',
                'secure' => false,
                'same_site' => 'lax',
            ],
            'storyblok' => [
                'enabled' => false,
            ],
        ]);

        $arguments = $container->getDefinition(CroctFactory::class)->getArguments();

        self::assertSame('app-123', $arguments[1]);
        self::assertSame('key-456', $arguments[2]);
        self::assertSame('https://api.example.test', $arguments[3]);
        self::assertFalse($arguments[4]);
        self::assertSame('en-GB', $arguments[5]);
        self::assertSame('example.com', $arguments[6]);
        self::assertFalse($arguments[7]);
        self::assertSame('lax', $arguments[8]);

        self::assertFalse($container->getParameter('croct.identity.enabled'));
        self::assertFalse($container->getParameter('croct.storyblok.enabled'));

        self::assertTrue($container->hasDefinition(Plug::class));
        self::assertTrue($container->hasDefinition(CroctResponseSubscriber::class));
    }

    #[TestDox('Applies the documented defaults for every optional option.')]
    public function testAppliesDefaults(): void
    {
        $container = $this->load([
            'app_id' => 'app-123',
            'api_key' => 'key-456',
        ]);

        $arguments = $container->getDefinition(CroctFactory::class)->getArguments();

        self::assertNull($arguments[3]);
        self::assertTrue($arguments[4]);
        self::assertNull($arguments[5]);
        self::assertNull($arguments[6]);
        self::assertTrue($arguments[7]);
        self::assertSame('none', $arguments[8]);

        self::assertTrue($container->getParameter('croct.identity.enabled'));
        self::assertTrue($container->getParameter('croct.storyblok.enabled'));
    }

    #[TestDox('Requires the application ID.')]
    public function testRequiresAppId(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'api_key' => 'key-456',
        ]);
    }

    #[TestDox('Requires the API key.')]
    public function testRequiresApiKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'app_id' => 'app-123',
        ]);
    }

    #[TestDox('Rejects an empty application ID.')]
    public function testRejectsEmptyAppId(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'app_id' => '',
            'api_key' => 'key-456',
        ]);
    }

    #[TestDox('Rejects an unknown same-site policy.')]
    public function testRejectsUnknownSameSite(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'app_id' => 'app-123',
            'api_key' => 'key-456',
            'cookie' => [
                'same_site' => 'invalid',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', \sys_get_temp_dir());

        $extension = (new CroctBundle())->getContainerExtension();

        self::assertNotNull($extension);

        $extension->load([$config], $container);

        return $container;
    }
}
