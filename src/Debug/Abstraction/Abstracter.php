<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
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

    /*
        "typeMore" values
    */
    const TYPE_ABSTRACTION = 'abstraction';
    const TYPE_RAW = 'raw'; // raw object or array
    const TYPE_STRING_BASE64 = 'base64';
    const TYPE_STRING_BINARY = 'binary';
    const TYPE_STRING_CLASSNAME = 'classname';
    const TYPE_STRING_JSON = 'json';
    const TYPE_STRING_LONG = 'maxLen';
    const TYPE_STRING_NUMERIC = 'numeric';
    const TYPE_STRING_SERIALIZED = 'serialized';

    public $debug;
    public static $utility;
    protected $abstractArray;
    protected $abstractObject;
    protected $abstractString;
    private $crateVals = array();

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
            'collectAttributesConst' => true,
            'collectAttributesMethod' => true,
            'collectAttributesObj' => true,
            'collectAttributesParam' => true,
            'collectAttributesProp' => true,
            'collectConstants' => true,
            'collectMethods' => true,
            'fullyQualifyPhpDocType' => false,
            'objectsExclude' => array(
                __NAMESPACE__,
            ),
            'objectSort' => 'visibility',   // none, visibility, or name
            'objectsWhitelist' => null,     // will be used if array
            'outputAttributesConst' => true,
            'outputAttributesMethod' => true,
            'outputAttributesObj' => true,
            'outputAttributesParam' => true,
            'outputAttributesProp' => true,
            'outputConstants' => true,
            'outputMethodDesc' => true,     // (or just summary)
            'outputMethods' => true,
            'stringMaxLen' => array(
                'base64' => 156, // 2 lines of chunk_split'ed
                'binary' => array(
                    128 => 0, // if over 128 bytes don't capture / store
                ),
                'other' => 8192,
            ),
            'stringMinLen' => array(
                'contentType' => 256, // try to determine content-type of binary string
                'encoded' => 16, // test if bas64, json, or serialized (-1 = don't check)
            ),
            'useDebugInfo' => true,
        );
        $this->abstractArray = new AbstractArray($this);
        $this->abstractObject = new AbstractObject($this, new PhpDoc());
        $this->abstractString = new AbstractString($this);
        $this->setCfg(\array_merge($this->cfg, $cfg));
        self::$utility = $debug->utility;
    }

    /**
     * "crate" value for logging
     *
     * Conditionally calls getAbstraction
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
        if (!$typeInfo) {
            return $mixed;
        }
        return $typeInfo === array(self::TYPE_ARRAY, self::TYPE_RAW)
            ? $this->abstractArray->crate($mixed, $method, $hist)
            : $this->getAbstraction($mixed, $method, $typeInfo, $hist);
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
        /*
            Note: this->crateValues is the raw values passed to this method
               the values may end up being processed in Abstraction::onSet
               ie, converting attribs.class to an array
        */
        $this->crateVals = $values;
        $abs = $this->getAbstraction($mixed);
        foreach ($values as $k => $v) {
            $abs[$k] = $v;
        }
        $this->crateVals = array();
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
        $typeInfo = $typeInfo ?: $this->getType($mixed);
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
                return $this->abstractString->getAbstraction($mixed, $typeInfo[1], $this->crateVals);
            default:
                return new Abstraction($typeInfo[0], array(
                    'typeMore' => $typeInfo[1],
                    'value' => $mixed,
                ));
        }
    }

    /**
     * Returns value's type and "extended type" (ie "numeric", "binary", etc)
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
    public function getType($val)
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
                return $this->getTypeArray($val);
            case self::TYPE_BOOL:
                return array(self::TYPE_BOOL, \json_encode($val));
            case self::TYPE_OBJECT:
                return $this->getTypeObject($val);
            case self::TYPE_RESOURCE:
                return array(self::TYPE_RESOURCE, self::TYPE_RAW);
            case self::TYPE_STRING:
                return $this->abstractString->getType($val);
            case 'unknown type':
                return $this->getTypeUnknown($val);
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
        list($type, $typeMore) = $this->getType($val);
        if ($typeMore === self::TYPE_RAW) {
            return array($type, $typeMore);
        }
        if ($type === self::TYPE_STRING && \in_array($typeMore, array(null, self::TYPE_STRING_NUMERIC), true) === false) {
            return array($type, $typeMore);
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function setCfg($mixed, $val = null)
    {
        if ($mixed === 'stringMaxLen') {
            if (!\is_array($val)) {
                $val = array('other' => $val);
            }
            $val = \array_merge($this->cfg['stringMaxLen'], $val);
        } elseif ($mixed === 'stringMinLen') {
            $val = \array_merge($this->cfg['stringMinLen'], $val);
        } elseif (\is_array($mixed)) {
            if (isset($mixed['stringMaxLen'])) {
                if (!\is_array($mixed['stringMaxLen'])) {
                    $mixed['stringMaxLen'] = array('other' => $mixed['stringMaxLen']);
                }
                $mixed['stringMaxLen'] = \array_merge($this->cfg['stringMaxLen'], $mixed['stringMaxLen']);
            }
            if (isset($mixed['stringMixLen'])) {
                $mixed['stringMinLen'] = \array_merge($this->cfg['stringMinLen'], $mixed['stringMinLen']);
            }
        }
        return parent::setCfg($mixed, $val);
    }

    /**
     * Get Array's type & typeMore
     *
     * @param array $val array value
     *
     * @return array
     */
    private function getTypeArray($val)
    {
        $type = self::TYPE_ARRAY;
        $typeMore = self::TYPE_RAW;  // needs abstracted (references removed / values abstracted if necessary)
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
    private function getTypeObject($object)
    {
        $type = self::TYPE_OBJECT;
        $typeMore = self::TYPE_RAW;  // needs abstracted
        if ($object instanceof Abstraction) {
            $type = $object['type'];
            $typeMore = self::TYPE_ABSTRACTION;
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
    private function getTypeUnknown($val)
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
            $typeMore = self::TYPE_RAW;  // needs abstracted
        }
        return array($type, $typeMore);
    }

    /**
     * {@inheritDoc}
     */
    protected function postSetCfg($cfg = array())
    {
        $debugClass = \get_class($this->debug);
        // string settings vs object settings
        $strCfg = array(
            'stringMaxLen' => null,
            'stringMinLen' => null,
        );
        if (!\array_intersect(array('*', $debugClass), $this->cfg['objectsExclude'])) {
            $this->cfg['objectsExclude'][] = $debugClass;
            $cfg['objectsExclude'] = $this->cfg['objectsExclude'];
        }
        $objCfg = \array_diff_key($cfg, $strCfg);
        if ($objCfg) {
            $this->abstractObject->setCfg($objCfg);
        }
        $strCfg = \array_intersect_key($cfg, $strCfg);
        if ($strCfg) {
            $this->abstractString->setCfg($strCfg);
        }
    }
}
