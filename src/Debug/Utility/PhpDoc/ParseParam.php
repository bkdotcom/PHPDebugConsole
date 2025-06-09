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

namespace bdk\Debug\Utility\PhpDoc;

/**
 * Parse 'param', 'property', 'property-read', 'property-write', & 'var'
 *
 * @psalm-import-type TagInfo from \bdk\Debug\Utility\PhpDoc
 */
class ParseParam
{
    /**
     * Parse @method tag
     *
     * @param array $parsed type, name, & desc
     * @param array $info   tagName, raw tag string, etc
     *
     * @return array
     *
     * @psalm-param TagInfo $info
     */
    public function __invoke(array $parsed, array $info)
    {
        $tagName = $info['tagName'];
        if (self::strStartsWithVariable($parsed['desc'])) {
            \preg_match('/^(\S*)/', $parsed['desc'], $matches);
            $parsed['name'] = $matches[1];
            $parsed['desc'] = \preg_replace('/^\S*\s+/', '', $parsed['desc']);
        }
        if ($tagName === 'param') {
            $parsed = $this->processParamTag($parsed);
        }
        if ($parsed['name']) {
            $parsed['name'] = \trim($parsed['name'], '&$,.');
        }
        if ($tagName === 'var') {
            $parsed = $this->processVarTag($parsed, $info);
        }
        $parsed['type'] = $info['phpDoc']->type->normalize(
            $parsed['type'],
            $info['className'],
            $info['fullyQualifyType']
        );
        return $parsed;
    }

    /**
     * Process param tag specific logic
     *
     * @param array $parsed Parsed tag data
     *
     * @return array Updated parsed data
     */
    private function processParamTag(array $parsed)
    {
        // Handle case where name is null but description looks like it should be name
        if ($parsed['name'] === null && \strpos((string) $parsed['desc'], ' ') === false) {
            $parsed['name'] = $parsed['desc'];
            $parsed['desc'] = '';
        }

        // Set isVariadic flag
        $parsed['isVariadic'] = \strpos((string) $parsed['name'], '...') !== false;

        return $parsed;
    }

    /**
     * Handle name mismatch in var tags
     *
     * @param array $parsed Parsed tag data
     * @param array $info   Tag info
     *
     * @return array Updated parsed data
     */
    private function processVarTag(array $parsed, array $info)
    {
        if ($info['elementName'] !== null && $parsed['name'] !== $info['elementName']) {
            $parsed['desc'] = \trim($parsed['name'] . ' ' . $parsed['desc']);
            $parsed['name'] = $info['elementName'];
        }
        return $parsed;
    }

    /**
     * Test if string appears to start with a variable name
     *
     * @param string $str String to test
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
