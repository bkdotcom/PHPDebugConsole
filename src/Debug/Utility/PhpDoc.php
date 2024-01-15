<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\PhpDoc\Helper;
use bdk\Debug\Utility\PhpDoc\Parsers;
use bdk\Debug\Utility\PhpDoc\Type;
use bdk\Debug\Utility\Reflection;
use ReflectionMethod;
use Reflector;

/**
 * Get and parse phpDoc block
 */
class PhpDoc
{
    const FULLY_QUALIFY = 1;
    const FULLY_QUALIFY_AUTOLOAD = 2;

    /** @var Type */
    public $type;

    protected $className;
    protected $fullyQualifyType;

    /** @var string[] */
    protected static $cache = array();
    /** @var Reflector */
    protected $reflector;
    /** @var Helper */
    protected $helper;
    /** @var Parsers */
    protected $parsers;

    /**
     * Constructor
     */
    public function __construct()
    {
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
            ? \is_callable(array($this->reflector, 'getDocComment'))
                ? $this->reflector->getDocComment()
                : ''
            : $what;
        // remove opening "/**" and closing "*/"
        $comment = \preg_replace('#^\s*/\*\*(.+)\*/$#s', '$1', (string) $comment);
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
     *
     * @return array
     */
    public function getParsed($what, $fullyQualifyType = 0)
    {
        $hash = $this->hash($what);
        if (isset(self::$cache[$hash])) {
            return self::$cache[$hash];
        }
        $comment = $this->getComment($what);
        $parsed = $this->parse($comment, $this->reflector, $fullyQualifyType);
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
     * @return string|null
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
     * @param string    $comment          comment content
     * @param Reflector $reflector        Reflector instance
     * @param int       $fullyQualifyType Whether to fully qualify type(s)
     *
     * @return array
     */
    private function parse($comment, Reflector $reflector = null, $fullyQualifyType = 0)
    {
        $this->reflector = $reflector;
        $this->fullyQualifyType = $fullyQualifyType;
        $this->className = $reflector
            ? Reflection::classname($reflector)
            : null;
        $elementName = $reflector
            ? $reflector->getName()
            : null;
        $matches = array();
        $parsed = array();
        if (\preg_match('/^@/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            // we have tags
            $pos = $matches[0][1];
            $strTags = \substr($comment, $pos);
            $parsed = $this->parseTags($strTags, $elementName);
            // remove tags from comment
            $comment = $pos > 0
                ? \substr($comment, 0, $pos - 1)
                : '';
        }
        $parsed = \array_merge($parsed, $this->helper->parseDescSummary($comment));
        $parsed = \array_merge($this->parseGetDefaults($parsed), $parsed);
        $parsed = \array_merge($parsed, $this->replaceInheritDoc($parsed, $comment));
        \ksort($parsed);
        return $parsed;
    }

    /**
     * Get default values
     *
     * @param array $parsed Parsed tags
     *
     * @return array
     */
    private function parseGetDefaults(array $parsed)
    {
        $default = array(
            'desc' => null,
            'summary' => null,
        );
        if ($this->reflector instanceof ReflectionMethod || !empty($parsed['param'])) {
            $default['return'] = array(
                'desc' => null,
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
     *         optional "static" keyword may preceed type & name
     *             'static' returned as a boolean value
     *         parameters:  defaultValue key only returned if defined.
     *                      defaultValue is not parsed
     *
     * @param string $tagName     tag name/type
     * @param string $tagStr      tag values (ie "[Type] [name] [<description>]")
     * @param string $elementName class, property, method, or constant name if available
     *
     * @return array
     */
    private function parseTag($tagName, $tagStr = '', $elementName = null)
    {
        $parser = $this->parsers->getTagParser($tagName);
        $parsed = $parser['regex']
            ? $this->parseTagRegex($parser, $tagStr)
            : \array_fill_keys($parser['parts'], null);
        foreach ((array) $parser['callable'] as $callable) {
            $parsed = \array_merge($parsed, \call_user_func(
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
            ));
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
     * @return array
     */
    private function parseTagRegex(array $parser, $tagStr)
    {
        $parsed = array();
        $matches = array();
        \preg_match($parser['regex'], $tagStr, $matches);
        foreach ($parser['parts'] as $part) {
            $parsed[$part] = isset($matches[$part]) && $matches[$part] !== ''
                ? \trim($matches[$part])
                : null;
        }
        return $parsed;
    }

    /**
     * Parse tags
     *
     * @param string $str         Portion of phpdoc content that contains tags
     * @param string $elementName class, property, method, or constant name if available
     *
     * @return array
     */
    private function parseTags($str, $elementName = null)
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
     */
    private function replaceInheritDoc(array $parsed, $comment)
    {
        if (!$this->reflector) {
            return $parsed;
        }
        if (\strtolower($comment) === '{@inheritdoc}') {
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
        if (!isset($parsed['desc'])) {
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
