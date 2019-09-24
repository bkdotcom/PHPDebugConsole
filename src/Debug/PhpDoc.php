<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use Reflector;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

/**
 * Get and parse phpDoc block
 */
class PhpDoc
{

    protected static $cache = array();
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
        $reflector = self::getReflector($what);
        if ($reflector) {
            self::$reflectorStack[] = $reflector;
            $comment = self::getCommentContent($reflector);
        } else {
            $comment = self::getCommentContent($what);
        }
        if (\is_array($comment)) {
            if ($reflector) {
                \array_pop(self::$reflectorStack);
            }
            return $comment;
        }
        $return = array(
            'summary' => null,
            'desc' => null,
        );
        if (\preg_match('/^@/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            // we have tags
            $pos = $matches[0][1];
            $strTags = \substr($comment, $pos);
            $return = \array_merge($return, self::parseTags($strTags));
            // remove tags from comment
            $comment = $pos > 0
                ? \substr($comment, 0, $pos-1)
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
        $return = \array_merge($return, \array_filter(array(
            'summary' => \trim($split[0].$split[1]),    // split[1] is the ".\n"
            'desc' => \trim($split[2]),
        )));
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
        $tagParsers = array(
            array(
                'tags' => array('param','property','property-read', 'property-write', 'var'),
                'parts' => array('type','name','desc'),
                'regex' => '/^'
                    .'(?:(?P<type>[^\$].*?)\s+)?'
                    .'(?:&?\$?(?P<name>\S+)\s+)?'
                    .'(?P<desc>.*)?'
                    .'$/s',
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
                    .'(?:(?P<static>static)\s+)?'
                    .'(?:(?P<type>.*?)\s+)?'
                    .'(?P<name>\S+)'
                    .'\((?P<param>((?>[^()]+)|(?R))*)\)'  // see http://php.net/manual/en/regexp.reference.recursive.php
                    .'(?:\s+(?P<desc>.*))?'
                    .'/s',
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
                    .'(?:\s+(?P<desc>.*))?$/s',
                'callable' => function ($parsed) {
                    $parsed['type'] = self::typeEdit($parsed['type']);
                    return $parsed;
                }
            ),
            array(
                'tags' => array('author'),
                'parts' => array('name', 'email','desc'),
                'regex' => '/^(?P<name>[^<]+)'
                    .'(?:\s+<(?P<email>\S*)>)?'
                    .'(?:\s+(?P<desc>.*))?'
                    .'$/s',
            ),
            array(
                'tags' => array('link'),
                'parts' => array('uri', 'desc'),
                'regex' => '/^(?P<uri>\S+)'
                    .'(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                'tags' => array('see'),
                'parts' => array('uri', 'fqsen', 'desc'),
                'regex' => '/^(?:'
                    .'(?P<uri>https?:\/\/\S+)|(?P<fqsen>\S+)'
                    .')'
                    .'(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                // default
                'tags' => array(),
                'parts' => array('desc'),
                'regex' => '/^(?P<desc>.*?)$/s',
            ),
        );
        foreach ($tagParsers as $parser) {
            if (\in_array($tag, $parser['tags'])) {
                break;
            }
        }
        \preg_match($parser['regex'], $tagStr, $matches);
        foreach ($parser['parts'] as $part) {
            $parsed[$part] = isset($matches[$part]) && $matches[$part] !== ''
                ? \trim($matches[$part])
                : null;
        }
        if (isset($parser['callable'])) {
            $parsed = \call_user_func($parser['callable'], $parsed, $tag);
        }
        $parsed['desc'] = self::trimDesc($parsed['desc']);
        return $parsed;
    }

    /**
     * Find "parent" phpDoc
     *
     * @param Reflector $reflector Reflector interface
     *
     * @return array
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
                $reflectionClass = new ReflectionClass($className);
                return self::getParsed($reflectionClass);
            }
        } else {
            /*
                Method or Property comment
            */
            $name = $reflector->getName();
            $reflectionClass = $reflector->getDeclaringClass();
            $interfaces = $reflectionClass->getInterfaceNames();
            foreach ($interfaces as $className) {
                $reflectionClass2 = new ReflectionClass($className);
                if ($reflectionClass2->hasMethod($name)) {
                    return self::getParsed($reflectionClass2->getMethod($name));
                }
            }
            $reflectionClass = $reflectionClass->getParentClass();
            if ($reflectionClass && $reflectionClass->hasMethod($name)) {
                return self::getParsed($reflectionClass->getMethod($name));
            }
        }
        return self::getParsed('');
    }

    /**
     * Get comment contents
     *
     * @param Reflector|string $what doc-block string, object, or reflector object
     *
     * @return string|array may return array if comment contains cached inheritdoc
     */
    private static function getCommentContent($what)
    {
        $reflector = null;
        if ($what instanceof Reflector) {
            $reflector = $what;
            $docComment = $reflector->getDocComment();
        } else {
            // assume code string
            $docComment = $what;
        }
        // remove opening "/**" and closing "*/"
        $docComment = \preg_replace('#^/\*\*(.+)\*/$#s', '$1', $docComment);
        // remove leading "*"s
        $docComment = \preg_replace('#^[ \t]*\*[ ]?#m', '', $docComment);
        $docComment = \trim($docComment);
        if ($reflector) {
            if (\strtolower($docComment) == '{@inheritdoc}') {
                return self::findInheritedDoc($reflector);
            } else {
                $docComment = \preg_replace_callback(
                    '/{@inheritdoc}/i',
                    function () use ($reflector) {
                        $phpDoc =  self::findInheritedDoc($reflector);
                        return $phpDoc['desc'];
                    },
                    $docComment
                );
            }
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
        $str = null;
        if (\is_object($what) && !($what instanceof Reflector)) {
            $str = \get_class($what);
        } elseif ($what instanceof ReflectionClass) {
            $str = $what->getName();
        } elseif ($what instanceof ReflectionMethod) {
            $str = $what->getDeclaringClass()->getName().'::'.$what->getName().'()';
        } elseif ($what instanceof ReflectionFunction) {
            $str = $what->getName().'()';
        } elseif ($what instanceof ReflectionProperty) {
            $str = $what->getDeclaringClass()->getName().'::'.$what->getName();
        } elseif (\is_string($what)) {
            $str = $what;
        }
        return $str
            ? \md5($str)
            : null;
    }

    /**
     * [getReflector description]
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
     * Parse tags
     *
     * @param string $str portion of phpdoc content that contains tags
     *
     * @return array
     */
    private static function parseTags($str)
    {
        $regexNotTag = '(?P<value>(?:(?!^@).)*)';
        $regexTags = '#^@(?P<tag>[\w-]+)[ \t]*'.$regexNotTag.'#sim';
        \preg_match_all($regexTags, $str, $matches, PREG_SET_ORDER);
        $singleTags = array('return');
        foreach ($matches as $match) {
            $value = $match['value'];
            $value = \preg_replace('/\n\s*\*\s*/', "\n", $value);
            $value = \trim($value);
            $value = self::parseTag($match['tag'], $value);
            if (\in_array($match['tag'], $singleTags)) {
                $return[ $match['tag'] ] = $value;
            } else {
                $return[ $match['tag'] ][] = $value;
            }
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
        $depth = 0;
        $startPos = 0;
        $chars = \str_split($paramStr);
        $params = array();
        foreach ($chars as $pos => $char) {
            switch ($char) {
                case ',':
                    if ($depth === 0) {
                        $params[] = \trim(\substr($paramStr, $startPos, $pos-$startPos));
                        $startPos = $pos + 1;
                    }
                    break;
                case '[':
                case '(':
                    $depth ++;
                    break;
                case ']':
                case ')':
                    $depth --;
                    break;
            }
        }
        $params[] = \trim(\substr($paramStr, $startPos, $pos+1-$startPos));
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
            if (\substr($type, -2) == '[]') {
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
