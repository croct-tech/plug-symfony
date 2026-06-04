<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\Plug;
use Croct\Plug\Symfony\DependencyInjection\Compiler\IdentityIntegrationPass;
use Croct\Plug\Symfony\DependencyInjection\Compiler\StoryblokIntegrationPass;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Plugs the Croct SDK into Symfony.
 */
final class CroctBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('app_id')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('api_key')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('base_endpoint_url')->defaultNull()->end()
                ->arrayNode('locale')
                    ->info('Detects the visitor locale from the request.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('default')
                            ->info('Locale used as the override when detection is on, or the fixed value when off.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('identity')
                    ->info('Keeps the visitor token in sync with the authenticated Symfony user.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
                ->arrayNode('cookie')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('domain')->defaultNull()->end()
                        ->booleanNode('secure')->defaultTrue()->end()
                        ->enumNode('same_site')
                            ->values(['strict', 'lax', 'none'])
                            ->defaultValue('none')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('storyblok')
                    ->info('Serves Croct content inside Storyblok stories when the Storyblok bundle is installed.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $locale = \is_array($config['locale']) ? $config['locale'] : [];
        $cookie = \is_array($config['cookie']) ? $config['cookie'] : [];
        $identity = \is_array($config['identity']) ? $config['identity'] : [];
        $storyblok = \is_array($config['storyblok']) ? $config['storyblok'] : [];

        $identityEnabled = \is_bool($identity['enabled']) ? $identity['enabled'] : true;
        $storyblokEnabled = \is_bool($storyblok['enabled']) ? $storyblok['enabled'] : true;

        $services = $container->services()->defaults()->autowire()->autoconfigure();

        $services->set(CroctFactory::class)
            ->args([
                service('request_stack'),
                $config['app_id'],
                $config['api_key'],
                $config['base_endpoint_url'],
                $locale['enabled'],
                $locale['default'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['same_site'],
            ])
            // Clears the per-request facade between requests in long-running workers (FrankenPHP, RoadRunner).
            ->tag('kernel.reset', ['method' => 'reset']);

        // The Plug facade, wrapped in a VaryingResponseObserver by the factory.
        $services->set(Plug::class)
            ->factory([service(CroctFactory::class), 'getPlug']);

        // Autoconfiguration tags the subscriber as a kernel event subscriber.
        $services->set(CroctResponseSubscriber::class);

        // Read by the guarded compiler passes; see build().
        $builder->setParameter('croct.identity.enabled', $identityEnabled);
        $builder->setParameter('croct.storyblok.enabled', $storyblokEnabled);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Both integrations self-activate only when their optional dependency is present.
        $container->addCompilerPass(new IdentityIntegrationPass());
        $container->addCompilerPass(new StoryblokIntegrationPass());
    }
}
