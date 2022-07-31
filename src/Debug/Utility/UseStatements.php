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

use ReflectionClass;

/**
 * Find use statements for given class
 *
 * @see https://www.php.net/manual/en/language.namespaces.importing.php
 * @see https://www.php.net/manual/en/language.namespaces.definitionmultiple.php
 */
class UseStatements
{
    protected static $cache = array();
    protected static $currentUse = null;
    protected static $categories = array(
        'class' => array(),
        'const' => array(),
        'function' => array(),
    );

    /**
     * Maintain "group" namespace (PHP 7+)
     *  ie `use function some\namespace\{fn_a, fn_b, fn_c};`
     *
     * @var string|null
     */
    protected static $groupNamespace = null;
    protected static $namespace = null;
    protected static $record = null;        // 'class', 'const', 'function', or 'namespace'
    protected static $recordPart = null;    // 'alias' or 'class'
    protected static $useStatements = array();

    /**
     * Return array of use statements from class.
     *
     * @param ReflectionClass $reflector ReflectionClass instance
     *
     * @return array
     */
    public static function getUseStatements(ReflectionClass $reflector)
    {
        if (!$reflector->isUserDefined()) {
            return self::$categories;
        }
        $name = $reflector->getName();
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $source = self::getPreceedingLines($reflector->getFileName(), $reflector->getStartLine());
        $useStatements = \strpos($source, 'use')
            ? self::extractUse($source)
            : array();
        $namespace = $reflector->getNamespaceName();
        $useStatements = isset($useStatements[$namespace])
            ? $useStatements[$namespace]
            : self::$categories;
        self::$cache[$name] = $useStatements;
        return $useStatements;
    }

    /**
     * Parse the use statements from given source code
     *
     * @param string $source php code
     *
     * @return array
     */
    public static function extractUse($source)
    {
        $tokens = \token_get_all($source);

        self::$namespace = null;
        self::$currentUse = null;
        self::$groupNamespace = null;
        self::$record = null;
        self::$recordPart = null;
        self::$useStatements = array();

        if (\defined('T_NAME_QUALIFIED') === false) {
            // T_NAME_QUALIFIED introduced with PHP 8.0
            \define('T_NAME_QUALIFIED', 314);
        }

        foreach ($tokens as $token) {
            if (self::$record) {
                self::recordToken($token);
                continue;
            }
            // check if we need to start recording
            self::setRecordInfo($token);
        }
        return self::sort(self::$useStatements);
    }

    /**
     * Add currentUse to self::$useStatements -> namespace
     *
     * @return void
     */
    private static function addUseStatement()
    {
        $class = \ltrim(self::$currentUse['class'], '\\');
        $alias = self::$currentUse['alias'] ?: self::getShortName($class);
        if (!isset(self::$useStatements[self::$namespace])) {
            self::$useStatements[self::$namespace] = self::$categories;
        }
        self::$useStatements[self::$namespace][self::$record][$alias] = $class;
        self::$currentUse = null;
    }

    /**
     * Read file source up to the line where our class is defined.
     *
     * @param string $file      filepath
     * @param int    $startLine line to stop reading source
     *
     * @return string
     */
    private static function getPreceedingLines($file, $startLine)
    {
        $file = \fopen($file, 'r');
        $line = 0;
        $source = '';
        while (\feof($file) === false) {
            ++$line;
            if ($line >= $startLine) {
                break;
            }
            $source .= \fgets($file);
        }
        \fclose($file);
        return $source;
    }

    /**
     * Get classname's "short name" (sans namespace)
     *
     * @param string $classname classname
     *
     * @return string
     */
    private static function getShortName($classname)
    {
        $pos = \strrpos($classname, '\\');
        return $pos
            ? \substr($classname, $pos + 1)
            : $classname;
    }

    /**
     * Record the specified token
     *
     * @param array $token Token to record
     *
     * @return void
     */
    private static function recordToken($token)
    {
        if (\is_array($token) === false) {
            self::recordTokenString($token);
            return;
        }
        self::setRecordInfo($token);
        self::recordTokenArray($token);
    }

    /**
     * Record the specified array token
     *
     * @param array $token Token to record
     *
     * @return void
     */
    private static function recordTokenArray($token)
    {
        switch (self::$record) {
            case 'namespace':
                switch ($token[0]) {
                    case T_STRING:
                    case T_NS_SEPARATOR:
                    case T_NAME_QUALIFIED:
                        self::$namespace .= $token[1];
                        break;
                }
                break;
            case 'class':
            case 'function':
            case 'const':
                switch ($token[0]) {
                    case T_STRING:
                    case T_NS_SEPARATOR:
                    case T_NAME_QUALIFIED:
                        self::$currentUse[self::$recordPart] .= $token[1];
                        break;
                }
                break;
        }
    }

    /**
     * comma encountered... reset currentUse
     *
     * @return void
     */
    private static function recordTokenClass()
    {
        self::$currentUse = array(
            'class' => self::$groupNamespace ?: '',
            'alias' => '',
        );
        self::$recordPart = 'class';
    }

    /**
     * Handle simple string token while recording
     *
     * @param string $token string token (ie "(",")",",", or ";" )
     *
     * @return void
     */
    private static function recordTokenString($token)
    {
        if ($token === '{') {
            // start group  (PHP 7.0+)
            self::$groupNamespace = \ltrim(self::$currentUse['class'], '\\');
            return;
        }
        if ($token === '}') {
            // end group
            self::$groupNamespace = null;
            return;
        }
        if (self::$currentUse) {
            self::addUseStatement();
        }
        if ($token === ',') {
            // multiple use statements on the same line
            self::recordTokenClass();
            return;
        }
        self::$record = null;
    }

    /**
     * Test if we should start "recording" use-statement info
     *
     * @param array|string $token token to test
     *
     * @return void
     */
    private static function setRecordInfo($token)
    {
        // $token may not be an array, but that's ok... $token[0] will just be first char of string
        switch ($token[0]) {
            case T_AS:
                self::$recordPart = 'alias';
                break;
            case T_CONST:
                // PHP 5.6+     `use const My\Full\CONSTANT;`
                self::$record = 'const';
                break;
            case T_FUNCTION:
                // PHP 5.6+     `use function My\Full\functionName as func;`
                self::$record = 'function';
                break;
            case T_NAMESPACE:
                self::$record = 'namespace';
                break;
            case T_USE:
                self::$record = 'class';
                self::$recordPart = 'class';
                self::$currentUse = array(
                    'class' => '',
                    'alias' => '',
                );
                break;
        }
    }

    /**
     * Sort use statements by namespace & alias
     *
     * @param array $statements use statement array
     *
     * @return array
     */
    private static function sort($statements)
    {
        \ksort($statements);
        foreach ($statements as &$nsStatements) {
            foreach ($nsStatements as &$useStatements) {
                \ksort($useStatements);
            }
        }
        return $statements;
    }
}
