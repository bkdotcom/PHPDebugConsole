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
 * Parse 'method' tag  (magic methods)
 *
 * @psalm-import-type TagInfo from \bdk\Debug\Utility\PhpDoc
 */
class ParseMethod
{
    /** @var array */
    private $paramParseInfo = array(
        'depth' => 0,
        'params' => array(),
        'startPos' => 0,
        'str' => '',
        'strOpenedWith' => null,
    );

    /**
     * Parse @method tag
     *
     * @param array{
     *    type: string,
     *    name: string,
     *    paramsAndDesc: string|null,
     *    static: string|null,
     * } $parsed type, name, & desc
     * @param array $info tagName, raw tag string, etc
     *
     * @return array
     *
     * @psalm-param TagInfo $info
     */
    public function __invoke(array $parsed, array $info)
    {
        $parsed = $this->parseParamSplit($parsed);
        $parsed = $this->parseParam($parsed, $info);
        $parsed['static'] = $parsed['static'] !== null;
        $parsed['type'] = $info['phpDoc']->type->normalize($parsed['type'], $info['className'], $info['fullyQualifyType']);
        return $parsed;
    }

    /**
     * Parse @method parameters
     *
     * @param array $parsed Parsed method info
     * @param array $info   tagName, raw tag string, etc
     *
     * @return array
     *
     * @psalm-param TagInfo $info
     */
    protected function parseParam($parsed, array $info)
    {
        $phpDoc = $info['phpDoc'];
        $matches = array();
        $params = $parsed['param'];
        foreach ($params as $i => $str) {
            \preg_match('/^(?:([^=]*?)\s)?([^\s=]+)(?:\s*=\s*(.+))?$/', $str, $matches);
            $matches = \array_replace([null, null, null], $matches);
            $name = $matches[2];
            $paramInfo = array(
                'isVariadic' => \strpos($name, '...') !== false,
                'name' => \trim($name, '&$,.'),
                'type' => $phpDoc->type->normalize($matches[1], $info['className'], $info['fullyQualifyType']),
            );
            if (!empty($matches[3])) {
                $paramInfo['defaultValue'] = $matches[3];
            }
            \ksort($paramInfo);
            $params[$i] = $paramInfo;
        }
        $parsed['param'] = $params;
        return $parsed;
    }

    /**
     * Append current param to params array
     *
     * @return void
     */
    private function parseParamAppend()
    {
        $pos = $this->paramParseInfo['pos'];
        $param = \trim(\substr($this->paramParseInfo['str'], $this->paramParseInfo['startPos'], $pos - $this->paramParseInfo['startPos']));
        if ($param !== '') {
            $this->paramParseInfo['params'][] = $param;
        }
        $this->paramParseInfo['startPos'] = $pos + 1;
    }

    /**
     * Split @method parameter string into individual params
     *
     * @param array $parsed Parsed tag info
     *
     * @return array
     */
    private function parseParamSplit($parsed)
    {
        $str = $parsed['paramsAndDesc'];
        $this->paramParseInfo = array(
            'depth' => 0,
            'params' => array(),
            'pos' => 0,
            'startPos' => 1,
            'str' => $str,
            'strOpenedWith' => null,
        );
        $continue = true;
        for ($strlen = \strlen($str); $this->paramParseInfo['pos'] < $strlen && $continue; $this->paramParseInfo['pos']++) {
            $char = $str[ $this->paramParseInfo['pos'] ];
            $continue = $this->parseParamSplitTest1($char);
        }
        $parsed['param'] = $this->paramParseInfo['params'];
        $parsed['desc'] = \trim(\substr($str, $this->paramParseInfo['startPos']));
        unset($parsed['paramsAndDesc']);
        return $parsed;
    }

    /**
     * Test current character / position of tag string
     *
     * @param string $char Current character being tested
     *
     * @return bool
     */
    private function parseParamSplitTest1($char)
    {
        if ($this->paramParseInfo['strOpenedWith'] === null) {
            // we're not in a quoted string
            return $this->parseParamTest2($char);
        }
        if ($char === '\\') {
            // skip over character following backslash
            $this->paramParseInfo['pos']++;
        } elseif ($char === $this->paramParseInfo['strOpenedWith']) {
            // end of quoted string
            $this->paramParseInfo['strOpenedWith'] = null;
        }
        return true;
    }

    /**
     * Test and handle current character
     *
     * We know we are outside of a quoted string
     *
     * @param string $char current character
     *
     * @return bool whether to continue parsing
     */
    private function parseParamTest2($char)
    {
        $endParam = false;
        if ($char === ',') {
            $endParam = $this->paramParseInfo['depth'] === 1;
        } elseif (\in_array($char, ['\'', '"'], true)) {
            // we're opening a quoted string
            $this->paramParseInfo['strOpenedWith'] = $char;
        } elseif (\preg_match('#\G\s*=>\s*#', $this->paramParseInfo['str'], $matches, 0, $this->paramParseInfo['pos'])) {
            $this->paramParseInfo['pos'] += \strlen($matches[0]) - 1;
        } elseif (\in_array($char, ['<', '(', '[', '{'], true)) {
            $this->paramParseInfo['depth']++;
        } elseif (\in_array($char, ['>', ')', ']', '}'], true)) {
            $endParam = $this->paramParseInfo['depth'] === 1;
            $this->paramParseInfo['depth']--;
        }
        if ($endParam) {
            $this->parseParamAppend();
        }
        return !($endParam && $this->paramParseInfo['depth'] === 0);
    }
}
