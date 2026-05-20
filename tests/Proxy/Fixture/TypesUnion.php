<?php

namespace bdk\Test\Proxy\Fixture;

/**
 * Since php 8.0
 */
class TypesUnion
{
    public function test(string|int|null $param): string|int|null
    {
    }
}
