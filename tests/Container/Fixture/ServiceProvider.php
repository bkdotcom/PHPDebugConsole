<?php

namespace bdk\Test\Container\Fixture;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the specified container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container Container instance
     *
     * @return void
     */
    public function register(Container $container)
    {
        $container['param'] = 'value';

        $container['service'] = static function () {
            return new Service();
        };

        $container['factory'] = $container->factory(static function () {
            return new Service();
        });

        $container['protected'] = $container->protect(static function () {
            return 'I am a test';
        });
    }
}
