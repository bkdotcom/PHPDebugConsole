<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

use BackedEnum;
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
    /**
     * Get the reflector's classname
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return string|false
     */
    public static function classname(Reflector $reflector)
    {
        if ($reflector instanceof ReflectionFunction) {
            return false;
        }
        return \method_exists($reflector, 'getDeclaringClass')
            ? $reflector->getDeclaringClass()->getName()
            : $reflector->getName();
    }

    /**
     * Find "parent" phpDoc
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
        $regex = '/^(?:'
                . '(?:'
                    . '(?P<class>[\w\\\]+)' // classname
                    . '(?:::(?:'
                        . '(?P<constant>\w+)|'       // constant
                        . '(?:\$(?P<property>\w+))|' // property
                        . '(?:(?P<method>\w+)\(\))|' // method
                    . '))?'
                . ')|(?:'
                    . '(?P<function>[\w\\\]*\w+)\(\)'
                . ')'
            . ')$/';
        $matches = array();
        \preg_match($regex, $string, $matches);
        $defaults = \array_fill_keys(array('class', 'constant', 'property', 'method', 'function'), null);
        $matches = \array_merge($defaults, $matches);
        if ($matches['method']) {
            return new ReflectionMethod($matches['class'], $matches['method']);
        }
        if ($matches['property']) {
            return new ReflectionProperty($matches['class'], $matches['property']);
        }
        if ($matches['class'] && PHP_VERSION_ID >= 80100 && \enum_exists($matches['class'])) {
            $refEnum = new ReflectionEnum($matches['class']);
            if (empty($matches['constant'])) {
                return $refEnum;
            }
            return $refEnum->hasCase($matches['constant'])
                ? $refEnum->getCase($matches['constant']) // ReflectionEnumUnitCase or ReflectionEnumBackedCase
                : $refEnum->getReflectionConstant($matches['constant']);  // ReflectionClassConstant
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
}