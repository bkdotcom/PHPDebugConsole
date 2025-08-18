<?php

namespace bdk\Test\Container\Fixture;

class UnresolvableConstructor
{
    public $dependency;

    public function __construct($dependency)
    {
    }
}
