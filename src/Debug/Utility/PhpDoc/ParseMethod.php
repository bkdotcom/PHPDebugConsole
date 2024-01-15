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
 */
class ParseMethod
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
        $phpDoc = $info['phpDoc'];
        $parsed['param'] = $this->parseMethodParams($parsed['param'], $info);
        $parsed['static'] = $parsed['static'] !== null;
        $parsed['type'] = $phpDoc->type->normalize($parsed['type'], $info['className'], $info['fullyQualifyType']);
        return $parsed;
    }

    /**
     * Parse @method parameters
     *
     * @param string $paramStr parameter string
     * @param array  $info     tagName, raw tag string, etc
     *
     * @return array
     */
    protected function parseMethodParams($paramStr, array $info)
    {
        $params = $paramStr
            ? self::paramsSplit($paramStr)
            : array();
        $phpDoc = $info['phpDoc'];
        $matches = array();
        foreach ($params as $i => $str) {
            \preg_match('/^(?:([^=]*?)\s)?([^\s=]+)(?:\s*=\s*(\S+))?$/', $str, $matches);
            $matches = \array_replace(array('?', null), $matches);
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
        return $params;
    }

    /**
     * Split @method parameter string into individual params
     *
     * @param string $paramStr parameter string
     *
     * @return string[]
     */
    private static function paramsSplit($paramStr)
    {
        $chars = \str_split($paramStr);
        $depth = 0;
        $params = array();
        $pos = 0;
        $startPos = 0;
        foreach ($chars as $pos => $char) {
            switch ($char) {
                case ',':
                    if ($depth === 0) {
                        $params[] = \trim(\substr($paramStr, $startPos, $pos - $startPos));
                        $startPos = $pos + 1;
                    }
                    break;
                case '[':
                case '(':
                    $depth++;
                    break;
                case ']':
                case ')':
                    $depth--;
                    break;
            }
        }
        $params[] = \trim(\substr($paramStr, $startPos, $pos + 1 - $startPos));
        return $params;
    }
}
