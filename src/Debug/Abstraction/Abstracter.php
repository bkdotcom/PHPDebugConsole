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

use bdk\Debug;
use bdk\Debug\Abstraction\AbstractArray;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Component;
use bdk\Debug\PhpDoc;

/**
 * Store array/object/resource info
 */
class Abstracter extends Component
{

    const ABSTRACTION = "\x00debug\x00";
    const NOT_INSPECTED = "\x00notInspected\x00";
    const RECURSION = "\x00recursion\x00";  // ie, array recursion
    const UNDEFINED = "\x00undefined\x00";

    public $debug;
    protected $abstractArray;
    protected $abstractObject;

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
        $type = \gettype($val);
        $typeMore = null;
        $map = array(
            'boolean' => 'bool',
            'double' => 'float',
            'integer' => 'int',
            'NULL' => 'null',
        );
        if (isset($map[$type])) {
            $type = $map[$type];
        }
        switch ($type) {
            case 'array':
                return self::getTypeArray($val);
            case 'bool':
                $typeMore = \json_encode($val);
                break;
            case 'object':
                return self::getTypeObject($val);
            case 'resource':
            case 'resource (closed)':
                return array('resource', 'raw');
            case 'string':
                return self::getTypeString($val);
            case 'unknown type':
                return self::getTypeUnknown($val);
        }
        return array($type, $typeMore);
    }

    /**
     * Is the passed value an abstraction
     *
     * @param mixed  $mixed value to check
     * @param string $type  additionally check type
     *
     * @return bool
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
     * @return bool
     */
    public static function needsAbstraction($val)
    {
        // function array dereferencing = php 5.4
        $typeMore = self::getType($val)[1];
        return $typeMore === 'raw';
    }

    /**
     * Get Array's type & typeMore
     *
     * @param array $val array value
     *
     * @return array
     */
    private static function getTypeArray($val)
    {
        $type = 'array';
        $typeMore = 'raw';  // needs abstracted (references removed / values abstracted if necessary)
        if (\count($val) === 2 && AbstractArray::isCallable($val)) {
            $type = 'callable';
            $typeMore = 'raw';  // needs abstracted
        }
        return array($type, $typeMore);
    }

    /**
     * Get Object's type & typeMore
     *
     * @param object $object any object
     *
     * @return array type & typeMore
     */
    private static function getTypeObject($object)
    {
        $type = 'object';
        $typeMore = 'raw';  // needs abstracted
        if ($object instanceof Abstraction) {
            $type = $object['type'];
            $typeMore = 'abstraction';
        }
        return array($type, $typeMore);
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
    private static function getTypeString($val)
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
     * Get "unknown" type & typeMore
     *
     * @param mixed $val value of unknown type (likely closed resource)
     *
     * @return array type and typeMore
     */
    private static function getTypeUnknown($val)
    {
        $type = 'unknown';
        $typeMore = null;
        /*
            closed resource?
            is_resource() returns false for a closed resource
            gettype  returns 'unknown type' or 'resource (closed)'
        */
        if (\strpos(\print_r($val, true), 'Resource') === 0) {
            $type = 'resource';
            $typeMore = 'raw';  // needs abstracted
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
