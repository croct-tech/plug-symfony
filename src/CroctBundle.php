<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\Plug;
use Croct\Plug\Symfony\DependencyInjection\Compiler\IdentityIntegrationPass;
use Croct\Plug\Symfony\DependencyInjection\Compiler\StoryblokIntegrationPass;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Symfony\EventListener\CroctScriptListener;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use Croct\Plug\Symfony\Twig\CroctScriptExtension;
use Croct\Plug\Symfony\Twig\CroctScriptRuntime;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Extension\RuntimeExtensionInterface as RuntimeExtension;
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
                ->arrayNode('script')
                    ->info('Injects the client-side SDK bootstrap into HTML responses.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_inject')->defaultTrue()->end()
                        ->enumNode('placement')
                            ->values(['head', 'body'])
                            ->defaultValue('head')
                        ->end()
                        ->scalarNode('path')
                            ->info('First-party path that serves the SDK. Set to false to use the CDN instead.')
                            ->defaultValue('/_croct/plug.js')
                        ->end()
                        ->scalarNode('loader_url')
                            ->cannotBeEmpty()
                            ->defaultValue('https://cdn.croct.io/js/v1/lib/plug.js')
                        ->end()
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

        $script = \is_array($config['script']) ? $config['script'] : [];

        $identityEnabled = \is_bool($identity['enabled']) ? $identity['enabled'] : true;
        $storyblokEnabled = \is_bool($storyblok['enabled']) ? $storyblok['enabled'] : true;
        $autoInject = \is_bool($script['auto_inject']) ? $script['auto_inject'] : true;
        // A string path serves the SDK first-party. A false or null value loads it from the CDN.
        $path = \is_string($script['path']) ? $script['path'] : null;
        $scriptSrc = $path ?? $script['loader_url'];

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

        if ($autoInject) {
            $services->set(CroctScriptSubscriber::class)
                ->args([
                    service(CroctFactory::class),
                    $scriptSrc,
                    $script['placement'],
                ]);
        }

        // The Twig function is registered only when Twig is installed.
        if (\interface_exists(RuntimeExtension::class)) {
            $services->set(CroctScriptRuntime::class)
                ->args([
                    service(CroctFactory::class),
                    service('request_stack'),
                    $scriptSrc,
                ]);

            $services->set(CroctScriptExtension::class);
        }

        // First-party serving: proxy the SDK through the app's own origin instead of the CDN.
        if ($path !== null) {
            $services->set(CroctScriptProvider::class)
                ->arg('$loaderUrl', $script['loader_url']);

            $services->set(CroctScriptListener::class)
                ->arg('$path', $path);
        }

        // Read by the guarded compiler passes. See build().
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
