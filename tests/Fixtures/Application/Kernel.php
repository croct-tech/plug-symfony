<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\Fixtures\Application;

use Croct\Plug\Symfony\CroctBundle;
use Croct\Plug\Symfony\Tests\Fixtures\Application\Controller\PersonalizedController;
use Croct\Plug\Symfony\Tests\Fixtures\Application\Controller\PublicController;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal Symfony application used to validate the bundle end to end.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private ?string $token = null;

    /**
     * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new CroctBundle();
    }

    public function getCacheDir(): string
    {
        return $this->resolveTempDir() . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->resolveTempDir() . '/log';
    }

    /**
     * Returns a directory unique to this kernel instance, so each boot recompiles the container
     * (otherwise the bundle's configuration code would only run on the first, cached compile).
     */
    private function resolveTempDir(): string
    {
        $this->token ??= \uniqid('', true);

        return \sys_get_temp_dir() . '/croct-plug-symfony/' . $this->token;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => [
                'log' => true,
            ],
        ]);

        $container->extension('croct', [
            'app_id' => '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a',
            'api_key' => '%env(CROCT_API_KEY)%',
        ]);

        $services = $container->services()
            ->defaults()
            ->autowire()
            ->autoconfigure();

        $services->set(PersonalizedController::class)
            ->tag('controller.service_arguments');

        $services->set(PublicController::class)
            ->tag('controller.service_arguments');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('personalized', '/personalized')
            ->controller(PersonalizedController::class);

        $routes->add('public_page', '/public')
            ->controller(PublicController::class);
    }
}
