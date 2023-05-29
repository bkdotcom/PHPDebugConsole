<?php

namespace bdk\Test\Debug\Fixture;

/**
 * Something more "complex" than stdClass
 */
class AnonBase extends \stdClass
{
    const ONE = 1;
    private const PRIVATE_CONST = 'dont look at me';

    protected $pro = 'gram';
    private $foo = 'bar';

    public function test()
    {
    }
}
