<?php

namespace bdk\Test\Debug\Fixture;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;

/**
 *
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services and factories
     *
     * @param Container $container Container instances
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function register(Container $container)
    {
        $container['foo'] = 'bar2';
    }
}
