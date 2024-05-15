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

namespace bdk\Debug\Utility;

use bdk\Debug;
use Closure;

/**
 * Test SQL queries for common performance issues
 */
class SqlQueryAnalysis
{
    /** @var Debug */
    private $debug;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Find common query performance issues
     *
     * @param string $sql SQL query
     *
     * @return void
     *
     * @link https://github.com/rap2hpoutre/mysql-xplain-xplain/blob/master/app/Explainer.php
     */
    public function analyze($sql)
    {
        \array_map(array($this, 'performQueryAnalysisTest'), array(
            array(\preg_match('/^\s*SELECT\s*`?[a-zA-Z0-9]*`?\.?\*/i', $sql) === 1,
                'Use %cSELECT *%c only if you need all columns from table',
            ),
            array(\stripos($sql, 'ORDER BY RAND()') !== false,
                '%cORDER BY RAND()%c is slow, avoid if you can.',
            ),
            array(\strpos($sql, '!=') !== false,
                'The %c!=%c operator is not standard. Use the %c<>%c operator instead.',
            ),
            array(\preg_match('/^SELECT\s/i', $sql) && \stripos($sql, 'WHERE') === false,
                'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
            ),
            static function () use ($sql) {
                $matches = array();
                return \preg_match('/LIKE\s+[\'"](%.*?)[\'"]/i', $sql, $matches)
                    ? 'An argument has a leading wildcard character: %c' . $matches[1] . '%c and cannot use an index if one exists.'
                    : false;
            },
            array(\preg_match('/LIMIT\s/i', $sql) && \stripos($sql, 'ORDER BY') === false,
                '%cLIMIT%c without %cORDER BY%c causes non-deterministic results',
            ),
        ));
    }

    /**
     * Process query analysis test and log result if test fails
     *
     * @param array|Closure $test query test
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function performQueryAnalysisTest($test)
    {
        if ($test instanceof Closure) {
            $test = $test();
            $test = array(
                $test,
                $test,
            );
        }
        if ($test[0] === false) {
            return;
        }
        $params = array(
            $test[1],
        );
        $cCount = \substr_count($params[0], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace';
            $params[] = '';
        }
        $params[] = $this->debug->meta('uncollapse', false);
        \call_user_func_array(array($this->debug, 'warn'), $params);
    }
}
