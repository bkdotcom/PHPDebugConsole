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
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use Reflector;

/**
 * Get and parse phpDoc block
 */
class PhpDoc
{

    /** @var array */
    protected static $cache = array();
    protected static $parsers = array();
    protected static $reflectorStack = [];

    /**
     * Rudimentary doc-block parsing
     *
     * @param string|object|Reflector $what doc-block string, object, or Reflector instance
     *
     * @return array
     */
    public static function getParsed($what)
    {
        $hash = self::getHash($what);
        if (isset(self::$cache[$hash])) {
            return self::$cache[$hash];
        }
        $reflector = null;
        $comment = self::getCommentContent($what, $reflector);
        if ($reflector) {
            self::$reflectorStack[] = $reflector;
        }
        if (\is_array($comment)) {
            if ($reflector) {
                \array_pop(self::$reflectorStack);
            }
            return $comment;
        }
        $return = self::parseComment($comment);
        if ($hash) {
            // cache it
            self::$cache[$hash] = $return;
        }
        if ($reflector) {
            \array_pop(self::$reflectorStack);
        }
        return $return;
    }

    /**
     * Parse phpDoc tag
     *
     * Notes:
     *    \@method tag:
     *         optional "static" keyword may preceed type & name
     *             'static' returned as a boolean value
     *         parameters:  defaultValue key only returned if defined.
     *                      defaultValue is not parsed
     *
     * @param string $tag    tag type
     * @param string $tagStr tag values (ie "[Type] [name] [<description>]")
     *
     * @return array
     */
    public static function parseTag($tag, $tagStr = '')
    {
        $parsed = array();
        $parser = self::getParser($tag);
        $matches = array();
        \preg_match($parser['regex'], $tagStr, $matches);
        foreach ($parser['parts'] as $part) {
            $parsed[$part] = isset($matches[$part]) && $matches[$part] !== ''
                ? \trim($matches[$part])
                : null;
        }
        if (isset($parser['callable'])) {
            $parsed = \call_user_func($parser['callable'], $parsed);
        }
        $parsed['desc'] = self::trimDesc($parsed['desc']);
        return $parsed;
    }

    /**
     * Find "parent" phpDoc
     *
     * @param Reflector $reflector Reflector interface
     *
     * @return array Parsed phpDoc
     */
    public static function findInheritedDoc(Reflector $reflector)
    {
        if ($reflector instanceof ReflectionClass) {
            /*
                Class comment
            */
            $parentClass = $reflector->getParentClass();
            if ($parentClass) {
                return self::getParsed($parentClass);
            }
            $interfaces = $reflector->getInterfaceNames();
            foreach ($interfaces as $className) {
                $reflectionInterface = new ReflectionClass($className);
                return self::getParsed($reflectionInterface);
            }
            return self::getParsed('');
        }
        if ($reflector instanceof ReflectionMethod) {
            return self::findInheritedMethod($reflector);
        }
        if ($reflector instanceof ReflectionProperty) {
            return self::findInheritedProperty($reflector);
        }
        return self::getParsed('');
    }

    /**
     * Get comment contents
     *
     * @param Reflector|object|string $what      doc-block string or reflector object
     * @param null|Reflector          $reflector set to reflector instance
     *
     * @return string|array may return array if comment contains cached inheritdoc
     */
    private static function getCommentContent($what, &$reflector)
    {
        $reflector = self::getReflector($what);
        $docComment = $reflector
            ? \is_callable(array($reflector, 'getDocComment'))
                ? $reflector->getDocComment()
                : ''
            : $what;
        // remove opening "/**" and closing "*/"
        $docComment = \preg_replace('#^/\*\*(.+)\*/$#s', '$1', $docComment);
        // remove leading "*"s
        $docComment = \preg_replace('#^[ \t]*\*[ ]?#m', '', $docComment);
        $docComment = \trim($docComment);
        if ($reflector) {
            if (\strtolower($docComment) === '{@inheritdoc}') {
                return self::findInheritedDoc($reflector);
            }
            $docComment = \preg_replace_callback(
                '/{@inheritdoc}/i',
                function () use ($reflector) {
                    $phpDoc =  self::findInheritedDoc($reflector);
                    return $phpDoc['desc'];
                },
                $docComment
            );
        }
        return $docComment;
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
    private static function getHash($what)
    {
        if (\is_string($what)) {
            return $str;
        }
        if (!\is_object($what)) {
            return null;
        }
        $str = null;
        if ($what instanceof ReflectionClass) {
            $str = 'class:' . $what->getName();
        } elseif ($what instanceof ReflectionClassConstant) {
            $str = 'const:' . $what->getName();
        } elseif ($what instanceof ReflectionMethod) {
            $str = 'method:' . $what->getDeclaringClass()->getName() . '::' . $what->getName() . '()';
        } elseif ($what instanceof ReflectionFunction) {
            $str = 'func:' . $what->getName() . '()';
        } elseif ($what instanceof ReflectionProperty) {
            $str = 'prop:' . $what->getDeclaringClass()->getName() . '::' . $what->getName();
        } elseif (!($what instanceof Reflector)) {
            $str = \get_class($what);
        }
        return \md5($str);
    }

    /**
     * Get the parser for the given tag type
     *
     * @param string $tag phpDoc tag
     *
     * @return array
     */
    protected static function getParser($tag)
    {
        $tagParsers = static::$parsers ?: static::parsers();
        $parser = array();
        foreach ($tagParsers as $parser) {
            if (\in_array($tag, $parser['tags'])) {
                break;
            }
        }
        return $parser;
    }

    /**
     * Returns reflector for given object
     *
     * @param mixed $what string|Reflector|object
     *
     * @return Reflector|null
     */
    private static function getReflector($what)
    {
        $reflector = null;
        if ($what instanceof Reflector) {
            $reflector = $what;
        } elseif (\is_object($what)) {
            $reflector = new ReflectionObject($what);
        }
        return $reflector;
    }

    /**
     * Find phpDoc in parent classes / interfaces
     *
     * @param ReflectionMethod $reflector ReflectionMethod instance
     *
     * @return array
     */
    private static function findInheritedMethod(ReflectionMethod $reflector)
    {
        $name = $reflector->getName();
        $reflectionClass = $reflector->getDeclaringClass();
        $interfaces = $reflectionClass->getInterfaceNames();
        foreach ($interfaces as $className) {
            $reflectionInterface = new ReflectionClass($className);
            if ($reflectionInterface->hasMethod($name)) {
                return self::getParsed($reflectionInterface->getMethod($name));
            }
        }
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass && $parentClass->hasMethod($name)) {
            return self::getParsed($parentClass->getMethod($name));
        }
        return self::getParsed('');
    }

    /**
     * Find phpDoc in parent classes / interfaces
     *
     * @param ReflectionProperty $reflector ReflectionProperty instance
     *
     * @return array
     */
    private static function findInheritedProperty(ReflectionProperty $reflector)
    {
        $name = $reflector->getName();
        $reflectionClass = $reflector->getDeclaringClass();
        $interfaces = $reflectionClass->getInterfaceNames();
        foreach ($interfaces as $className) {
            $reflectionInterface = new ReflectionClass($className);
            if ($reflectionInterface->hasProperty($name)) {
                return self::getParsed($reflectionInterface->getProperty($name));
            }
        }
        $parentClass = $reflectionClass->getParentClass();
        if ($parentClass && $parentClass->hasProperty($name)) {
            return self::getParsed($parentClass->getProperty($name));
        }
        return self::getParsed('');
    }

    /**
     * Parse comment content
     *
     * Comment has already been stripped of comment *s
     *
     * @param string $comment comment content
     *
     * @return array
     */
    private static function parseComment($comment)
    {
        $return = array(
            'summary' => null,
            'desc' => null,
        );
        $matches = array();
        if (\preg_match('/^@/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            // we have tags
            $pos = $matches[0][1];
            $strTags = \substr($comment, $pos);
            $return = \array_merge($return, self::parseTags($strTags));
            // remove tags from comment
            $comment = $pos > 0
                ? \substr($comment, 0, $pos - 1)
                : '';
        }
        /*
            Do some string replacement
        */
        $comment = \preg_replace('/^\\\@/m', '@', $comment);
        $comment = \str_replace('{@*}', '*/', $comment);
        /*
            split into summary & description
            summary ends with empty whiteline or "." followed by \n
        */
        $split = \preg_split('/(\.[\r\n]+|[\r\n]{2})/', $comment, 2, PREG_SPLIT_DELIM_CAPTURE);
        $split = \array_replace(array('','',''), $split);
        // assume that summary and desc won't be "0"..  remove empty value and merge
        return \array_merge($return, \array_filter(array(
            'summary' => \trim($split[0] . $split[1]),    // split[1] is the ".\n"
            'desc' => \trim($split[2]),
        )));
    }

    /**
     * Parse @method parameters
     *
     * @param string $paramStr parameter string
     *
     * @return array
     */
    private static function parseParams($paramStr)
    {
        $params = $paramStr
            ? self::splitParams($paramStr)
            : array();
        $matches = array();
        foreach ($params as $i => $str) {
            \preg_match('/^(?:([^=]*?)\s)?([^\s=]+)(?:\s*=\s*(\S+))?$/', $str, $matches);
            $info = array(
                'type' => self::typeEdit($matches[1]) ?: null,
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
     * Get the tag parsers
     *
     * @return array
     */
    protected static function parsers()
    {
        self::$parsers = array(
            array(
                'tags' => array('param','property','property-read', 'property-write', 'var'),
                'parts' => array('type','name','desc'),
                'regex' => '/^'
                    . '(?:(?P<type>[^\$].*?)\s+)?'
                    . '(?:&?\$?(?P<name>\S+)\s+)?'
                    . '(?P<desc>.*)?'
                    . '$/s',
                'callable' => function ($parsed) {
                    if (\strpos($parsed['desc'], ' ') === false) {
                        if (!$parsed['type']) {
                            $parsed['type'] = $parsed['desc'];
                            $parsed['desc'] = null;
                        } elseif (!$parsed['name']) {
                            $parsed['name'] = \ltrim($parsed['desc'], '&$');
                            $parsed['desc'] = null;
                        }
                    }
                    $parsed['type'] = self::typeEdit($parsed['type']);
                    return $parsed;
                },
            ),
            array(
                'tags' => array('method'),
                'parts' => array('static', 'type', 'name', 'param', 'desc'),
                'regex' => '/'
                    . '(?:(?P<static>static)\s+)?'
                    . '(?:(?P<type>.*?)\s+)?'
                    . '(?P<name>\S+)'
                    . '\((?P<param>((?>[^()]+)|(?R))*)\)'  // see http://php.net/manual/en/regexp.reference.recursive.php
                    . '(?:\s+(?P<desc>.*))?'
                    . '/s',
                'callable' => function ($parsed) {
                    $parsed['param'] = self::parseParams($parsed['param']);
                    $parsed['static'] = $parsed['static'] !== null;
                    $parsed['type'] = self::typeEdit($parsed['type']);
                    return $parsed;
                },
            ),
            array(
                'tags' => array('return', 'throws'),
                'parts' => array('type','desc'),
                'regex' => '/^(?P<type>.*?)'
                    . '(?:\s+(?P<desc>.*))?$/s',
                'callable' => function ($parsed) {
                    $parsed['type'] = self::typeEdit($parsed['type']);
                    return $parsed;
                }
            ),
            array(
                'tags' => array('author'),
                'parts' => array('name', 'email','desc'),
                'regex' => '/^(?P<name>[^<]+)'
                    . '(?:\s+<(?P<email>\S*)>)?'
                    . '(?:\s+(?P<desc>.*))?'
                    . '$/s',
            ),
            array(
                'tags' => array('link'),
                'parts' => array('uri', 'desc'),
                'regex' => '/^(?P<uri>\S+)'
                    . '(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                'tags' => array('see'),
                'parts' => array('uri', 'fqsen', 'desc'),
                'regex' => '/^(?:'
                    . '(?P<uri>https?:\/\/\S+)|(?P<fqsen>\S+)'
                    . ')'
                    . '(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                // default
                'tags' => array(),
                'parts' => array('desc'),
                'regex' => '/^(?P<desc>.*?)$/s',
            ),
        );
        return self::$parsers;
    }

    /**
     * Parse tags
     *
     * @param string $str portion of phpdoc content that contains tags
     *
     * @return array
     */
    private static function parseTags($str)
    {
        $regexNotTag = '(?P<value>(?:(?!^@).)*)';
        $regexTags = '#^@(?P<tag>[\w-]+)[ \t]*' . $regexNotTag . '#sim';
        \preg_match_all($regexTags, $str, $matches, PREG_SET_ORDER);
        $singleTags = array('return');
        $return = array();
        foreach ($matches as $match) {
            $value = $match['value'];
            $value = \preg_replace('/\n\s*\*\s*/', "\n", $value);
            $value = \trim($value);
            $value = self::parseTag($match['tag'], $value);
            if (\in_array($match['tag'], $singleTags)) {
                $return[ $match['tag'] ] = $value;
                continue;
            }
            $return[ $match['tag'] ][] = $value;
        }
        return $return;
    }

    /**
     * Split parameter string into individual params
     *
     * @param string $paramStr parameter string
     *
     * @return string[]
     */
    private static function splitParams($paramStr)
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
     * Trim leading spaces from each description line
     *
     * @param string $desc string to trim
     *
     * @return string
     */
    private static function trimDesc($desc)
    {
        $lines = \explode("\n", $desc);
        $leadingSpaces = array();
        foreach ($lines as $line) {
            if (\strlen($line)) {
                $leadingSpaces[] = \strspn($line, ' ');
            }
        }
        \array_shift($leadingSpaces);    // first line will always have zero leading spaces
        $trimLen = $leadingSpaces
            ? \min($leadingSpaces)
            : 0;
        if (!$trimLen) {
            return $desc;
        }
        foreach ($lines as $i => $line) {
            $lines[$i] = $i > 0 && \strlen($line)
                ? \substr($line, $trimLen)
                : $line;
        }
        $desc = \implode("\n", $lines);
        return $desc;
    }

    /**
     * Convert "boolean" & "integer" to "bool" & "int"
     *
     * @param string $type type hint
     *
     * @return string
     */
    private static function typeEdit($type)
    {
        $types = \preg_split('/\s*\|\s*/', $type);
        foreach ($types as &$type) {
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
                    $type = \end(self::$reflectorStack)->getName();
                    break;
            }
            if ($isArray) {
                $type .= '[]';
            }
        }
        return \implode('|', $types);
    }
}
