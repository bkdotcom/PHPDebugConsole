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

use bdk\Debug\Utility\ArrayUtil;
use bdk\HttpMessage\Utility\Uri as UriUtil;
use DateTime;

/**
 * Utilities for formatting SQL statements
 */
class Sql
{
    /**
     * Build DSN url from params
     *
     * @return string
     */
    public static function buildDsn(array $params)
    {
        $parts = self::buildDsnParamsToUrlParts($params);
        $dsn = (string) UriUtil::fromParsed($parts);
        if ($parts['path'] === ':memory:') {
            $dsn = \str_replace('/localhost', '/', $dsn);
        }
        return $dsn;
    }

    /**
     * "Parse" the sql statement to get a label
     *
     * This "parser" has a *very* limited scope.  For internal use only.
     *
     * @param string $sql SQL statement
     *
     * @return array|false
     */
    public static function parse($sql)
    {
        $regex = '/^(?<method>
                (?:DROP|SHOW).+|
                CREATE(?:\sTEMPORARY)?\s+TABLE(?:\sIF\sNOT\sEXISTS)?\s+\S+|
                DELETE.*?FROM\s+\S+|
                INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE|INTO))*\s+\S+|
                SELECT\s+(?P<select>.*?)\s+FROM\s+(?<from>\S+)|
                UPDATE\s+\S+
            )
            (?P<afterMethod>.*?)
            (?:\s+WHERE\s+(?P<where>.*?))?
            (?:\s+GROUP BY\s+(?P<groupBy>.*?))?
            (?:\s+HAVING\s+(?P<having>.*?))?
            (?:\s+WINDOW\s+(?P<window>.*?))?
            (?:\s+ORDER BY\s+(?P<orderBy>.*?))?
            (?:\s+LIMIT\s+(?P<limit>.*?))?
            (?:\s+FOR\s+(?P<for>.*?))?
            $/six';
        $keysAlwaysReturn = ['method', 'select', 'from', 'afterMethod', 'where'];
        return \preg_match($regex, $sql, $matches) === 1
            ? \array_merge(\array_fill_keys($keysAlwaysReturn, ''), $matches)
            : false;
    }

    /**
     * Replace param holders with param values
     *
     * @param string $sql    SQL statement
     * @param array  $params Bound Parameters
     *
     * @return string
     */
    public static function replaceParams($sql, array $params)
    {
        if (ArrayUtil::isList($params) === false) {
            // named params
            foreach ($params as $name => $value) {
                $value = self::doParamSubstitutionValue($value);
                $sql = \str_replace($name, $value, $sql);
            }
            return $sql;
        }
        // anonymous params
        if (\substr_count($sql, '?') !== \count($params)) {
            return $sql;
        }
        $strposOffset = 0;
        foreach ($params as $value) {
            $value = self::doParamSubstitutionValue($value);
            $pos = \strpos($sql, '?', $strposOffset);
            $sql = \substr_replace($sql, $value, $pos, 1);
            $strposOffset = $pos + \strlen($value);
        }
        return $sql;
    }

    /**
     * Convert params to url parts
     *
     * @param array $params Connection params
     *
     * @return array
     */
    private static function buildDsnParamsToUrlParts(array $params)
    {
        $map = array(
            'dbname' => 'path',
            'driver' => 'scheme',
        );
        \ksort($params);
        $rename = \array_intersect_key($params, $map);
        $keysNew = \array_values(\array_intersect_key($map, $rename));
        $renamed = \array_combine($keysNew, \array_values($rename));
        $paramsDefault = array(
            'host' => 'localhost',
            'memory' => false,
            'path' => null,
            // password, pass, or passwd
            // username or user
            'scheme' => null,
        );
        $parts = \array_merge($paramsDefault, $renamed, $params);
        if ($parts['memory']) {
            $parts['path'] = ':memory:';
        }
        $parts['scheme'] = \str_replace('_', '-', (string) $parts['scheme']);
        return $parts;
    }

    /**
     * Get param value for injection into SQL statement
     *
     * @param mixed $value Param value
     *
     * @return int|string
     */
    private static function doParamSubstitutionValue($value)
    {
        if (\is_string($value)) {
            return "'" . \addslashes($value) . "'";
        }
        if (\is_numeric($value)) {
            return $value;
        }
        if (\is_array($value)) {
            return \implode(', ', \array_map([__CLASS__, __FUNCTION__], $value));
        }
        if (\is_bool($value)) {
            return (int) $value;
        }
        if ($value === null) {
            return 'null';
        }
        if ($value instanceof DateTime) {
            $value = $value->format(DateTime::ISO8601);
        }
        return \call_user_func([__CLASS__, __FUNCTION__], (string) $value);
    }
}
