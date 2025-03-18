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
        return \bdk\Debug\Utility\PhpType::getDebugTypeObject($reflector->getName());
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
        return \bdk\Debug\Utility\PhpType::getDebugType($val);
    }

    /**
     * returns required/included files sorted by directory
     *
     * @return array
     */
    public static function getIncludedFiles()
    {
        return \bdk\Debug\Utility::sortFiles(\get_included_files());
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
        return \bdk\Debug\Utility\PhpType::isCallable($val, $opts);
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
        return \bdk\Debug\Utility\PhpType::isThrowable($val);
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
     * Gracefully handle unsafe classes implementing Serializable
     *
     * @param string        $serialized     serialized string
     * @param string[]|bool $allowedClasses allowed class names (stdClass will always be allowed)
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
