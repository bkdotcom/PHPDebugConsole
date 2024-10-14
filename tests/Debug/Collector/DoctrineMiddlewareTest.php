<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Collector\DoctrineMiddleware;
use bdk\Debug\Utility\Reflection;
use bdk\Test\Debug\DebugTestFramework;
use Doctrine\DBAL\DriverManager;

/**
 * @covers \bdk\Debug\Collector\DoctrineLogger
 * @covers \bdk\Debug\Collector\DoctrineMiddleware
 * @covers \bdk\Debug\Collector\Doctrine\Connection
 * @covers \bdk\Debug\Collector\Doctrine\Driver
 * @covers \bdk\Debug\Collector\Doctrine\Statement
 * @covers \bdk\Debug\Utility\Sql
 */
class DoctrineMiddlewareTest extends DebugTestFramework
{
    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        Reflection::propSet('bdk\Debug\Collector\StatementInfo', 'id', 0);
    }

    public function testOutput()
    {
        $conn = $this->getConnection();
        if ($conn === false) {
            return;
        }

        $datetime = \date('Y-m-d H:i:s');

        $statement = $conn->prepare('SELECT *
            FROM `bob`
            WHERE e < :datetime');
        $statement->bindValue(':datetime', $datetime, \Doctrine\DBAL\ParameterType::STRING);
        \method_exists($statement, 'executeQuery')
            ? $statement->executeQuery()
            : $statement->execute();

        $accounts = array('foo', 'bar');
        $sql = 'select * from bob where k in (?) and v = ?';
        $values = array($accounts, 'declined');
        $types = [
            \class_exists('Doctrine\DBAL\ArrayParameterType')
                ? \Doctrine\DBAL\ArrayParameterType::INTEGER
                : \Doctrine\DBAL\Connection::PARAM_INT_ARRAY,
            \Doctrine\DBAL\ParameterType::STRING // pre Doctrine 4.x this maps to PDO::PARAM_STR
        ];

        $stmt = $conn->executeQuery($sql, $values, $types);
        \method_exists($stmt, 'fetchAllAssociative')
            ? $stmt->fetchAllAssociative()
            : $stmt->fetchAll();

        $output = $this->debug->output();
        $output = \preg_replace('#\s$#m', '', $output);

        $runtimeOutput = <<<'EOD'
%A
<li class="level-info m_group" data-channel="general.Doctrine" data-icon="fa fa-database">
<div class="group-header"><span class="font-weight-bold group-label">Doctrine:</span> <span class="t_string">pdo-sqlite:///:memory:</span></div>
<ul class="group-body">
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">logged operations: </span><span class="t_int">3</span></li>
<li class="m_time" data-channel="general.Doctrine"><span class="no-quotes t_string">total time: %f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">max memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">server info</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
	<li><span class="t_key">Version</span><span class="t_operator">=&gt;</span><span class="t_string">%s</span></li>
</ul><span class="t_punct">)</span></span></li>
</ul>
</li>
%A
EOD;
        // \bdk\Debug::varDump('expect', $runtimeOutput);
        // \bdk\Debug::varDump('actual', $output);
        self::assertStringMatchesFormatNormalized($runtimeOutput, $output);

        // Doctrine 4.x Doctrine\DBAL\ParameterType is an enum
        // pre Doctrine 4.x Doctrine\DBAL\ParameterType::STRING maps to PDO::PARAM_STR value
        $parameterTypeTd = \function_exists('enum_exists') && \enum_exists('Doctrine\DBAL\ParameterType')
            ? '<td class="t_identifier" data-type-more="const" title="Represents the SQL CHAR, VARCHAR, or other string data type.
Statement parameter type."><span class="classname"><span class="namespace">Doctrine\DBAL\</span>ParameterType</span><span class="t_operator">::</span><span class="t_name">STRING</span></td>'
            : '<td class="t_identifier" data-type-more="const" title="value: 2"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_name">PARAM_STR</span></td>';

        $select1expect = <<<EOD
%A
<li class="m_group" data-channel="general.Doctrine" data-icon="fa fa-database" id="statementInfo2">
<div class="group-header"><span class="group-label">SELECT * FROM `bob` WHERE e &lt; &#039;$datetime&#039;</span></div>
<ul class="group-body">
<li class="m_log no-indent" data-channel="general.Doctrine"><span class="highlight language-sql no-quotes t_string">SELECT
  *
FROM
  `bob`
WHERE
  e &lt; :datetime</span></li>
<li class="m_table" data-channel="general.Doctrine">
<table class="sortable table-bordered">
<caption>parameters</caption>
<thead>
<tr><th>&nbsp;</th><th scope="col">value</th><th scope="col">type</th></tr>
</thead>
<tbody>
<tr><th class="t_key t_string text-right" scope="row">:datetime</th><td class="t_string">{$datetime}</td>{$parameterTypeTd}</tr>
</tbody>
</table>
</li>
<li class="m_time" data-channel="general.Doctrine"><span class="no-quotes t_string">duration: %f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_warn" data-channel="general.Doctrine" data-detect-files="true" data-file="%s" data-line="%d" data-uncollapse="false"><span class="no-quotes t_string">Use <span style="font-family:monospace">SELECT *</span><span> only if you need all columns from table</span></span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">rowCount</span> = <span class="t_int">0</span></li>
</ul>
</li>
%A
EOD;
        // \bdk\Debug::varDump('expect', $select1expect);
        // \bdk\Debug::varDump('actual', $output);
        self::assertStringMatchesFormatNormalized($select1expect, $output);

        $params =\method_exists($statement, 'bindValue') && \is_int(\Doctrine\DBAL\ParameterType::STRING) === false
            ? '<tr><th class="t_int t_key text-right" scope="row">1</th><td class="t_string">foo</td><td class="t_identifier" data-type-more="const" title="Represents the SQL INTEGER data type.
                    Statement parameter type."><span class="classname"><span class="namespace">Doctrine\DBAL\</span>ParameterType</span><span class="t_operator">::</span><span class="t_name">INTEGER</span></td></tr>
                <tr><th class="t_int t_key text-right" scope="row">2</th><td class="t_string">bar</td><td class="t_identifier" data-type-more="const" title="Represents the SQL INTEGER data type.
                    Statement parameter type."><span class="classname"><span class="namespace">Doctrine\DBAL\</span>ParameterType</span><span class="t_operator">::</span><span class="t_name">INTEGER</span></td></tr>
                <tr><th class="t_int t_key text-right" scope="row">3</th><td class="t_string">declined</td><td class="t_identifier" data-type-more="const" title="Represents the SQL CHAR, VARCHAR, or other string data type.
                    Statement parameter type."><span class="classname"><span class="namespace">Doctrine\DBAL\</span>ParameterType</span><span class="t_operator">::</span><span class="t_name">STRING</span></td></tr>'
            : '<tr><th class="t_int t_key text-right" scope="row">1</th><td class="t_string">foo</td><td class="t_identifier" data-type-more="const" title="value: 1"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_name">PARAM_INT</span></td></tr>
                <tr><th class="t_int t_key text-right" scope="row">2</th><td class="t_string">bar</td><td class="t_identifier" data-type-more="const" title="value: 1"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_name">PARAM_INT</span></td></tr>
                <tr><th class="t_int t_key text-right" scope="row">3</th><td class="t_string">declined</td><td class="t_identifier" data-type-more="const" title="value: 2"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_name">PARAM_STR</span></td></tr>';
        $select2expect = <<<EOD
%A
<li class="m_group" data-channel="general.Doctrine" data-icon="fa fa-database" id="statementInfo3">
<div class="group-header"><span class="group-label">select * from bob WHERE k in (?, ?) and v = ?</span></div>
<ul class="group-body">
<li class="m_log no-indent" data-channel="general.Doctrine"><span class="highlight language-sql no-quotes t_string">select
  *
from
  bob
where
  k in (?, ?)
  and v = ?</span></li>
<li class="m_table" data-channel="general.Doctrine">
<table class="sortable table-bordered">
<caption>parameters</caption>
<thead>
<tr><th>&nbsp;</th><th scope="col">value</th><th scope="col">type</th></tr>
</thead>
<tbody>
{$params}
</tbody>
</table>
</li>
<li class="m_time" data-channel="general.Doctrine"><span class="no-quotes t_string">duration: %f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_warn" data-channel="general.Doctrine" data-detect-files="true" data-file="%s" data-line="%d" data-uncollapse="false"><span class="no-quotes t_string">Use <span style="font-family:monospace">SELECT *</span><span> only if you need all columns from table</span></span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">rowCount</span> = <span class="t_int">0</span></li>
</ul>
</li>
%A
EOD;
        // \bdk\Debug::varDump('expect', $select2expect);
        // \bdk\Debug::varDump('actual', $output);
        self::assertStringMatchesFormatNormalized($select2expect, $output);
    }

    public function getConnection()
    {
        $supportsMiddleware = \method_exists('Doctrine\DBAL\Configuration', 'setMiddlewares');
        if ($supportsMiddleware === false) {
            $this->markTestSkipped('This version of Doctrine does not support middlewares');
            return false;
        }

        $conn = DriverManager::getConnection(
            array(
                'dbname' => ':memory:',
                'driver' => 'pdo_sqlite',
            ),
            (new \Doctrine\DBAL\Configuration())->setMiddlewares([
                new DoctrineMiddleware(),
            ])
        );

        $this->createScheme($conn);
        return $conn;
    }

    private function createScheme($conn)
    {
        $createTableSql = <<<'EOD'
        CREATE TABLE IF NOT EXISTS bob (
            k VARCHAR(255) NOT NULL PRIMARY KEY,
            v BLOB,
            t char(32) NOT NULL,
            e datetime NULL DEFAULT NULL,
            ct INT NULL DEFAULT 0,
            KEY e
        )
EOD;
        \method_exists($conn, 'executeStatement')
            ? $conn->executeStatement($createTableSql)
            : $conn->exec($createTableSql);
    }
}
