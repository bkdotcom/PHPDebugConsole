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

use bdk\Debug\Utility\Php as PhpUtil;
use bdk\Debug\Utility\PhpDoc;
use bdk\Debug\Utility\UseStatements;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Reflector;

/**
 * Get object method info
 */
class AbstractObjectHelper
{
    private $phpDoc;

    /**
     * Constructor
     *
     * @param PhpDoc $phpDoc PhpDoc instance
     */
    public function __construct(PhpDoc $phpDoc)
    {
        $this->phpDoc = $phpDoc;
    }

    /**
     * Get object, constant, property, or method attributes
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return array
     */
    public function getAttributes(Reflector $reflector)
    {
        if (PHP_VERSION_ID < 80000) {
            return array();
        }
        return \array_map(static function (ReflectionAttribute $attribute) {
            // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
            return array(
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments(),
            );
        }, $reflector->getAttributes());
    }

    /**
     * Get the "friendly" class-name
     *
     * @param ReflectionClass $reflector ReflectionClass instance
     *
     * @return string
     */
    public function getClassName(ReflectionClass $reflector)
    {
        return $reflector->isAnonymous()
            ? PhpUtil::friendlyClassName($reflector)
            : $reflector->getName();
    }

    /**
     * Get parsed PhpDoc
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return array
     */
    public function getPhpDoc(Reflector $reflector)
    {
        return $this->phpDoc->getParsed($reflector);
    }

    /**
     * Get type and description from phpDoc comment for Constant or Property
     *
     * @param Reflector $reflector ReflectionProperty or ReflectionClassConstant property object
     *
     * @return array
     */
    public function getPhpDocVar(Reflector $reflector)
    {
        /** @psalm-suppress NoInterfaceProperties */
        $name = $reflector->name;
        $phpDoc = $this->phpDoc->getParsed($reflector);
        $info = array(
            'desc' => $phpDoc['summary'],
            'type' => null,
        );
        if (!isset($phpDoc['var'])) {
            return $info;
        }
        /*
            php's getDocComment doesn't play nice with compound statements
            https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/var.html
        */
        $var = array();
        foreach ($phpDoc['var'] as $var) {
            if ($var['name'] === $name) {
                break;
            }
        }
        $info['type'] = $var['type'];
        if (!$info['desc']) {
            $info['desc'] = $var['desc'];
        } elseif ($var['desc']) {
            $info['desc'] = $info['desc'] . ': ' . $var['desc'];
        }
        return $info;
    }

    /**
     * Get constant/method/property visibility
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return 'public'|'private'|'protected'
     */
    public function getVisibility(Reflector $reflector)
    {
        if ($reflector->isPrivate()) {
            return 'private';
        }
        if ($reflector->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    /**
     * Get string representation of ReflectionNamedType or ReflectionType
     *
     * @param ReflectionType|null $type ReflectionType
     *
     * @return string|null
     */
    public function getTypeString(ReflectionType $type = null)
    {
        if ($type === null) {
            return null;
        }
        return $type instanceof ReflectionNamedType
            ? $type->getName()
            : (string) $type;
    }

    /**
     * Fully quallify type-hint
     *
     * This is only performed if `fullyQualifyPhpDocType` = true
     *
     * @param string|null $type Type-hint string from phpDoc (may be or'd with '|')
     * @param Abstraction $abs  Abstraction instance
     *
     * @return string|null
     */
    public function resolvePhpDocType($type, Abstraction $abs)
    {
        if (!$type || !$abs['fullyQualifyPhpDocType']) {
            return $type;
        }
        if (\preg_match('/array[<([{]/', $type)) {
            // type contains "complex" array type... don't deal with parsing
            return $type;
        }
        $types = \preg_split('#\s*\|\s*#', $type);
        foreach ($types as $i => $type) {
            if (\strpos($type, '\\') === 0) {
                $types[$i] = \substr($type, 1);
                continue;
            }
            $isArray = false;
            if (\substr($type, -2) === '[]') {
                $isArray = true;
                $type = \substr($type, 0, -2);
            }
            if (\in_array($type, $this->phpDoc->types, true)) {
                continue;
            }
            $type = $this->resolvePhpDocTypeClass($type, $abs);
            if ($isArray) {
                $type .= '[]';
            }
            $types[$i] = $type;
        }
        return \implode('|', $types);
    }

    /**
     * Check type-hint in use statements, and whether relative or absolute
     *
     * @param string      $type Type-hint string
     * @param Abstraction $abs  Abstraction instance
     *
     * @return string
     */
    private function resolvePhpDocTypeClass($type, Abstraction $abs)
    {
        $first = \substr($type, 0, \strpos($type, '\\') ?: 0) ?: $type;
        $useStatements = UseStatements::getUseStatements($abs['reflector'])['class'];
        if (isset($useStatements[$first])) {
            return $useStatements[$first] . \substr($type, \strlen($first));
        }
        $namespace = \substr($abs['className'], 0, \strrpos($abs['className'], '\\') ?: 0);
        if (!$namespace) {
            return $type;
        }
        /*
            Truly relative?  Or, does PhpDoc omit '\' ?
            Not 100% accurate, but check if assumed namespace'd class exists
        */
        return \class_exists($namespace . '\\' . $type)
            ? $namespace . '\\' . $type
            : $type;
    }
}
