<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Symfony\DebugBundle\DependencyInjection;

use bdk\Debug;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * {@inheritDoc}
 */
class BdkDebugExtension extends Extension implements PrependExtensionInterface
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

    /**
     * "Prepend" / modify other extension config
     *
     * @param ContainerBuilder $container ContainerBuilder instance
     *
     * @return void
     */
    public function prepend(ContainerBuilder $container)
    {
        $kernelDebug = $container->getParameter('kernel.debug');
        if ($kernelDebug) {
            $container->prependExtensionConfig('framework', array(
                'php_errors' => array(
                    'throw' => false,
                ),
            ));
        }

        $container->prependExtensionConfig('monolog', array(
            'handlers' => array(
                'phpDebugConsole' => array(
                    'type' => 'service',
                    'id' => 'monologHandler',
                ),
            ),
        ));
    }
}
