<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Collector\DoctrineLogger;
use bdk\Debug\Utility\Reflection;
use bdk\Test\Debug\DebugTestFramework;
use Doctrine\DBAL\DriverManager;

/**
 * @covers \bdk\Debug\Collector\DoctrineLogger
 * @covers \bdk\Debug\Collector\Doctrine\LoggerCompatTrait
 * @covers \bdk\Debug\Utility\Sql
 */
class DoctrineLoggerTest extends DebugTestFramework
{
    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        Reflection::propSet('bdk\Debug\Collector\StatementInfoLogger', 'id', 0);
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
        \class_exists('Doctrine\DBAL\ParameterType')
            ? $statement->bindValue(':datetime', $datetime, \Doctrine\DBAL\ParameterType::STRING)
            : $statement->bindParam(':datetime', $datetime, \PDO::PARAM_STR);
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
            \class_exists('\Doctrine\DBAL\ParameterType')
                ? \Doctrine\DBAL\ParameterType::STRING
                : \PDO::PARAM_STR,
        ];

        $stmt = $conn->executeQuery($sql, $values, $types);
        \method_exists($stmt, 'fetchAllAssociative')
            ? $stmt->fetchAllAssociative()
            : $stmt->fetchAll();

        $output = $this->debug->output();
        $output = \preg_replace('#\s$#m', '', $output);

        $runtimeOutput = <<<'EOD'
%A
<li class="level-info m_group" data-channel="general.doctrine" data-icon="fa fa-database">
<div class="group-header"><span class="font-weight-bold group-label">Doctrine</span>: <span class="t_string">sqlite:///:memory:</span></div>
<ul class="group-body">
<li class="m_log" data-channel="general.doctrine"><span class="no-quotes t_string">Logged operations: </span><span class="t_int">3</span></li>
<li class="m_time" data-channel="general.doctrine"><span class="no-quotes t_string">Total time: %f %s</span></li>
<li class="m_log" data-channel="general.doctrine"><span class="no-quotes t_string">Peak memory usage</span> = <span class="t_string">%f %s</span></li>
</ul>
</li>
%A
EOD;
        // \bdk\Debug::varDump('expect', $runtimeOutput);
        // \bdk\Debug::varDump('actual', $output);
        self::assertStringMatchesFormatNormalized($runtimeOutput, $output);

        $select1expect = <<<EOD
%A
<li class="m_group" data-channel="general.doctrine" data-icon="fa fa-database" id="statementInfo2">
<div class="group-header"><span class="group-label">SELECT * FROM `bob` WHERE e &lt; &#039;$datetime&#039;</span></div>
<ul class="group-body">
<li class="m_log no-indent" data-channel="general.doctrine"><span class="highlight language-sql no-quotes t_string">SELECT
  *
FROM
  `bob`
WHERE
  e &lt; :datetime</span></li>
<li class="m_table" data-channel="general.doctrine">
<table class="sortable table-bordered">
<caption>parameters</caption>
<thead>
<tr><th>&nbsp;</th><th scope="col">value</th><th scope="col">type</th></tr>
</thead>
<tbody>
<tr><th class="t_key t_string text-right" scope="row">:datetime</th><td class="t_string">{$datetime}</td><td class="t_identifier" data-type-more="const" title="value: 2"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_name">PARAM_STR</span></td></tr>
</tbody>
</table>
</li>
<li class="m_time" data-channel="general.doctrine"><span class="no-quotes t_string">duration: %f %s</span></li>
<li class="m_log" data-channel="general.doctrine"><span class="no-quotes t_string">Memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_warn" data-channel="general.doctrine" data-file="%s" data-line="%d" data-uncollapse="false"><span class="no-quotes t_string">Use <span style="font-family:monospace">SELECT *</span><span> only if you need all columns from table</span></span></li>
</ul>
</li>
%A
EOD;
        // \bdk\Debug::varDump('expect', $select1expect);
        // \bdk\Debug::varDump('actual', $output);
        self::assertStringMatchesFormatNormalized($select1expect, $output);

        $select2expect = <<<'EOD'
%A
<li class="m_group" data-channel="general.doctrine" data-icon="fa fa-database" id="statementInfo3">
<div class="group-header"><span class="group-label">select * from bob…</span></div>
<ul class="group-body">
<li class="m_log no-indent" data-channel="general.doctrine"><span class="highlight language-sql no-quotes t_string">select
  *
from
  bob
where
  k in (?)
  and v = ?</span></li>
<li class="m_table" data-channel="general.doctrine">
<table class="sortable table-bordered">
<caption>parameters</caption>
<thead>
<tr><th>&nbsp;</th><th scope="col">value</th><th scope="col">type</th></tr>
</thead>
<tbody>
<tr><th class="t_int t_key text-right" scope="row">0</th><td class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
    <li><span class="t_int t_key">0</span><span class="t_operator">=&gt;</span><span class="t_string">foo</span></li>
    <li><span class="t_int t_key">1</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
</ul><span class="t_punct">)</span></td><td class="t_identifier" data-type-more="const" title="value: 101"><span class="classname"><span class="namespace">Doctrine\DBAL\</span>Connection</span><span class="t_operator">::</span><span class="t_name">PARAM_INT_ARRAY</span></td></tr>
<tr><th class="t_int t_key text-right" scope="row">1</th><td class="t_string">declined</td><td class="t_identifier" data-type-more="const" title="value: 2"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_name">PARAM_STR</span></td></tr>
</tbody>
</table>
</li>
<li class="m_time" data-channel="general.doctrine"><span class="no-quotes t_string">duration: %f %s</span></li>
<li class="m_log" data-channel="general.doctrine"><span class="no-quotes t_string">Memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_warn" data-channel="general.doctrine" data-file="%s" data-line="%d" data-uncollapse="false"><span class="no-quotes t_string">Use <span style="font-family:monospace">SELECT *</span><span> only if you need all columns from table</span></span></li>
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
        $supportsLogger = \method_exists('Doctrine\DBAL\Configuration', 'setSQLLogger');
        if ($supportsLogger === false) {
            $this->markTestSkipped('This version of Doctrine does not support setSQLLogger');
            return false;
        }

        $conn = DriverManager::getConnection(array(
            'url' => 'sqlite:///:memory:',
        ));
        $logger = new DoctrineLogger($conn);
        $conn->getConfiguration()->setSQLLogger($logger);

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
