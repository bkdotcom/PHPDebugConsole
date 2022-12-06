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

/**
 * Html utilities
 */
class Html
{
    /** @var int whether to decode data attribute */
    const PARSE_ATTRIB_DATA = 1;
    /** @var int whether to explode class attribute */
    const PARSE_ATTRIB_CLASS = 2;
    /** @var int whether to cast numeric attribute value */
    const PARSE_ATTRIB_NUMERIC = 4;

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
     * enum attribues that behave like bool, but have "true" / "false" value
     *
     * @var array
     */
    public static $htmlBoolAttrEnum = array(
        'aria-checked',
        'aria-expanded',
        'aria-grabbed',
        'aria-hidden',
        'aria-pressed',
        'aria-selected',
        'contenteditable',
        'draggable',
        'spellcheck',
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
        foreach ($attribs as $name => $value) {
            // buildAttribNameValue updates $name by reference
            $nameVal = static::buildAttribNameValue($name, $value);
            $attribPairs[$name] = $nameVal;
        }
        $attribPairs = \array_filter($attribPairs, 'strlen');
        \ksort($attribPairs);
        return \rtrim(' ' . \implode(' ', $attribPairs));
    }

    /**
     * Build an html tag
     *
     * @param string         $tagName   tag name (ie "div" or "input")
     * @param array|string   $attribs   key/value attributes
     * @param string|Closure $innerhtml inner HTML if applicable
     *
     * @return string
     */
    public static function buildTag($tagName, $attribs = array(), $innerhtml = '')
    {
        $tagName = \strtolower($tagName);
        $attribStr = self::buildAttribString($attribs);
        if ($innerhtml instanceof \Closure) {
            $innerhtml = \call_user_func($innerhtml);
        }
        return \in_array($tagName, self::$htmlEmptyTags, true)
            ? '<' . $tagName . $attribStr . ' />'
            : '<' . $tagName . $attribStr . '>' . $innerhtml . '</' . $tagName . '>';
    }

    /**
     * Parse string of attributes into a key => value array
     *
     * @param string         $str     string to parse
     * @param int|null|false $options bitmask of PARSE_ATTRIB_x flags
     *
     * @return array
     */
    public static function parseAttribString($str, $options = null)
    {
        if ($options === false) {
            $options = 0;
        } elseif ($options === null) {
            $options = self::PARSE_ATTRIB_CLASS | self::PARSE_ATTRIB_DATA | self::PARSE_ATTRIB_NUMERIC;
        }
        $attribs = array();
        if ($options & self::PARSE_ATTRIB_CLASS) {
            // if "parsing" class attribute, always include it
            $attribs['class'] = array();
        }
        $regexAttribs = '/\b([\w\-]+)\b(?: \s*=\s*(["\'])(.*?)\\2 | \s*=\s*(\S+) )?/xs';
        \preg_match_all($regexAttribs, $str, $matches);
        $names = \array_map('strtolower', $matches[1]);
        $values = \array_replace($matches[3], \array_filter($matches[4], 'strlen'));
        foreach ($names as $i => $name) {
            $attribs[$name] = self::parseAttribValue($name, $values[$i], $options);
        }
        \ksort($attribs);
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
     * @param string   $tag     html tag to parse
     * @param int|null $options bitmask of PARSE_ATTRIB_x flags
     *
     * @return array|false
     */
    public static function parseTag($tag, $options = null)
    {
        $regexTag = '#<([^\s>]+)([^>]*)>(.*)</\\1>#is';
        $regexTag2 = '#^<(?:\/\s*)?([^\s>]+)(.*?)\/?>$#s';
        $tag = \trim($tag);
        if (\preg_match($regexTag, $tag, $matches)) {
            return array(
                'tagname' => $matches[1],
                'attribs' => self::parseAttribString($matches[2], $options),
                'innerhtml' => $matches[3],
            );
        }
        if (\preg_match($regexTag2, $tag, $matches)) {
            return array(
                'tagname' => $matches[1],
                'attribs' => self::parseAttribString($matches[2], $options),
                'innerhtml' => null,
            );
        }
        return false;
    }

    /**
     * Remove "invalid" characters from id attribute
     *
     * @param string $id Id value
     *
     * @return string
     */
    public static function sanitizeId($id)
    {
        if ($id === null) {
            return $id;
        }
        $id = \preg_replace('/^[^A-Za-z]+/', '', $id);
        // note that ":" and "." are  allowed chars but not practical... removing
        $id = \preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $id);
        $id = \preg_replace('/_+/', '_', $id);
        return $id;
    }

    /**
     * Buile name="value"
     *
     * @param string $name  Attribute name
     * @param mixed  $value Attribute value
     *
     * @return string
     */
    private static function buildAttribNameValue(&$name, $value)
    {
        // buildAttribVal updates $name by reference
        $value = self::buildAttribVal($name, $value);
        if ($value === null) {
            return '';
        }
        if ($value === '' && \in_array($name, array('class', 'style'), true)) {
            return '';
        }
        return $name . '="' . \htmlspecialchars($value) . '"';
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
        if ($key === 'id') {
            return static::sanitizeId($val);
        }
        switch (static::buildAttribValType($key, $val)) {
            case 'class':
                return static::buildAttribValClass($val);
            case 'data':
                return \is_string($val)
                    ? $val
                    : \json_encode($val);
            case 'valArray':
                return static::buildAttribValArray($key, $val);
            case 'valBool':
                return static::buildAttribValBool($key, $val);
            case 'valNull':
                return null;
            default:
                return \trim($val);
        }
    }

    /**
     * Determine the type of attribute being updated
     *
     * @param string $name Attribute name
     * @param mixed  $val  Attribute value
     *
     * @return string 'class', data', 'valArray', valBool', or 'valNull'
     */
    private static function buildAttribValType($name, $val)
    {
        if (\substr($name, 0, 5) === 'data-') {
            return 'data';
        }
        if ($val === null) {
            return 'valNull';
        }
        if (\is_bool($val)) {
            return 'valBool';
        }
        if ($name === 'class') {
            return 'class';
        }
        if (\is_array($val)) {
            return 'valArray';
        }
        return 'string';
    }

    /**
     * Convert array attribute value to string
     *
     * This function is not meant for data attributs
     *
     * @param string $key    attribute name (class|style)
     * @param array  $values key/value for style
     *
     * @return string|null
     */
    private static function buildAttribValArray($key, $values = array())
    {
        if ($key === 'style') {
            $keyValues = array();
            foreach ($values as $key => $val) {
                $keyValues[] = $key . ':' . $val . ';';
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
    private static function buildAttribValBool($key, $value = true)
    {
        if (\in_array($key, self::$htmlBoolAttrEnum, true)) {
            return \json_encode($value);
        }
        $values = array(
            'autocapitalize' => array('on','off'),
            'autocomplete' => array('on','off'), // autocapitalize also takes other values...
            'translate' => array('yes','no'),
        );
        if (isset($values[$key])) {
            return $value
                ? $values[$key][0]
                : $values[$key][1];
        }
        return $value
            ? $key // even if not a recognized boolean attribute
            : null;
    }

    /**
     * Build class attribute value
     * May pass
     *   string:  'foo bar'
     *   array:  [
     *      'foo',
     *      'bar' => true,
     *      'notIncl' => false,
     *      'key' => 'classValue',
     *   ]
     *
     * @param string|array $values Class values.  May be array or space-separated string
     *
     * @return string
     */
    private static function buildAttribValClass($values)
    {
        if (\is_array($values) === false) {
            $values = \explode(' ', $values);
        }
        $values = \array_map(static function ($key, $val) {
            return \is_bool($val)
                ? ($val ?  $key : null)
                : $val;
        }, \array_keys($values), $values);
        // only interested in unique, non-empty values
        $values = \array_filter(\array_unique($values));
        \sort($values);
        return \implode(' ', $values);
    }

    /**
     * Parse attribute value
     *
     * @param string $name    attribute name
     * @param string $val     value (assumed to be htmlspecialchar'd)
     * @param int    $options bitmask of self::PARSE_ATTRIB_x FLAGS
     *
     * @return mixed
     */
    private static function parseAttribValue($name, $val, $options)
    {
        $val = \htmlspecialchars_decode($val);
        if ($name === 'class') {
            return self::parseAttribClass($val, $options & self::PARSE_ATTRIB_CLASS);
        }
        if (\substr($name, 0, 5) === 'data-') {
            return self::parseAttribData($val, $options & self::PARSE_ATTRIB_DATA);
        }
        if (\in_array($name, self::$htmlBoolAttr, true)) {
            return true;
        }
        if (\in_array($name, self::$htmlBoolAttrEnum, true)) {
            return self::parseAttribBoolEnum($val);
        }
        if (\is_numeric($val)) {
            return self::parseAttribNumeric($val, $options & self::PARSE_ATTRIB_NUMERIC);
        }
        return $val;
    }

    /**
     * Convert bool enum attribute value to bool
     *
     * @param string $val enum attribute value
     *
     * @return bool
     *
     * @see self::$htmlBoolAttrEnum
     */
    private static function parseAttribBoolEnum($val)
    {
        $val = \strtolower($val);
        return \in_array($val, array('true','false'), true)
            ? $val === 'true'
            : false;
    }

    /**
     * Convert class attribute value to array of classes
     *
     * @param string $val    attribute value to decode
     * @param bool   $decode whether to decode
     *
     * @return array|string
     */
    private static function parseAttribClass($val, $decode)
    {
        if (!$decode) {
            return $val;
        }
        $classes = \explode(' ', $val);
        \sort($classes);
        return \array_unique($classes);
    }

    /**
     * Json decode data-xxx attribute
     *
     * @param string $val    attribute value to decode
     * @param bool   $decode whether to decode
     *
     * @return mixed
     */
    private static function parseAttribData($val, $decode)
    {
        if (!$decode) {
            return $val;
        }
        $decoded = \json_decode((string) $val, true);
        if ($decoded === null && $val !== 'null') {
            $decoded = \json_decode('"' . $val . '"', true);
        }
        return $decoded;
    }

    /**
     * Convert numeric attribute value to float/int
     *
     * @param string $val    enum attribute value
     * @param bool   $decode whether to decode
     *
     * @return string|float|int
     */
    private static function parseAttribNumeric($val, $decode)
    {
        return $decode
            ? \json_decode($val)
            : $val;
    }
}
