<?php

namespace bdk\Test\Container\Fixture;

use bdk\Container\ServiceProviderInterface;

class ResolvableConstructorUnion
{
    public $dependency1;
    public $dependency2;

    public function __construct(\stdClass|ResolvableConstructorPhpDoc $dependency1, ServiceProviderInterface&ServiceProvider $dependency2)
    {
        $this->dependency1 = $dependency1;
        $this->dependency2 = $dependency2;
    }
}
