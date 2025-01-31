<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Utility;

use BackedEnum;
use InvalidArgumentException;
use OutOfBoundsException;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionEnumUnitCase;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Reflector;
use UnitEnum;

/**
 * Reflection related utils
 */
class Reflection
{
    /** @var array<string,array> */
    protected static $methodDefaultArgs = array();

    /** @var non-empty-string */
    private static $regex = '/^(?:
            (?:
                (?P<class>[\w\\\]+) # classname
                (?:::(?:
                    (?P<constant>\w+)|       # constant
                    (?:\$(?P<property>\w+))| # property
                    (?:(?P<method>\w+)\(\))| # method
                ))?
            )|(?:
                (?P<function>[\w\\\]*\w+)\(\)
            )
        )$/x';

    /**
     * Get the reflector's classname
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return string|null
     *
     * @psalm-suppress MixedInferredReturnType
     * @psalm-suppress MixedMethodCall
     * @psalm-suppress MixedReturnStatement
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public static function classname(Reflector $reflector)
    {
        if ($reflector instanceof ReflectionFunction) {
            return null;
        }
        return \method_exists($reflector, 'getDeclaringClass')
            ? $reflector->getDeclaringClass()->getName()
            : $reflector->getName();
    }

    /**
     * Get Method's default argument list
     *
     * @param string $method Method identifier
     *
     * @return array
     */
    public static function getMethodDefaultArgs($method)
    {
        if (isset(self::$methodDefaultArgs[$method])) {
            return self::$methodDefaultArgs[$method];
        }
        $regex = '/^(?P<class>[\w\\\]+)::(?P<method>\w+)(?:\(\))?$/';
        \preg_match($regex, $method, $matches);
        $refMethod = new ReflectionMethod($matches['class'], $matches['method']);
        $params = $refMethod->getParameters();
        $defaultArgs = array();
        foreach ($params as $refParameter) {
            $name = $refParameter->getName();
            $defaultArgs[$name] = $refParameter->isOptional()
                ? $refParameter->getDefaultValue()
                : null;
        }
        unset($defaultArgs['args']);
        self::$methodDefaultArgs[$method] = $defaultArgs;
        return $defaultArgs;
    }

    /**
     * Find "parent" reflector
     *
     * @param Reflector $reflector Reflector interface
     *
     * @return Reflector|false
     */
    public static function getParentReflector(Reflector $reflector)
    {
        if ($reflector instanceof ReflectionMethod) {
            return self::getParentReflectorMpc($reflector, 'method');
        }
        if ($reflector instanceof ReflectionProperty) {
            return self::getParentReflectorMpc($reflector, 'property');
        }
        if ($reflector instanceof ReflectionClassConstant) {
            return self::getParentReflectorMpc($reflector, 'constant');
        }
        // ReflectionClass  (incl ReflectionObject & ReflectionEnum)
        return self::getParentReflectorC($reflector);
    }

    /**
     * Get Reflector for given value
     *
     * Accepts:
     *   * object
     *   * Reflector
     *   * string  class
     *   * string  class::method()
     *   * string  class::$property
     *   * string  class::CONSTANT
     *   * string  [namespace\]function()    (namespace is optional)
     *
     * @param object|string $mixed      object, or string
     * @param bool          $returnSelf (false) if passed obj is a Reflector, return it
     *
     * @return Reflector|false
     */
    public static function getReflector($mixed, $returnSelf = false)
    {
        if ($mixed instanceof Reflector && $returnSelf) {
            return $mixed;
        }
        if ($mixed instanceof BackedEnum) {
            return new ReflectionEnumBackedCase($mixed, $mixed->name);
        }
        if ($mixed instanceof UnitEnum) {
            return new ReflectionEnumUnitCase($mixed, $mixed->name);
        }
        if (\is_object($mixed)) {
            return new ReflectionObject($mixed);
        }
        try {
            return \is_string($mixed)
                ? static::getReflectorFromString($mixed)
                : false;
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * Hash reflector name
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return string
     */
    public static function hash(Reflector $reflector)
    {
        $str = '';
        $name = $reflector->getName();
        if ($reflector instanceof ReflectionClass) {
            // and ReflectionEnum
            $str = $name;
        } elseif ($reflector instanceof ReflectionClassConstant) {
            // and ReflectionEnumUnitCase / ReflectionEnumBackedCase
            $str = $reflector->getDeclaringClass()->getName() . '::' . $name;
        } elseif ($reflector instanceof ReflectionMethod) {
            $str = $reflector->getDeclaringClass()->getName() . '::' . $name .= '()';
        } elseif ($reflector instanceof ReflectionProperty) {
            $str = $reflector->getDeclaringClass()->getName() . '::$' . $name;
        } elseif ($reflector instanceof ReflectionFunction) {
            $str = $name .= '()';
        }
        return \md5($str);
    }

    /**
     * Get inaccessible property value via reflection
     *
     * @param object|classname $obj  object instance
     * @param string           $prop property name
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public static function propGet($obj, $prop)
    {
        $refProp = static::getReflectionProperty($obj, $prop);
        if ($refProp->isStatic()) {
            return $refProp->getValue();
        }
        if (\is_object($obj) === false) {
            throw new InvalidArgumentException(\sprintf(
                'propGet: object must be provided to retrieve instance value %s',
                $prop
            ));
        }
        return PHP_VERSION_ID < 70400 || $refProp->isInitialized($obj)
            ? $refProp->getValue($obj)
            : null;
    }

    /**
     * Set inaccessible property value via reflection
     *
     * @param object|classname $obj  object or classname
     * @param string           $prop property name
     * @param mixed            $val  new value
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public static function propSet($obj, $prop, $val)
    {
        $refProp = static::getReflectionProperty($obj, $prop);
        if ($refProp->isStatic()) {
            return $refProp->setValue(null, $val);
        }
        if (\is_object($obj) === false) {
            throw new InvalidArgumentException(\sprintf(
                'propSet: object must be provided to set instance value %s',
                $prop
            ));
        }
        return $refProp->setValue($obj, $val);
    }

    /**
     * Get ReflectionProperty
     *
     * @param object|classname $obj  object or classname
     * @param string           $prop property name
     *
     * @return ReflectionProperty
     * @throws OutOfBoundsException
     */
    private static function getReflectionProperty($obj, $prop)
    {
        $refProp = null;
        $ref = new ReflectionClass($obj);
        do {
            if ($ref->hasProperty($prop)) {
                $refProp = $ref->getProperty($prop);
                break;
            }
            $ref = $ref->getParentClass();
        } while ($ref);
        if ($refProp === null) {
            throw new OutOfBoundsException(\sprintf(
                'Property %s::$%s does not exist',
                \is_string($obj)
                    ? $obj
                    : \get_class($obj),
                $prop
            ));
        }
        $refProp->setAccessible(true);
        return $refProp;
    }

    /**
     * Find parent class reflector or first interface
     *
     * @param ReflectionClass $reflector ReflectionClass instance
     *
     * @return ReflectionClass|false
     */
    private static function getParentReflectorC(ReflectionClass $reflector)
    {
        $parentReflector = $reflector->getParentClass();
        if ($parentReflector) {
            return $parentReflector;
        }
        $interfaces = $reflector->getInterfaceNames();
        foreach ($interfaces as $className) {
            return new ReflectionClass($className);
        }
        return false;
    }

    /**
     * Find method/property/constant phpDoc in parent classes / interfaces
     *
     * @param Reflector $reflector Reflector interface
     * @param string    $what      'method' or 'property', or 'constant'
     *
     * @return Reflector|false
     */
    private static function getParentReflectorMpc(Reflector $reflector, $what)
    {
        $hasWhat = 'has' . \ucfirst($what);
        $getWhat = $what === 'constant'
            ? 'getReflectionConstant'  // php 7.1
            : 'get' . \ucfirst($what);
        $name = $reflector->getName();
        $declaringClassRef = $reflector->getDeclaringClass();

        $parentClass = $declaringClassRef->getParentClass();
        if ($parentClass && $parentClass->{$hasWhat}($name)) {
            return $parentClass->{$getWhat}($name);
        }

        $interfaces = $declaringClassRef->getInterfaceNames();
        foreach ($interfaces as $className) {
            $reflectionInterface = new ReflectionClass($className);
            if ($reflectionInterface->{$hasWhat}($name)) {
                return $reflectionInterface->{$getWhat}($name);
            }
        }

        return false;
    }

    /**
     * String to Reflector
     *
     * Accepts:
     *   * 'class'              ReflectionClass
     *   * 'class::method()'    ReflectionMethod
     *   * 'class::$property'   ReflectionProperty
     *   * 'class::CONSTANT'    ReflectionClassConstant (if Php >= 7.1)
     *   * 'enum::CASE'         ReflectionEnumUnitCase
     *
     * @param string $string string representing class, method, property, or class constant
     *
     * @return Reflector|false
     */
    private static function getReflectorFromString($string)
    {
        $matches = [];
        \preg_match(self::$regex, $string, $matches);
        $defaults = \array_fill_keys(['class', 'constant', 'property', 'method', 'function'], null);
        $matches = \array_merge($defaults, $matches);
        if ($matches['method']) {
            return new ReflectionMethod($matches['class'], $matches['method']);
        }
        if ($matches['property']) {
            return new ReflectionProperty($matches['class'], $matches['property']);
        }
        if ($matches['class'] && PHP_VERSION_ID >= 80100 && \enum_exists($matches['class'])) {
            return self::getReflectorFromStringEnum($matches);
        }
        if ($matches['constant'] && PHP_VERSION_ID >= 70100) {
            return new ReflectionClassConstant($matches['class'], $matches['constant']);
        }
        if ($matches['class']) {
            return new ReflectionClass($matches['class']);
        }
        if ($matches['function']) {
            return new ReflectionFunction($matches['function']);
        }
        return false;
    }

    /**
     * Get enum reflector from string matches
     *
     * @param array $matches regex matches
     *
     * @return Reflector ReflectionEnum | ReflectionEnumUnitCase | ReflectionEnumBackedCase | ReflectionClassConstant
     */
    private static function getReflectorFromStringEnum(array $matches)
    {
        $refEnum = new ReflectionEnum($matches['class']);
        if (empty($matches['constant'])) {
            return $refEnum;
        }
        return $refEnum->hasCase($matches['constant'])
            ? $refEnum->getCase($matches['constant']) // ReflectionEnumUnitCase or ReflectionEnumBackedCase
            : $refEnum->getReflectionConstant($matches['constant']);  // ReflectionClassConstant
    }
}
