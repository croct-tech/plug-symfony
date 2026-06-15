<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Croct;
use Croct\Plug\CroctScript;
use Croct\Plug\CroctScriptProvider;
use Croct\Plug\LoadMode;
use Croct\Plug\Plug;
use Croct\Plug\Symfony\DependencyInjection\Compiler\IdentityIntegrationPass;
use Croct\Plug\Symfony\DependencyInjection\Compiler\StoryblokIntegrationPass;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Symfony\EventListener\CroctScriptListener;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use Croct\Plug\Symfony\Twig\CroctScriptExtension;
use Croct\Plug\Symfony\Twig\CroctScriptRuntime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\Psr18Client;
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
                ->integerNode('token_duration')
                    ->info('Lifetime in seconds of the issued visitor tokens.')
                    ->defaultValue(Croct::DEFAULT_TOKEN_DURATION)
                ->end()
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
                        ->scalarNode('script_url')
                            ->cannotBeEmpty()
                            ->defaultValue(CroctScript::DEFAULT_SCRIPT_URL)
                        ->end()
                        ->enumNode('mode')
                            ->info('How the SDK loader is fetched: sync (blocking), defer, or async.')
                            ->values(['sync', 'defer', 'async'])
                            ->defaultValue('defer')
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
        $scriptSrc = $path ?? $script['script_url'];
        // The enum node validates the value; the default arm covers the defer default.
        $mode = match ($script['mode']) {
            'sync' => LoadMode::SYNC,
            'async' => LoadMode::ASYNC,
            default => LoadMode::DEFER,
        };

        $services = $container->services()->defaults()->autowire()->autoconfigure();

        $services->set(CroctManager::class)
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
            // Optional integrations: a content provider for fallback content and the app logger.
            ->arg('$contentProvider', service(ContentProvider::class)->nullOnInvalid())
            ->arg('$logger', service(LoggerInterface::class)->nullOnInvalid())
            ->arg('$tokenDuration', $config['token_duration'])
            // Clears the per-request facade between requests in long-running workers (FrankenPHP, RoadRunner).
            ->tag('kernel.reset', ['method' => 'reset']);

        // The plug facade. The manager wraps it to flag responses that vary by visitor.
        $services->set(Plug::class)
            ->factory([service(CroctManager::class), 'getPlug']);

        $services->set(PrivateResponseMarker::class);

        // Autoconfiguration tags the subscriber as a kernel event subscriber.
        $services->set(CroctResponseSubscriber::class)
            ->args([
                service(CroctManager::class),
                service(PrivateResponseMarker::class),
            ]);

        if ($autoInject) {
            $services->set(CroctScriptSubscriber::class)
                ->args([
                    service(CroctManager::class),
                    $scriptSrc,
                    $script['placement'],
                    $mode,
                ]);
        }

        // The Twig function is registered only when Twig is installed.
        if (\interface_exists(RuntimeExtension::class)) {
            $services->set(CroctScriptRuntime::class)
                ->args([
                    service(CroctManager::class),
                    service('request_stack'),
                    $scriptSrc,
                    $mode,
                ]);

            $services->set(CroctScriptExtension::class);
        }

        // First-party serving: proxy the SDK through the app's own origin instead of the CDN.
        if ($path !== null) {
            $services->set(Psr18Client::class)
                ->args([service('http_client')]);

            $services->set(Psr16Cache::class)
                ->args([service('cache.app')]);

            $services->set(CroctScriptProvider::class)
                ->args([service(Psr18Client::class), service(Psr18Client::class), service(Psr16Cache::class)])
                ->arg('$scriptUrl', $script['script_url']);

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
