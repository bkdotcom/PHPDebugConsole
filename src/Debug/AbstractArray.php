<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug;

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
     * @return array
     */
    public function getAbstraction(&$array, $method = null, &$hist = array())
    {
        if (\in_array($array, $hist, true)) {
            return $this->abstracter->RECURSION;
        }
        if (self::isCallable($array)) {
            // this appears to be a "callable"
            return array(
                'debug' => $this->abstracter->ABSTRACTION,
                'type' => 'callable',
                'values' => array(\get_class($array[0]), $array[1]),
            );
        }
        $return = array();
        $hist[] = $array;
        foreach ($array as $k => $v) {
            if ($this->abstracter->needsAbstraction($v)) {
                $v = $this->abstracter->getAbstraction($array[$k], $method, $hist);
            }
            $return[$k] = $v;
        }
        return $return;
    }

    /**
     * Is array a callable?
     *
     * @param array $array array to check
     *
     * @return boolean
     */
    public static function isCallable($array)
    {
        return \array_keys($array) == array(0,1)
            && \is_object($array[0])
            && \is_string($array[1])
            && \method_exists($array[0], $array[1]);
    }
}
