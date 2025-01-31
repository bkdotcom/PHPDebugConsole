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

namespace bdk\Debug\Utility\PhpDoc;

use bdk\Debug\Utility\PhpDoc;
use bdk\Debug\Utility\Reflection;
use bdk\Debug\Utility\UseStatements;

/**
 * PhpDoc parsing helper methods
 */
class Type
{
    /** @var list<string> */
    public $types = [
        'null',
        'mixed', 'scalar',
        'bool', 'boolean', 'true', 'false',
        'callable', 'callable-array', 'callable-string', 'iterable',
        'int', 'integer', 'negative-int', 'positive-int', 'non-positive-int', 'non-negative-int', 'non-zero-int',
        'int-mask', 'int-mask-of',
        'float', 'double',
        'numeric', // int, float, or numeric-string
        'array', 'non-empty-array', 'list', 'non-empty-list',
        'array-key',
        'void',
        'object',
        'string', 'non-falsy-string', 'numeric-string', 'non-empty-string', 'class-string', 'literal-string',
        '$this', 'self', 'static',
        'resource', 'closed-resource', 'open-resource',
        'key-of', 'value-of',
        'never', 'never-return', 'never-returns', 'no-return',
    ];

    /**
     * Convert "self[]|null" to array
     *
     * @param string $type             type hint
     * @param string $className        Classname where element is defined
     * @param int    $fullyQualifyType Whether to fully qualify type(s)
     *                                   Bitmask of FULLY_QUALIFY* constants
     *
     * @return string|null
     */
    public function normalize($type, $className, $fullyQualifyType = 0)
    {
        if (\in_array($type, ['', null], true)) {
            return null;
        }
        if (\preg_match('/array[<([{]/', $type)) {
            // type contains "complex" array type... don't deal with parsing
            return $type;
        }
        $types = \preg_split('#\s*\|\s*#', $type);
        foreach ($types as $i => $type) {
            $types[$i] = $this->normalizeSingle($type, $className, $fullyQualifyType);
        }
        return \implode('|', $types);
    }

    /**
     * Normalize individual part of type
     *
     * @param string $type             type hint
     * @param string $className        Classname where element is defined
     * @param int    $fullyQualifyType Whether to fully qualify type(s)
     *                                   Bitmask of FULLY_QUALIFY* constants
     *
     * @return string
     */
    private function normalizeSingle($type, $className, $fullyQualifyType = 0)
    {
        if (\strpos($type, '\\') === 0) {
            return \substr($type, 1);
        }
        $isArray = false;
        if (\substr($type, -2) === '[]') {
            $isArray = true;
            $type = \substr($type, 0, -2);
        }
        $translate = array(
            'boolean' => 'bool',
            'integer' => 'int',
            'self' => $className,
        );
        if (isset($translate[$type])) {
            $type = $translate[$type];
        } elseif ($fullyQualifyType && \in_array($type, $this->types, true) === false) {
            $type = $this->resolveTypeClass($type, $className, $fullyQualifyType);
        }
        if ($isArray) {
            $type .= '[]';
        }
        return $type;
    }

    /**
     * Check type-hint in use statements, and whether relative or absolute
     *
     * @param string $type             Type-hint
     * @param string $className        Classname where element is defined
     * @param int    $fullyQualifyType Whether to fully qualify type(s)
     *                                   Bitmask of FULLY_QUALIFY* constants
     *
     * @return string
     */
    private function resolveTypeClass($type, $className, $fullyQualifyType = 0)
    {
        $first = \substr($type, 0, \strpos($type, '\\') ?: 0) ?: $type;
        $className = $className ?: '';
        $classReflector = Reflection::getReflector($className, true);
        $useStatements = $classReflector
            ? UseStatements::getUseStatements($classReflector)['class']
            : array();
        if (isset($useStatements[$first])) {
            return $useStatements[$first] . \substr($type, \strlen($first));
        }
        $namespace = \substr($className, 0, \strrpos($className, '\\') ?: 0);
        if (!$namespace) {
            return $type;
        }
        /*
            Truly relative?  Or, does PhpDoc omit '\' ?
            Not 100% accurate, but check if assumed namespace'd class exists
        */
        $autoload = ($fullyQualifyType & PhpDoc::FULLY_QUALIFY_AUTOLOAD) === PhpDoc::FULLY_QUALIFY_AUTOLOAD;
        return \class_exists($namespace . '\\' . $type, $autoload)
            ? $namespace . '\\' . $type
            : $type;
    }
}
