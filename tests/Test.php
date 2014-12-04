<?php

namespace bdk\Debug;

define('SOMECONSTANT', 'Constant value');

/**
 * Test
 */
class Test
{

	/**
	 * Test var comment
	 */
    public $propPublic = 'iAmPublic';
    private $propPrivate = 'iAmPrivate';
    protected $propProtected = 'iAmProtected';

    /**
     * This method is public
     *
     * @param mixed $param1 first param (passed by ref)
     * @param mixed $param2 second param (passed by ref)
     *                      two-line description!
     * @param array $param3 third param
     *
     * @return void
     * @deprecated
     */
    public function methodPublic(&$param1, &$param2 = SOMECONSTANT, array $param3 = array())
	{

    }

    /**
     * This method is private
     *
     * @param mixed   $param1     first param
     * @param boolean $moreParams variadic param
	 *
     * @return void
     */
    private function methodPrivate($param1, ...$moreParams)
    {

    }

    /**
     * This method is protected
     *
     * @param mixed $param1     first param
     * @param mixed $moreParams variadic param by reference
     *
     * @return void
     */
    protected function methodProtected($param1, &...$moreParams)
    {

    }
}
