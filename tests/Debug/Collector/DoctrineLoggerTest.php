<?php

namespace bdk\Test\Debug\Collector;

use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Collector\DoctrineLogger
 */
class DoctrineLoggerTest extends DebugTestFramework
{
    public function testOutput()
    {
        // $logger = $this->getLogger();
        // $logger->startQuery('SELECT * FROM `test` where `foo` = :value', array(':value' => 'bar', 's'));

        $conn = $this->getConnection();

        $datetime = \date('Y-m-d H:i:s');

        // $conn->fetchAll('SELECT * from "bob"');
        $statement = $conn->prepare('SELECT *
            FROM `bob`
            WHERE e < :datetime');
        $datetime = $datetime;
        $statement->bindParam(':datetime', $datetime, \PDO::PARAM_STR);
        $statement->execute();

        $accounts = array('foo', 'bar');
        $sql = 'select * from bob where k in (?) and v = ?';
        $values = array($accounts, 'declined');
        $types = [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY, \PDO::PARAM_STR];
        $stmt = $conn->executeQuery($sql, $values, $types);
        $stmt->fetchAll();

        $output = $this->debug->output();
        $output = \preg_replace('#\s$#m', '', $output);

        $runtimeOutput = <<<'EOD'
%A
<li class="level-info m_group" data-channel="general.Doctrine" data-icon="fa fa-database">
<div class="group-header"><span class="font-weight-bold group-label">Doctrine:</span> <span class="t_string">sqlite:///:memory:</span></div>
<ul class="group-body">
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">logged operations: </span><span class="t_int">3</span></li>
<li class="m_time" data-channel="general.Doctrine"><span class="no-quotes t_string">total time: %f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">max memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">connection info</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
    <li><span class="t_key">url</span><span class="t_operator">=&gt;</span><span class="t_string">sqlite:///:memory:</span></li>
    <li><span class="t_key">driver</span><span class="t_operator">=&gt;</span><span class="t_string">pdo_sqlite</span></li>
    <li><span class="t_key">host</span><span class="t_operator">=&gt;</span><span class="t_string">localhost</span></li>
    <li><span class="t_key">memory</span><span class="t_operator">=&gt;</span><span class="t_bool" data-type-more="true">true</span></li>
</ul><span class="t_punct">)</span></span></li>
</ul>
</li>
%A
EOD;
        self::assertStringMatchesFormatNormalized($runtimeOutput, $output);

        $select1expect = <<<EOD
%A
<li class="m_group" data-channel="general.Doctrine" data-icon="fa fa-database">
<div class="group-header"><span class="group-label">SELECT * FROM `bob`…</span></div>
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
<tr><th class="t_key t_string text-right" scope="row">:datetime</th><td class="t_string">{$datetime}</td><td class="t_const" title="value: 2"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_identifier">PARAM_STR</span></td></tr>
</tbody>
</table>
</li>
<li class="m_time" data-channel="general.Doctrine"><span class="no-quotes t_string">duration: %f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_warn" data-channel="general.Doctrine" data-detect-files="true" data-file="%s" data-line="%d" data-uncollapse="false"><span class="no-quotes t_string">Use <span style="font-family:monospace">SELECT *</span><span> only if you need all columns from table</span></span></li>
</ul>
</li>
%A
EOD;
        self::assertStringMatchesFormatNormalized($select1expect, $output);

        $select2expect = <<<'EOD'
%A
<li class="m_group" data-channel="general.Doctrine" data-icon="fa fa-database">
<div class="group-header"><span class="group-label">select * from bob…</span></div>
<ul class="group-body">
<li class="m_log no-indent" data-channel="general.Doctrine"><span class="highlight language-sql no-quotes t_string">select
  *
from
  bob
where
  k in (?)
  and v = ?</span></li>
<li class="m_table" data-channel="general.Doctrine">
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
</ul><span class="t_punct">)</span></td><td class="t_const" title="value: 101"><span class="classname"><span class="namespace">Doctrine\DBAL\</span>Connection</span><span class="t_operator">::</span><span class="t_identifier">PARAM_INT_ARRAY</span></td></tr>
<tr><th class="t_int t_key text-right" scope="row">1</th><td class="t_string">declined</td><td class="t_const" title="value: 2"><span class="classname">PDO</span><span class="t_operator">::</span><span class="t_identifier">PARAM_STR</span></td></tr>
</tbody>
</table>
</li>
<li class="m_time" data-channel="general.Doctrine"><span class="no-quotes t_string">duration: %f %s</span></li>
<li class="m_log" data-channel="general.Doctrine"><span class="no-quotes t_string">memory usage</span> = <span class="t_string">%f %s</span></li>
<li class="m_warn" data-channel="general.Doctrine" data-detect-files="true" data-file="%s" data-line="%d" data-uncollapse="false"><span class="no-quotes t_string">Use <span style="font-family:monospace">SELECT *</span><span> only if you need all columns from table</span></span></li>
</ul>
</li>
%A
EOD;
        self::assertStringMatchesFormatNormalized($select2expect, $output);
    }

    public function getConnection()
    {
        // $connection = new \Doctrine\DBAL\Connection();
        // return new \bdk\Debug\Collector\DoctrineLogger($connection, $this->debug);

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

        $conn = \Doctrine\DBAL\DriverManager::getConnection(array(
            'url' => 'sqlite:///:memory:',
        ));
        // $debug->log('conn', $conn);

        $logger = new \bdk\Debug\Collector\DoctrineLogger($conn);
        $conn->getConfiguration()->setSQLLogger($logger);
        $conn->exec($createTableSql);

        return $conn;
    }
}
