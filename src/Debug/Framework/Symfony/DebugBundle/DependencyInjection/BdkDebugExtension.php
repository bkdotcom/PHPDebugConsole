<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Framework\Symfony\DebugBundle\DependencyInjection;

use bdk\Debug;
use bdk\Debug\Collector\DoctrineMiddleware;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * {@inheritDoc}
 */
class BdkDebugExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
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
    public function prepend(ContainerBuilder $container): void
    {
        // doctrine v3.2 added setMiddlewares
        // doctrine v3.3 added AbstractConnectionMiddleware (and other abstract classes)
        // doctrine v3.4 deprecated SqlLogger
        $doctrineSupportsMiddleware = \method_exists('Doctrine\DBAL\Configuration', 'setMiddlewares')
            && \class_exists('Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware');
        if ($doctrineSupportsMiddleware) {
            $container->register('doctrineMiddleware', DoctrineMiddleware::class)
                ->addTag('doctrine.middleware')
                ->addArgument(new Reference('bdk_debug'));
        }

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
                    'id' => 'monologHandler',
                    'type' => 'service',
                ),
            ),
        ));
    }
}
