<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.5
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Php;
use Closure;
use Exception;
use InvalidArgumentException;
use UnitEnum;

/**
 * Php type utilities
 */
class PhpType
{
    /**
     * Assert that a value is of specified type
     *
     * PHPDebugConsole supports an extreme range of PHP versions : 5.4 - 8.4 (and beyond)
     * `func(MyObj $obj = null)` has been deprecated in PHP 8.4
     * must now be `func(?MyObj $obj = null)` (which is a php 7.1 feature)
     * Workaround - remove type-hint when we allow null (not ideal) and call assertType
     * When we drop support for php < 7.1, we can remove this method and do proper type-hinting
     *
     * @param mixed  $value       Value to test
     * @param string $type        Php type (or class name) to check
     * @param string $paramName   (optional) parameter name
     * @param int    $frameOffset {@internal}
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function assertType($value, $type, $paramName = null, $frameOffset = 0)
    {
        if (self::assertTypeCheck($value, $type)) {
            return;
        }
        $frameIndex = $frameOffset + 1;
        $frame = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, $frameIndex + 1)[$frameIndex];
        $msg = $paramName
            ? 'exception.method-expects-param'
            : 'exception.method-expects';
        throw new InvalidArgumentException(\bdk\Debug\Utility::trans($msg, array(
            'actual' => self::getDebugType($value),
            'expect' => $type,
            'method' => isset($frame['class'])
                ? $frame['class'] . '::' . $frame['function'] . '()'
                : $frame['function'] . '()',
            'param' => '$' . $paramName,
        )));
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * like php's `get_debug_type`, but will return
     *  - 'callable' for callable array
     *  - enumClass::name for enums (unless Php::ENUM_AS_OBJECT flag passed)
     *
     * @param mixed $val      The value being type checked
     * @param int   $opts     Bitmask of ENUM_AS_OBJECT flag
     * @param bool  $isObject Set to true if value is an object (but not Closure or UnitEnum)
     *
     * @return string
     *
     * @see https://github.com/symfony/polyfill/blob/main/src/Php80/Php80.php
     */
    public static function getDebugType($val, $opts = 0, &$isObject = false)
    {
        $isObject = false;
        $type = \strtr(\strtolower(\gettype($val)), array(
            'boolean' => 'bool',
            'double' => 'float',
            'integer' => 'int',
        ));
        if (PHP_VERSION_ID >= 80000 && \in_array($type, ['array', 'object'], true) === false) {
            // simply use php 8's get_debug_type() for non-array/object
            return \get_debug_type($val);
        }
        switch (true) {
            case \in_array($type, ['bool', 'float', 'int', 'null', 'string'], true):
                return $type;
            case $type === 'array':
                return self::isCallable($val, $opts)
                    ? 'callable'
                    : 'array';
            case $val instanceof \__PHP_Incomplete_Class:
                return '__PHP_Incomplete_Class';
            case $type === 'object':
                return self::getDebugTypeObject($val, $opts, $isObject);
            default:
                return self::getDebugTypeResource($val);
        }
    }

    /**
     * Get friendly class name
     *
     * @param object|class-string $obj      Object to inspect
     * @param int                 $opts     Bitmask of ENUM_AS_OBJECT flag
     * @param bool                $isObject Whether the value is an object
     *                                         Closure: false
     *                                         UnitEnum: true/false depending on  Php::ENUM_AS_OBJECT flag passed
     *                                         other objects: true
     *
     * @return string
     */
    public static function getDebugTypeObject($obj, $opts = 0, &$isObject = false)
    {
        $isObject = false;
        $enumAsObject = ($opts & Php::ENUM_AS_OBJECT) === Php::ENUM_AS_OBJECT;
        if ($obj instanceof UnitEnum && !$enumAsObject) {
            return \get_class($obj) . '::' . $obj->name;
        }
        $isObject = $obj instanceof Closure === false;
        $class = \is_object($obj)
            ? \get_class($obj)
            : $obj;
        if (\strpos($class, '@') === false) {
            return $class;
        }
        $class = \get_parent_class($class) ?: \key(\class_implements($class)) ?: 'class';
        return $class . '@anonymous';
    }

    /**
     * Test if value is "callable"
     *
     * Like php's is_callable but
     *   * more options
     *   * stricter syntaxOnly option (test valid string labels)
     *   * does not test against current context
     *   * does not trigger autoloader
     *
     * @param string|array $val  value to check
     * @param int          $opts bitmask of IS_CALLABLE_x constants
     *                         IS_CALLABLE_ARRAY_ONLY
     *                             must be `[x, 'method']`
     *                             (does not apply for Closure and invokable obj)
     *                         IS_CALLABLE_OBJ_ONLY
     *                             if array, first value must be object
     *                             (does not apply for Closure and invokable obj)
     *                         IS_CALLABLE_SYNTAX_ONLY
     *                             non-namespaced strings will ignore this flag and do full check
     *                         IS_CALLABLE_NO_CALL
     *                             don't test for __call / __callStatic method
     *
     * @return bool
     */
    public static function isCallable($val, $opts = 0)
    {
        if (\is_object($val)) {
            // test if Closure or obj with __invoke
            return \is_callable($val, false);
        }
        if (\is_array($val)) {
            return self::isCallableArray($val, $opts);
        }
        if ($opts & Php::IS_CALLABLE_ARRAY_ONLY) {
            return false;
        }
        $syntaxOnly = \is_string($val) && \preg_match('/(::|\\\)/', $val) !== 1
            ? false // string without namespace: do a full check
            : ($opts & Php::IS_CALLABLE_SYNTAX_ONLY) === Php::IS_CALLABLE_SYNTAX_ONLY;
        return \is_callable($val, $syntaxOnly);
    }

    /**
     * Throwable is a PHP 7+ thing
     *
     * @param mixed $val Value to test
     *
     * @return bool
     */
    public static function isThrowable($val)
    {
        return $val instanceof \Error || $val instanceof Exception;
    }

    /**
     * Test if value is of a certain type
     *
     * @param mixed  $value Value to test
     * @param string $type  php type(s) to check
     *
     * @return bool
     */
    private static function assertTypeCheck($value, $type)
    {
        foreach (\explode('|', $type) as $type) {
            $isType = self::isType($value, $type);
            if ($isType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get resource type
     *
     * This method is only used for php < 8.0
     *
     * @param mixed $val Resource
     *
     * @return string
     */
    private static function getDebugTypeResource($val)
    {
        // @phpcs:ignore Squiz.WhiteSpace.ScopeClosingBrace
        \set_error_handler(static function () {});
        $type = \get_resource_type($val);
        \restore_error_handler();

        if ($type === null) {
            // closed resource (php < 7.2)
            $type = 'closed';
        } elseif ($type === 'Unknown') {
            $type = 'closed';
        }

        return 'resource (' . $type . ')';
    }

    /**
     * Test if array is a callable
     *
     * We will ignore current context
     *
     * @param array $val  array to test
     * @param int   $opts bitmask of IS_CALLABLE_x constants
     *
     * @return bool
     */
    private static function isCallableArray(array $val, $opts)
    {
        if (\is_callable($val, true) === false) {
            return false;
        }
        $regexLabel = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';
        if (\preg_match($regexLabel, $val[1]) !== 1) {
            return false;
        }
        if (\is_object($val[0])) {
            return self::isCallableArrayObj($val, $opts);
        }
        return $opts & Php::IS_CALLABLE_OBJ_ONLY
            ? false
            : self::isCallableArrayString($val, $opts);
    }

    /**
     * Test if `[obj, 'method']` is callable
     *
     * @param array $val  array to test
     * @param int   $opts bitmask of IS_CALLABLE_x constants
     *
     * @return bool
     */
    private static function isCallableArrayObj(array $val, $opts)
    {
        if ($opts & Php::IS_CALLABLE_SYNTAX_ONLY) {
            return true;
        }
        if (\method_exists($val[0], $val[1])) {
            return true;
        }
        return $opts & Php::IS_CALLABLE_NO_CALL
            ? false
            : \method_exists($val[0], '__call');
    }

    /**
     * Test if `['string', 'method']` is callable
     *
     * @param array $val  array to test
     * @param int   $opts bitmask of IS_CALLABLE_x constants
     *
     * @return bool
     */
    private static function isCallableArrayString(array $val, $opts)
    {
        if ($opts & Php::IS_CALLABLE_SYNTAX_ONLY) {
            // is_callable syntaxOnly only tested if 1st val is obj or string
            //    we'll test that string is a valid label
            $regexClass = '/^(\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)+$/';
            return \preg_match($regexClass, $val[0]) === 1;
        }
        if (\class_exists($val[0], false) === false) {
            // test if class exists before calling method_exists to avoid autoload attempt
            return false;
        }
        if (\method_exists($val[0], $val[1])) {
            return true;
        }
        return $opts & Php::IS_CALLABLE_NO_CALL
            ? false
            : \method_exists($val[0], '__callStatic');
    }

    /**
     * Test if value is of a certain type
     *
     * @param mixed  $value Value to test
     * @param string $type  php type to check
     *
     * @return bool
     */
    private static function isType($value, $type)
    {
        $simpleTypes = ['array', 'bool', 'callable', 'float', 'int', 'null', 'numeric', 'object', 'string'];
        $method = 'is_' . $type;
        $isType = \in_array($type, $simpleTypes, true)
            ? $method($value)
            : \is_a($value, $type);
        if ($isType) {
            return true;
        }
        // Test stringable for php < 8.0
        return $type === 'Stringable' && \is_object($value) && \method_exists($value, '__toString');
    }
}
