<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\Abstraction\AbstractArray;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Component;
use bdk\Debug\Utility\PhpDoc;

/**
 * Store array/object/resource info
 */
class Abstracter extends Component
{

    const ABSTRACTION = "\x00debug\x00";
    const NOT_INSPECTED = "\x00notInspected\x00";
    const RECURSION = "\x00recursion\x00";  // ie, array recursion
    const UNDEFINED = "\x00undefined\x00";

    const TYPE_ARRAY = 'array';
    const TYPE_BOOL = 'bool';
    const TYPE_CALLABLE = 'callable'; // non-native type : callable array
    const TYPE_INT = 'int';
    const TYPE_FLOAT = 'float';
    const TYPE_NULL = 'null';
    const TYPE_OBJECT = 'object';
    const TYPE_RESOURCE = 'resource';
    const TYPE_STRING = 'string';
    const TYPE_CONST = 'const'; // non-native type (Abstraction: we store name and value)
    const TYPE_UNDEFINED = 'undefined'; // non-native type
    const TYPE_NOT_INSPECTED = 'notInspected'; // non-native type
    const TYPE_RECURSION = 'recursion'; // non-native type
    const TYPE_UNKNOWN = 'unknown'; // non-native type

    public $debug;
    public static $utility;
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
        self::$utility = $debug->utility;
    }

    /**
     * "crate" value for logging
     *
     * @param mixed  $mixed  value to crate
     * @param string $method Method doing the crating
     * @param array  $hist   (@internal) array/object history (used to test for recursion)
     *
     * @return mixed
     */
    public function crate($mixed, $method = null, $hist = array())
    {
        $typeInfo = self::needsAbstraction($mixed);
        if ($typeInfo) {
            $mixed = $typeInfo === array(self::TYPE_ARRAY, 'raw')
                ? $this->abstractArray->crate($mixed, $method, $hist)
                : $this->getAbstraction($mixed, $method, $typeInfo, $hist);
        }
        return $mixed;
    }

    /**
     * Wrap value in Abstraction
     *
     * @param mixed $mixed  value to abstract
     * @param array $values additional values to set
     *
     * @return Abstraction
     */
    public function crateWithVals($mixed, $values = array())
    {
        $abs = $this->getAbstraction($mixed);
        foreach ($values as $k => $v) {
            $abs[$k] = $v;
        }
        return $abs;
    }

    /**
     * Store a "snapshot" of arrays, objects, & resources (or any other value)
     * along with other meta info/options for the value
     *
     * Remove any reference to an "external" variable
     * Deep cloning objects = problematic
     *   + some objects are uncloneable & throw fatal error
     *   + difficult to maintain circular references
     * Instead of storing objects in log, store "Abstraction" which containing
     *     type, methods, & properties
     *
     * @param mixed  $mixed    array, object, or resource to prep
     * @param string $method   Method requesting abstraction
     * @param array  $typeInfo (@internal) array specifying value's type & "typeMore"
     * @param array  $hist     (@internal) array/object history (used to test for recursion)
     *
     * @return Abstraction
     *
     * @internal
     */
    public function getAbstraction($mixed, $method = null, $typeInfo = array(), $hist = array())
    {
        $typeInfo = $typeInfo ?: self::getType($mixed);
        switch ($typeInfo[0]) {
            case self::TYPE_ARRAY:
                return $this->abstractArray->getAbstraction($mixed, $method, $hist);
            case self::TYPE_CALLABLE:
                return $this->abstractArray->getCallableAbstraction($mixed);
            case self::TYPE_OBJECT:
                return $this->abstractObject->getAbstraction($mixed, $method, $hist);
            case self::TYPE_RESOURCE:
                return new Abstraction($typeInfo[0], array(
                    'value' => \print_r($mixed, true) . ': ' . \get_resource_type($mixed),
                ));
            case self::TYPE_STRING:
                $maxLen = $this->debug->getCfg('stringMaxLen', Debug::CONFIG_DEBUG);
                return new Abstraction($typeInfo[0], array(
                    'strlen' => \strlen($mixed),
                    'typeMore' => $typeInfo[1],
                    'value' => $this->debug->utf8->strcut($mixed, 0, $maxLen),
                ));
            default:
                return new Abstraction($typeInfo[0], array(
                    'typeMore' => $typeInfo[1],
                    'value' => $mixed,
                ));
        }
    }

    /**
     * Returns value's type and "extended type" (ie "numeric"/"binary")
     *
     * @param mixed $val value
     *
     * @return array [$type, $typeMore] typeMore may be
     *    null
     *    'raw' indicates value needs crating
     *    'abstraction'
     *    'true'  (type bool)
     *    'false' (type bool)
     *    'numeric' (type string)
     */
    public static function getType($val)
    {
        $type = \gettype($val);
        $map = array(
            'boolean' => self::TYPE_BOOL,
            'double' => self::TYPE_FLOAT,
            'integer' => self::TYPE_INT,
            'NULL' => self::TYPE_NULL,
            'resource (closed)' => self::TYPE_RESOURCE,
        );
        if (isset($map[$type])) {
            $type = $map[$type];
        }
        switch ($type) {
            case self::TYPE_ARRAY:
                return self::getTypeArray($val);
            case self::TYPE_BOOL:
                return array(self::TYPE_BOOL, \json_encode($val));
            case self::TYPE_OBJECT:
                return self::getTypeObject($val);
            case self::TYPE_RESOURCE:
                return array(self::TYPE_RESOURCE, 'raw');
            case self::TYPE_STRING:
                return self::getTypeString($val);
            case 'unknown type':
                return self::getTypeUnknown($val);
            default:
                return array($type, null);
        }
    }

    /**
     * Is the passed value an abstraction
     *
     * @param mixed  $mixed value to check
     * @param string $type  additionally check type
     *
     * @return bool
     *
     * @psalm-assert-if-true Abstraction $mixed
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
     * @return array|false array(type, typeMore) or false
     */
    public function needsAbstraction($val)
    {
        if ($val instanceof Abstraction) {
            return false;
        }
        list($type, $typeMore) = self::getType($val);
        if ($typeMore === 'raw') {
            return array($type, $typeMore);
        }
        if ($type === self::TYPE_STRING) {
            $maxLen = $this->debug->getCfg('stringMaxLen', Debug::CONFIG_DEBUG);
            if ($maxLen && \strlen($val) > $maxLen) {
                return array($type, $typeMore);
            }
        }
        return false;
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
        $type = self::TYPE_ARRAY;
        $typeMore = 'raw';  // needs abstracted (references removed / values abstracted if necessary)
        if (\count($val) === 2 && self::$utility->isCallable($val)) {
            $type = self::TYPE_CALLABLE;
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
        $type = self::TYPE_OBJECT;
        $typeMore = 'raw';  // needs abstracted
        if ($object instanceof Abstraction) {
            $type = $object['type'];
            $typeMore = 'abstraction';
        }
        return array($type, $typeMore);
    }

    /**
     * Get string's type.
     *
     * @param string $val string value
     *
     * @return array type and typeMore
     */
    private static function getTypeString($val)
    {
        if ($val === self::UNDEFINED) {
            return array(self::TYPE_UNDEFINED, null);       // not a native php type!
        }
        if ($val === self::RECURSION) {
            return array(self::TYPE_RECURSION, null);       // not a native php type!
        }
        if ($val === self::NOT_INSPECTED) {
            return array(self::TYPE_NOT_INSPECTED, null);   // not a native php type!
        }
        return array(
            self::TYPE_STRING,
            \is_numeric($val)
                ? 'numeric'
                : null
        );
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
        $type = self::TYPE_UNKNOWN;
        $typeMore = null;
        /*
            closed resource?
            is_resource() returns false for a closed resource
            gettype  returns 'unknown type' or 'resource (closed)'
        */
        if (\strpos(\print_r($val, true), 'Resource') === 0) {
            $type = self::TYPE_RESOURCE;
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
