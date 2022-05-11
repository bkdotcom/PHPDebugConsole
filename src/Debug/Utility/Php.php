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
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Reflector;

/**
 * Php language utilities
 */
class Php
{
    const IS_CALLABLE_ARRAY_ONLY = 1;
    const IS_CALLABLE_OBJ_ONLY = 2;
    const IS_CALLABLE_SYNTAX_ONLY = 4;

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
        $extends = $parentClassRef ? $parentClassRef->getName() : null;
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
        \usort($includedFiles, function ($valA, $valB) {
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
        if (\is_object($mixed)) {
            return new ReflectionObject($mixed);
        }
        if (\is_string($mixed)) {
            return static::getReflectorFromString($mixed);
        }
        return null;
    }

    /**
     * Test if value is callable
     *
     * @param string|array $val  value to check
     * @param int          $opts bitmask of IS_CALLABLE_x constants
     *                         default:  IS_CALLABLE_ARRAY_ONLY | IS_CALLABLE_OBJ_ONLY
     *                         IS_CALLABLE_ARRAY_ONLY
     *                             must be array(x, 'method')
     *                             (does not apply for Closure and invokable obj)
     *                         IS_CALLABLE_OBJ_ONLY
     *                             must be array(obj, 'methodName')
     *                             (does not apply for Closure and invokable obj)
     *                         IS_CALLABLE_SYNTAX_ONLY
     *                             strict by default... set to flag for syntax only
     *                             (non-namespaced strings will always be strict)
     *
     * @return bool
     */
    public static function isCallable($val, $opts = 0b011)
    {
        if (\is_object($val) && \method_exists($val, '__invoke')) {
            // Closure && method with __invoke
            return true;
        }
        $syntaxOnly = \is_string($val) && !\preg_match('/(::|\\\)/', $val)
            ? false // string without namespace do a full check
            : ($opts & self::IS_CALLABLE_SYNTAX_ONLY) === self::IS_CALLABLE_SYNTAX_ONLY;
        if (\is_array($val) === false) {
            return $opts & self::IS_CALLABLE_ARRAY_ONLY
                ? false
                : \is_callable($val, $syntaxOnly);
        }
        if (!isset($val[0])) {
            return false;
        }
        if ($opts & self::IS_CALLABLE_OBJ_ONLY && \is_object($val[0]) === false) {
            return false;
        }
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
     *   * 'class'
     *   * 'class::method()'
     *   * 'class::$property'
     *   * 'class::CONSTANT'
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
        if ($matches['constant'] && PHP_VERSION_ID >= 70100) {
            return new ReflectionClassConstant($matches['class'], $matches['constant']);
        }
        if ($matches['class']) {
            return new ReflectionClass($matches['class']);
        }
        return null;
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
        if (\strlen($matches['classname']) !== (int) $matches['strlen'] || \in_array($matches['classname'], self::$allowedClasses)) {
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
