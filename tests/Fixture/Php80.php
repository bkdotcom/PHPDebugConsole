<?php

namespace bdk\DebugTests\Fixture;

/**
 * PhpDoc Summary
 *
 * @link http://www.bradkent.com/php/debug PHPDebugConsole Homepage
 */
#[ExampleClassAttribute('foo', PHP_VERSION_ID, name:'bar')]
class Php80
{

    #[ExampleConstAttribute]
    private const FOO = 'foo';

    /**
     * Test isInitialized
     */
    #[ExamplePropAttribute]
    protected int $id;

    /**
     * Test attributes and promoted param
     *
     * Promoted param attributes will also be avail on the property
     *
     * @param int $arg1 Attributed & promoted param
     */
    #[ExampleMethodAttribute]
    public function __construct(
        #[ExampleParamAttribute] public int $arg1
    )
    {
    }
}
