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

namespace bdk\Debug\Utility\PhpDoc;

/**
 * PhpDoc parsing helper methods
 *
 * @psalm-import-type TagInfo from \bdk\Debug\Utility\PhpDoc
 * @psalm-import-type ParserInfo from \bdk\Debug\Utility\PhpDoc
 */
class Parsers
{
    /** @var Helper */
    protected $helper;
    /** @var ParserInfo[] */
    protected $parsers = array();
    /** @var ParseMethod */
    protected $parseMethod;
    /** @var ParseParam */
    protected $parseParam;

    /** @var array{string,int|string|null} */
    protected static $typeInfo = array();

    /**
     * Constructor
     *
     * @param Helper $helper Helper instance
     */
    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
        $this->parseMethod = new ParseMethod();
        $this->parseParam = new ParseParam();
        $this->setParsers();
    }

    /**
     * Get the parser for the given tag type
     *
     * @param string $tag phpDoc tag
     *
     * @return array
     *
     * @psalm-return ParserInfo
     */
    public function getTagParser($tag)
    {
        $parser = array();
        foreach ($this->parsers as $parser) {
            if (\in_array($tag, $parser['tags'], true)) {
                break;
            }
        }
        // if not found, last parser was default
        return \array_merge(array(
            'callable' => array(),
            'parts' => array(),
            'regex' => null,
        ), $parser);
    }

    /**
     * For a given tag string, extract the type definition
     *
     * type will be extracted from the beginning of the tag string
     * desc will be the remainder
     *
     * @param array $parsed Parsed tag info
     * @param array $info   tagName, raw tag string, etc
     *
     * @return array{desc:string|null,type:string}
     *
     * @psalm-param TagInfo $info
     *
     * @psalm-suppress PossiblyUnusedParam
     */
    public static function extractTypeFromBody(array $parsed, array $info)
    {
        self::$typeInfo = array(
            'depth' => 0,
            'pos' => 0,
            'str' => $info['tagStr'],
            'strOpenedWith' => null,
        );
        $continue = true;
        $tagStr = $info['tagStr'];
        for ($strlen = \strlen($tagStr); self::$typeInfo['pos'] < $strlen && $continue; self::$typeInfo['pos']++) {
            $char = $tagStr[ self::$typeInfo['pos'] ];
            $continue = self::extractTypeFromBodyTest1($char);
        }
        $type = \substr($tagStr, 0, self::$typeInfo['pos']);
        return \array_merge($parsed, array(
            'desc' => \trim(\substr($tagStr, \strlen($type))) ?: null,
            'type' => $type,
        ));
    }

    /**
     * Test current character / position of tag string
     *
     * @param string $char Current character being tested
     *
     * @return bool
     */
    private static function extractTypeFromBodyTest1($char)
    {
        if (self::$typeInfo['strOpenedWith'] === null) {
            // we're not in a quoted string
            return self::extractTypeFromBodyTest2($char);
        }
        if ($char === '\\') {
            // skip over character following backslash
            self::$typeInfo['pos']++;
        } elseif ($char === self::$typeInfo['strOpenedWith']) {
            // end of quoted string
            self::$typeInfo['strOpenedWith'] = null;
        }
        return true;
    }

    /**
     * Test current character / position of tag string
     *
     * We know we are not in a quoted string
     *
     * @param string $char Current character being tested
     *
     * @return bool
     */
    private static function extractTypeFromBodyTest2($char)
    {
        if (\in_array($char, array('\'', '"'), true)) {
            // we're opening a quoted string
            self::$typeInfo['strOpenedWith'] = $char;
        } elseif (\preg_match('#\G\s*[|&]\s*#', self::$typeInfo['str'], $matches, 0, self::$typeInfo['pos'])) {
            // intersection or union
            self::$typeInfo['pos'] += \strlen($matches[0]) - 1;
        } elseif (self::$typeInfo['depth'] === 0 && \trim($char) === '') {
            // whitespace found (not surrounding | or &)..  end of type
            self::$typeInfo['pos']--;
            return false;
        } elseif (\in_array($char, array('<', '(', '[', '{'), true)) {
            self::$typeInfo['depth']++;
        } elseif (\in_array($char, array('>', ')', ']', '}'), true)) {
            self::$typeInfo['depth']--;
        }
        return true;
    }

    /**
     * Get the tag parsers
     *
     * @return void
     */
    protected function setParsers() // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    {
        $this->parsers = array(
            array(
                'callable' => array(
                    array($this, 'extractTypeFromBody'),
                    $this->parseParam,
                ),
                'parts' => array('type', 'name', 'desc'),
                'tags' => array('param', 'property', 'property-read', 'property-write', 'var'),
            ),
            $this->parserReturnThrows(),
            $this->parserMethod(),
            $this->parserAuthor(),
            $this->parserLink(),
            $this->parserSee(),
            $this->parserDefault(),
        );
    }

    /**
     * Parser "definition" for @author tag
     *
     * @return ParserInfo
     */
    private function parserAuthor()
    {
        return array(
            'parts' => array('name', 'email', 'desc'),
            'regex' => '/^(?P<name>[^<]+)'
                . '(?:\s+<(?P<email>\S*)>)?'
                . '(?:\s+(?P<desc>.*))?' // desc isn't part of the standard
                . '$/s',
            'tags' => array('author'),
        );
    }

    /**
     * Default parser "definition"
     *
     * @return ParserInfo
     */
    private function parserDefault()
    {
        return array(
            'parts' => array('desc'),
            'regex' => '/^(?P<desc>.*?)$/s',
            'tags' => array(),
        );
    }

    /**
     * Parser "definition" for @link tag
     *
     * @return ParserInfo
     */
    private function parserLink()
    {
        return array(
            'parts' => array('uri', 'desc'),
            'regex' => '/^(?P<uri>\S+)'
                . '(?:\s+(?P<desc>.*))?$/s',
            'tags' => array('link'),
        );
    }

    /**
     * Parser "definition" for @method tag
     *
     * parsing the method params is non-trivial
     * strings may be improperly escaped
     *
     * @return ParserInfo
     */
    private function parserMethod()
    {
        return array(
            'callable' => array(
                $this->parseMethod,
            ),
            'parts' => array('static', 'type', 'name', 'param', 'desc', 'paramsAndDesc'),
            'regex' => '/^'
                . '(?:(?P<static>static)\s+)?'
                . '(?:(?P<type>[^()]*?)\s+)?'
                . '(?P<name>\S+)'
                . '\s*' // shouldn't be any space between method name & opening paren, but we'll allow it
                . '(?P<paramsAndDesc>\(.*\).*)'
                . '$/is',
            'tags' => array('method'),
        );
    }

    /**
     * Parser "definition" for @return & @throws tags
     *
     * @return ParserInfo
     */
    private function parserReturnThrows()
    {
        return array(
            'callable' => array(
                array($this, 'extractTypeFromBody'),
                /**
                 * @psalm-param TagInfo $info
                 */
                static function (array $parsed, array $info) {
                    $parsed['type'] = $info['phpDoc']->type->normalize($parsed['type'], $info['className'], $info['fullyQualifyType']);
                    return $parsed;
                },
            ),
            'parts' => array('type', 'desc'),
            'regex' => '/^(?P<type>.*?)'
                . '(?:\s+(?P<desc>.*))?$/s',
            'tags' => array('return', 'throws'),
        );
    }

    /**
     * Parser "definition" for @see tag
     *
     * @return ParserInfo
     */
    private function parserSee()
    {
        return array(
            'parts' => array('uri', 'fqsen', 'desc'),
            'regex' => '/^(?:'
                . '(?P<uri>https?:\/\/\S+)|(?P<fqsen>\S+)'
                . ')'
                . '(?:\s+(?P<desc>.*))?$/s',
            'tags' => array('see'),
        );
    }
}
