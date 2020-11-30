<?php

namespace bdk\DebugTests\Fixture;

/**
 * PhpDoc Summary
 *
 * @link http://www.bradkent.com/php/debug PHPDebugConsole Homepage
 */
#[ExampleAttribute("foo", PHP_VERSION_ID, name:"bar")]
class Attributed
{

    #[ExampleAttribute]
    private const FOO = 'foo';

    #[ExampleAttribute]
    protected int $id;

    #[ExampleAttribute]
    public function myMethod(
        #[ExampleAttribute] int $arg1
    )
    {
    }

    public function __toString(): string
    {
        return 'turd';
    }
}
