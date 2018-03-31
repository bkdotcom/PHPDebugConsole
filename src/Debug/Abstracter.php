<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 */

namespace bdk\Debug;

use bdk\PubSub\Manager as EventManager;

/**
 * Methods used store array/object/resource info
 */
class Abstracter
{

    public $eventManager;
    protected $cfg = array();
    protected $abstractArray;
    protected $abstractObject;

    const ABSTRACTION = "\x00debug\x00";
    const RECURSION = "\x00recursion\x00";  // ie, array recursion
    const UNDEFINED = "\x00undefined\x00";

    /**
     * Constructor
     *
     * @param EventManager $eventManager event manager
     * @param array        $cfg          config options
     */
    public function __construct(EventManager $eventManager, $cfg = array())
    {
        $this->eventManager = $eventManager;
        $this->cfg = array(
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
     * @param mixed $mixed array, object, or resource to prep
     * @param array $hist  (@internal) array/object history (used to test for recursion)
     *
     * @return array
     */
    public function getAbstraction(&$mixed, $hist = array())
    {
        if (\is_array($mixed)) {
            return $this->abstractArray->getAbstraction($mixed, $hist);
        } elseif (\is_object($mixed)) {
            return $this->abstractObject->getAbstraction($mixed, $hist);
        } elseif (\is_resource($mixed) || \strpos(\print_r($mixed, true), 'Resource') === 0) {
            return array(
                'debug' => self::ABSTRACTION,
                'type' => 'resource',
                'value' => \print_r($mixed, true).': '.\get_resource_type($mixed),
            );
        }
    }

    /**
     * Special abstraction for arrays being logged via table() method.
     *
     * Array may be an array of objects
     *
     * @param array $mixed 1st level array or nested traversable object
     * @param array $hist  (@internal) array/object history (used to test for recursion)
     *
     * @return array
     */
    public function getAbstractionTable(&$mixed, $hist = array())
    {
        // first pass
        if (\is_array($mixed)) {
            return $this->abstractArray->getAbstractionTable($mixed);
        } elseif (\is_object($mixed)) {
            return $this->abstractObject->getAbstractionTable($mixed, $hist);
        }
    }

    /**
     * Returns value's type
     *
     * @param mixed  $val      value
     * @param string $typeMore will be populated with additional type info ("numeric"/"binary")
     *
     * @return [type] [description]
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
            $typeMore = $val ? 'true' : 'false';
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
            $type = 'resource';
            $typeMore = 'raw';  // needs abstracted
        }
        return $type;
    }

    /**
     * Get values for passed keys
     *
     * @param mixed $row     should be array or abstraction
     * @param array $keys    column keys
     * @param array $objInfo if row is an object, this will be populated with className and phpDoc
     *                         Otherwise, this will be false
     *
     * @return array
     */
    public static function keyValues($row, $keys, &$objInfo)
    {
        $values = array();
        $objInfo = false;
        $rowIsAbstraction = \is_array($row) && \in_array(self::ABSTRACTION, $row, true);
        $rowIsObject = $rowIsAbstraction && $row['type'] == 'object';
        $rowIsTraversable = $rowIsObject && \in_array('Traversable', $row['implements']) && isset($row['values']);
        if ($rowIsObject) {
            $objInfo = array(
                'className' => $row['className'],
                'phpDoc' => $row['phpDoc'],
            );
        }
        if ($rowIsTraversable) {
            $row = $row['values'];
        } elseif ($rowIsObject) {
            foreach ($row['properties'] as $k => $info) {
                if ($info['visibility'] !== 'public') {
                    unset($row['properties'][$k]);
                } else {
                    $row['properties'][$k] = $info['value'];
                }
            }
            $row = $row['properties'];
        }
        foreach ($keys as $key) {
            $value = self::UNDEFINED;
            if (\is_array($row)) {
                if (\array_key_exists($key, $row)) {
                    $value = $row[$key];
                }
            } elseif ($key === '') {
                $value = $row;
            }
            $values[$key] = $value;
        }
        return $values;
    }

    /**
     * * Is the passed value an abstraction
     *
     * @param mixed $mixed value to check
     *
     * @return boolean
     */
    public static function isAbstraction($mixed)
    {
        return \is_array($mixed) && \in_array(self::ABSTRACTION, $mixed, true);
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
     * @param string $path   key
     * @param mixed  $newVal value
     *
     * @return mixed
     */
    public function setCfg($path, $newVal = null)
    {
        $ret = null;
        if (\is_string($path)) {
            $ret = $this->cfg[$path];
            $this->cfg[$path] = $newVal;
        } elseif (\is_array($path)) {
            $this->cfg = \array_merge($this->cfg, $path);
        }
        if (!\in_array(__NAMESPACE__, $this->cfg['objectsExclude'])) {
            $this->cfg['objectsExclude'][] = __NAMESPACE__;
        }
        return $ret;
    }
}
