<?php

namespace bdk\DebugTests\Container\Fixture;

class NonInvokable
{
    public function __call($a, $b)
    {
    }
}
