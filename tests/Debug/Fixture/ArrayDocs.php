<?php

namespace bdk\Test\Debug\Fixture;

/**
 * "Array Shapes" and "General Arrays"
 *
 * @link https://phpstan.org/writing-php-code/phpdoc-types#array-shapes
 * @link https://phpstan.org/writing-php-code/phpdoc-types#general-arrays
 */
class ArrayDocs
{
    /** @var array{'name': string, "value": positive-int|string, foo: bar, number: 42, string: "theory"} Shape Description */
    public $shape;

    /** @var non-empty-array<string, array<int, int|string>|int|string>[] General Description */
    public $general;

    /** @var null|"literal"|123 Union test */
    public $literal;

    /**
     * Method description
     *
     * @param int|string $foo I'm a description
     *
     * @return void
     */
    public function myMethod($foo)
    {
    }
}
