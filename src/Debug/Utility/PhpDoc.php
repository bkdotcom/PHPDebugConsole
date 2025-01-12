<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\PhpDoc\Helper;
use bdk\Debug\Utility\PhpDoc\Parsers;
use bdk\Debug\Utility\PhpDoc\Type;
use bdk\Debug\Utility\Reflection;
use ReflectionMethod;
use Reflector;

/**
 * Get and parse phpDoc block
 *
 * @psalm-type TagInfo = array{
 *   className: string,
 *   elementName: string,
 *   fullyQualifyType: string,
 *   phpDoc: \bdk\Debug\Utility\PhpDoc,
 *   reflector: Reflector,
 *   tagName: string,
 *   tagStr: string,
 * }
 * @psalm-type ParserInfo array{
 *   callable?: callable|list<callable>,
 *   parts:list<string>,
 *   regex:string,
 *   tags:list<string>,
 * }
 * @psalm-type Parsed = array{
 *   desc: string,
 *   summary?: string,
 *   ...<string,array>,
 * }
 */
class PhpDoc
{
    const FULLY_QUALIFY = 1;
    const FULLY_QUALIFY_AUTOLOAD = 2;

    /** @var Type */
    public $type;

    /** @var array<string,Parsed> */
    protected static $cache = array();

    /** @var string|null */
    protected $className;

    /** @var array<string,mixed> */
    protected $cfg = array();

    /** @var int */
    protected $fullyQualifyType = 0;

    /** @var Helper */
    protected $helper;

    /** @var Parsers */
    protected $parsers;

    /** @var Reflector|null */
    protected $reflector;

    /**
     * Constructor
     *
     * @param array<string,mixed> $cfg Configuration
     */
    public function __construct($cfg = array())
    {
        $this->cfg = \array_merge(array(
            'sanitizer' => ['bdk\Debug\Utility\HtmlSanitize', 'sanitize'],
        ), $cfg);
        $this->helper = new Helper();
        $this->parsers = new Parsers($this->helper);
        $this->type = new Type();
    }

    /**
     * Get comment contents
     *
     * @param Reflector|object|string $what Object, Reflector, className, or doc-block string
     *
     * @return string
     */
    public function getComment($what)
    {
        $this->reflector = Reflection::getReflector($what, true) ?: null;
        $comment = $this->reflector
            ? (\is_callable([$this->reflector, 'getDocComment'])
                ? $this->reflector->getDocComment()
                : '')
            : $what;
        if (\is_string($comment) === false) {
            return '';
        }
        // remove opening "/**" and closing "*/"
        $comment = \preg_replace('#^\s*/\*\*(.+)\*/$#s', '$1', $comment);
        // remove leading "*"s
        $comment = \preg_replace('#^[ \t]*\*[ ]?#m', '', $comment);
        return \trim($comment);
    }

    /**
     * Rudimentary doc-block parsing
     *
     * @param string|object|Reflector $what             doc-block string, object, or Reflector instance
     * @param int                     $fullyQualifyType Whether to fully qualify type(s)
     *                                                    Bitmask of FULLY_QUALIFY* constants
     * @param bool                    $sanitize         (true) Whether to sanitize comment
     *
     * @return array
     *
     * @psalm-return Parsed
     */
    public function getParsed($what, $fullyQualifyType = 0, $sanitize = true)
    {
        $hash = $this->hash($what);
        if (isset(self::$cache[$hash])) {
            return self::$cache[$hash];
        }
        $comment = $this->getComment($what);
        $parsed = $this->parse($comment, $this->reflector, $fullyQualifyType, $sanitize);
        self::$cache[$hash] = $parsed;
        return $parsed;
    }

    /**
     * PhpDoc won't be different between object instances
     *
     * Generate an identifier for what we're parsing
     *
     * @param mixed $what classname, object, or Reflector
     *
     * @return string
     */
    public static function hash($what)
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
     * Parse comment content
     *
     * Comment has already been stripped of comment "*"s
     *
     * @param string         $comment          comment content
     * @param Reflector|null $reflector        Reflector instance
     * @param int            $fullyQualifyType Whether to fully qualify type(s)
     * @param bool           $sanitize         Whether to sanitize comment
     *
     * @return array
     *
     * @psalm-return Parsed
     */
    private function parse($comment, $reflector = null, $fullyQualifyType = 0, $sanitize = true)
    {
        \bdk\Debug\Utility::assertType($reflector, 'Reflector');

        $this->reflector = $reflector;
        $this->fullyQualifyType = $fullyQualifyType;
        $this->className = $reflector
            ? Reflection::classname($reflector)
            : null;
        /** @var string|null */
        $elementName = $reflector
            ? $reflector->getName()
            : null;
        $parsed = array();
        list($comment, $strTags) = $this->separateComment($comment);
        $parsed = \array_merge($parsed, $this->helper->parseDescSummary($comment));
        $parsed = \array_merge($parsed, $this->parseTags($strTags, $elementName));
        $parsed = \array_merge($this->parseGetDefaults($parsed), $parsed);
        $parsed = \array_merge($parsed, $this->replaceInheritDoc($parsed, $comment));
        if ($sanitize) {
            $parsed = ArrayUtil::mapRecursive(function ($value, $key) {
                return \is_string($value) && \in_array($key, array('defaultValue', 'type', 'uri'), true) === false
                    ? \call_user_func($this->cfg['sanitizer'], $value)
                    : $value;
            }, $parsed);
        }
        \ksort($parsed);
        return $parsed;
    }

    /**
     * Split phpDoc comment into description/summary & tags
     *
     * @param string $comment phpDoc Comment content
     *
     * @return list<string>
     */
    private function separateComment($comment)
    {
        $matches = [];
        $strTags = '';
        if (\preg_match('/^@/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            // we have tags
            $pos = $matches[0][1];
            $strTags = \substr($comment, $pos);
            // remove tags from comment
            $comment = $pos > 0
                ? \substr($comment, 0, $pos - 1)
                : '';
        }
        return [$comment, $strTags];
    }

    /**
     * Get default values
     *
     * @param array $parsed Parsed tags
     *
     * @return array<string,string|null>
     *
     * @psalm-return Parsed
     */
    private function parseGetDefaults(array $parsed)
    {
        $default = array(
            'desc' => '',
            'summary' => '',
        );
        if ($this->reflector instanceof ReflectionMethod || !empty($parsed['param'])) {
            $default['return'] = array(
                'desc' => '',
                'type' => null,
            );
        }
        return $default;
    }

    /**
     * Get parent method/property/etc's parsed docblock
     *
     * @param Reflector $reflector Reflector instance
     *
     * @return array
     *
     * @psalm-return Parsed
     */
    private function parseParent(Reflector $reflector)
    {
        $parentReflector = Reflection::getParentReflector($reflector);
        return $this->getParsed($parentReflector, $this->fullyQualifyType);
    }

    /**
     * Parse phpDoc tag
     *
     * Notes:
     *    \@method tag:
     *         optional "static" keyword may precede type & name
     *             'static' returned as a boolean value
     *         parameters:  defaultValue key only returned if defined.
     *                      defaultValue is not parsed
     *
     * @param string $tagName     tag name/type
     * @param string $tagStr      tag values (ie "[Type] [name] [<description>]")
     * @param string $elementName class, property, method, or constant name if available
     *
     * @return Parsed
     */
    private function parseTag($tagName, $tagStr = '', $elementName = null)
    {
        $parser = $this->parsers->getTagParser($tagName);
        $parsed = $parser['regex']
            ? $this->parseTagRegex($parser, $tagStr)
            : \array_merge(
                \array_fill_keys($parser['parts'], null),
                \array_fill_keys(\array_intersect($parser['parts'], ['desc', 'summary']), '')
            );
        foreach ((array) $parser['callable'] as $callable) {
            $parsed = \call_user_func(
                $callable,
                $parsed,
                array(
                    'className' => $this->className,
                    'elementName' => $elementName,
                    'fullyQualifyType' => $this->fullyQualifyType,
                    'phpDoc' => $this,
                    'reflector' => $this->reflector,
                    'tagName' => $tagName,
                    'tagStr' => $tagStr,
                )
            );
        }
        $parsed['desc'] = $this->helper->trimDesc($parsed['desc']);
        \ksort($parsed);
        return $parsed;
    }

    /**
     * Parse tag from regex
     *
     * @param array  $parser Parser info (regex & parts)
     * @param string $tagStr Raw tag body
     *
     * @return array<string,string>
     */
    private function parseTagRegex(array $parser, $tagStr)
    {
        $parsed = array();
        $matches = array();
        \preg_match($parser['regex'], $tagStr, $matches);
        foreach ($parser['parts'] as $part) {
            $default = \in_array($part, array('desc', 'summary'), true)
                ? ''
                : null;
            $parsed[$part] = isset($matches[$part]) && $matches[$part] !== ''
                ? \trim($matches[$part])
                : $default;
        }
        return $parsed;
    }

    /**
     * Parse tags
     *
     * @param string      $str         Portion of phpdoc content that contains tags
     * @param string|null $elementName class, property, method, or constant name if available
     *
     * @return array<string,array>
     */
    private function parseTags($str, $elementName = null)
    {
        $regexNotTag = '(?P<value>(?:(?!^@).)*)';
        $regexTags = '#^@(?P<tag>[\w-]+)[ \t]*' . $regexNotTag . '#imsu';
        \preg_match_all($regexTags, $str, $matches, PREG_SET_ORDER);
        $singleTags = ['package', 'return'];
        $return = array();
        foreach ($matches as $match) {
            $value = $match['value'];
            $value = \preg_replace('/\n\s*\*\s*/', "\n", $value);
            $value = \trim($value);
            $value = $this->parseTag($match['tag'], $value, $elementName);
            if (\in_array($match['tag'], $singleTags, true)) {
                $return[ $match['tag'] ] = $value;
                continue;
            }
            $return[ $match['tag'] ][] = $value;
        }
        return $return;
    }

    /**
     * Replace "{@inheritDoc}"
     *
     * @param array  $parsed  Parsed PhpDoc comment
     * @param string $comment raw comment (asterisks removed)
     *
     * @return array Parsed PhpDoc comment
     *
     * @psalm-return Parsed
     */
    private function replaceInheritDoc(array $parsed, $comment)
    {
        if (!$this->reflector) {
            return $parsed;
        }
        if ($comment === '' || \strtolower($comment) === '{@inheritdoc}') {
            // phpDoc considers this non-standard
            return $this->parseParent($this->reflector);
        }
        if (\strtolower($parsed['desc'] . $parsed['summary']) === '{@inheritdoc}') {
            // phpDoc considers this non-standard
            $parentParsed = $this->parseParent($this->reflector);
            $parsed['summary'] = $parentParsed['summary'];
            $parsed['desc'] = $parentParsed['desc'];
            return $parsed;
        }
        $parsed['desc'] = \preg_replace_callback(
            '/{@inheritdoc}/i',
            function () {
                $parentParsed = $this->parseParent($this->reflector);
                return $parentParsed['desc'];
            },
            $parsed['desc']
        );
        return $parsed;
    }
}
