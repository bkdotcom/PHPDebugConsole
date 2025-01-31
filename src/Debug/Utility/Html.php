<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3.1
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Php as PhpUtil;
use Closure;
use UnexpectedValueException;

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
    public static $tagsEmpty = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * The presence of these attributes = true / absence = false
     *
     * Value not necessary, but may be written as key="key"  (ie autofocus="autofocus" )
     *
     * @var array
     *
     * @link https://developer.mozilla.org/en-US/docs/Glossary/Boolean/HTML
     */
    public static $htmlBoolAttr = [
        // GLOBAL
        'hidden', 'itemscope',

        // FORM / INPUT
        'autofocus', 'checked', 'disabled', 'formnovalidate', 'multiple', 'novalidate', 'readonly', 'required', 'selected',

        // AUDIO / VIDEO / TRACK
        'autoplay', 'controls', 'default', 'loop', 'muted', 'playsinline',

        'open', // <details> & <dialog>
        'reversed', // <ol>
        'frameborder', // <iframe> removed from draft
        'ismap', // <img>
        'truespeed', // <marquee>
        'typemustmatch', // <object> removed from draft
        'async', 'defer', 'nomodule',  // <script>
        'scoped',   // <style> removed from draft

        // OBSOLETE / DEPRECATED / NEVER-A-THING
        'allowfullscreen',     // <iframe> - legacy: redefined as allow="fullscreen"
        'allowpaymentrequest', // <iframe> - legacy: redefined as allow="payment"
        'compact',  // <dir> and <ol>
        'nohref',   // <area>
        'noresize', // <frame>
        'noshade',  // <hr>
        'nowrap',   // dt, dd, td, th
        'scrolling',// <iframe>
        'seamless', // <iframe> - removed from draft
        'sortable', // <table> - removed from draft
    ];

    /**
     * Enum attributes that behave like bool, but have "true" / "false" value
     *
     * @var array
     */
    public static $htmlBoolAttrEnum = [
        'autocapitalize', // on|off (other values also accepted)
        'autocomplete', // on|off (other values also accepted)
        'translate', // yes|no

        // "true"/"false" :
        'aria-checked', 'aria-expanded', 'aria-grabbed', 'aria-hidden', 'aria-pressed', 'aria-selected',
        'contenteditable', 'draggable', 'spellcheck',
    ];

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
     * @param array<string,mixed>|string $attribs key/values
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
            $nameVal = HtmlBuild::buildAttribNameValue($name, $value);
            $attribPairs[$name] = $nameVal;
        }
        $attribPairs = \array_filter($attribPairs, 'strlen');
        \ksort($attribPairs);
        return \rtrim(' ' . \implode(' ', $attribPairs));
    }

    /**
     * Build an html tag
     *
     * @param string                     $tagName   tag name (ie "div" or "input")
     * @param array<string,mixed>|string $attribs   key/value attributes
     * @param string|Closure             $innerhtml inner HTML if applicable
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    public static function buildTag($tagName, $attribs = array(), $innerhtml = '')
    {
        $tagName = \strtolower($tagName);
        $attribStr = self::buildAttribString($attribs);
        if ($innerhtml instanceof Closure) {
            $innerhtml = \call_user_func($innerhtml);
            if (\is_string($innerhtml) === false) {
                throw new UnexpectedValueException(\sprintf(
                    'Innerhtml closure should return string.  Got %s',
                    PhpUtil::getDebugType($innerhtml)
                ));
            }
        }
        return \in_array($tagName, self::$tagsEmpty, true)
            ? '<' . $tagName . $attribStr . ' />'
            : '<' . $tagName . $attribStr . '>' . $innerhtml . '</' . $tagName . '>';
    }

    /**
     * Parse string of attributes into a key => value array
     *
     * @param string         $str     string to parse
     * @param int|null|false $options bitmask of PARSE_ATTRIB_x flags
     *
     * @return array<string,mixed>
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
        $regexAttribs = '/
            \b(?P<name>[\w\-]+)\b
            (?: \s*=\s* (?:
                (["\'])(?P<valQuoted>.*?)\\2
                | (?P<valUnquoted>\S+)
            ))?/xs';
        \preg_match_all($regexAttribs, $str, $matches);
        $values = \array_replace($matches['valQuoted'], \array_filter($matches['valUnquoted'], 'strlen'));
        foreach ($matches[1] as $i => $attribName) {
            $attribName = \strtolower($attribName);
            $attribs[$attribName] = HtmlParse::parseAttribValue($attribName, $values[$i], $options);
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
     * @param int|null $options bitmask of self::PARSE_ATTRIB_x flags
     *
     * @return array|false
     */
    public static function parseTag($tag, $options = null)
    {
        $regexTag = '#<(?P<tagname>[^\s<>]+)(?P<attributes>[^<>]*)>(?P<innerhtml>.*)</\\1>#is';
        $regexTag2 = '#^<(?:/\s*)?(?P<tagname>[^\s<>]+)(?P<attributes>[^<>]*?)/?>$#s'; // self-closing tag or closing tag
        $tag = \trim($tag);
        if (\preg_match($regexTag, $tag, $matches)) {
            // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
            return array(
                'tagname' => \strtolower($matches['tagname']),
                'attribs' => self::parseAttribString($matches['attributes'], $options),
                'innerhtml' => $matches['innerhtml'],
            );
        }
        if (\preg_match($regexTag2, $tag, $matches)) {
            // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
            return array(
                'tagname' => \strtolower($matches['tagname']),
                'attribs' => self::parseAttribString($matches['attributes'], $options),
                'innerhtml' => null,
            );
        }
        return false;
    }

    /**
     * Sanitize html
     *
     * @param string $html html snippet
     *
     * @return string sanitized html
     */
    public static function sanitize($html)
    {
        return HtmlSanitize::sanitize($html);
    }

    /**
     * Remove "invalid" characters from id attribute
     *
     * @param string|null $id Id value
     *
     * @return string|null
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
}
