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

use bdk\Debug\Utility\Reflection;
use bdk\Debug\Utility\UseStatements;
use Reflector;

/**
 * Get unparsed PhpDoc comment
 */
class PhpDocBase
{
    const FULLY_QUALIFY = 1;
    const FULLY_QUALIFY_AUTOLOAD = 2;

    /** @var Reflector */
    protected $reflector;

    protected $className = null;
    protected $fullyQualifyType = 0;

    /**
     * Get comment contents
     *
     * @param Reflector|object|string $what Object, Reflector, className, or doc-block string
     *
     * @return string
     */
    protected function getComment($what)
    {
        $this->reflector = Reflection::getReflector($what, true);
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
     * @param mixed $what classname, object, or Reflector
     *
     * @return string|null
     */
    protected static function getHash($what)
    {
        if (\is_string($what)) {
            return \md5($what);
        }
        if ($what instanceof Reflector) {
            return Reflection::hash($what);
        }
        $str = \is_object($what) ? \get_class($what) : \gettype($what);
        return \md5($str);
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
                'name' => $matches[2],
                'type' => $this->typeNormalize($matches[1]),
            );
            if (!empty($matches[3])) {
                $info['defaultValue'] = $matches[3];
            }
            \ksort($info);
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
        if (\in_array($type, array('', null), true)) {
            return null;
        }
        if (\preg_match('/array[<([{]/', $type)) {
            // type contains "complex" array type... don't deal with parsing
            return $type;
        }
        $types = \preg_split('#\s*\|\s*#', $type);
        foreach ($types as $i => $type) {
            $types[$i] = $this->typeNormalizeSingle($type);
        }
        return \implode('|', $types);
    }

    /**
     * Normalize individual part of type
     *
     * @param string $type type hint
     *
     * @return string
     */
    private function typeNormalizeSingle($type)
    {
        if (\strpos($type, '\\') === 0) {
            return \substr($type, 1);
        }
        $isArray = false;
        if (\substr($type, -2) === '[]') {
            $isArray = true;
            $type = \substr($type, 0, -2);
        }
        $translate = array(
            'boolean' => 'bool',
            'integer' => 'int',
            'self' => $this->className,
        );
        if (isset($translate[$type])) {
            $type = $translate[$type];
        } elseif ($this->fullyQualifyType && \in_array($type, $this->types, true) === false) {
            $type = $this->resolvePhpDocTypeClass($type);
        }
        if ($isArray) {
            $type .= '[]';
        }
        return $type;
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

    /**
     * Check type-hint in use statements, and whether relative or absolute
     *
     * @param string $type Type-hint
     *
     * @return string
     */
    private function resolvePhpDocTypeClass($type)
    {
        $first = \substr($type, 0, \strpos($type, '\\') ?: 0) ?: $type;
        $className = $this->className;
        $classReflector = Reflection::getReflector($className, true);
        $useStatements = UseStatements::getUseStatements($classReflector)['class'];
        if (isset($useStatements[$first])) {
            return $useStatements[$first] . \substr($type, \strlen($first));
        }
        $namespace = \substr($className, 0, \strrpos($className, '\\') ?: 0);
        if (!$namespace) {
            return $type;
        }
        /*
            Truly relative?  Or, does PhpDoc omit '\' ?
            Not 100% accurate, but check if assumed namespace'd class exists
        */
        $autoload = ($this->fullyQualifyType & self::FULLY_QUALIFY_AUTOLOAD) === self::FULLY_QUALIFY_AUTOLOAD;
        return \class_exists($namespace . '\\' . $type, $autoload)
            ? $namespace . '\\' . $type
            : $type;
    }
}
