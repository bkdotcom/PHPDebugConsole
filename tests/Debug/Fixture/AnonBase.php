<?php

namespace bdk\Test\Debug\Fixture;

/**
 * Something more "complex" than stdClass
 */
class AnonBase extends \stdClass
{
    const ONE = 1;

    protected $pro = 'gram';
    private $foo = 'bar';

    public function test()
    {
    }
}
