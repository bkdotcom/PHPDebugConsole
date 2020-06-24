<?php

namespace bdk\Debug\Framework\Symfony\DebugBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 *
 */
class BdkDebugBundle extends Bundle
{

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        return parent::build($container);
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        // \bdk\Debug::_info(__METHOD__, $container);
        parent::setContainer($container);
    }
}
