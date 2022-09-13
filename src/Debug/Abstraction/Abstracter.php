<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\AbstractArray;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Utility\Php;

/**
 * Store array/object/resource info
 */
class Abstracter extends AbstractComponent
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
        (no constants for "true" & "false")
    */
    const TYPE_ABSTRACTION = 'abstraction';
    const TYPE_RAW = 'raw'; // raw object or array
    const TYPE_FLOAT_INF = "\x00inf\x00";
    const TYPE_FLOAT_NAN = "\x00nan\x00";
    const TYPE_STRING_BASE64 = 'base64';
    const TYPE_STRING_BINARY = 'binary';
    const TYPE_STRING_CLASSNAME = 'classname';
    const TYPE_STRING_JSON = 'json';
    const TYPE_STRING_LONG = 'maxLen';
    const TYPE_STRING_NUMERIC = 'numeric';
    const TYPE_STRING_SERIALIZED = 'serialized';
    const TYPE_TIMESTAMP = 'timestamp';

    public $debug;
    protected $abstractArray;
    protected $abstractObject;
    protected $abstractString;
    protected $cfg = array(
        'brief' => false, // collect & output less details
        'fullyQualifyPhpDocType' => false,
        'methodCache' => true,
        'objectsExclude' => array(
            // __NAMESPACE__ added in constructor
            'DOMNode',
        ),
        'objectSort' => 'visibility',   // none, visibility, or name
        'objectsWhitelist' => null,     // will be used if array
        'stringMaxLen' => array(
            'base64' => 156, // 2 lines of chunk_split'ed
            'binary' => array(
                128 => 0, // if over 128 bytes don't capture / store
            ),
            'other' => 8192,
        ),
        'stringMinLen' => array(
            'contentType' => 256, // try to determine content-type of binary string
            'encoded' => 16, // test if base64, json, or serialized (-1 = don't check)
        ),
        'useDebugInfo' => true,
    );
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
        $this->cfg['objectsExclude'][] = __NAMESPACE__;
        $this->abstractArray = new AbstractArray($this);
        $this->abstractObject = new AbstractObject($this);
        $this->abstractString = new AbstractString($this);
        $this->cfg = \array_merge(
            $this->cfg,
            \array_fill_keys(
                \array_keys(AbstractObject::$cfgFlags),
                true
            ),
            array(
                'brief' => false,
            )
        );
        $this->setCfg(\array_merge($this->cfg, $cfg));
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
     * @param mixed  $val      value to "abstract"
     * @param string $method   Method requesting abstraction
     * @param array  $typeInfo (@internal) array specifying value's type & "typeMore"
     * @param array  $hist     (@internal) array/object history (used to test for recursion)
     *
     * @return Abstraction
     *
     * @internal
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    public function getAbstraction($val, $method = null, $typeInfo = array(), $hist = array())
    {
        list($type, $typeMore) = $typeInfo ?: $this->getType($val);
        switch ($type) {
            case self::TYPE_ARRAY:
                return $this->abstractArray->getAbstraction($val, $method, $hist);
            case self::TYPE_CALLABLE:
                return $this->abstractArray->getCallableAbstraction($val);
            case self::TYPE_FLOAT:
                return $this->getAbstractionFloat($val, $typeMore);
            case self::TYPE_OBJECT:
                return $val instanceof \SensitiveParameterValue
                    ? $this->abstractString->getAbstraction(\call_user_func($this->debug->pluginRedaction->getCfg('redactReplace'), 'redacted'))
                    : $this->abstractObject->getAbstraction($val, $method, $hist);
            case self::TYPE_RESOURCE:
                return new Abstraction($type, array(
                    'value' => \print_r($val, true) . ': ' . \get_resource_type($val),
                ));
            case self::TYPE_STRING:
                return $this->abstractString->getAbstraction($val, $typeMore, $this->crateVals);
            default:
                return new Abstraction($type, array(
                    'typeMore' => $typeMore,
                    'value' => $val,
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
        $type = $this->getTypePhp($val);
        switch ($type) {
            case self::TYPE_ARRAY:
                return $this->getTypeArray($val);
            case self::TYPE_BOOL:
                return array(self::TYPE_BOOL, \json_encode($val));
            case self::TYPE_FLOAT:
                return $this->getTypeFloat($val);
            case self::TYPE_INT:
                return $this->getTypeInt($val);
            case self::TYPE_OBJECT:
                return $this->getTypeObject($val);
            case self::TYPE_RESOURCE:
                return array(self::TYPE_RESOURCE, self::TYPE_RAW);
            case self::TYPE_STRING:
                return $this->abstractString->getType($val);
            case self::TYPE_UNKNOWN:
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
        if ($type === self::TYPE_BOOL) {
            return false;
        }
        if (\in_array($typeMore, array(self::TYPE_ABSTRACTION, self::TYPE_STRING_NUMERIC), true)) {
            return false;
        }
        return $typeMore
            ? array($type, $typeMore)
            : false;
    }

    /**
     * Does value appear to be a unix timestamp?
     *
     * @param int|string|float $val Value to test
     *
     * @return bool
     */
    public function testTimestamp($val)
    {
        $secs = 86400 * 90; // 90 days worth o seconds
        $tsNow = \time();
        return $val > $tsNow - $secs && $val < $tsNow + $secs;
    }

    /**
     * Abstract a float
     *
     * This is done to avoid having NAN & INF values.. which can't be json encoded
     *
     * @param float       $val      float value
     * @param string|null $typeMore (optional) TYPE_FLOAT_INF or TYPE_FLOAT_NAN
     *
     * @return Abstraction
     */
    private function getAbstractionFloat($val, $typeMore)
    {
        if ($typeMore === self::TYPE_FLOAT_INF) {
            $val = self::TYPE_FLOAT_INF;
        } elseif ($typeMore === self::TYPE_FLOAT_NAN) {
            $val = self::TYPE_FLOAT_NAN;
        }
        return new Abstraction(self::TYPE_FLOAT, array(
            'typeMore' => $typeMore,
            'value' => $val,
        ));
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
        if (\count($val) === 2 && $this->debug->php->isCallable($val, Php::IS_CALLABLE_OBJ_ONLY)) {
            $type = self::TYPE_CALLABLE;
        }
        return array($type, $typeMore);
    }

    /**
     * Get Float's type & typeMore
     *
     * INF and NAN are considered "float"
     *
     * @param float $val float/INF/NAN
     *
     * @return array
     */
    private function getTypeFloat($val)
    {
        $typeMore = null;
        if ($val === INF) {
            $typeMore = self::TYPE_FLOAT_INF;
        } elseif (\is_nan($val)) {
            // using is_nan() func as comparing with NAN constant doesn't work
            $typeMore = self::TYPE_FLOAT_NAN;
        } elseif ($this->testTimestamp($val)) {
            $typeMore = self::TYPE_TIMESTAMP;
        }
        return array(self::TYPE_FLOAT, $typeMore);
    }

    /**
     * Get Int's type & typeMore
     *
     * INF and NAN are considered "float"
     *
     * @param float $val float/INF/NAN
     *
     * @return array
     */
    private function getTypeInt($val)
    {
        $typeMore = $this->testTimestamp($val)
            ? self::TYPE_TIMESTAMP
            : null;
        return array(self::TYPE_INT, $typeMore);
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
     * Get PHP type
     *
     * @param mixed` $val value
     *
     * @return string "array", "bool", "float","int","null","object","resource","string","unknown"
     */
    private function getTypePhp($val)
    {
        $type = \gettype($val);
        $map = array(
            'boolean' => self::TYPE_BOOL,
            'double' => self::TYPE_FLOAT,
            'integer' => self::TYPE_INT,
            'NULL' => self::TYPE_NULL,
            'resource (closed)' => self::TYPE_RESOURCE,
            'unknown type' => self::TYPE_UNKNOWN,  // closed resource < php 7.2
        );
        if (isset($map[$type])) {
            $type = $map[$type];
        }
        return $type;
    }

    /**
     * Get "unknown" type & typeMore
     *
     * @param mixed $val value of unknown type (likely closed resource)
     *
     * @return array type and typeMore
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
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
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        $debugClass = \get_class($this->debug);
        if (!\array_intersect(array('*', $debugClass), $this->cfg['objectsExclude'])) {
            $this->cfg['objectsExclude'][] = $debugClass;
            $cfg['objectsExclude'] = $this->cfg['objectsExclude'];
        }
        if (isset($cfg['stringMaxLen'])) {
            if (\is_array($cfg['stringMaxLen']) === false) {
                $cfg['stringMaxLen'] = array(
                    'other' => $cfg['stringMaxLen'],
                );
            }
            $cfg['stringMaxLen'] = \array_merge($prev['stringMaxLen'], $cfg['stringMaxLen']);
            $this->cfg['stringMaxLen'] = $cfg['stringMaxLen'];
        }
        if (isset($cfg['stringMinLen'])) {
            $cfg['stringMinLen'] = \array_merge($prev['stringMinLen'], $cfg['stringMinLen']);
            $this->cfg['stringMinLen'] = $cfg['stringMinLen'];
        }
        $this->setCfgDependencies($cfg);
    }

    /**
     * Pass relevent config updates to AbstractObject & AbstractString
     *
     * @param array $cfg Updated config values
     *
     * @return void
     */
    private function setCfgDependencies($cfg)
    {
        $keysAll = array(
            'brief',
        );
        $keysStr = array(
            'stringMaxLen',
            'stringMinLen',
        );
        $strCfg = \array_intersect_key($cfg, \array_flip($keysAll) + \array_flip($keysStr));
        if ($strCfg) {
            $this->abstractString->setCfg($strCfg);
        }
        $objCfg = \array_diff_key($cfg, \array_flip($keysStr));
        if ($objCfg) {
            $this->abstractObject->setCfg($objCfg);
        }
    }
}
