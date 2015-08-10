<?php

namespace bdk\DebugTest;

/**
 * Test
 *
 * Variadic: PHP >= 5.6
 */
class TestClassVariadic extends TestClass
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
