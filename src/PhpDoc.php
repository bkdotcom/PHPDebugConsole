<?php
/**
 * Methods used to display objects
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2016 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * Use reflection to get dump object info
 */
class PhpDoc
{

    /**
     * Rudimentary doc-block parsing
     *
     * @param string|object|\Reflector $what doc-block string, object, or reflector object
     *
     * @return array
     */
    public static function getParsed($what)
    {
        /*
        return array(
            'summary' => null,
            'description' => null,
        );
        */
        $reflector = null;
        if (is_object($what)) {
            if ($what instanceof \Reflector) {
                $reflector = $what;
                $docComment = $what->getDocComment();
            } else {
                $reflector = new \ReflectionObject($what);
                $docComment = $reflector->getDocComment();
            }
        } else {
            $docComment = $what;
        }
        // remove openining "/**" and closing "*/"
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
        $regexTags = '#^@(?P<tag>\w+)[ \t]*'.$regexNotTag.'#sim';
        preg_match_all($regexTags, $strTags, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $value = $match['value'];
            $value = preg_replace('/\n\s*\*\s*/', "\n", $value);
            $value = trim($value);
            $return[ $match['tag'] ][] = static::parseTag($match['tag'], $value);
        }
        return $return;
    }

    /**
     * Parse phpDoc tag
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
                'tags' => array('param'),
                'parts' => array('type','name','desc'),
                'regex' => '/^(?P<type>.*?)'
                    .'(?:\s+&?\$?(?P<name>\S+))?'
                    .'(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                'tags' => array('return'),
                'parts' => array('type','desc'),
                'regex' => '/^(?P<type>.*?)'
                    .'(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                'tags' => array('var'),
                'parts' => array('type','name','desc'),
                'regex' => '/^(?P<type>.*?)'
                    .'(?:\s+&?\$(?P<name>\S+))?'
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
        foreach ($parser['parts'] as $i => $part) {
            $parsed[$part] = isset($matches[$i+1]) && $matches[$i+1] !== ''
                ? trim($matches[$i+1])
                : null;
        }
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
}
