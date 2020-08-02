<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;

/**
 * Abstracter:  Methods used de=refrence and store arrays
 */
class AbstractArray
{

	protected $abstracter;

    /**
     * Constructor
     *
     * @param Abstracter $abstracter abstracter obj
     */
    public function __construct(Abstracter $abstracter)
    {
        $this->abstracter = $abstracter;
    }

    /**
     * returns information about an array
     *
     * @param array  $array  Array to inspect
     * @param string $method Method requesting abstraction
     * @param array  $hist   (@internal) array/object history
     *
     * @return array|Abstraction|string
     */
    public function getAbstraction(&$array, $method = null, $hist = array())
    {
        if (\in_array($array, $hist, true)) {
            return Abstracter::RECURSION;
        }
        if ($this->abstracter->debug->utility->isCallable($array)) {
            // this appears to be a "callable"
            return new Abstraction(array(
                'type' => Abstracter::TYPE_CALLABLE,
                'value' => array(\get_class($array[0]), $array[1]),
            ));
        }
        $hist[] = $array;
        $return = array();
        foreach ($array as $k => $v) {
            $absInfo = $this->abstracter->needsAbstraction($v);
            if ($absInfo) {
                $v = $this->abstracter->getAbstraction($v, $method, $absInfo, $hist);
            }
            $return[$k] = $v;
        }
        return $return;
    }
}
