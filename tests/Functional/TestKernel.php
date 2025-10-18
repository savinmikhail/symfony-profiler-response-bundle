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
        $routes->add(name: 'json', path: '/json')->controller(controller: static fn(): Response => new JsonResponse(data: ['hello' => 'world', 'answer' => 42]));

        $routes->add(name: 'text', path: '/text')->controller(controller: static fn(): Response => new Response(content: 'plain text body', headers: ['Content-Type' => 'text/plain']));

        $routes->add(name: 'pdf', path: '/pdf')->controller(controller: static fn(): Response => new Response(content: '%PDF-1.4 binary data', headers: ['Content-Type' => 'application/pdf']));

        $routes->add(name: 'binary', path: '/binary')->controller(controller: static function (): Response {
            $file = sys_get_temp_dir() . '/rf_binary_test_' . uniqid(more_entropy: true) . '.bin';
            file_put_contents(filename: $file, data: random_bytes(length: 16));

            return new BinaryFileResponse(file: $file);
        });

        $routes->add(name: 'stream', path: '/stream')->controller(controller: static fn(): Response => new StreamedResponse(callbackOrChunks: static function (): void {
            echo 'streamed';
        }));

        $routes->add(name: 'bigjson', path: '/bigjson')->controller(controller: static function (): Response {
            $payload = ['chunk' => str_repeat(string: 'A', times: 3_000)];

            return new JsonResponse(data: $payload);
        });
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
