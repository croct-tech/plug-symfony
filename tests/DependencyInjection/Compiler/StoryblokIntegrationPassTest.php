<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\DependencyInjection\Compiler;

use Croct\Plug\Symfony\DependencyInjection\Compiler\StoryblokIntegrationPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(StoryblokIntegrationPass::class)]
#[TestDox('The Storyblok integration pass')]
final class StoryblokIntegrationPassTest extends TestCase
{
    private const DECORATOR = 'Croct\\Plug\\Storyblok\\CroctStoriesApi';

    private const STORIES_API = 'Storyblok\\Api\\StoriesApiInterface';

    #[TestDox('Does not register the decorator when Storyblok is disabled.')]
    public function testSkipsWhenDisabled(): void
    {
        $container = self::createContainer(enabled: false, withService: true);

        (new StoryblokIntegrationPass())->process($container);

        self::assertFalse($container->hasDefinition(self::DECORATOR));
    }

    #[TestDox('Does not register the decorator when the Storyblok service is absent.')]
    public function testSkipsWhenServiceAbsent(): void
    {
        $container = self::createContainer(enabled: true, withService: false);

        (new StoryblokIntegrationPass())->process($container);

        self::assertFalse($container->hasDefinition(self::DECORATOR));
    }

    #[RunInSeparateProcess]
    #[TestDox('Registers the decorator when enabled and the Storyblok service is present.')]
    public function testRegistersDecorator(): void
    {
        // Simulate croct/plug-storyblok being installed so the class is discoverable.
        if (!\class_exists(self::DECORATOR)) {
            \class_alias(\stdClass::class, self::DECORATOR);
        }

        $container = self::createContainer(enabled: true, withService: true);

        (new StoryblokIntegrationPass())->process($container);

        self::assertTrue($container->hasDefinition(self::DECORATOR));

        $definition = $container->getDefinition(self::DECORATOR);

        $decorated = $definition->getDecoratedService();

        self::assertNotNull($decorated);
        self::assertSame(self::STORIES_API, $decorated[0]);

        $arguments = $definition->getArguments();

        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame('.inner', (string) $arguments[0]);
        self::assertSame('Croct\\Plug\\Plug', (string) $arguments[1]);
        self::assertFalse($definition->isAutowired());
    }

    private static function createContainer(bool $enabled, bool $withService): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('croct.storyblok.enabled', $enabled);

        if ($withService) {
            $container->register(self::STORIES_API);
        }

        return $container;
    }
}
