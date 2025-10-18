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
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('response_profiler.enabled', (bool) $config['enabled']);
        $container->setParameter('response_profiler.max_length', (int) $config['max_length']);
        $container->setParameter('response_profiler.allowed_mime_types', (array) $config['allowed_mime_types']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}

