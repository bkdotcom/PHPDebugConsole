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

use bdk\Debug;

/**
 * Methods used store array/object/resource info
 */
class Abstracter
{

    public $debug;
    protected $cfg = array();
    protected $abstractArray;
    protected $abstractObject;

    const ABSTRACTION = "\x00debug\x00";
    const RECURSION = "\x00recursion\x00";  // ie, array recursion
    const UNDEFINED = "\x00undefined\x00";

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     * @param array $cfg   config options
     */
    public function __construct(Debug $debug, $cfg = array())
    {
        $this->debug = $debug;  // we need debug instance so we can bubble events up channels
        $this->cfg = array(
            'cacheMethods' => true,
            'collectConstants' => true,
            'collectMethods' => true,
            'objectsExclude' => array(
                __NAMESPACE__,
            ),
            'objectSort' => 'visibility',   // none, visibility, or name
            'useDebugInfo' => true,
        );
        $this->setCfg($cfg);
        $this->abstractArray = new AbstractArray($this);
        $this->abstractObject = new AbstractObject($this, new PhpDoc());
    }

    /**
     * Magic getter
     *
     * Used to get constants via -> operator
     * which is more friendly for dependency injection
     *
     * @param string $name name of property or constant
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (\defined(__CLASS__.'::'.$name)) {
            return \constant(__CLASS__.'::'.$name);
        }
    }

    /**
     * Retrieve a config or data value
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function getCfg($path = null)
    {
        if (!\strlen($path)) {
            return $this->cfg;
        }
        if (isset($this->cfg[$path])) {
            return $this->cfg[$path];
        }
        return null;
    }

    /**
     * Want to store a "snapshot" of arrays, objects, & resources
     * Remove any reference to an "external" variable
     *
     * Deep cloning objects = problematic
     *   + some objects are uncloneable & throw fatal error
     *   + difficult to maintain circular references
     * Instead of storing objects in log, store "abstraction" array containing
     *     type, methods, & properties
     *
     * @param mixed  $mixed  array, object, or resource to prep
     * @param string $method Method requesting abstraction
     * @param array  $hist   (@internal) array/object history (used to test for recursion)
     *
     * @return array
     */
    public function getAbstraction(&$mixed, $method = null, $hist = array())
    {
        if (\is_array($mixed)) {
            return $this->abstractArray->getAbstraction($mixed, $method, $hist);
        } elseif (\is_object($mixed)) {
            return $this->abstractObject->getAbstraction($mixed, $method, $hist);
        } elseif (\is_resource($mixed) || \strpos(\print_r($mixed, true), 'Resource') === 0) {
            return array(
                'debug' => self::ABSTRACTION,
                'type' => 'resource',
                'value' => \print_r($mixed, true).': '.\get_resource_type($mixed),
            );
        }
    }

    /**
     * Returns value's type
     *
     * @param mixed  $val      value
     * @param string $typeMore will be populated with additional type info ("numeric"/"binary")
     *
     * @return string
     */
    public static function getType($val, &$typeMore = null)
    {
        if (\is_string($val)) {
            $type = 'string';
            if (\is_numeric($val)) {
                $typeMore = 'numeric';
            } elseif ($val === self::UNDEFINED) {
                $type = 'undefined';    // not a native php type!
            } elseif ($val === self::RECURSION) {
                $type = 'recursion';    // not a native php type!
            }
        } elseif (\is_array($val)) {
            if (\in_array(self::ABSTRACTION, $val, true)) {
                $type = $val['type'];
                $typeMore = 'abstraction';
            } elseif (AbstractArray::isCallable($val)) {
                $type = 'callable';
                $typeMore = 'raw';  // needs abstracted
            } else {
                $type = 'array';
                $typeMore = 'raw';  // needs abstracted
            }
        } elseif (\is_bool($val)) {
            $type = 'bool';
            $typeMore = \json_encode($val);
        } elseif (\is_float($val)) {
            $type = 'float';
        } elseif (\is_int($val)) {
            $type = 'int';
        } elseif (\is_null($val)) {
            $type = 'null';
        } elseif (\is_object($val)) {
            $type = 'object';
            $typeMore = 'raw';  // needs abstracted
        } elseif (\is_resource($val) || \strpos(\print_r($val, true), 'Resource') === 0) {
            // is_resource() returns false for a closed resource
            // (it's also not a string)
            $type = 'resource';
            $typeMore = 'raw';  // needs abstracted
        }
        return $type;
    }

    /**
     * Is the passed value an abstraction
     *
     * @param mixed $mixed value to check
     *
     * @return boolean
     */
    public static function isAbstraction($mixed)
    {
        return \is_array($mixed) && isset($mixed['debug']) && $mixed['debug'] === self::ABSTRACTION;
    }

    /**
     * Is the passed value an array, object, or resource that needs abstracted?
     *
     * @param mixed $val value to check
     *
     * @return boolean
     */
    public static function needsAbstraction($val)
    {
        self::getType($val, $typeMore);
        return $typeMore === 'raw';
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $mixed  key=>value array or key
     * @param mixed  $newVal value
     *
     * @return mixed old value(s)
     */
    public function setCfg($mixed, $newVal = null)
    {
        $ret = null;
        if (\is_string($mixed)) {
            $ret = isset($this->cfg[$mixed])
                ? $this->cfg[$mixed]
                : null;
            $this->cfg[$mixed] = $newVal;
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
            $this->cfg = \array_merge($this->cfg, $mixed);
        }
        if (!\in_array(__NAMESPACE__, $this->cfg['objectsExclude'])) {
            $this->cfg['objectsExclude'][] = __NAMESPACE__;
        }
        return $ret;
    }
}
