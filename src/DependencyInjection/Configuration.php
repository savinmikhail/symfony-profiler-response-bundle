<?php

declare(strict_types=1);

namespace SavinMikhail\ResponseProfilerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @no-named-arguments
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(name: 'response_profiler');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @var NodeBuilder $children */
        $children = $rootNode->children();

        $children->booleanNode('enabled')->defaultTrue()->end();

        $children
            ->integerNode('max_length')
                ->min(1_024)
                ->defaultValue(262_144) // 256 KB
            ->end();

        $children
            ->arrayNode('allowed_mime_types')
                ->scalarPrototype()->end()
                ->defaultValue([
                    'application/json',
                    'application/ld+json',
                    'application/problem+json',
                    'application/vnd.api+json',
                    'text/plain',
                    'text/json',
                    'application/x-ndjson',
                ])
            ->end();

        // close children builder
        $children->end();

        return $treeBuilder;
    }
}
