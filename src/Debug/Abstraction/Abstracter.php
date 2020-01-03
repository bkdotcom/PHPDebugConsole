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

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\Component;
use bdk\Debug\PhpDoc;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractArray;
use bdk\Debug\Abstraction\AbstractObject;

/**
 * Store array/object/resource info
 */
class Abstracter extends Component
{

    public $debug;
    protected $abstractArray;
    protected $abstractObject;

    const ABSTRACTION = "\x00debug\x00";
    const NOT_INSPECTED = "\x00notInspected\x00";
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
            'outputConstants' => true,
            'outputMethodDesc' => true,     // (or just summary)
            'outputMethods' => true,
            'useDebugInfo' => true,
            'fullyQualifyPhpDocType' => false,
        );
        $this->setCfg($cfg);
        $this->abstractArray = new AbstractArray($this);
        $this->abstractObject = new AbstractObject($this, new PhpDoc());
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
     * @return Abstraction|array|string
     */
    public function getAbstraction($mixed, $method = null, $hist = array())
    {
        if (\is_array($mixed)) {
            return $this->abstractArray->getAbstraction($mixed, $method, $hist);
        } elseif (\is_object($mixed) || \is_string($mixed) && (\class_exists($mixed) || \interface_exists($mixed))) {
            return $this->abstractObject->getAbstraction($mixed, $method, $hist);
        } elseif (\is_resource($mixed) || \strpos(\print_r($mixed, true), 'Resource') === 0) {
            return new Abstraction(array(
                'type' => 'resource',
                'value' => \print_r($mixed, true) . ': ' . \get_resource_type($mixed),
            ));
        }
    }

    /**
     * Returns value's type and "extended type" (ie "numeric"/"binary")
     *
     * @param mixed $val value
     *
     * @return array [$type, $typeMore]
     */
    public static function getType($val)
    {
        $type = null;
        $typeMore = null;
        if ($val instanceof Abstraction) {
            $type = $val['type'];
            $typeMore = 'abstraction';
        } elseif (\is_string($val)) {
            list($type, $typeMore) = self::getStringType($val);
        } elseif (\is_array($val)) {
            $type = 'array';
            $typeMore = 'raw';  // needs abstracted (references removed / values abstracted if necessary)
            if (AbstractArray::isCallable($val)) {
                $type = 'callable';
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
        return array($type, $typeMore);
    }

    /**
     * Is the passed value an abstraction
     *
     * @param mixed  $mixed value to check
     * @param string $type  additionally check type
     *
     * @return boolean
     */
    public static function isAbstraction($mixed, $type = null)
    {
        $isAbstraction = $mixed instanceof Abstraction;
        if (!$isAbstraction) {
            return false;
        }
        return $type
            ? $mixed['type'] === $type
            : true;
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
        // function array dereferencing = php 5.4
        $typeMore = self::getType($val)[1];
        return $typeMore === 'raw';
    }

    /**
     * Get string's type.
     * String could actually be "undefined" or "recursion"
     * Further, check if numeric
     *
     * @param string $val string value
     *
     * @return array type and typeMore
     */
    private static function getStringType($val)
    {
        $type = 'string';
        $typeMore = null;
        if (\is_numeric($val)) {
            $typeMore = 'numeric';
        } elseif ($val === self::UNDEFINED) {
            $type = 'undefined';    // not a native php type!
        } elseif ($val === self::RECURSION) {
            $type = 'recursion';    // not a native php type!
        } elseif ($val === self::NOT_INSPECTED) {
            $type = 'notInspected';
        }
        return array($type, $typeMore);
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array())
    {
        $debugClass = \get_class($this->debug);
        if (!\in_array($debugClass, $this->cfg['objectsExclude'])) {
            $this->cfg['objectsExclude'][] = $debugClass;
        }
    }
}
