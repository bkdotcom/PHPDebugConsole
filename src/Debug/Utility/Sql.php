<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Utility;

use bdk\Debug\Utility\ArrayUtil;
use bdk\HttpMessage\Utility\Uri as UriUtil;
use DateTime;
use ReflectionClass;

/**
 * Utilities for formatting SQL statements
 */
class Sql
{
    /**
     * Build DSN url from params
     *
     * @param array $params Connection params
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
     * Get PDO & Doctrine constants as a val => name array
     *
     * @return array
     */
    public static function getParamConstants()
    {
        $constants = self::constantsPdo();
        if (\defined('Doctrine\\DBAL\\Connection::PARAM_INT_ARRAY')) {
            $constants += array(
                \Doctrine\DBAL\Connection::PARAM_INT_ARRAY => 'Doctrine\\DBAL\\Connection::PARAM_INT_ARRAY',
                \Doctrine\DBAL\Connection::PARAM_STR_ARRAY => 'Doctrine\\DBAL\\Connection::PARAM_STR_ARRAY',
            );
        }
        return $constants;
    }

    /**
     * Get "label" / condensed sql from parsed sql
     *
     * @param array $parsed Parsed sql
     *
     * @return string
     */
    public static function labelFromParsed(array $parsed)
    {
        $label = $parsed['methodPlus']; // method + table
        $labelInfo = self::labelInfo($parsed);
        if ($labelInfo['includeWhere']) {
            $label .= $labelInfo['beforeWhere'] . ' WHERE ' . $parsed['where'];
        }
        if (\strlen($label) > 100 && $parsed['select']) {
            $label = \str_replace($parsed['select'], ' (…)', $label);
        }
        return $label . ($labelInfo['haveMore'] ? '…' : '');
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
        $regex = '/^(?P<methodPlus>
                (?:DROP|SHOW).+|
                CREATE(?:\sTEMPORARY)?\s+(TABLE|DATABASE)(?:\sIF\sNOT\sEXISTS)?\s+\S+|
                DELETE.*?FROM\s+\S+|
                INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE|INTO))*\s+\S+|
                SELECT\s+(?P<select>.*?)\s+FROM\s+(?<from>\S+)|
                SET\s+.+|
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
        return \preg_match($regex, $sql, $matches) === 1
            ? self::parseFinalize($matches)
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
     * Get PDO param constants val => name array
     *
     * @return array
     */
    private static function constantsPdo()
    {
        $pdoConstants = array();
        /** @psalm-suppress ArgumentTypeCoercion ignore expects class-string */
        if (\class_exists('PDO')) {
            $ref = new ReflectionClass('PDO');
            $pdoConstants = $ref->getConstants();
        }
        $constants = array();
        foreach ($pdoConstants as $name => $val) {
            if (\strpos($name, 'PARAM_') === 0 && \strpos($name, 'PARAM_EVT_') !== 0) {
                $constants[$val] = 'PDO::' . $name;
            }
        }
        return $constants;
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
            $value = $value->format(DateTime::RFC3339);
        }
        return \call_user_func([__CLASS__, __FUNCTION__], (string) $value);
    }

    /**
     * Get info about what parts of the query are included in the label
     *
     * @param array $parsed Parsed sql
     *
     * @return array
     */
    private static function labelInfo(array $parsed)
    {
        $afterWhereKeys = ['groupBy', 'having', 'window', 'orderBy', 'limit', 'for'];
        $afterWhereValues = \array_filter(\array_intersect_key($parsed, \array_flip($afterWhereKeys)));
        $includeWhere = $parsed['where'] && \strlen($parsed['where']) < 35;
        return array(
            'beforeWhere' => $parsed['afterMethod'] ? ' (…)' : '',
            'haveMore' => \count($afterWhereValues) > 0
                || (!$includeWhere && \array_filter([$parsed['afterMethod'], $parsed['where']])),
            'includeWhere' => $includeWhere,
        );
    }

    /**
     * Clean up matches
     *
     * @param array $matches regex matches
     *
     * @return array
     */
    private static function parseFinalize(array $matches)
    {
        foreach (\range(0, \count($matches) / 2) as $index) {
            unset($matches[$index]);
        }
        $keysAlwaysReturn = ['method', 'select', 'from', 'afterMethod', 'where', 'limit'];
        \preg_match('/^\s*(\w+)\b/', $matches['methodPlus'], $methodMatches);
        $matches['method'] = \strtolower($methodMatches[1]);
        return \array_merge(\array_fill_keys($keysAlwaysReturn, null), $matches);
    }
}
