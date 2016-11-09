<?php
/**
 * Methods used to display objects
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2016 Brad Kent
 * @version   v1.3.3
 */

namespace bdk\Debug;

/**
 * Use reflection to get dump object info
 */
class PhpDoc
{

    /**
     * Rudimentary doc-comment parsing
     *
     * @param string $docComment doc-comment string
     *
     * @return array
     */
    public static function parse($docComment)
    {
        // remove openining /** and closing */
        $docComment = preg_replace('#^/\*\*(.+)\*/$#s', '$1', $docComment);
        // remove leading "*"s
        $docComment = preg_replace('#^[ \t]*\*[ ]?#m', '', $docComment);
        $docComment = trim($docComment);
        $tags = array(
            'comment' => array( !empty($docComment) ? $docComment : '' ),
        );
        $regexNotTag = '(?P<value>(?:(?!^@).)*)';
        $regexTags = '#^@(?P<tag>\w+)[ \t]*'.$regexNotTag.'#sim';
        // get general comment
        // go through each line rather than using regexNotTag... which caused segfault on larger comments
        $lines = explode("\n", $docComment);
        foreach ($lines as $i => $line) {
            if (!empty($line) && $line{0} == '@') {
                $tags['comment'][0] = trim(implode("\n", array_slice($lines, 0, $i)));
                break;
            }
        }
        $tags['comment'][0] = trim($tags['comment'][0]);
        if ($tags['comment'][0] === '') {
            $tags['comment'][0] = null;
        }
        preg_match_all($regexTags, $docComment, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $value = $match['value'];
            $value = preg_replace('/\n\s*\*\s*/', "\n", $value);
            $value = trim($value);
            $tags[ $match['tag'] ][] = $value;
        }
        return $tags;
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
        $info = array(
            'param' => array(
                'parts' => array('type','name','desc'),
                'regex' => '/^(?P<type>.*?)'
                    .'(?:\s+&?\$?(?P<name>\S+))?'
                    .'(?:\s+(?P<desc>.*))?$/s',
            ),
            'default' => array(
                'parts' => array('type','desc'),
                'regex' => '/(?P<type>.*?)\s+'
                    .'(?P<desc>.*)?/s',
            ),
        );
        $info = isset($info[$tag])
            ? $info[$tag]
            : $info['default'];
        preg_match($info['regex'], $tagStr, $matches);
        foreach ($info['parts'] as $i => $part) {
            $parsed[$part] = isset($matches[$i]) && $matches[$i] !== ''
                ? trim($matches[$i])
                : null;
        }
        return $parsed;
    }
}
