<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

/**
 * Get and parse phpDoc block
 */
class PhpDoc
{

    protected static $cache = array();

    /**
     * Rudimentary doc-block parsing
     *
     * @param string|object|\Reflector $what doc-block string, object, or reflector object
     *
     * @return array
     */
    public static function getParsed($what)
    {
        $reflector = null;
        $hash = null;
        if (is_object($what)) {
            $hash = self::getHash($what);
            if (isset(self::$cache[$hash])) {
                return self::$cache[$hash];
            }
            $reflector = $what instanceof \Reflector
                ? $what
                : new \ReflectionObject($what);
            $docComment = $reflector->getDocComment();
        } else {
            // assume string
            $docComment = $what;
        }
        // remove opening "/**" and closing "*/"
        $docComment = preg_replace('#^/\*\*(.+)\*/$#s', '$1', $docComment);
        // remove leading "*"s
        $docComment = preg_replace('#^[ \t]*\*[ ]?#m', '', $docComment);
        $docComment = trim($docComment);
        if (strtolower($docComment) == '{@inheritdoc}' && $reflector) {
            return static::findInheritedDoc($reflector);
        } else {
            $docComment = preg_replace_callback(
                '/{@inheritDoc}/i',
                function () use ($reflector) {
                    $phpDoc =  static::findInheritedDoc($reflector);
                    return $phpDoc['description'];
                },
                $docComment
            );
        }
        $desc = $docComment;
        $strTags = '';
        if (preg_match('/^@/m', $docComment, $matches, PREG_OFFSET_CAPTURE)) {
            // we have tags
            $pos = $matches[0][1];
            $desc = $pos
                ? substr($docComment, 0, $pos-1)
                : '';
            $strTags = substr($docComment, $pos);
        }
        /*
            Do some string replacement on summary/description
        */
        $desc = preg_replace('/^\\\@/m', '@', $desc);
        $desc = str_replace('{@*}', '*/', $desc);
        /*
            split desc into summary & description
            summary ends with empty whiteline or "." followed by \n
        */
        $split = preg_split('/(\.[\r\n]+|[\r\n]{2})/', $desc, 2, PREG_SPLIT_DELIM_CAPTURE);
        $split = array_replace(array('','',''), $split);
        $return = array(
            'summary' => trim($split[0].$split[1]),
            'description' => trim($split[2]),
        );
        foreach (array('summary','description') as $k) {
            if ($return[$k] === '') {
                $return[$k] = null;
            }
        }
        // now find tags
        $regexNotTag = '(?P<value>(?:(?!^@).)*)';
        $regexTags = '#^@(?P<tag>[\w-]+)[ \t]*'.$regexNotTag.'#sim';
        preg_match_all($regexTags, $strTags, $matches, PREG_SET_ORDER);
        $singleTags = array('return');
        foreach ($matches as $match) {
            $value = $match['value'];
            $value = preg_replace('/\n\s*\*\s*/', "\n", $value);
            $value = trim($value);
            $value = static::parseTag($match['tag'], $value);
            if (in_array($match['tag'], $singleTags)) {
                $return[ $match['tag'] ] = $value;
            } else {
                $return[ $match['tag'] ][] = $value;
            }
        }
        if ($hash) {
            self::$cache[$hash] = $return;
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
                'regex' => '/^(?P<type>.*?)'
                    .'(?:\s+&?\$?(?P<name>\S+))?'
                    .'(?:\s+(?P<desc>.*))?$/s',
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
            ),
            array(
                'tags' => array('return'),
                'parts' => array('type','desc'),
                'regex' => '/^(?P<type>.*?)'
                    .'(?:\s+(?P<desc>.*))?$/s',
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
            if (in_array($tag, $parser['tags'])) {
                break;
            }
        }
        preg_match($parser['regex'], $tagStr, $matches);
        foreach ($parser['parts'] as $part) {
            $parsed[$part] = isset($matches[$part]) && $matches[$part] !== ''
                ? trim($matches[$part])
                : null;
        }
        if ($tag == 'method') {
            $parsed['static'] = $parsed['static'] !== null;
            $parsed['param'] = self::parseParams($parsed['param']);
        }
        $parsed['desc'] = self::trimDesc($parsed['desc']);
        return $parsed;
    }

    /**
     * Find "parent" phpDoc
     *
     * @param \Reflector $reflector reflectionMethod
     *
     * @return array
     */
    public static function findInheritedDoc(\Reflector $reflector)
    {
        $name = $reflector->getName();
        $reflectionClass = $reflector->getDeclaringClass();
        $interfaces = $reflectionClass->getInterfaceNames();
        foreach ($interfaces as $className) {
            $reflectionClass2 = new \ReflectionClass($className);
            if ($reflectionClass2->hasMethod($name)) {
                return static::getParsed($reflectionClass2->getMethod($name));
            }
        }
        if ($reflectionClass = $reflectionClass->getParentClass()) {
            if ($reflectionClass->hasMethod($name)) {
                return static::getParsed($reflectionClass->getMethod($name));
            }
        }
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
        if (!($what instanceof \Reflector)) {
            $str = get_class($what);
        } elseif ($what instanceof \ReflectionClass) {
            $str = $what->getName();
        } elseif ($what instanceof \ReflectionMethod) {
            $str = $what->getDeclaringClass()->getName().'::'.$what->getName().'()';
        } elseif ($what instanceof \ReflectionFunction) {
            $str = $what->getName().'()';
        } elseif ($what instanceof \ReflectionProperty) {
            $str = $what->getDeclaringClass()->getName().'::'.$what->getName();
        }
        return $str
            ? md5($str)
            : null;
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
        $params = self::splitParams($paramStr);
        foreach ($params as $i => $str) {
            preg_match('/^(?:([^=]*?)\s)?([^\s=]+)(?:\s*=\s*(\S+))?$/', $str, $matches);
            $info = array(
                'type' => $matches[1] ?: null,
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
        $chars = str_split($paramStr);
        $params = array();
        foreach ($chars as $pos => $char) {
            switch ($char) {
                case ',':
                    if ($depth === 0) {
                        $params[] = trim(substr($paramStr, $startPos, $pos-$startPos));
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
        $params[] = trim(substr($paramStr, $startPos, $pos+1-$startPos));
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
        $lines = explode("\n", $desc);
        $leadingSpaces = array();
        foreach ($lines as $line) {
            if (strlen($line)) {
                $leadingSpaces[] = strspn($line, ' ');
            }
        }
        array_shift($leadingSpaces);    // first line will always have zero leading spaces
        $trimLen = $leadingSpaces
            ? min($leadingSpaces)
            : 0;
        if (!$trimLen) {
            return $desc;
        }
        foreach ($lines as $i => $line) {
            $lines[$i] = $i > 0 && strlen($line)
                ? substr($line, $trimLen)
                : $line;
        }
        $desc = implode("\n", $lines);
        return $desc;
    }
}
