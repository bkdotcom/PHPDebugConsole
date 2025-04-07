<?php

namespace bdk\Test\Container\Fixture;

class ResolvableConstructor
{
    public $dependency;
    public $cfg = array();
    public $flag = true;

    public function __construct(\stdClass $dependency, array $cfg = array(), $flag = true)
    {
        $this->dependency = $dependency;
    }
}
