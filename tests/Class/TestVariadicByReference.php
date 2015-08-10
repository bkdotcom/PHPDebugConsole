<?php

namespace bdk\DebugTest;

/**
 * Test
 *
 * Variadic: PHP >= 5.6
 * HHVM does not support Variadic by reference
 */
class TestVariadicByReference extends TestVariadic
{

    /**
     * This method is private
     *
     * @param mixed   $param1     first param (passed by ref)
     * @param boolean $moreParams variadic param by reference (PHP 5.6)
     *
     * @return void
     */
    public function methodVariadicByReference(&$param1, &...$moreParams)
    {

    }
}
