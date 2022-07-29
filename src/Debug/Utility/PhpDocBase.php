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

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Php;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
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
        $this->reflector = Php::getReflector($what, true);
        $docComment = $this->reflector
            ? \is_callable(array($this->reflector, 'getDocComment'))
                ? $this->reflector->getDocComment()
                : ''
            : $what;
        // remove opening "/**" and closing "*/"
        $docComment = \preg_replace('#^\s*/\*\*(.+)\*/$#s', '$1', (string) $docComment);
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
        if ($reflector instanceof ReflectionMethod) {
            return $this->getParentReflectorMpc($reflector, 'method');
        }
        if ($reflector instanceof ReflectionProperty) {
            return $this->getParentReflectorMpc($reflector, 'property');
        }
        if ($reflector instanceof ReflectionClassConstant) {
            return $this->getParentReflectorMpc($reflector, 'constant');
        }
        // ReflectionClass  (incl ReflectionObject & ReflectionEnum)
        return $this->getParentReflectorC($reflector);
    }

    /**
     * Parse @method parameters
     *
     * @param string $paramStr parameter string
     *
     * @return array
     */
    protected function parseMethodParams($paramStr)
    {
        $params = $paramStr
            ? self::paramsSplit($paramStr)
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
        if (\in_array($type, array('',null), true)) {
            return null;
        }
        return \preg_replace_callback('/\b(boolean|integer|self)\b/', function ($matches) {
            switch ($matches[1]) {
                case 'boolean':
                    return 'bool';
                case 'integer':
                    return 'int';
                case 'self':
                    return $this->reflector
                        ? $this->getReflectorsClassname($this->reflector)
                        : 'self';
            }
        }, $type);
    }

    /**
     * Hash reflector name
     *
     * @param Reflector $reflector Reflector instance
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
     * @param ReflectionClass $reflector ReflectionClass instance
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
     * @param string    $what      'method' or 'property', or 'constant'
     *
     * @return Reflector|null
     */
    private function getParentReflectorMpc(Reflector $reflector, $what)
    {
        $hasWhat = 'has' . \ucfirst($what);
        $getWhat = 'get' . \ucfirst($what);
        if ($what === 'constant') {
            $getWhat = 'getReflectionConstant';  // php 7.1
        }
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
     * Split @method parameter string into individual params
     *
     * @param string $paramStr parameter string
     *
     * @return string[]
     */
    private static function paramsSplit($paramStr)
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
}
