<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Reflection;
use Exception;
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

    /** @var string[] list of allowed-to-be-unserialized classes passed to unserializeSafe */
    protected static $allowedClasses = [];

    /**
     * Return the build date of the PHP binary
     *
     * @return string|null
     */
    public static function buildDate()
    {
        if (\defined('PHP_BUILD_DATE')) {
            return PHP_BUILD_DATE;
        }

        \ob_start();
        \phpinfo(INFO_GENERAL);
        $phpInfo = \ob_get_clean();
        $phpInfo = \strip_tags($phpInfo);
        \preg_match('/Build Date (?:=> )?([^\n]*)/', $phpInfo, $matches);
        return $matches
            ? $matches[1]
            : null;
    }

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
        $reflector = Reflection::getReflector($mixed, true);
        if ($reflector && \method_exists($reflector, 'getDeclaringClass')) {
            $reflector = $reflector->getDeclaringClass();
        }
        return self::getDebugTypeObject($reflector->getName());
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * like php's `get_debug_type`, but will return
     *  - 'callable' for callable array
     *  - enum name for enum value
     *
     * @param mixed $val The value being type checked
     *
     * @return string
     *
     * @see https://github.com/symfony/polyfill/blob/main/src/Php80/Php80.php
     */
    public static function getDebugType($val)
    {
        if (PHP_VERSION_ID >= 80000 && \in_array(\gettype($val), ['array', 'object'], true) === false) {
            return \get_debug_type($val);
        }

        switch (true) {
            case $val === null:
                return 'null';
            case \is_bool($val):
                return 'bool';
            case \is_string($val):
                return 'string';
            case \is_array($val):
                return self::isCallable($val)
                    ? 'callable'
                    : 'array';
            case \is_int($val):
                return 'int';
            case \is_float($val):
                return 'float';
            case \is_object($val):
                return self::getDebugTypeObject($val);
            case $val instanceof \__PHP_Incomplete_Class:
                return '__PHP_Incomplete_Class';
            default:
                return self::getDebugTypeResource($val);
        }
    }

    /**
     * returns required/included files sorted by directory
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        $includedFiles = \get_included_files();
        return \bdk\Debug\Utility::sortFiles($includedFiles);
    }

    /**
     * Return path to the loaded php.ini file along with .ini files parsed from the additional ini dir
     *
     * @return array
     */
    public static function getIniFiles()
    {
        return \array_merge(
            [\php_ini_loaded_file()],
            \array_filter(\preg_split('#\s*[,\r\n]+\s*#', \trim((string) \php_ini_scanned_files())))
        );
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
        return \trim(\ini_get('memory_limit') ?: \get_cfg_var('memory_limit')) ?: '128M';
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
            $allowedClasses = [];
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
     * Get friendly class name
     *
     * @param object $obj Object to inspect
     *
     * @return string
     */
    private static function getDebugTypeObject($obj)
    {
        if ($obj instanceof UnitEnum) {
            return \get_class($obj) . '::' . $obj->name;
        }
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
        }
        if ($type === 'Unknown') {
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
        return $opts & self::IS_CALLABLE_OBJ_ONLY
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
     * Test if `['string', 'method']` is callable
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
        $matches = [];
        $offset = 0;
        $regex = '/(^|;)([OC]):(\d+):"([\w\\\\]+)":(\d+):\{/';
        $regexKeys = ['full', 'prefix', 'type', 'strlen', 'classname', 'length'];
        $serializedNew = '';
        while (\preg_match($regex, $serialized, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            /** @var array<string,int> */
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
        if (
            \strlen($matches['classname']) !== (int) $matches['strlen']
            || \in_array($matches['classname'], self::$allowedClasses, true)
        ) {
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
