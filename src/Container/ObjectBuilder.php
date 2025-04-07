<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.5
 */

namespace bdk\Container;

use bdk\Container;
use bdk\Container\UseStatements;
use Exception;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

/**
 * Container
 *
 * Forked from pimple/pimple
 *    adds:
 *       get()
 *       has()
 *       needsInvoked()
 *       setCfg()
 *          allowOverride & onInvoke callback
 *       setValues()
 *
 * @author Fabien Potencier
 * @author Brad Kent <bkfake-github@yahoo.com>
 */
class ObjectBuilder
{
    /** @var list<string> */
    public static $phpDocTypes = [
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

    /** @var Container */
    private $container;

    /** @var bool */
    private $useGetType = true; // php 7.0+ - Use this property to unit test legacy method of getting types

    /**
     * Constructor
     *
     * @param Container $container Container instance
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->useGetType = (new ReflectionClass('ReflectionParameter'))->hasMethod('getType');
    }

    /**
     * Instantiate a class
     *
     * We will look for the class in the container
     * If it is not found, we will try to instantiate it via reflection and obtaining dependencies via the container
     * We will save the instance in the container
     *
     * @param string $classname      Fully qualified class name
     * @param bool   $addToContainer (true) Add instantiated object to container?
     *
     * @return object
     *
     * @throws RuntimeException
     */
    public function build($classname, $addToContainer = true)
    {
        if ($this->container->has($classname)) {
            return $this->container->get($classname);
        }
        $refClass = new ReflectionClass($classname);
        $refConstructor = $refClass->getConstructor();
        if ($refConstructor === null) {
            $return = new $classname();
            $this->container->offsetSet($classname, $return);
            return $return;
        }
        $paramValues = $this->resolveConstructorArgs($refConstructor);
        $obj = $refClass->newInstanceArgs($paramValues);
        if ($addToContainer) {
            $this->container->offsetSet($classname, $obj);
        }
        return $obj;
    }

    /**
     * Resolve constructor arguments
     *
     * @param ReflectionMethod $refConstructor ReflectionMethod instance
     *
     * @return array
     */
    private function resolveConstructorArgs(ReflectionMethod $refConstructor)
    {
        $paramValues = array();
        $refParams = $refConstructor->getParameters();
        \array_walk($refParams, function ($refParam) use (&$paramValues) {
            $value = $this->resolveParamValue($refParam);
            if ($value !== false) {
                $paramValues[$refParam->getName()] = $value;
                return;
            }
            if ($refParam->isOptional()) {
                $paramValues[$refParam->getName()] = $refParam->getDefaultValue();
                return;
            }
            throw new RuntimeException(\sprintf(
                'GetObject(%s) : Cannot resolve parameter "%s"',
                $refParam->getDeclaringClass()->getName(),
                $refParam->getName()
            ));
        });
        return $paramValues;
    }

    /**
     * Attempt to get non-built-in type value
     *
     * @param ReflectionParameter $refParam Reflection Parameter
     *
     * @return object|false
     */
    private function resolveParamValue(ReflectionParameter $refParam)
    {
        $types = $this->useGetType
            ? $this->getParamTypes($refParam)   // php 7.0+
            : $this->getParamTypesOld($refParam);
        $value = false;
        foreach ($types as $type) {
            try {
                $value = $this->container->has($type)
                    ? $this->container->get($type)
                    : $this->build($type);
                break;
            } catch (Exception $e) {
                continue;
            }
        }
        return $value;
    }

    /**
     * Get non-built-in parameter type(s) from ReflectionParameter
     *
     * If type hint is not available, we will try to get the type from phpDoc
     *
     * @param ReflectionParameter $refParam ReflectionParameter instance
     *
     * @return string[]
     */
    private function getParamTypes(ReflectionParameter $refParam)
    {
        $refType = $refParam->getType();
        if ($refType === null) {
            return $this->getParamTypesPhpDoc($refParam);
        }
        // ReflectionUnionType:  php 8.0
        // ReflectionIntersecionType:  php 8.1
        $refTypes = $refType instanceof ReflectionUnionType || $refType instanceof ReflectionIntersectionType
            ? $refType->getTypes()
            : [$refType];
        $refTypes = \array_filter($refTypes, static function (ReflectionType $refType) {
            return $refType->isBuiltin() === false;
        });
        return \array_map(static function (ReflectionType $refType) {
            return $refType instanceof ReflectionNamedType
                ? $refType->getName()
                : (string) $refType;
        }, $refTypes);
    }

    /**
     * Get parameter type(s) from ReflectionParameter for PHP < 7.0
     *
     * @param ReflectionParameter $refParam ReflectionParameter instance
     *
     * @return string[]
     */
    private function getParamTypesOld(ReflectionParameter $refParam)
    {
        if (\preg_match('/\[\s<\w+>\s([\w\\\\]+)/s', $refParam->__toString(), $matches)) {
            // Parameter #0 [ <required> namespace\Type $varName ]
            return [$matches[1]];
        }
        return $this->getParamTypesPhpDoc($refParam);
    }

    /**
     * Attempt to get parameter type(s) from phpDoc
     *
     * @param ReflectionParameter $refParam ReflectionParameter instance
     *
     * @return string[]
     */
    private function getParamTypesPhpDoc(ReflectionParameter $refParam)
    {
        $types = [];
        $refMethod = $refParam->getDeclaringFunction();
        $phpDoc = $refMethod->getDocComment();
        if ($phpDoc === false) {
            return $types;
        }
        $phpDoc = \preg_replace('/\s+/', ' ', $phpDoc);
        if (\preg_match('/@param\s+([^\$]+)\s+\$' . $refParam->getName() . '/i', $phpDoc, $matches)) {
            $types = \preg_split('#\s*\|\s*#', $matches[1]);
        }
        // remove known phpDoc types
        $types = \array_filter($types, static function ($type) {
            return \in_array($type, self::$phpDocTypes, true) === false;
        });
        // types should now only be classnames
        $types = \array_map(static function ($type) use ($refParam) {
            return self::resolvePhpDocTypeClassname($type, $refParam);
        }, $types);
        return $types;
    }

    /**
     * Determine fully qualified classname from name specofied in phpDoc
     *
     * @param string              $type     clasaname
     * @param ReflectionParameter $refParam ReflectionParameter instance
     *
     * @return string
     */
    private static function resolvePhpDocTypeClassname($type, ReflectionParameter $refParam)
    {
        if (\strpos($type, '\\') === 0) {
            return \substr($type, 1);
        }
        // are we
        //   * alias in use statement?
        //   * relative to current namespace?
        //   * a fully qualified class name?
        $refDeclaringClass = $refParam->getDeclaringClass();
        $useStatements = UseStatements::getUseStatements($refDeclaringClass)['class'];
        $firstPart = \substr($type, 0, \strpos($type, '\\') ?: 0) ?: $type;
        if (isset($useStatements[$firstPart])) {
            return $useStatements[$firstPart] . \substr($type, \strlen($firstPart));
        }
        $declaringClassName = $refDeclaringClass->getName();
        $idx = \strrpos($declaringClassName, '\\');
        $namespace = $idx
            ? \substr($declaringClassName, 0, $idx + 1)  // namespace with trailing slash
            : '';
        return \class_exists($namespace . $type)
            ? $namespace . $type
            : $type;
    }
}
