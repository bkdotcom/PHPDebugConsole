<?php

namespace bdk\Test\Debug\Fixture;

abstract class TestAbstract
{
    protected $cfg = array();

    public function __construct(array $cfg = array())
    {
        $this->cfg = $cfg;
    }

    abstract public function foo($bar);
}
