<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\DependencyInjection\Compiler;

use Croct\Plug\Plug;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the Croct/Storyblok decorator when both the decorator and the Storyblok service are present.
 */
final class StoryblokIntegrationPass implements CompilerPass
{
    private const STORIES_API_INTERFACE = 'Storyblok\\Api\\StoriesApiInterface';

    private const CROCT_STORIES_API = 'Croct\\Plug\\Storyblok\\CroctStoriesApi';

    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('croct.storyblok.enabled') !== true) {
            return;
        }

        // The decorator ships in croct/plug-storyblok, and the service comes from storyblok/symfony-bundle.
        if (!\class_exists(self::CROCT_STORIES_API) || !$container->has(self::STORIES_API_INTERFACE)) {
            return;
        }

        $definition = new Definition(self::CROCT_STORIES_API);
        $definition->setDecoratedService(self::STORIES_API_INTERFACE);

        $definition->setArguments([
            new Reference('.inner'),
            new Reference(Plug::class),
        ]);

        $definition->setAutowired(false);
        $definition->setAutoconfigured(false);

        $container->setDefinition(self::CROCT_STORIES_API, $definition);
    }
}
