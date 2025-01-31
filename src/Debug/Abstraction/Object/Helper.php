<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Object\Abstraction as ObjectAbstraction;
use bdk\Debug\Utility;
use bdk\Debug\Utility\Php as PhpUtil;
use bdk\Debug\Utility\PhpDoc;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
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
                'desc' => '',
                'summary' => '',
            );
            foreach (\array_keys($methodInfo['params']) as $index) {
                $methodInfo['params'][$index]['desc'] = '';
            }
            $methodInfo['return']['desc'] = '';
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
     * Get type and description from phpDoc comment for Constant, Case, or Property
     *
     * @param Reflector $reflector        ReflectionClassConstant, ReflectionEnumUnitCase, or ReflectionProperty
     * @param bool      $fullyQualifyType Whether to further parse / resolve types
     *
     * @return array
     */
    public function getPhpDocVar(Reflector $reflector, $fullyQualifyType = false)
    {
        /** @psalm-suppress NoInterfaceProperties */
        $name = $reflector->name;
        $phpDoc = \array_merge(array(
            'desc' => '',
            'summary' => '',
            'type' => null,
            'var' => array(),
        ), $this->getPhpDoc($reflector, $fullyQualifyType));
        $foundVar = array(
            'desc' => '',
            'type' => null,
        );
        /*
            php's getDocComment doesn't play nice with compound statements
            https://github.com/php-fig/fig-standards/blob/master/proposed/phpdoc-tags.md#518-var

            @todo check other constants/properties for matching @var tag
        */
        foreach ($phpDoc['var'] as $var) {
            if ($var['name'] === $name) {
                $foundVar = $var;
            }
        }
        unset($phpDoc['var']);
        $phpDoc['type'] = $foundVar['type'];
        if (!$phpDoc['summary']) {
            $phpDoc['summary'] = $foundVar['desc'];
        } elseif ($foundVar['desc']) {
            $phpDoc['summary'] = \trim($phpDoc['summary'] . "\n\n" . $foundVar['desc']);
        }
        return $phpDoc;
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
     * Get Constant, Property, or Parameter's type or Method's return type
     * Priority given to phpDoc type, followed by reflection type (if available)
     *
     * @param string                                                                          $phpDocType Type specified in phpDoc block
     * @param ReflectionClassConstant|ReflectionMethod|ReflectionParameter|ReflectionProperty $reflector  ClassConstant, Method, Parameter, or Property Reflector instance
     *
     * @return string|null
     */
    public static function getType($phpDocType, Reflector $reflector)
    {
        if ($phpDocType !== null) {
            return $phpDocType;
        }
        if (\method_exists($reflector, 'getType')) {
            // ReflectionClassConstant : php >= 8.3
            // ReflectionParameter : php >= 7.0
            // ReflectionProperty : php >= 7.4
            return static::getTypeString($reflector->getType());
        }
        if ($reflector instanceof ReflectionMethod && PHP_VERSION_ID >= 70000) {
            return static::getTypeString($reflector->getReturnType());
        }
        if ($reflector instanceof ReflectionParameter) {
            return static::getParamTypeOld($reflector);
        }
        return null;
    }

    /**
     * Get string representation of ReflectionNamedType or ReflectionType
     *
     * @param ReflectionType|null $type ReflectionType
     *
     * @return string|null
     */
    protected static function getTypeString($type = null)
    {
        Utility::assertType($type, 'ReflectionType');
        if ($type === null) {
            return null;
        }
        return $type instanceof ReflectionNamedType
            ? $type->getName()
            : (string) $type;
    }

    /**
     * Get parameter type from ReflectionParameter for PHP < 7.0
     *
     * @param ReflectionParameter $reflector ReflectionParameter instance
     *
     * @return string|null
     */
    protected static function getParamTypeOld(ReflectionParameter $reflector)
    {
        if ($reflector->isArray()) {
            // isArray is deprecated in php 8.0
            // isArray is only concerned with type-hint and does not look at default value
            return 'array';
        }
        if (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $reflector->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            return $matches[1];
        }
        return null;
    }
}
