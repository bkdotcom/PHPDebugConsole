<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Abstraction;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Utility\Php;

/**
 * Determine value type / extended type
 */
class Type
{
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
                                //   deprecated (use TYPE_IDENTIFIER)
    const TYPE_IDENTIFIER = 'identifier'; // const, className, method, property
                                            // value: identifier
                                            // backedValue: underlying const or property value
                                            // typeMore: 'const', 'className', 'method', 'property'
    const TYPE_NOT_INSPECTED = 'notInspected'; // non-native type
    const TYPE_RECURSION = 'recursion'; // non-native type
    const TYPE_UNDEFINED = 'undefined'; // non-native type
    const TYPE_UNKNOWN = 'unknown'; // non-native type

    /*
        "typeMore" values
        (no constants for "true" & "false")
    */
    const TYPE_ABSTRACTION = 'abstraction';
    const TYPE_RAW = 'raw'; // raw object or array
    const TYPE_FLOAT_INF = "\x00inf\x00";
    const TYPE_FLOAT_NAN = "\x00nan\x00";
    const TYPE_IDENTIFIER_CLASSNAME = 'className';
    const TYPE_IDENTIFIER_CONST = 'const';
    const TYPE_IDENTIFIER_METHOD = 'method';
    const TYPE_STRING_BASE64 = 'base64';            // "encoded" / auto-detected
    const TYPE_STRING_BINARY = 'binary';            // string that contains non-utf8
    const TYPE_STRING_CLASSNAME = 'classname';      // deprecated (use TYPE_IDENTIFIER)
    const TYPE_STRING_FORM = 'form';                // "encoded" / NOT auto-detected
    const TYPE_STRING_JSON = 'json';                // "encoded" / auto-detected
    const TYPE_STRING_LONG = 'maxLen';
    const TYPE_STRING_NUMERIC = 'numeric';
    const TYPE_STRING_SERIALIZED = 'serialized';    // encoded / auto-detected
    const TYPE_TIMESTAMP = 'timestamp';

    protected $abstracter;

    /**
     * Constructor
     *
     * @param Abstracter $abstracter Abstracter instance
     */
    public function __construct(Abstracter $abstracter)
    {
        $this->abstracter = $abstracter;
    }

    /**
     * Returns value's type and "extended type" (ie "numeric", "binary", etc)
     *
     * @param mixed $val value
     *
     * @return list{self::TYPE_*,self::TYPE_*|null} [$type, $typeMore] typeMore may be
     *    null
     *    'raw' indicates value needs crating
     *    'abstraction'
     *    'true'  (type bool)
     *    'false' (type bool)
     *    'numeric' (type string)
     */
    public function getType($val)
    {
        $type = self::getTypePhp($val);
        switch ($type) {
            case self::TYPE_ARRAY:
                return $this->getTypeArray($val);
            case self::TYPE_BOOL:
                return [self::TYPE_BOOL, \json_encode($val)];
            case self::TYPE_FLOAT:
                return $this->getTypeFloat($val);
            case self::TYPE_INT:
                return $this->getTypeInt($val);
            case self::TYPE_OBJECT:
                return $this->getTypeObject($val);
            case self::TYPE_RESOURCE:
                return [self::TYPE_RESOURCE, self::TYPE_RAW];
            case self::TYPE_STRING:
                return $this->abstracter->abstractString->getType($val);
            default:
                return [$type, null];
        }
    }

    /**
     * Does value appear to be a unix timestamp?
     *
     * @param int|string|float $val Value to test
     *
     * @return bool
     */
    public function isTimestamp($val)
    {
        $secs = 86400 * 90; // 90 days worth o seconds
        $tsNow = \time();
        return \is_numeric($val) && $val > $tsNow - $secs && $val < $tsNow + $secs;
    }

    /**
     * Get Array's type & typeMore
     *
     * @param array $val array value
     *
     * @return list{self::TYPE_*,self::TYPE_RAW}
     */
    private function getTypeArray($val)
    {
        $type = self::TYPE_ARRAY;
        $typeMore = self::TYPE_RAW;  // needs abstracted (references removed / values abstracted if necessary)
        if (\count($val) === 2 && $this->abstracter->debug->php->isCallable($val, Php::IS_CALLABLE_ARRAY_ONLY)) {
            $type = self::TYPE_CALLABLE;
        }
        return [$type, $typeMore];
    }

    /**
     * Get Float's type & typeMore
     *
     * INF and NAN are considered "float"
     *
     * @param float $val float/INF/NAN
     *
     * @return list{self::TYPE_FLOAT,self::TYPE_*}
     */
    private function getTypeFloat($val)
    {
        $typeMore = null;
        if ($val === INF) {
            $typeMore = self::TYPE_FLOAT_INF;
        } elseif (\is_nan($val)) {
            // using is_nan() func as comparing with NAN constant doesn't work
            $typeMore = self::TYPE_FLOAT_NAN;
        } elseif ($this->isTimestamp($val)) {
            $typeMore = self::TYPE_TIMESTAMP;
        }
        return [self::TYPE_FLOAT, $typeMore];
    }

    /**
     * Get Int's type & typeMore
     *
     * INF and NAN are considered "float"
     *
     * @param float $val float/INF/NAN
     *
     * @return list{self::TYPE_INT,self::TYPE_TIMESTAMP|null}
     */
    private function getTypeInt($val)
    {
        $typeMore = $this->isTimestamp($val)
            ? self::TYPE_TIMESTAMP
            : null;
        return [self::TYPE_INT, $typeMore];
    }

    /**
     * Get Object's type & typeMore
     *
     * @param object $object any object
     *
     * @return list{self::TYPE_*,self::TYPE_*} type & typeMore
     */
    private function getTypeObject($object)
    {
        $type = self::TYPE_OBJECT;
        $typeMore = self::TYPE_RAW;  // needs abstracted
        if ($object instanceof Abstraction) {
            $type = $object['type'];
            $typeMore = $object['typeMore'];
        }
        return [$type, $typeMore];
    }

    /**
     * Get PHP type
     *
     * @param mixed $val value
     *
     * @return "array"|"bool"|"float"|"int"|"null"|"object"|"resource"|"string"|"unknown"
     */
    private static function getTypePhp($val)
    {
        $type = \gettype($val);
        $map = array(
            'boolean' => self::TYPE_BOOL,
            'double' => self::TYPE_FLOAT,
            'integer' => self::TYPE_INT,
            'NULL' => self::TYPE_NULL,
            'resource (closed)' => self::TYPE_RESOURCE,
            'unknown type' => self::TYPE_UNKNOWN,  // closed resource (php < 7.2)
        );
        if (isset($map[$type])) {
            $type = $map[$type];
        }
        if ($type === self::TYPE_UNKNOWN && \strpos(\print_r($val, true), 'Resource') === 0) {
            /*
            closed resource?
            is_resource() returns false for a closed resource
            gettype() returns 'unknown type' or 'resource (closed)'
            */
            $type = self::TYPE_RESOURCE;
        }
        return $type;
    }
}
