<?php

namespace bdk\Test\Proxy\Fixture;

use Countable;
use Stringable;

class TypesDisjunctiveNormalForm
{
    public function test(string|int|null|(Stringable&Countable) $param): WidgetInterface|(Stringable&Countable)|null
    {
    }
}
