<?php

namespace bdk\Debug\Framework\Symfony\DebugBundle\DependencyInjection;

use bdk\Debug;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * {@inheritDoc}
 */
class BdkDebugExtension extends Extension
{

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('config.yml');

        $definition = $container->getDefinition(Debug::class);

        /*
            Config will get passed to constructor (or factory) defined in config
        */
        $definition->setArgument(0, $configs[0]);
    }
}
