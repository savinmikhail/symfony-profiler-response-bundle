<?php

declare(strict_types=1);

namespace SavinMikhail\ResponseProfilerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(name: 'response_profiler');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->integerNode('max_length')
                    ->min(1_024)
                    ->defaultValue(262_144) // 256 KB
                ->end()
                ->arrayNode('allowed_mime_types')
                    ->prototype('scalar')->end()
                    ->defaultValue([
                        'application/json',
                        'application/ld+json',
                        'application/problem+json',
                        'application/vnd.api+json',
                        'text/plain',
                        'text/json',
                        'application/x-ndjson',
                    ])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
