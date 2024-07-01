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
        if ($parsed['desc'] === '') {
            $parsed['desc'] = null;
        }
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
            $matches = \array_replace(array(null, null, null), $matches);
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
     * Append param to params array
     *
     * @param int $pos current character position
     *
     * @return void;
     */
    private function parseParamAppend($pos)
    {
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
            'startPos' => 1,
            'str' => $str,
            'strOpenedWith' => null,
        );
        $chars = \str_split($str);
        $count = \count($chars);
        $continue = true;
        for ($pos = 0; $pos < $count && $continue; $pos++) {
            $char = $chars[$pos];
            if ($this->paramParseInfo['strOpenedWith'] === null) {
                // we're not in a quoted string
                $continue = $this->parseParamChar($char, $pos);
            } elseif ($char === '\\') {
                $pos++;
            } elseif ($char === $this->paramParseInfo['strOpenedWith']) {
                $this->paramParseInfo['strOpenedWith'] = null;
            }
        }
        $parsed['param'] = $this->paramParseInfo['params'];
        $parsed['desc'] = \trim(\substr($str, $this->paramParseInfo['startPos']));
        unset($parsed['paramsAndDesc']);
        return $parsed;
    }

    /**
     * Test and handle current character
     *
     * We know we are outside of a quoted string
     *
     * @param string $char current character
     * @param int    $pos  current character position
     *
     * @return bool // whether to continue parsing
     */
    private function parseParamChar($char, $pos)
    {
        $endParam = false;
        switch ($char) {
            case ',':
                $endParam = $this->paramParseInfo['depth'] === 1;
                break;
            case '\'':
            case '"':
                // we're opening a quoted string
                $this->paramParseInfo['strOpenedWith'] = $char;
                break;
            case '[':
            case '(':
                $this->paramParseInfo['depth']++;
                break;
            case ']':
            case ')':
                $this->paramParseInfo['depth']--;
                $endParam = $this->paramParseInfo['depth'] === 0;
                break;
        }
        if ($endParam) {
            $this->parseParamAppend($pos);
        }
        return !($endParam && $this->paramParseInfo['depth'] === 0);
    }
}
