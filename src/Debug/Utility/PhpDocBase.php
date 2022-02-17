<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Reflector;

/**
 * Get unparsed PhpDoc comment
 */
class PhpDocBase
{
    /** @var Reflector */
    protected $reflector;

    /**
     * Get comment contents
     *
     * @param Reflector|object|string $what Object, Reflector, or doc-block string or
     *
     * @return string
     */
    protected function getComment($what)
    {
        $this->reflector = $this->getReflector($what);
        $docComment = $this->reflector
            ? \is_callable(array($this->reflector, 'getDocComment'))
                ? $this->reflector->getDocComment()
                : ''
            : $what;
        // remove opening "/**" and closing "*/"
        $docComment = \preg_replace('#^\s*/\*\*(.+)\*/$#s', '$1', $docComment);
        // remove leading "*"s
        $docComment = \preg_replace('#^[ \t]*\*[ ]?#m', '', $docComment);
        return \trim($docComment);
    }

    /**
     * PhpDoc won't be different between object instances
     *
     * Generate an identifier for what we're parsing
     *
     * @param mixed $what Object or Reflector
     *
     * @return string|null
     */
    protected static function getHash($what)
    {
        if (\is_string($what)) {
            return \md5($what);
        }
        if ($what instanceof Reflector) {
            return self::getHashFromReflector($what);
        }
        $str = \is_object($what) ? \get_class($what) : \gettype($what);
        return \md5($str);
    }

    /**
     * Find "parent" phpDoc
     *
     * @param Reflector $reflector Reflector interface
     *
     * @return Reflector|null
     */
    protected function getParentReflector(Reflector $reflector)
    {
        if ($reflector instanceof ReflectionClass) {
            return $this->getParentReflectorC($reflector);
        }
        if ($reflector instanceof ReflectionMethod) {
            return $this->getParentReflectorMp($reflector, 'method');
        }
        if ($reflector instanceof ReflectionProperty) {
            return $this->getParentReflectorMp($reflector, 'property');
        }
        return null;
    }

    /**
     * Parse @method parameters
     *
     * @param string $paramStr parameter string
     *
     * @return array
     */
    protected function parseParams($paramStr)
    {
        $params = $paramStr
            ? self::parseParamsSplit($paramStr)
            : array();
        $matches = array();
        foreach ($params as $i => $str) {
            \preg_match('/^(?:([^=]*?)\s)?([^\s=]+)(?:\s*=\s*(\S+))?$/', $str, $matches);
            $info = array(
                'type' => $this->typeNormalize($matches[1]),
                'name' => $matches[2],
            );
            if (!empty($matches[3])) {
                $info['defaultValue'] = $matches[3];
            }
            $params[$i] = $info;
        }
        return $params;
    }

    /**
     * Convert "self[]|null" to array
     *
     * @param string $type type hint
     *
     * @return string|null
     */
    protected function typeNormalize($type)
    {
        $types = \preg_split('/\s*\|\s*/', (string) $type);
        $types = \array_map(array($this, 'typeNormalizeSingle'), $types);
        return \implode('|', $types) ?: null;
    }

    /**
     * Hash reflector name
     *
     * @param Reflector $reflector [description]
     *
     * @return string
     */
    private static function getHashFromReflector(Reflector $reflector)
    {
        $str = '';
        $name = $reflector->getName();
        if (\method_exists($reflector, 'getDeclaringClass')) {
            $str .= $reflector->getDeclaringClass()->getName() . '::';
        }
        if ($reflector instanceof ReflectionClass) {
            $str = $name;
        } elseif ($reflector instanceof ReflectionMethod) {
            $str .= $name .= '()';
        } elseif ($reflector instanceof ReflectionProperty) {
            $str .= '$' . $name;
        } elseif ($reflector instanceof ReflectionClassConstant) {
            $str .= $name;
        }
        return \md5($str);
    }

    /**
     * Find parent class reflector or first interface
     *
     * @param ReflectionClass $reflector [description]
     *
     * @return ReflectionClass|null
     */
    private function getParentReflectorC(ReflectionClass $reflector)
    {
        $parentReflector = $reflector->getParentClass();
        if ($parentReflector) {
            return $parentReflector;
        }
        $interfaces = $reflector->getInterfaceNames();
        foreach ($interfaces as $className) {
            return new ReflectionClass($className);
        }
        return null;
    }

    /**
     * Find method/property phpDoc in parent classes / interfaces
     *
     * @param Reflector $reflector Reflector interface
     * @param string    $what      'method' or 'property'
     *
     * @return Reflector|null
     */
    private function getParentReflectorMp(Reflector $reflector, $what)
    {
        $hasWhat = 'has' . \ucfirst($what);
        $getWhat = 'get' . \ucfirst($what);
        $name = $reflector->getName();
        $reflectionClass = $reflector->getDeclaringClass();
        $interfaces = $reflectionClass->getInterfaceNames();
        foreach ($interfaces as $className) {
            $reflectionInterface = new ReflectionClass($className);
            if ($reflectionInterface->{$hasWhat}($name)) {
                return $reflectionInterface->{$getWhat}($name);
            }
        }
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass && $parentClass->{$hasWhat}($name)) {
            return $parentClass->{$getWhat}($name);
        }
        return null;
    }

    /**
     * Returns reflector
     *
     * Accepts:
     *   * object
     *   * Reflector
     *   * string  class
     *   * string  class::method()
     *   * string  class::$property
     *   * string  class::CONSTANT
     *
     * @param mixed $what string|Reflector|object
     *
     * @return Reflector|null
     */
    private static function getReflector($what)
    {
        if ($what instanceof Reflector) {
            return $what;
        }
        if (\is_object($what)) {
            return new ReflectionObject($what);
        }
        return \is_string($what)
            ? self::getReflectorFromString($what)
            : null;
    }

    /**
     * Get the current classname
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return string
     */
    private function getReflectorsClassname(Reflector $reflector)
    {
        return \method_exists($reflector, 'getDeclaringClass')
            ? $reflector->getDeclaringClass()->getName()
            : $reflector->getName();
    }

    /**
     * String to Reflector
     *
     * @param string $string string representing class, method, property, or class constant
     *
     * @return Reflector|nul
     */
    private static function getReflectorFromString($string)
    {
        $regex = '/^'
            . '(?P<class>[\w\\\]+)' // classname
            . '(?:::(?:'
                . '(?P<constant>\w+)|'       // constant
                . '(?:\$(?P<property>\w+))|' // property
                . '(?:(?P<method>\w+)\(\))|' // method
            . '))?'
            . '$/';
        $matches = array();
        \preg_match($regex, $string, $matches);
        $defaults = \array_fill_keys(array('class','constant','property','method'), null);
        $matches = \array_merge($defaults, $matches);
        if ($matches['method']) {
            return new ReflectionMethod($matches['class'], $matches['method']);
        }
        if ($matches['property']) {
            return new ReflectionProperty($matches['class'], $matches['property']);
        }
        if ($matches['constant']) {
            return new ReflectionClassConstant($matches['class'], $matches['constant']);
        }
        if ($matches['class']) {
            return new ReflectionClass($matches['class']);
        }
        return null;
    }

    /**
     * Split parameter string into individual params
     *
     * @param string $paramStr parameter string
     *
     * @return string[]
     */
    private static function parseParamsSplit($paramStr)
    {
        $chars = \str_split($paramStr);
        $depth = 0;
        $params = array();
        $pos = 0;
        $startPos = 0;
        foreach ($chars as $pos => $char) {
            switch ($char) {
                case ',':
                    if ($depth === 0) {
                        $params[] = \trim(\substr($paramStr, $startPos, $pos - $startPos));
                        $startPos = $pos + 1;
                    }
                    break;
                case '[':
                case '(':
                    $depth++;
                    break;
                case ']':
                case ')':
                    $depth--;
                    break;
            }
        }
        $params[] = \trim(\substr($paramStr, $startPos, $pos + 1 - $startPos));
        return $params;
    }

    /**
     * Convert "boolean" & "integer" to "bool" & "int", self[], etc
     *
     * @param string $type type hint
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via typeNormalize
     */
    private function typeNormalizeSingle($type)
    {
        $isArray = false;
        if (\substr($type, -2) === '[]') {
            $isArray = true;
            $type = \substr($type, 0, -2);
        }
        switch ($type) {
            case 'boolean':
                $type = 'bool';
                break;
            case 'integer':
                $type = 'int';
                break;
            case 'self':
                if (!$this->reflector) {
                    break;
                }
                $type = $this->getReflectorsClassname($this->reflector);
                break;
        }
        if ($isArray) {
            $type .= '[]';
        }
        return $type;
    }
}