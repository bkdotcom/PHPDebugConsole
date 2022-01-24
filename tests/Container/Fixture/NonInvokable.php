<?php

namespace bdk\Test\Container\Fixture;

class NonInvokable
{
    public function __call($a, $b)
    {
    }
}
