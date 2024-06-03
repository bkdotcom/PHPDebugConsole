<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Utility\Php as PhpUtil;
use bdk\Debug\Utility\PhpDoc;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
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
     * Remove desc & summary if not collecting phpDoc
     *
     * Easier to collect and then remove vs having logic everywhere
     *
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return void
     */
    public function clearPhpDoc(Abstraction $abs)
    {
        if ($abs['cfgFlags'] & AbstractObject::PHPDOC_COLLECT) {
            return;
        }
        $methods = $abs['methods'];
        foreach ($methods as &$methodInfo) {
            $methodInfo['phpDoc'] = array(
                'desc' => null,
                'summary' => null,
            );
            foreach (\array_keys($methodInfo['params']) as $index) {
                $methodInfo['params'][$index]['desc'] = null;
            }
            $methodInfo['return']['desc'] = null;
        }
        $abs['methods'] = $methods;
    }

    /**
     * Get object, constant, property, or method attributes
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return array
     */
    public static function getAttributes(Reflector $reflector)
    {
        if (PHP_VERSION_ID < 80000) {
            return array();
        }
        return \array_map(static function (ReflectionAttribute $attribute) {
            return array(
                'arguments' => $attribute->getArguments(),
                'name' => $attribute->getName(),
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
    public static function getClassName(ReflectionClass $reflector)
    {
        return PHP_VERSION_ID >= 70000 && $reflector->isAnonymous()
            ? PhpUtil::friendlyClassName($reflector)
            : $reflector->getName();
    }

    /**
     * Get param type
     *
     * @param ReflectionParameter $refParameter reflectionParameter
     *
     * @return string|null
     */
    public static function getParamType(ReflectionParameter $refParameter)
    {
        if (PHP_VERSION_ID >= 70000) {
            return self::getTypeString($refParameter->getType());
        }
        if ($refParameter->isArray()) {
            // isArray is deprecated in php 8.0
            // isArray is only concerned with type-hint and does not look at default value
            return 'array';
        }
        if (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $refParameter->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            return $matches[1];
        }
        return null;
    }

    /**
     * Get parsed PhpDoc
     *
     * @param Reflector $reflector        Reflector instance
     * @param bool      $fullyQualifyType Whether to further parse / resolve types
     *
     * @return array
     */
    public function getPhpDoc(Reflector $reflector, $fullyQualifyType = false)
    {
        return $this->phpDoc->getParsed($reflector, $fullyQualifyType);
    }

    /**
     * Get type and description from phpDoc comment for Constant or Property
     *
     * @param Reflector $reflector        ReflectionProperty or ReflectionClassConstant property object
     * @param bool      $fullyQualifyType Whether to further parse / resolve types
     *
     * @return array
     */
    public function getPhpDocVar(Reflector $reflector, $fullyQualifyType = false)
    {
        /** @psalm-suppress NoInterfaceProperties */
        $name = $reflector->name;
        $phpDoc = $this->getPhpDoc($reflector, $fullyQualifyType);
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
     * Test if only need to populate traverseValues
     *
     * @param ObjectAbstraction $abs Abstraction instance
     *
     * @return bool
     */
    public function isTraverseOnly(ObjectAbstraction $abs)
    {
        if ($abs['debugMethod'] === 'table' && \count($abs['hist']) < 4) {
            $abs['cfgFlags'] &= ~AbstractObject::CONST_COLLECT;  // set collect constants to "false"
            $abs['cfgFlags'] &= ~AbstractObject::METHOD_COLLECT;  // set collect methods to "false"
            return true;
        }
        return false;
    }

    /**
     * Get constant/method/property visibility
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return 'public'|'private'|'protected'
     */
    public static function getVisibility(Reflector $reflector)
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
    public static function getTypeString(ReflectionType $type = null)
    {
        if ($type === null) {
            return null;
        }
        return $type instanceof ReflectionNamedType
            ? $type->getName()
            : (string) $type;
    }
}
