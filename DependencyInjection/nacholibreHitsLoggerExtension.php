<?php

namespace nacholibre\HitsLoggerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class nacholibreHitsLoggerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        //$container->setParameter('nacholibre.hits_logger.site_identifier', $config['']);
        $container->setParameter('nacholibre.hits_logger.history_count', $config['history_count']);
        $container->setParameter('nacholibre.hits_logger.redis_service_name', $config['redis_service_name']);
        $container->setParameter('nacholibre.hits_logger.redis_keys_prefix', $config['redis_keys_prefix']);
    }
}
