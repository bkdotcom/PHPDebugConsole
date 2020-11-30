<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

/**
 * Html utilities
 */
class Html
{

    /**
     * self closing / empty / void html tags
     *
     * Not including 'command' (obsolete) and 'keygen' (deprecated),
     *
     * @var array
     */
    public static $htmlEmptyTags = array('area','base','br','col','embed','hr','img','input','link','meta','param','source','track','wbr');

    /**
     * Used by parseAttribString
     *
     * @var array
     */
    public static $htmlBoolAttr = array(
        /*
            enum attribues that behave like bool :
                'autocapitalize' :  on|off  (along with other values)
                'autocomplete' :    on|off
                'contenteditable' : true|false
                'draggable' :       true|false
                'spellcheck' :      true|false
                'translate' :       yes|no
        */

        // GLOBAL
        'hidden', 'itemscope',

        // FORM / INPUT
        'autofocus', 'checked', 'disabled', 'formnovalidate',
        'multiple', 'novalidate', 'readonly', 'required', 'selected',

        // AUDIO / VIDEO / TRACK
        'autoplay', 'controls', 'default', 'loop', 'muted', 'playsinline',

        // DETAILS / DIALOG
        'open',

        // OL
        'reversed',

        // IFRAME
        'frameborder', // removed from draft

        // IMG
        'ismap',

        // MARQUEE
        'truespeed',

        // OBJECT
        'typemustmatch', // removed from draft

        // SCRIPT
        'async', 'defer', 'nomodule',

        // STYLE
        'scoped',   // removed from draft

        // OBSOLETE / DEPRECATED / NEVER-A-THING
        'allowfullscreen',     // <iframe> - legacy: redefined as allow="fullscreen"
        'allowpaymentrequest', // <iframe> - legacy: redefined as allowe="payment"
        'compact',  // <dir> and <ol>
        'nohref',   // <area>
        'noresize', // <frame>
        'noshade',  // <hr>
        'nowrap',   // dt, dd, td, th
        'scrolling',// <iframe>
        'seamless', // <iframe> - removed from draft
        'sortable', // <table> - removed from draft
    );

    /**
     * Build attribute string
     *
     * Attributes will be sorted by name
     * class & style attributes may be provided as arrays
     * data-* attributes will be json-encoded (if non-string)
     * non data attribs with null value will not be output
     *
     * If a string is passed, it will be parsed and rebuilt
     *
     * @param array|string $attribs key/values
     *
     * @return string
     * @see    https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#autofilling-form-controls:-the-autocomplete-attribute
     */
    public static function buildAttribString($attribs)
    {
        if (\is_string($attribs)) {
            $attribs = static::parseAttribString($attribs);
        }
        $attribPairs = array();
        foreach ($attribs as $key => $val) {
            $val = self::buildAttribVal($key, $val);
            if ($val === null) {
                continue;
            }
            if ($val === '' && \in_array($key, array('class', 'style'))) {
                continue;
            }
            $attribPairs[] = $key . '="' . \htmlspecialchars($val) . '"';
        }
        \sort($attribPairs);
        return \rtrim(' ' . \implode(' ', $attribPairs));
    }

    /**
     * Build an html tag
     *
     * @param string       $tagName   tag name (ie "div" or "input")
     * @param array|string $attribs   key/value attributes
     * @param string       $innerhtml inner HTML if applicable
     *
     * @return string
     */
    public static function buildTag($tagName, $attribs = array(), $innerhtml = '')
    {
        $tagName = \strtolower($tagName);
        $attribStr = self::buildAttribString($attribs);
        return \in_array($tagName, self::$htmlEmptyTags)
            ? '<' . $tagName . $attribStr . ' />'
            : '<' . $tagName . $attribStr . '>' . $innerhtml . '</' . $tagName . '>';
    }

    /**
     * Parse string -o- attributes into a key=>value array
     *
     * @param string $str        string to parse
     * @param bool   $dataDecode (true) whether to json_decode data attributes
     *
     * @return array
     */
    public static function parseAttribString($str, $dataDecode = true)
    {
        $attribs = array();
        $regexAttribs = '/\b([\w\-]+)\b(?: \s*=\s*(["\'])(.*?)\\2 | \s*=\s*(\S+) )?/xs';
        \preg_match_all($regexAttribs, $str, $matches);
        $keys = \array_map('strtolower', $matches[1]);
        $values = \array_replace($matches[3], \array_filter($matches[4], 'strlen'));
        foreach ($keys as $i => $k) {
            $attribs[$k] = $values[$i];
            if (\in_array($k, self::$htmlBoolAttr)) {
                $attribs[$k] = true;
            }
        }
        \ksort($attribs);
        foreach ($attribs as $k => $v) {
            if (\is_string($v)) {
                $attribs[$k] = \htmlspecialchars_decode($v);
            }
            $isDataAttrib = \strpos($k, 'data-') === 0;
            if ($isDataAttrib && $dataDecode) {
                $val = $attribs[$k];
                $attribs[$k] = \json_decode((string) $attribs[$k], true);
                if ($attribs[$k] === null && $val !== 'null') {
                    $attribs[$k] = \json_decode('"' . $val . '"', true);
                }
            }
        }
        return $attribs;
    }

    /**
     * Parse HTML/XML tag
     *
     * returns array(
     *    'tagname' => string
     *    'attribs' => array
     *    'innerhtml' => string | null
     * )
     *
     * @param string $tag html tag to parse
     *
     * @return array|false
     */
    public static function parseTag($tag)
    {
        $regexTag = '#<([^\s>]+)([^>]*)>(.*)</\\1>#is';
        $regexTag2 = '#^<(?:\/\s*)?([^\s>]+)(.*?)\/?>$#s';
        $tag = \trim($tag);
        if (\preg_match($regexTag, $tag, $matches)) {
            return array(
                'tagname' => $matches[1],
                'attribs' => self::parseAttribString($matches[2]),
                'innerhtml' => $matches[3],
            );
        }
        if (\preg_match($regexTag2, $tag, $matches)) {
            return array(
                'tagname' => $matches[1],
                'attribs' => self::parseAttribString($matches[2]),
                'innerhtml' => null,
            );
        }
        return false;
    }

    /**
     * Convert array attribute value to string
     *
     * Convert class/style array value to string
     * This function is not meant for data attributs
     *
     * @param string $key   attribute name (class|style)
     * @param array  $value classnames for class, key/value for style
     *
     * @return string|null
     */
    private static function buildAttribArrayVal($key, $value = array())
    {
        if ($key === 'class') {
            if (!\is_array($value)) {
                $value = \explode(' ', $value);
            }
            $value = \array_filter(\array_unique($value));
            \sort($value);
            return \implode(' ', $value);
        }
        if ($key === 'style') {
            $keyValues = array();
            foreach ($value as $k => $v) {
                $keyValues[] = $k . ':' . $v . ';';
            }
            \sort($keyValues);
            return \implode('', $keyValues);
        }
        return null;
    }

    /**
     * Convert boolean attribute value to string
     *
     * @param string $key   attribute name
     * @param bool   $value true|false
     *
     * @return string|null
     */
    private static function buildAttribBoolVal($key, $value = true)
    {
        if (\in_array($key, array('autocapitalize', 'autocomplete'))) {
            // autocapitalize also takes other values...
            return $value ? 'on' : 'off';
        }
        if (\in_array($key, array('contenteditable', 'draggable', 'spellcheck'))) {
            return $value ? 'true' : 'false';
        }
        if ($key === 'translate') {
            return $value ? 'yes' : 'no';
        }
        if ($value) {
            // even if not a recognized boolean attribute
            return $key;
        }
        return null;
    }

    /**
     * Converts attribute value to string
     *
     * @param string $key key
     * @param mixed  $val value
     *
     * @return string|null
     */
    private static function buildAttribVal(&$key, $val)
    {
        if (\is_int($key)) {
            $key = $val;
            $val = true;
        }
        $key = \strtolower($key);
        if (\strpos($key, 'data-') === 0) {
            if (!\is_string($val)) {
                $val = \json_encode($val);
            }
            return $val;
        }
        if ($val === null) {
            return null;
        }
        if (\is_bool($val)) {
            return self::buildAttribBoolVal($key, $val);
        }
        if (\is_array($val) || $key === 'class') {
            return self::buildAttribArrayVal($key, $val);
        }
        return \trim($val);
    }
}
