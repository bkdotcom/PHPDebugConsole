<?php
/**
 * Methods used to de-reference and store arrays
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * Abstracter:  Methods used store arrays
 */
class AbstractArray
{

	protected $abstracter;

    /**
     * Constructor
     *
     * @param object $abstracter abstracter obj
     */
    public function __construct($abstracter)
    {
        $this->abstracter = $abstracter;
    }

    /**
     * returns information about an array
     *
     * @param array $array array to inspect
     * @param array $hist  (@internal) array/object history
     *
     * @return array
     */
    public function getAbstraction(&$array, &$hist = array())
    {
        if (in_array($array, $hist, true)) {
        	return $this->abstracter->RECURSION;
        }
        if (array_keys($array) == array(0,1) && is_object($array[0]) && is_string($array[1]) && method_exists($array[0], $array[1])) {
            // this appears to be a "callable"
	        return array(
	            'debug' => $this->abstracter->ABSTRACTION,
	            'type' => 'callable',
	            'values' => array(get_class($array[0]), $array[1]),
	        );
        }
        $return = array();
        $hist[] = $array;
        foreach ($array as $k => $v) {
            if ($this->abstracter->needsAbstraction($v)) {
                $v = $this->abstracter->getAbstraction($array[$k], $hist);
            }
            $return[$k] = $v;
        }
        return $return;
    }

    /**
     * Special abstraction for arrays being logged via table()
     *
     * Could be an array of objects
     *
     * @param array $array array
     *
     * @return array
     */
    public function getAbstractionTable(&$array)
    {
        $return = array();
        $hist[] = $array;
        foreach ($array as $k => $v) {
            if (is_object($v)) {
                $v = $this->abstracter->getAbstractionTable($v, $hist);
            } elseif ($this->abstracter->needsAbstraction($v)) {
                $v = $this->abstracter->getAbstraction($array[$k], $hist);
            }
            $return[$k] = $v;
        }
        return $return;
    }
}
