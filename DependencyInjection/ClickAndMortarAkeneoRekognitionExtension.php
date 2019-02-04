<?php

namespace ClickAndMortar\AkeneoRekognitionBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class ClickAndMortarAkeneoRekognitionExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        // From https://docs.akeneo.com/latest/import_and_export_data/guides/create-connector.html#configure-a-job
        // > Make sure that the file containing your declaration is correctly loaded by your bundle extension.
        $loader->load('jobs.yml');
        $loader->load('processors.yml');
        $loader->load('steps.yml');
    }
}
