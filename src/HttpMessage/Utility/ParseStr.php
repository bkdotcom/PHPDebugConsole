<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage\Utility;

use InvalidArgumentException;

/**
 * PHP's parse_str(), but does not convert dots and spaces to '_' by default
 *
 * @psalm-api
 */
class ParseStr
{
    /** @var array  */
    private static $parseStrOpts = array(
        'convDot' => false,     // whether to convert '.' to '_'  (php's default is true)
        'convSpace' => false,   // whether to convert ' ' to '_'  (php's default is true)
    );

    /**
     * like PHP's parse_str()
     *   key difference: by default this does not convert root key dots and spaces to '_'
     *
     * @param string|null $str  input string
     * @param array       $opts parse options (default: {convDot:false, convSpace:false})
     *
     * @return array
     *
     * @see https://github.com/api-platform/core/blob/main/src/Core/Util/RequestParser.php#L50
     */
    public static function parse($str, $opts = array())
    {
        $str = (string) $str;
        $opts = \array_merge(self::$parseStrOpts, $opts);
        $useParseStr = ($opts['convDot'] || \strpos($str, '.') === false)
            && ($opts['convSpace'] || \strpos($str, ' ') === false);
        if ($useParseStr) {
            // there are no spaces or dots in serialized data
            //   and/or we're not interested in converting them
            // just use parse_str
            $params = array();
            \parse_str($str, $params);
            return $params;
        }
        return self::parseStrCustom($str, $opts);
    }

    /**
     * Set default parseStr option(s)
     *
     *    parseStrOpts('convDot', true)
     *    parseStrOpts(array('convDot'=>true, 'convSpace'=>true))
     *
     * @param array|string $mixed key=>value array or key
     * @param mixed        $val   new value
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function setOpts($mixed, $val = null)
    {
        if (\is_string($mixed)) {
            $mixed = array($mixed => $val);
        }
        if (\is_array($mixed) === false) {
            throw new InvalidArgumentException(\sprintf(
                'parseStrOpts expects string or array. %s provided.',
                self::getDebugType($mixed)
            ));
        }
        $mixed = \array_intersect_key($mixed, self::$parseStrOpts);
        self::$parseStrOpts = \array_merge(self::$parseStrOpts, $mixed);
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \gettype($value);
    }

    /**
     * Parses request parameters from the specified string
     *
     * @param string $str  input string
     * @param array  $opts parse options
     *
     * @return array
     */
    private static function parseStrCustom($str, $opts)
    {
        // Use a regex to replace keys with a bin2hex'd version
        // this will prevent parse_str from modifying the keys
        // '[' is urlencoded ('%5B') in the input, but we must urldecode it in order
        // to find it when replacing names with the regexp below.
        $str = \str_replace('%5B', '[', $str);
        $str = \preg_replace_callback(
            '/(^|(?<=&))[^=[&]+/',
            static function ($matches) {
                return \bin2hex(\urldecode($matches[0]));
            },
            $str
        );

        // parse_str urldecodes both keys and values in resulting array
        \parse_str($str, $params);

        $replace = array();
        if ($opts['convDot']) {
            $replace['.'] = '_';
        }
        if ($opts['convSpace']) {
            $replace[' '] = '_';
        }
        $keys = \array_map(static function ($key) use ($replace) {
            return \strtr(\hex2bin((string) $key), $replace);
        }, \array_keys($params));
        return \array_combine($keys, $params);
    }
}
