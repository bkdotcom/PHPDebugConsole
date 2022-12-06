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

namespace bdk\Debug\Utility;

use Exception;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Reflector;
use UnitEnum;

/**
 * Php language utilities
 */
class Php
{
    const IS_CALLABLE_ARRAY_ONLY = 1;
    const IS_CALLABLE_OBJ_ONLY = 2;
    const IS_CALLABLE_SYNTAX_ONLY = 4;
    const IS_CALLABLE_NO_CALL = 8; // don't test for __call / __callStatic methods

    public static $allowedClasses = array();

    /**
     * Get friendly classname for given classname or object
     * This is primarily useful for anonymous classes
     *
     * @param object|class-string $mixed Reflector instance, object, or classname
     *
     * @return string
     */
    public static function friendlyClassName($mixed)
    {
        $reflector = static::getReflector($mixed, true);
        if ($reflector && \method_exists($reflector, 'getDeclaringClass')) {
            $reflector = $reflector->getDeclaringClass();
        }
        if (PHP_VERSION_ID < 70000 || $reflector->isAnonymous() === false) {
            return $reflector->getName();
        }
        // anonymous class
        $parentClassRef = $reflector->getParentClass();
        $extends = $parentClassRef
            ? $parentClassRef->getName()
            : null;
        return ($extends ?: \current($reflector->getInterfaceNames()) ?: 'class') . '@anonymous';
    }

    /**
     * returns required/included files sorted by directory
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        $includedFiles = \get_included_files();
        \usort($includedFiles, static function ($valA, $valB) {
            $valA = \str_replace('_', '0', $valA);
            $valB = \str_replace('_', '0', $valB);
            $dirA = \dirname($valA);
            $dirB = \dirname($valB);
            return $dirA === $dirB
                ? \strnatcasecmp($valA, $valB)
                : \strnatcasecmp($dirA, $dirB);
        });
        return $includedFiles;
    }

    /**
     * Get Reflector for given value
     *
     * Accepts:
     *   * object
     *   * Reflector
     *   * string  class
     *   * string  class::method()
     *   * string  class::$property
     *   * string  class::CONSTANT
     *
     * @param object|string $mixed      object, or string
     * @param bool          $returnSelf (false) if passed obj is a Reflector, return it
     *
     * @return Reflector|null
     */
    public static function getReflector($mixed, $returnSelf = false)
    {
        if ($mixed instanceof Reflector && $returnSelf) {
            return $mixed;
        }
        if ($mixed instanceof UnitEnum) {
            return new ReflectionEnum($mixed);
        }
        if (\is_object($mixed)) {
            return new ReflectionObject($mixed);
        }
        if (\is_string($mixed)) {
            return static::getReflectorFromString($mixed);
        }
        return null;
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
     *                             must be array(x, 'method')
     *                             (does not apply for Closure and invokable obj)
     *                         IS_CALLABLE_OBJ_ONLY
     *                             if array, first value must be object
     *                             (does not apply for Closure and invokable obj)
     *                         IS_CALLABLE_SYNTAX_ONLY
     *                             strict by default... set to flag for syntax only
     *                             (non-namespaced strings will always be strict)
     *                         IS_CALLABLE_NO_CALL
     *                             don't test for __call / __callStatic method
     *
     * @return bool
     */
    public static function isCallable($val, $opts = 0)
    {
        if (\is_object($val)) {
            // test if Closure or obj with __invoke
            return \is_callable($val);
        }
        if (\is_array($val)) {
            return self::isCallableArray($val, $opts);
        }
        if ($opts & self::IS_CALLABLE_ARRAY_ONLY) {
            return false;
        }
        $syntaxOnly = \is_string($val) && \preg_match('/(::|\\\)/', $val) !== 1
            ? false // string without namespace: do a full check
            : ($opts & self::IS_CALLABLE_SYNTAX_ONLY) === self::IS_CALLABLE_SYNTAX_ONLY;
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
     * Determine PHP's MemoryLimit
     *
     * @return string
     */
    public static function memoryLimit()
    {
        $iniVal = \trim(\ini_get('memory_limit') ?: \get_cfg_var('memory_limit'));
        return $iniVal ?: '128M';
    }

    /**
     * Unserialize while only allowing the specified classes to be unserialized
     *
     * stdClass will always be allowed
     *
     * Gracefully handle unsafe classes implementing Serializable
     *
     * @param string        $serialized     serialized string
     * @param string[]|bool $allowedClasses allowed class names
     *
     * @return mixed
     */
    public static function unserializeSafe($serialized, $allowedClasses = array())
    {
        if ($allowedClasses === true) {
            return \unserialize($serialized);
        }
        if ($allowedClasses === false) {
            $allowedClasses = array();
        }
        $allowedClasses[] = 'stdClass';
        self::$allowedClasses = \array_unique($allowedClasses);
        $hasSerializable = \preg_match('/(^|;)C:(\d+):"([\w\\\\]+)":(\d+):\{/', $serialized) === 1;
        if ($hasSerializable === false && PHP_VERSION_ID >= 70000) {
            return \unserialize($serialized, array(
                'allowed_classes' => self::$allowedClasses,
            ));
        }
        $serialized = self::unserializeSafeModify($serialized);
        return \unserialize($serialized);
    }

    /**
     * String to Reflector
     *
     * Accepts:
     *   * 'class'              ReflectionClass
     *   * 'class::method()'    ReflectionMethod
     *   * 'class::$property'   ReflectionProperty
     *   * 'class::CONSTANT'    ReflectionClassConstant (if Php >= 7.1)
     *   * 'enum::CASE'         ReflectionEnumUnitCase
     *
     * @param string $string string representing class, method, property, or class constant
     *
     * @return Reflector|null
     */
    private static function getReflectorFromString($string)
    {
        $regex = '/^'
            . '(?P<class>[\w\\\]+)' // classname
            . '(?:::(?:'
                . '(?P<constant>\w+)|'       // constant
                . '(?:\$(?P<property>\w+))|' // property
                . '(?:(?P<method>\w+)\(\))|' // method
            . '))?'
            . '$/';
        $matches = array();
        \preg_match($regex, $string, $matches);
        $defaults = \array_fill_keys(array('class','constant','property','method'), null);
        $matches = \array_merge($defaults, $matches);
        if ($matches['method']) {
            return new ReflectionMethod($matches['class'], $matches['method']);
        }
        if ($matches['property']) {
            return new ReflectionProperty($matches['class'], $matches['property']);
        }
        if ($matches['constant'] && PHP_VERSION_ID >= 80100 && \enum_exists($matches['class'])) {
            return (new ReflectionEnum($matches['class']))->getCase($matches['constant']);
        }
        if ($matches['constant'] && PHP_VERSION_ID >= 70100) {
            return new ReflectionClassConstant($matches['class'], $matches['constant']);
        }
        if ($matches['class']) {
            return new ReflectionClass($matches['class']);
        }
        return null;
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
        if ($opts & self::IS_CALLABLE_OBJ_ONLY) {
            return false;
        }
        return self::isCallableArrayString($val, $opts);
    }

    /**
     * Test if array(obj, 'method') is callable
     *
     * @param array $val  array to test
     * @param int   $opts bitmask of IS_CALLABLE_x constants
     *
     * @return bool
     */
    private static function isCallableArrayObj(array $val, $opts)
    {
        if ($opts & self::IS_CALLABLE_SYNTAX_ONLY) {
            return true;
        }
        if (\method_exists($val[0], $val[1])) {
            return true;
        }
        return $opts & self::IS_CALLABLE_NO_CALL
            ? false
            : \method_exists($val[0], '__call');
    }

    /**
     * Test if array('string', 'method') is callable
     *
     * @param array $val  array to test
     * @param int   $opts bitmask of IS_CALLABLE_x constants
     *
     * @return bool
     */
    private static function isCallableArrayString(array $val, $opts)
    {
        if ($opts & self::IS_CALLABLE_SYNTAX_ONLY) {
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
        return $opts & self::IS_CALLABLE_NO_CALL
            ? false
            : \method_exists($val[0], '__callStatic');
    }

    /**
     * Modify serialized string to remove classes that are not explicitly allowed
     *
     * @param string $serialized Output from `serialize()`
     *
     * @return string
     */
    private static function unserializeSafeModify($serialized)
    {
        $matches = array();
        $offset = 0;
        $regex = '/(^|;)([OC]):(\d+):"([\w\\\\]+)":(\d+):\{/';
        $regexKeys = array('full', 'prefix', 'type', 'strlen', 'classname', 'length');
        $serializedNew = '';
        while (\preg_match($regex, $serialized, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offsets = array();
            foreach ($regexKeys as $i => $key) {
                $matches[$key] = $matches[$i][0];
                $offsets[$key] = $matches[$i][1];
            }
            // only applicable to 'C' type
            $matches['data'] = $matches['type'] === 'C'
                ? \substr(
                    $serialized,
                    $offsets['length'] + \strlen($matches['length']) + 2, // length + xx + :{
                    $matches['length']
                )
                : null;
            $serializedNew .= \substr($serialized, $offset, $offsets['full'] - $offset);
            $serializedNew .= self::unserializeSafeModifyMatch($matches, $offsets, $offset);
        }
        return $serializedNew . \substr($serialized, $offset);
    }

    /**
     * Update object serialization
     *
     * @param array $matches match strings
     * @param array $offsets match offsets
     * @param int   $offset  Updated with new string offset
     *
     * @return string
     */
    private static function unserializeSafeModifyMatch($matches, $offsets, &$offset)
    {
        $offset = $offsets['full'] + \strlen($matches['full']);
        if (\strlen($matches['classname']) !== (int) $matches['strlen'] || \in_array($matches['classname'], self::$allowedClasses, true)) {
            return $matches['full'];
        }
        if ($matches['type'] === 'O') {
            return $matches['prefix']
                . 'O:22:"__PHP_Incomplete_Class":'
                . ($matches['length'] + 1)
                . ':{s:27:"__PHP_Incomplete_Class_Name";' . \serialize($matches['classname']);
        }
        // Object was serialized via Serializable interface
        $offset += $matches['length'] + 1;
        return $matches['prefix']
            . 'O:22:"__PHP_Incomplete_Class":'
            . \substr(\serialize((object) array(
                '__PHP_Incomplete_Class_Name' => $matches['classname'],
                '__serialized_data' => $matches['data'],
            )), \strlen('O:8:"stdClass":'));
    }
}
