<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Abstraction\Object;

use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\Php as PhpUtil;
use bdk\Debug\Utility\PhpDoc;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use Reflector;

/**
 * Object Abstraction Helper methods
 */
class Helper
{
    /** @var PhpDoc */
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
                'arguments' => $attribute->getName() === 'Deprecated'
                    ? self::toNamedArguments(
                        $attribute->getArguments(),
                        ['message', 'since']
                    )
                    : $attribute->getArguments(),
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
     * Get Constant, Property, or Parameter's type or Method's return type
     * Priority given to phpDoc type, followed by reflection type (if available)
     *
     * @param ReflectionClassConstant|ReflectionMethod|ReflectionParameter|ReflectionProperty|null $reflector  ClassConstant, Method, Parameter, or Property Reflector instance
     * @param string                                                                               $phpDocType Type specified in phpDoc block
     *
     * @return array{allowsNull:bool,php:string|null,phpDoc:string|null}
     */
    public static function getType($reflector, $phpDocType = null)
    {
        $typeInfo = $reflector instanceof Reflector
            ? self::getTypeInfoReflection($reflector)
            : array(
                'allowsNull' => null,
                'php' => null,
            );

        $typeInfo['phpDoc'] = $phpDocType;
        if ($typeInfo['php'] === null && $phpDocType !== null && $phpDocType !== 'mixed') {
            // not strictly enforced, but if phpDoc type is provided, we'll base nullability on that (vs assuming nullable if php type is missing)
            $typeInfo['allowsNull'] = false;
        }
        return $typeInfo;
    }

    /**
     * Get parameter type from legacy ReflectionConstant, ReflectionParameter, or ReflectionProperty
     *
     * @param Reflector $reflector Reflection instance
     *
     * @return array{allowsNull:bool,php:string|null}
     */
    private static function getTypeInfoLegacyParam(ReflectionParameter $reflector)
    {
        $type = null;
        if ($reflector->isArray()) {
            // isArray is deprecated in php 8.0
            // isArray is only concerned with type-hint and does not look at default value
            $type = 'array';
        } elseif (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $reflector->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            $type = $matches[1];
        }
        return array(
            'allowsNull' => $type === null || ($reflector->isDefaultValueAvailable() && $reflector->getDefaultValue() === null),
            'php' => $type,
        );
    }

    /**
     * Get type-info
     *
     * @param ReflectionClassConstant|ReflectionMethod|ReflectionParameter|ReflectionProperty $reflector Reflector instance
     *
     * @return array{allowsNull:bool,php:string|null}
     */
    private static function getTypeInfoReflection($reflector)
    {
        if ($reflector instanceof ReflectionMethod) {
            return self::getTypeInfoReturn($reflector);
        }
        if (\method_exists($reflector, 'getType')) {
            // ReflectionClassConstant : php >= 8.3
            // ReflectionParameter : php >= 7.0
            // ReflectionProperty : php >= 7.4
            return static::getTypeInfoRefType($reflector->getType(), $reflector);
        }
        return $reflector instanceof ReflectionParameter
            ? static::getTypeInfoLegacyParam($reflector)
            : array(
                'allowsNull' => !($reflector instanceof ReflectionClassConstant),
                'php' => null,
            );
    }

    /**
     * Get string representation of ReflectionNamedType or ReflectionType
     *
     * @param ReflectionType|null                                                             $refType   ReflectionType
     * @param ReflectionClassConstant|ReflectionMethod|ReflectionParameter|ReflectionProperty $reflector Reflection instance (for context)
     *
     * @return array{allowsNull:bool,php:string|null}
     */
    private static function getTypeInfoRefType($refType, Reflector $reflector)
    {
        \bdk\Debug\Utility\PhpType::assertType($refType, 'ReflectionType|null');

        if ($refType === null) {
            // no explicit type declaration implicitly allows null.
            // untyped properties, params, & return values effectively behave as if they have a mixed type
            //   (constants cannot be null)
            return array(
                'allowsNull' => !($reflector instanceof ReflectionClassConstant),
                'php' => null, // may use phpDoc type
            );
        }

        \set_error_handler(static function () {
            // suppress ReflectionType::__toString() deprecation notice in php 7.x
        });
        $allowsNull = $refType->allowsNull();
        // just use __toString() - handles namedType, unionType (php 8.0+), intersectionType (php 8.1+), and nullable types
        $type = (string) $refType;
        \restore_error_handler();
        if (PHP_VERSION_ID < 80000 && $allowsNull) {
            // PHP 7.x did not include the '?' prefix for nullable types, so we'll add it here for consistency with PHP 8.x
            $type = '?' . $type;
        }
        return array(
            'allowsNull' => $allowsNull,
            'php' => $type,
        );
    }

    /**
     * Get the return type of a method
     *
     * @param ReflectionMethod $reflector ReflectionMethod instance
     *
     * @return array{allowsNull:bool,php:string|null}
     */
    private static function getTypeInfoReturn(ReflectionMethod $reflector)
    {
        if ($reflector->getName() === '__toString') {
            // __toString must return string and cannot return null
            return array(
                'allowsNull' => false,
                'php' => 'string',
            );
        }
        if (PHP_VERSION_ID < 70000) {
            // no return type info available prior to php 7.0
            return array(
                'allowsNull' => true,
                'php' => null,
            );
        }
        $refType = $reflector->getReturnType();
        if (!$refType && \method_exists($reflector, 'getTentativeReturnType')) {
            $refType = $reflector->getTentativeReturnType();
        }
        return self::getTypeInfoRefType($refType, $reflector);
    }

    /**
     * Convert positional arguments to named arguments
     *
     * @param array $arguments Arguments from getArguments()
     * @param array $names     Names of the parameters
     *
     * @return array
     */
    private static function toNamedArguments(array $arguments, array $names)
    {
        $namedArgs = \array_intersect_key($arguments, \array_flip($names));
        $arguments = \array_diff_key($arguments, $namedArgs);
        foreach ($names as $name) {
            if (\array_key_exists($name, $namedArgs)) {
                continue;
            }
            $namedArgs[$name] = \array_shift($arguments);
        }
        ArrayUtil::sortWithOrder($namedArgs, $names, 'key');
        return $namedArgs;
    }
}
