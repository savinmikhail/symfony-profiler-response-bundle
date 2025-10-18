<?php

declare(strict_types=1);

namespace Tests\Functional;

use SavinMikhail\ResponseProfilerBundle\ResponseProfilerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function dirname;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new ResponseProfilerBundle();
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader): void
    {
        $c->loadFromExtension(extension: 'framework', values: [
            'secret' => 'test_secret',
            'http_method_override' => false,
            'test' => true,
            'profiler' => [
                'enabled' => true,
                'collect' => true,
            ],
            'router' => [
                'utf8' => true,
            ],
        ]);

        $c->loadFromExtension(extension: 'response_profiler', values: [
            'enabled' => true,
            'max_length' => 1_024, // smaller for truncation tests
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('json', '/json')->controller('Tests\\Functional\\Controller\\TestController::json');
        $routes->add('text', '/text')->controller('Tests\\Functional\\Controller\\TestController::text');
        $routes->add('pdf', '/pdf')->controller('Tests\\Functional\\Controller\\TestController::pdf');
        $routes->add('binary', '/binary')->controller('Tests\\Functional\\Controller\\TestController::binary');
        $routes->add('stream', '/stream')->controller('Tests\\Functional\\Controller\\TestController::stream');
        $routes->add('bigjson', '/bigjson')->controller('Tests\\Functional\\Controller\\TestController::bigjson');
    }

    public function getProjectDir(): string
    {
        return dirname(path: __DIR__);
    }

    public function getCacheDir(): string
    {
        return __DIR__ . '/../var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return __DIR__ . '/../var/log';
    }
}
