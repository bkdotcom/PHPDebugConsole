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
 * Parse 'param', 'property', 'property-read', 'property-write', & 'var'
 */
class ParseParam
{
    protected $helper;

    /**
     * Constructor
     *
     * @param PhpDocHelper $helper Helper instance
     */
    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Parse @method tag
     *
     * @param array $parsed type, name, & desc
     * @param array $info   tagName, raw tag string, etc
     *
     * @return array
     */
    public function __invoke(array $parsed, array $info)
    {
        $tagName = $info['tagName'];
        if (self::strStartsWithVariable($parsed['desc'])) {
            \preg_match('/^(\S*)/', $parsed['desc'], $matches);
            $parsed['name'] = $matches[1];
            $parsed['desc'] = \preg_replace('/^\S*\s+/', '', $parsed['desc']);
        }
        if ($tagName === 'param' && $parsed['name'] === null && \strpos($parsed['desc'], ' ') === false) {
            $parsed['name'] = $parsed['desc'];
            $parsed['desc'] = null;
        }
        if ($tagName === 'param') {
            $parsed['isVariadic'] = \strpos((string) $parsed['name'], '...') !== false;
        }
        if ($parsed['name']) {
            $parsed['name'] = \trim($parsed['name'], '&$,.');
        }
        if ($tagName === 'var' && $info['elementName'] !== null && $parsed['name'] !== $info['elementName']) {
            // name mismatch
            $parsed['desc'] = \trim($parsed['name'] . ' ' . $parsed['desc']);
            $parsed['name'] = $info['elementName'];
        }
        $parsed['type'] = $info['phpDoc']->type->normalize($parsed['type'], $info['className'], $info['fullyQualifyType']);
        return $parsed;
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
}
