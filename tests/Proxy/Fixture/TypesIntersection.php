<?php

namespace bdk\Test\Proxy\Fixture;

use Countable;
use Stringable;

/**
 * Since php 8.1
 */
class TypesIntersection
{
    public function test(Stringable&Countable $param): Stringable&Countable
    {
    }
}
