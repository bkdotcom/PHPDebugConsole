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
 * Test SQL queries for common performance issues
 */
class SqlQueryAnalysis
{
    /**
     * Find common query performance issues
     *
     * @param string $sql SQL query
     *
     * @return array list of issues
     *
     * @link https://github.com/rap2hpoutre/mysql-xplain-xplain/blob/master/app/Explainer.php
     */
    public static function analyze($sql)
    {
        return \array_values(\array_filter([
            self::testSelectAll($sql),
            self::testOrderByRand($sql),
            self::testNonStandardNotEqual($sql),
            self::testNoWhere($sql),
            self::testLikeLeadingWildcard($sql),
            self::testLimitNoOrder($sql),
        ]));
    }

    /**
     * Test for leading wildcard in LIKE clause
     *
     * @param string $sql SQL query
     *
     * @return string|false message or false if no issue found
     */
    protected static function testLikeLeadingWildcard($sql)
    {
        $matches = [];
        return \preg_match('/LIKE\s+[\'"](%.*?)[\'"]/i', $sql, $matches)
            ? \bdk\Debug::getInstance()->i18n->trans('sql.analysis.leading_wildcard', array(
                'arg' => $matches[1],
            ))
            : false;
    }

    /**
     * Test for LIMIT without ORDER BY
     *
     * @param string $sql SQL query
     *
     * @return string|false message or false if no issue found
     */
    protected static function testLimitNoOrder($sql)
    {
        return \preg_match('/LIMIT\s/i', $sql) && \stripos($sql, 'ORDER BY') === false
            ? \bdk\Debug::getInstance()->i18n->trans('sql.analysis.limit_no_order')
            : false;
    }

    /**
     * Test for non-standard not-equal operator
     *
     * @param string $sql SQL query
     *
     * @return string|false message or false if no issue found
     */
    protected static function testNonStandardNotEqual($sql)
    {
        return \strpos($sql, '!=') !== false
            ? \bdk\Debug::getInstance()->i18n->trans('sql.analysis.not_standard')
            : false;
    }

    /**
     * Test for WHERE clause
     *
     * @param string $sql SQL query
     *
     * @return string|false message or false if no issue found
     */
    protected static function testNoWhere($sql)
    {
        return \preg_match('/^SELECT\s/i', $sql) && \stripos($sql, 'WHERE') === false
            ? \bdk\Debug::getInstance()->i18n->trans('sql.analysis.no_where')
            : false;
    }

    /**
     * Test for ORDER BY RAND()
     *
     * @param string $sql SQL query
     *
     * @return string|false message or false if no issue found
     */
    protected static function testOrderByRand($sql)
    {
        return \stripos($sql, 'ORDER BY RAND()') !== false
            ? \bdk\Debug::getInstance()->i18n->trans('sql.analysis.order_by_rand')
            : false;
    }

    /**
     * Test for SELECT *
     *
     * @param string $sql SQL query
     *
     * @return string|false message or false if no issue found
     */
    protected static function testSelectAll($sql)
    {
        return \preg_match('/^\s*SELECT\s*`?[a-zA-Z0-9]*`?\.?\*/i', $sql) === 1
            ? \bdk\Debug::getInstance()->i18n->trans('sql.analysis.select_all')
            : false;
    }
}
