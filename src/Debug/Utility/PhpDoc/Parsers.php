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
 */
class Parsers
{
    protected $helper;
    protected $parsers = array();
    protected $parseMethod;
    protected $parseParam;

    /**
     * Constructor
     *
     * @param Helper $helper Helper instance
     */
    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
        $this->parseMethod = new ParseMethod($helper);
        $this->parseParam = new ParseParam($helper);
        $this->setParsers();
    }

    /**
     * Get the parser for the given tag type
     *
     * @param string $tag phpDoc tag
     *
     * @return array
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
     * Get the tag parsers
     *
     * @return void
     */
    protected function setParsers() // phpcs:ignore SlevomatCodingStandard.Functions.FunctionLength.FunctionLength
    {
        $this->parsers = array(
            array(
                'callable' => array(
                    array($this->helper, 'extractTypeFromBody'),
                    $this->parseParam,
                ),
                'parts' => array('type', 'name', 'desc'),
                'tags' => array('param', 'property', 'property-read', 'property-write', 'var'),
            ),
            array(
                'callable' => array(
                    $this->parseMethod,
                ),
                'parts' => array('static', 'type', 'name', 'param', 'desc'),
                'regex' => '/'
                    . '(?:(?P<static>static)\s+)?'
                    . '(?:(?P<type>.*?)\s+)?'
                    . '(?P<name>\S+)'
                    . '\((?P<param>((?>[^()]+)|(?R))*)\)'  // see http://php.net/manual/en/regexp.reference.recursive.php
                    . '(?:\s+(?P<desc>.*))?'
                    . '/s',
                'tags' => array('method'),
            ),
            array(
                'callable' => array(
                    array($this->helper, 'extractTypeFromBody'),
                    static function (array $parsed, array $info) {
                        $parsed['type'] = $info['phpDoc']->type->normalize($parsed['type'], $info['className'], $info['fullyQualifyType']);
                        return $parsed;
                    },
                ),
                'parts' => array('type', 'desc'),
                'regex' => '/^(?P<type>.*?)'
                    . '(?:\s+(?P<desc>.*))?$/s',
                'tags' => array('return', 'throws'),
            ),
            array(
                'parts' => array('name', 'email', 'desc'),
                'regex' => '/^(?P<name>[^<]+)'
                    . '(?:\s+<(?P<email>\S*)>)?'
                    . '(?:\s+(?P<desc>.*))?' // desc isn't part of the standard
                    . '$/s',
                'tags' => array('author'),
            ),
            array(
                'parts' => array('uri', 'desc'),
                'regex' => '/^(?P<uri>\S+)'
                    . '(?:\s+(?P<desc>.*))?$/s',
                'tags' => array('link'),
            ),
            array(
                'parts' => array('uri', 'fqsen', 'desc'),
                'regex' => '/^(?:'
                    . '(?P<uri>https?:\/\/\S+)|(?P<fqsen>\S+)'
                    . ')'
                    . '(?:\s+(?P<desc>.*))?$/s',
                'tags' => array('see'),
            ),
            array(
                // default
                'parts' => array('desc'),
                'regex' => '/^(?P<desc>.*?)$/s',
                'tags' => array(),
            ),
        );
    }
}
