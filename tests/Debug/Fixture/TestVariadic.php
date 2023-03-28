<?php

namespace bdk\Test\Debug\Fixture;

/**
 * Test
 *
 * Variadic: PHP >= 5.6
 */
class TestVariadic extends TestObj
{
    /**
     * This method is protected
     *
     * @param mixed $param1     first param
     * @param mixed $moreParams variadic param (PHP 5.6)
     *
     * @return void
     */
    public function methodVariadic($param1, ...$moreParams)
    {
    }
}
