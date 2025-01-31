<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;

/**
 * Abstracter:  Methods used de=reference and store arrays
 */
class AbstractArray extends AbstractComponent
{
    /** @var Abstracter */
    protected $abstracter;

    /** @var array<string,mixed> */
    protected $cfg = array(
        'maxDepth' => 0,
    );

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
     * "Crate" array for logging
     *
     * @param array  $array  Array to crate
     * @param string $method Method requesting abstraction
     * @param array  $hist   (@internal) array/object history
     *
     * @return array|Abstraction|Abstracter::RECURSION
     */
    public function crate(array $array, $method = null, array $hist = array())
    {
        if (\in_array($array, $hist, true)) {
            return Abstracter::RECURSION;
        }
        if ($this->cfg['maxDepth'] && \count($hist) === $this->cfg['maxDepth']) {
            return new Abstraction(Type::TYPE_ARRAY, array(
                'options' => array(
                    'isMaxDepth' => true,
                ),
            ));
        }
        return $this->doCrate($array, $method, $hist);
    }

    /**
     * Returns array crated and wrapped in Abstraction
     *
     * @param array  $array  Array to crate as abstraction
     * @param string $method Method requesting abstraction
     * @param array  $hist   (@internal) array/object history
     *
     * @return Abstraction
     */
    public function getAbstraction(array &$array, $method = null, array $hist = array())
    {
        $value = $this->crate($array, $method, $hist);
        return $value instanceof Abstraction
            ? $value
            : new Abstraction(Type::TYPE_ARRAY, array(
                'value' => $value,
            ));
    }

    /**
     * Returns a callable array(obj, 'method') abstraction
     *
     * @param callable-array $array array callable
     *
     * @return Abstraction
     */
    public function getCallableAbstraction(array $array)
    {
        /** @var class-string */
        $className = \is_object($array[0])
            ? \get_class($array[0])
            : $array[0];
        if (PHP_VERSION_ID >= 70000 && \strpos($className, "@anonymous\0") !== false) {
            $className = $this->abstracter->debug->php->friendlyClassName($array[0]);
        }
        return new Abstraction(Type::TYPE_CALLABLE, array(
            'value' => [$className, $array[1]],
        ));
    }

    /**
     * Walk the array and crate values
     *
     * @param array  $array  Array to crate
     * @param string $method Method requesting abstraction
     * @param array  $hist   (@internal) array/object history
     *
     * @return array|Abstraction
     */
    private function doCrate(array $array, $method, array $hist)
    {
        $hist[] = $array;
        $utf8 = $this->abstracter->debug->utf8;
        $keys = array();
        $return = array();
        /** @var mixed $v */
        foreach ($array as $k => $v) {
            if (\is_string($k) && $utf8->isUtf8($k) === false) {
                $md5 = \md5($k);
                $keys[$md5] = $this->abstracter->crate($k, $method, $hist);
                $k = $md5;
            }
            $return[$k] = $this->abstracter->crate($v, $method, $hist);
        }
        return $keys
            ? new Abstraction(Type::TYPE_ARRAY, array(
                'keys' => $keys,
                'value' => $return,
            ))
            : $return;
    }
}
