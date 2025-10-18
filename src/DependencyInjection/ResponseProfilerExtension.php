<?php

declare(strict_types=1);

namespace SavinMikhail\ResponseProfilerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class ResponseProfilerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration(configuration: $configuration, configs: $configs);

        $container->setParameter(name: 'response_profiler.enabled', value: (bool) $config['enabled']);
        $container->setParameter(name: 'response_profiler.max_length', value: (int) $config['max_length']);
        $container->setParameter(name: 'response_profiler.allowed_mime_types', value: (array) $config['allowed_mime_types']);

        $loader = new YamlFileLoader(container: $container, locator: new FileLocator(paths: __DIR__.'/../Resources/config'));
        $loader->load(resource: 'services.yaml');
    }
}
