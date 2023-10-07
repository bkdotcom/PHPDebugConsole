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

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Utility\Php as PhpUtil;
use bdk\Debug\Utility\PhpDoc;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use Reflector;

/**
 * Get object method info
 */
class Helper
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
        return PHP_VERSION_ID >= 70000 && $reflector->isAnonymous()
            ? PhpUtil::friendlyClassName($reflector)
            : $reflector->getName();
    }

    /**
     * Get parsed PhpDoc
     *
     * @param Reflector $reflector              Reflector instance
     * @param bool      $fullyQualifyPhpDocType Whether to further parse / resolve types
     *
     * @return array
     */
    public function getPhpDoc(Reflector $reflector, $fullyQualifyPhpDocType = false)
    {
        return $this->phpDoc->getParsed($reflector, $fullyQualifyPhpDocType);
    }

    /**
     * Get type and description from phpDoc comment for Constant or Property
     *
     * @param Reflector $reflector              ReflectionProperty or ReflectionClassConstant property object
     * @param bool      $fullyQualifyPhpDocType Whether to further parse / resolve types
     *
     * @return array
     */
    public function getPhpDocVar(Reflector $reflector, $fullyQualifyPhpDocType = false)
    {
        /** @psalm-suppress NoInterfaceProperties */
        $name = $reflector->name;
        $phpDoc = $this->getPhpDoc($reflector, $fullyQualifyPhpDocType);
        $info = array(
            'desc' => $phpDoc['summary'],
            'type' => null,
        );
        if (isset($phpDoc['var']) === false) {
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
}