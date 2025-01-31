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

namespace bdk\Debug\Utility;

/**
 * Html utilities
 */
class HtmlParse
{
    /**
     * Parse attribute value
     *
     * @param string $name    attribute name
     * @param string $val     value (assumed to be html encoded)
     * @param int    $options bitmask of Html::PARSE_ATTRIB_x FLAGS
     *
     * @return mixed
     */
    public static function parseAttribValue($name, $val, $options)
    {
        $val = \htmlspecialchars_decode($val);
        if ($name === 'class') {
        	$decode = (bool) ($options & Html::PARSE_ATTRIB_CLASS);
            return self::parseAttribClass($val, $decode);
        }
        if (\substr($name, 0, 5) === 'data-') {
        	$decode = (bool) ($options & Html::PARSE_ATTRIB_DATA);
            return self::parseAttribData($val, $decode);
        }
        if (\in_array($name, Html::$htmlBoolAttr, true)) {
            return true;
        }
        if (\in_array($name, Html::$htmlBoolAttrEnum, true)) {
            return self::parseAttribBoolEnum($val);
        }
        if (\is_numeric($val)) {
        	$decode = (bool) ($options & Html::PARSE_ATTRIB_NUMERIC);
            return self::parseAttribNumeric($val, $decode);
        }
        return $val;
    }

    /**
     * Convert bool enum attribute value to bool
     *
     * @param string $val enum attribute value
     *
     * @return bool|string
     */
    private static function parseAttribBoolEnum($val)
    {
        $parsed = \filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed !== null
            ? $parsed
            : $val;
    }

    /**
     * Convert class attribute value to array of classes
     *
     * @param string $val     attribute value to decode
     * @param bool   $asArray whether to return array
     *
     * @return array|string
     */
    private static function parseAttribClass($val, $asArray)
    {
        if (!$asArray) {
            return $val;
        }
        $classes = \explode(' ', $val);
        \sort($classes);
        return \array_unique($classes);
    }

    /**
     * Json decode data-xxx attribute
     *
     * @param string $val    attribute value to decode
     * @param bool   $decode whether to decode
     *
     * @return mixed
     */
    private static function parseAttribData($val, $decode)
    {
        if (!$decode) {
            return $val;
        }
        $decoded = \json_decode((string) $val, true);
        if ($decoded === null && $val !== 'null') {
            $decoded = \json_decode('"' . $val . '"', true);
        }
        return $decoded;
    }

    /**
     * Convert numeric attribute value to float/int
     *
     * @param numeric $val    enum attribute value
     * @param bool    $decode whether to cast to int/float
     *
     * @return string|float|int
     */
    private static function parseAttribNumeric($val, $decode)
    {
        return $decode
            ? \json_decode($val)
            : $val;
    }
}
