<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;

/**
 * Abstracter:  Methods used de=refrence and store arrays
 */
class AbstractArray extends AbstractComponent
{
    /** @var Abstracter */
    protected $abstracter;

    /** @var array<string, mixed> */
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
        $hist[] = $array;
        $return = array();
        /** @var mixed $v */
        foreach ($array as $k => $v) {
            $return[$k] = $this->abstracter->crate($v, $method, $hist);
        }
        return $return;
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
        return new Abstraction(Type::TYPE_ARRAY, array(
            'value' => $this->crate($array, $method, $hist),
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
            'value' => array($className, $array[1]),
        ));
    }
}
