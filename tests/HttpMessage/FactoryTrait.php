<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Factory;

trait FactoryTrait
{
    private $factory;

    public function factory()
    {
        if ($this->factory === null) {
            $this->factory = new Factory();
        }
        return $this->factory;
    }
}
