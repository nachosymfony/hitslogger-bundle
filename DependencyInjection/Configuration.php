<?php

namespace nacholibre\HitsLoggerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('nacholibre_hits_logger');

        $rootNode
            ->children()
                ->integerNode('history_count')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('How many ip latest clicks to keep for each ip.')
                    ->defaultValue(50)
                ->end()
                ->scalarNode('redis_service_name')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('The service name of the redis client used for this statistics.')
                    ->defaultValue('@snc_redis.default')
                ->end()
                ->scalarNode('redis_keys_prefix')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Redis keys prefix.')
                    ->defaultValue('st')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
