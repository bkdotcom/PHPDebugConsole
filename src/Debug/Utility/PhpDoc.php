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
 * Get and parse phpDoc block
 */
class PhpDoc extends PhpDocBase
{
    public $types = array(
        'array','bool','callable','float','int','iterable','null','object','string',
        '$this','self','static',
        'array-key','double','false','mixed','non-empty-array','resource','scalar','true','void',
        'key-of', 'value-of',
        'callable-string', 'class-string', 'literal-string', 'numeric-string', 'non-empty-string',
        'negative-int', 'positive-int',
        'int-mask', 'int-mask-of',
    );

    /** @var string[] */
    protected static $cache = array();
    protected $parsers = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setParsers();
    }

    /**
     * Rudimentary doc-block parsing
     *
     * @param string|object|Reflector $what doc-block string, object, or Reflector instance
     *
     * @return array
     */
    public function getParsed($what)
    {
        $hash = $this->getHash($what);
        if (isset(self::$cache[$hash])) {
            return self::$cache[$hash];
        }
        $comment = $this->getComment($what);
        $parsed = $this->parseComment($comment);
        $parsed = $this->replaceInheritDoc($parsed, $comment);
        self::$cache[$hash] = $parsed;
        return $parsed;
    }

    /**
     * @param string $body tag content
     *
     * @return string[]
     */
    protected static function extractTypeFromBody($body)
    {
        $type = '';
        $nestingLevel = 0;
        for ($i = 0, $iMax = \strlen($body); $i < $iMax; $i++) {
            $char = $body[$i];
            if ($nestingLevel === 0 && \trim($char) === '') {
                break;
            }
            $type .= $char;
            if (\in_array($char, array('<', '(', '[', '{'), true)) {
                $nestingLevel++;
                continue;
            }
            if (\in_array($char, array('>', ')', ']', '}'), true)) {
                $nestingLevel--;
                continue;
            }
        }
        return array(
            'type' => $type,
            'desc' => \trim(\substr($body, \strlen($type))) ?: null,
        );
    }

    /**
     * Get parent method/property/etc's parsed docblock
     *
     * @return array
     */
    private function getParentParsed()
    {
        $parentReflector = $this->getParentReflector($this->reflector);
        return $this->getParsed($parentReflector);
    }

    /**
     * Get the parser for the given tag type
     *
     * @param string $tag phpDoc tag
     *
     * @return array
     */
    private function getTagParser($tag)
    {
        $parser = array();
        foreach ($this->parsers as $parser) {
            if (\in_array($tag, $parser['tags'], true)) {
                break;
            }
        }
        // if not found, last parser was default
        return $parser;
    }

    /**
     * Parse comment content
     *
     * Comment has already been stripped of comment "*"s
     *
     * @param string $comment comment content
     *
     * @return array
     */
    private function parseComment($comment)
    {
        $parsed = array(
            'summary' => null,
            'desc' => null,
        );
        $elementName = $this->reflector ? $this->reflector->getName() : null;
        $matches = array();
        if (\preg_match('/^@/m', $comment, $matches, PREG_OFFSET_CAPTURE)) {
            // we have tags
            $pos = $matches[0][1];
            $strTags = \substr($comment, $pos);
            $parsed = \array_merge($parsed, $this->parseTags($strTags, $elementName));
            // remove tags from comment
            $comment = $pos > 0
                ? \substr($comment, 0, $pos - 1)
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
        return \array_merge($parsed, \array_filter(array(
            'summary' => \trim($split[0] . $split[1]),    // split[1] is the ".\n"
            'desc' => $this->trimDesc(\trim($split[2])),
        )));
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
        $parser = \array_merge(array(
            'parts' => array(),
            'regex' => null,
            'callable' => array(),
        ), $this->getTagParser($tagName));
        $parsed = \array_fill_keys($parser['parts'], null);
        if (isset($parser['regex'])) {
            $matches = array();
            \preg_match($parser['regex'], $tagStr, $matches);
            foreach ($parser['parts'] as $part) {
                $parsed[$part] = isset($matches[$part]) && $matches[$part] !== ''
                    ? \trim($matches[$part])
                    : null;
            }
        }
        foreach ((array) $parser['callable'] as $callable) {
            $parsed = \array_merge($parsed, \call_user_func($callable, $tagStr, $tagName, $parsed, $elementName));
        }
        $parsed['desc'] = $this->trimDesc($parsed['desc']);
        return $parsed;
    }

    /**
     * Parse tags
     *
     * @param string $str         portion of phpdoc content that contains tags
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
     * Replace "{@inheritDoc}""
     *
     * @param array  $parsed  Parsed PhpDoc comment
     * @param string $comment raw comment (asterisks removed)
     *
     * @return array Parsed PhpDoc comment
     */
    private function replaceInheritDoc($parsed, $comment)
    {
        if (!$this->reflector) {
            return $parsed;
        }
        if (\strtolower($comment) === '{@inheritdoc}') {
            // phpDoc considers this non-standard
            return $this->getParentParsed();
        }
        if (\strtolower($parsed['desc'] . $parsed['summary']) === '{@inheritdoc}') {
            // phpDoc considers this non-standard
            $parentParsed = $this->getParentParsed();
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
                $parentParsed = $this->getParentParsed();
                return $parentParsed['desc'];
            },
            $parsed['desc']
        );
        return $parsed;
    }

    /**
     * Get the tag parsers
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
     */
    protected function setParsers()
    {
        $this->parsers = array(
            array(
                'tags' => array('param','property','property-read', 'property-write', 'var'),
                'parts' => array('type','name','desc'),
                'callable' => array(
                    array($this, 'extractTypeFromBody'),
                    array($this, 'tagParam'),
                ),
            ),
            array(
                'tags' => array('method'),
                'parts' => array('static', 'type', 'name', 'param', 'desc'),
                'regex' => '/'
                    . '(?:(?P<static>static)\s+)?'
                    . '(?:(?P<type>.*?)\s+)?'
                    . '(?P<name>\S+)'
                    . '\((?P<param>((?>[^()]+)|(?R))*)\)'  // see http://php.net/manual/en/regexp.reference.recursive.php
                    . '(?:\s+(?P<desc>.*))?'
                    . '/s',
                'callable' => function ($tagStr, $tagName, $parsed) {
                    $parsed['param'] = $this->parseMethodParams($parsed['param']);
                    $parsed['static'] = $parsed['static'] !== null;
                    $parsed['type'] = $this->typeNormalize($parsed['type']);
                    return $parsed;
                },
            ),
            array(
                'tags' => array('return', 'throws'),
                'parts' => array('type','desc'),
                'regex' => '/^(?P<type>.*?)'
                    . '(?:\s+(?P<desc>.*))?$/s',
                'callable' => array(
                    array($this, 'extractTypeFromBody'),
                    function ($tagStr, $tagName, $parsed) {
                        $parsed['type'] = $this->typeNormalize($parsed['type']);
                        return $parsed;
                    },
                ),
            ),
            array(
                'tags' => array('author'),
                'parts' => array('name', 'email','desc'),
                'regex' => '/^(?P<name>[^<]+)'
                    . '(?:\s+<(?P<email>\S*)>)?'
                    . '(?:\s+(?P<desc>.*))?' // desc isn't part of the standard
                    . '$/s',
            ),
            array(
                'tags' => array('link'),
                'parts' => array('uri', 'desc'),
                'regex' => '/^(?P<uri>\S+)'
                    . '(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                'tags' => array('see'),
                'parts' => array('uri', 'fqsen', 'desc'),
                'regex' => '/^(?:'
                    . '(?P<uri>https?:\/\/\S+)|(?P<fqsen>\S+)'
                    . ')'
                    . '(?:\s+(?P<desc>.*))?$/s',
            ),
            array(
                // default
                'tags' => array(),
                'parts' => array('desc'),
                'regex' => '/^(?P<desc>.*?)$/s',
            ),
        );
    }

    /**
     * Test is string appears to start with a variable name
     *
     * @param string $str Stringto test
     *
     * @return bool
     */
    private static function strStartsWithVariable($str)
    {
        if ($str === null) {
            return false;
        }
        return \strpos($str, '$') === 0
           || \strpos($str, '&$') === 0
           || \strpos($str, '...$') === 0
           || \strpos($str, '&...$') === 0;
    }

    /**
     * clean up parsed tag
     * 'param','property','property-read', 'property-write', 'var'
     *
     * @param string $tagStr      phpDoc tag body
     * @param string $tagName     phpDoc tag name
     * @param array  $parsed      type, name, & desc
     * @param string $elementName name of element tag attached to
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    private function tagParam($tagStr, $tagName, $parsed, $elementName)
    {
        if (self::strStartsWithVariable($parsed['desc'])) {
            \preg_match('/^(\S*)/', $parsed['desc'], $matches);
            $parsed['name'] = $matches[1];
            $parsed['desc'] = \preg_replace('/^\S*\s+/', '', $parsed['desc']);
        }
        if ($tagName !== 'param' && $parsed['name'] !== null) {
            $parsed['name'] = \ltrim($parsed['name'], '&$');
        }
        if ($tagName === 'param' && $parsed['name'] === null && \strpos($parsed['desc'], ' ') === false) {
            $parsed['name'] = $parsed['desc'];
            $parsed['desc'] = null;
        }
        if ($tagName === 'var' && $elementName !== null && $parsed['name'] !== $elementName) {
            // name mismatch
            $parsed['desc'] = \trim($parsed['name'] . ' ' . $parsed['desc']);
            $parsed['name'] = $elementName;
        }
        $parsed['type'] = $this->typeNormalize($parsed['type']);
        return $parsed;
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
        $lines = \explode("\n", (string) $desc);
        $leadingSpaces = array();
        foreach (\array_filter($lines) as $line) {
            $leadingSpaces[] = \strspn($line, ' ');
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
}
