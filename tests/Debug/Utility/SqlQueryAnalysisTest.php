<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\SqlQueryAnalysis;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SqlQueryAnalysis
 *
 * @covers \bdk\Debug\Utility\SqlQueryAnalysis
 */
class SqlQueryAnalysisTest extends TestCase
{
    /**
     * @dataProvider sqlProvider
     */
    public function testAnalyze($sql, $expectedIssues)
    {
        $issues = SqlQueryAnalysis::analyze($sql);
        // \bdk\Debug::varDump('expect', $expectedIssues);
        // \bdk\Debug::varDump('actual', $issues);
        $this->assertEquals($expectedIssues, $issues);
    }

    public function sqlProvider()
    {
        return [
            // Test SELECT *
            'selectAll' => [
                'SELECT * FROM users',
                [
                    'Use %cSELECT *%c only if you need all columns from table',
                    'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
                ],
            ],
            // Test ORDER BY RAND()
            'orderByRand' => [
                'SELECT id FROM users ORDER BY RAND()',
                [
                    '%cORDER BY RAND()%c is slow, avoid if you can.',
                    'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
                ],
            ],
            // Test non-standard not-equal operator
            'nonStandardNotEqual' => [
                'SELECT id FROM users WHERE status != "active"',
                [
                    'The %c!=%c operator is not standard. Use the %c<>%c operator instead.',
                ],
            ],
            // Test missing WHERE clause
            'noWhere' => [
                'SELECT id FROM users',
                [
                    'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
                ],
            ],
            // Test LIKE with leading wildcard
            'likeLeadingWildcard' => [
                'SELECT id FROM users WHERE name LIKE "%John%"',
                [
                    'An argument has a leading wildcard character: %c%John%%c and cannot use an index if one exists.',
                ],
            ],
            // Test LIMIT without ORDER BY
            'limitNoOrder' => [
                'SELECT id FROM users LIMIT 10',
                [
                    'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
                    '%cLIMIT%c without %cORDER BY%c causes non-deterministic results',
                ],
            ],
            // Test no issues
            'noIssues' => [
                'SELECT id FROM users WHERE name = "John" ORDER BY id LIMIT 10',
                [],
            ],
        ];
    }
}