<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\Html;

/**
 * Sanitize html
 *
 * @link https://github.github.com/gfm/#disallowed-raw-html-extension-
 * @link https://github.com/gjtorikian/html-pipeline/blob/main/lib/html_pipeline/sanitization_filter.rb
 * @link https://gist.github.com/seanh/13a93686bf4c2cb16e658b3cf96807f2
 * @link https://github.com/github/markup/issues/245
 */
class HtmlSanitize
{
    public static $tagsWhitelist = [
        'a',
        'abbr',
        'b',
        'bdo',
        'blockquote',
        'br',
        'caption',
        'cite',
        'code',
        'dd',
        'del',
        'details',
        'dfn',
        'div',
        'dl',
        'dt',
        'em',
        'figcaption',
        'figure',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'ins',
        'kbd',
        'li',
        'mark',
        'ol',
        'p',
        'picture',
        'pre',
        'q',
        'rp',
        'rt',
        'ruby',
        's',
        'samp',
        'small',
        'source',
        'span',
        'strike',
        'strong',
        'sub',
        'summary',
        'sup',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'time',
        'tr',
        'tt',
        'ul',
        'var',
        'wbr',
    ];

    /**
     * allowed attributes per tag
     *
     * @var array<string,list<string>>
     *
     * phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
     */
    public static $attributes = array(
        'a' => ['href'],
        'blockquote' => ['cite'],
        'del' => ['cite'],
        'div' => ['itemscope', 'itemtype'],
        'img' => ['alt', 'loading', 'longdesc', 'src'],
        'ins' => ['cite'],
        'q' => ['cite'],
        'source' => ['srcset'],
        'all' => [
            'abbr',
            'accept',
            'accept-charset',
            'accesskey',
            'action',
            'align',
            'alt',
            'aria-describedby',
            'aria-hidden',
            'aria-label',
            'aria-labelledby',
            'axis',
            'border',
            'char',
            'charoff',
            'charset',
            'checked',
            'clear',
            'cols',
            'colspan',
            'compact',
            'coords',
            'datetime',
            'dir',
            'disabled',
            'enctype',
            'for',
            'frame',
            'headers',
            'height',
            'hreflang',
            'hspace',
            'id',
            'ismap',
            'label',
            'lang',
            'maxlength',
            'media',
            'method',
            'multiple',
            'name',
            'nohref',
            'noshade',
            'nowrap',
            'open',
            'progress',
            'prompt',
            'readonly',
            'rel',
            'rev',
            'role',
            'rows',
            'rowspan',
            'rules',
            'scope',
            'selected',
            'shape',
            'size',
            'span',
            'start',
            'summary',
            'tabindex',
            'type',
            'usemap',
            'valign',
            'value',
            'width',
            'itemprop',
        ],
    );

    /**
     * Sanitize html
     *
     * @param string $html html snippet
     *
     * @return string sanitized html
     */
    public static function sanitize($html)
    {
        $regEx = '#<
            (?P<slashA>/\s*)?
            (?P<tagname>[a-z][a-z0-9\-]*)
            (?P<attributes>[^<>]*?)
            (?P<slashB>/?)
            >#six';
        $htmlSpecialCharsFlags = ENT_COMPAT | ENT_HTML401;
        $htmlNew = '';
        $offset = 0;
        while (\preg_match($regEx, $html, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $offsetMatch = $matches[0][1];
            $precedingText = \substr($html, $offset, $offsetMatch - $offset);
            $tag = $matches[0][0];
            $tagName = \strtolower($matches['tagname'][0]);
            $htmlNew .= \htmlspecialchars($precedingText, $htmlSpecialCharsFlags, null, false);
            $htmlNew .= self::sanitizeTag($tagName, $matches);
            $offset = $offsetMatch + \strlen($tag);
        }
        $finalText = \substr($html, $offset);
        return $htmlNew . \htmlspecialchars($finalText, $htmlSpecialCharsFlags, null, false);
    }

    /**
     * Sanitize tag (opening, closing, or void/empty/self-closing)
     *
     * @param string $tagName tag name
     * @param array  $matches regex matches with offset
     *
     * @return string sanitized tag
     */
    private static function sanitizeTag($tagName, array $matches)
    {
        $htmlSpecialCharsFlags = ENT_COMPAT | ENT_HTML401;
        $isVoidTag = \in_array($tagName, Html::$tagsEmpty, true);
        $isNotAllowed = \in_array($tagName, self::$tagsWhitelist, true) === false
            || ($matches['slashA'][0] && $isVoidTag);
        if ($isNotAllowed) {
            return \htmlspecialchars($matches[0][0], $htmlSpecialCharsFlags);
        }
        if ($matches['slashA'][0]) {
            return '</' . $tagName . '>';
        }
        // allow tag, but sanitize attributes
        $attribs = self::sanitizeAttributes($tagName, $matches['attributes'][0]);
        return '<' . $tagName . $attribs . ($isVoidTag ? ' /' : '')  . '>';
    }

    /**
     * Remove attributes not whitelisted
     *
     * @param string $tagName      tag name (ie "div" or "input")
     * @param string $attribString attribute string
     *
     * @return array<string,mixed>
     */
    private static function sanitizeAttributes($tagName, $attribString)
    {
        $attributesAllowed = \array_merge(
            isset(self::$attributes[$tagName]) ? self::$attributes[$tagName] : [],
            self::$attributes['all']
        );
        $attribs = Html::parseAttribString($attribString);
        $attribs = \array_intersect_key($attribs, \array_flip($attributesAllowed));
        return Html::buildAttribString($attribs);
    }
}
