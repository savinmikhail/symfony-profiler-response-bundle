<?php

declare(strict_types=1);

namespace Tests\Functional;

use SavinMikhail\ResponseProfilerBundle\ResponseProfilerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;

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
        $c->loadFromExtension('framework', [
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

        $c->loadFromExtension('response_profiler', [
            'enabled' => true,
            'max_length' => 1024, // smaller for truncation tests
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('json', '/json')->controller(function (): Response {
            return new JsonResponse(['hello' => 'world', 'answer' => 42]);
        });

        $routes->add('text', '/text')->controller(function (): Response {
            return new Response('plain text body', 200, ['Content-Type' => 'text/plain']);
        });

        $routes->add('pdf', '/pdf')->controller(function (): Response {
            return new Response('%PDF-1.4 binary data', 200, ['Content-Type' => 'application/pdf']);
        });

        $routes->add('binary', '/binary')->controller(function (): Response {
            $file = sys_get_temp_dir() . '/rf_binary_test_' . uniqid('', true) . '.bin';
            file_put_contents($file, random_bytes(16));
            return new BinaryFileResponse($file);
        });

        $routes->add('stream', '/stream')->controller(function (): Response {
            return new StreamedResponse(static function (): void {
                echo 'streamed';
            });
        });

        $routes->add('bigjson', '/bigjson')->controller(function (): Response {
            $payload = ['chunk' => str_repeat('A', 3000)];
            return new JsonResponse($payload);
        });
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 1);
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

