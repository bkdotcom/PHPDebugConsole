<?php

namespace bdk\Debug\Framework\Symfony\DebugBundle\DependencyInjection;

use bdk\Debug;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 *
 */
class BdkDebugExtension extends Extension
{

    public $debug;

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $debug = Debug::getInstance(array(
            'collect' => true,
            'output' => true,
        ));

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        // $debug->warn(__METHOD__, $container);
        // $container->getDefinition('monolog.logger')
            // ->addMethodCall('pushHandler', [new \Monolog\Handler\PsrHandler($debug->logger)]);
    }
}
