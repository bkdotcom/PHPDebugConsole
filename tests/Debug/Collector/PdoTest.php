<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Collector\Pdo;
use bdk\Debug\Utility\Reflection;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Collector\Pdo
 * @covers \bdk\Debug\Collector\Pdo\CompatTrait
 * @covers \bdk\Debug\Collector\Pdo\Statement
 * @covers \bdk\Debug\Collector\StatementInfo
 * @covers \bdk\Debug\Collector\StatementInfoLogger
 * @covers \bdk\Debug\Utility\Sql
 */
class PdoTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    private static $client;

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

    public static function setUpBeforeClass(): void
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

        $pdoBase = new \PDO('sqlite::memory:');
        self::$client = new Pdo($pdoBase);
        self::$client->exec($createTableSql);
        self::$client->exec('INSERT INTO `bob` (k, t, e) VALUES ("test", "test", "2022-05-18 13:10:00")');
    }

    public static function tearDownAfterClass(): void
    {
        $debug = \bdk\Debug::getInstance();
        $debug->getChannel('PDO')
            ->eventManager->unsubscribe(Debug::EVENT_OUTPUT, array(self::$client, 'onDebugOutput'));
    }

    public function testConstruct()
    {
        $pdoBase = new \PDO('sqlite::memory:');
        $pdoClient = new Pdo($pdoBase);
        $attrVal = $pdoClient->getAttribute(\PDO::ATTR_STATEMENT_CLASS);
        $this->debug->getChannel('PDO')
            ->eventManager->unsubscribe(Debug::EVENT_OUTPUT, array($pdoClient, 'onDebugOutput'));
        $this->assertSame('bdk\Debug\Collector\Pdo\Statement', $attrVal[0]);
    }

    public function testBindColumn()
    {
        $stmt = self::$client->prepare('SELECT * FROM `bob`');
        $stmt->execute();
        $dateTime = null;
        $stmt->bindColumn('e', $dateTime);
        $vals = array();
        while ($stmt->fetch(\PDO::FETCH_BOUND)) {
           $vals[] = $dateTime;
        }
        $this->assertSame(array(
            '2022-05-18 13:10:00',
        ), $vals);
    }

    public function testMergeParams()
    {
        $stmt = self::$client->prepare('SELECT *
            FROM `bob`
            WHERE e = :datetime');
        $stmt->execute(array(
            'datetime' => '2022-05-18 13:10:00',
        ));
        $statements = self::$client->getStatementInfoLogger()->getLoggedStatements();
        $stmtInfo = \end($statements);
        $this->assertSame(array(
            'datetime' => '2022-05-18 13:10:00',
        ), $stmtInfo->params);
    }

    public function testBindValue()
    {
        $stmt = self::$client->prepare('SELECT *
            FROM `bob`
            WHERE e = :datetime');
        $datetime = '2022-05-18 13:10:00';
        // $text = null;
        // $stmt->bindValue('t', $text, \PDO::PARAM_STR);
        $stmt->bindValue(':datetime', $datetime, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(array(
            'k' => 'test',
            'v' => null,
            't' => 'test',
            'e' => '2022-05-18 13:10:00',
            'ct' => $row['ct'],  // may be 0 or "0"
            'KEY' => null,
        ), $row);
    }

    public function testExecute()
    {
        $statement = self::$client->prepare('SELECT *
            FROM `bob`
            WHERE e < :datetime');
        $datetime = '2020-12-04 22:00:00';
        $line = __LINE__ + 2;
        $statement->bindParam(':datetime', $datetime, \PDO::PARAM_STR);
        $statement->execute();

        $logEntries = $this->getLogEntries(8);

        $logEntriesExpect = array(
            'statementInfo1' => array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'SELECT * FROM `bob` WHERE e < \'' . $datetime . '\'',
                ),
                'meta' => array(
                    'attribs' => array(
                        'id' => 'statementInfo1',
                        'class' => array(),
                    ),
                    'boldLabel' => false,
                    'channel' => 'general.pdo',
                    'icon' => 'fa fa-database',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    array(
                        'attribs' => array(
                            'class' => array('highlight', 'language-sql', 'no-quotes'),
                        ),
                        'brief' => false,
                        'contentType' => ContentType::SQL,
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => false,
                        'type' => Type::TYPE_STRING,
                        'typeMore' => null,
                        'value' => "SELECT \n  * \nFROM \n  `bob` \nWHERE \n  e < :datetime",
                    ),
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => array('no-indent'),
                    ),
                    'channel' => 'general.pdo',
                ),
            ),
            array(
                'method' => 'table',
                'args' => array(
                    array(
                        ':datetime' => array(
                            'value' => $datetime,
                            'type' => array(
                                'backedValue' => 2,
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Type::TYPE_IDENTIFIER,
                                'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                                'value' => 'PDO::PARAM_STR',
                            ),
                        ),
                    ),
                ),
                'meta' => array(
                    'caption' => 'parameters',
                    'channel' => 'general.pdo',
                    'sortable' => true,
                    'tableInfo' => array(
                        'class' => null,
                        'columns' => array(
                            array('key' => 'value'),
                            array('key' => 'type'),
                        ),
                        'haveObjRow' => false,
                        'indexLabel' => null,
                        'rows' => array(),
                        'summary' => '',
                    ),
                ),
            ),
            array(
                'asString' => true,
                'method' => 'time',
                'args' => array(
                    'duration: %f %ss',
                ),
                'meta' => array(
                    'channel' => 'general.pdo',
                ),
            ),
            array(
                'asString' => true,
                'method' => 'log',
                'args' => array(
                    'Memory usage',
                    '%d B',
                ),
                'meta' => array(
                    'channel' => 'general.pdo',
                ),
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'Use %cSELECT *%c only if you need all columns from table',
                    'font-family:monospace',
                    '',
                ),
                'meta' => array(
                    'channel' => 'general.pdo',
                    'detectFiles' => true,
                    // 'evalLine' => null,
                    'file' => __FILE__,
                    'line' => $line,
                    'uncollapse' => false,
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'rowCount',
                    1,
                ),
                'meta' => array(
                    'channel' => 'general.pdo',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.pdo',
                ),
            ),
        );
        foreach ($logEntriesExpect as $i => $valsExpect) {
            $asString = !empty($valsExpect['asString']);
            unset($valsExpect['asString']);
            if ($asString) {
                $this->assertStringMatchesFormat(\json_encode($valsExpect), \json_encode($logEntries[$i]));
                continue;
            }
            $this->assertSame($valsExpect, $logEntries[$i]);
        }
        $this->assertIsString(self::$client->lastInsertId());
    }

    public function testExecuteException()
    {
        $this->expectException('PDOException');
        self::$client->setAttribute(PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $statement = self::$client->prepare('SELECT *
            FROM `bob`
            WHERE e < :datetime');
        $datetime = '2020-12-04 22:00:00';
        $statement->bindParam(':bogusParam', $datetime, \PDO::PARAM_STR);
        $statement->execute();
    }

    public function testExec()
    {
        $count = self::$client->exec('DELETE FROM `bob`');
        $this->assertIsInt($count);

        $logEntriesExpectJson = <<<'EOD'
        {
            "statementInfo1": {
                "method": "groupCollapsed",
                "args": ["DELETE FROM `bob`"],
                "meta": {
                    "attribs": {
                        "id": "statementInfo1",
                        "class": []
                    },
                    "boldLabel": false, "channel": "general.pdo", "icon": "fa fa-database"
                }
            },
            "0": {
                "method": "time",
                "args": ["duration: 46.0148 \u03bcs"],
                "meta": {"channel": "general.pdo"}
            },
            "1": {
                "method": "log",
                "args": ["Memory usage", "0 B"],
                "meta": {"channel": "general.pdo"}
            },
            "2": {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.pdo"}
            }
        }
EOD;
        $logEntriesExpect = \json_decode($logEntriesExpectJson, true);

        $logEntries = $this->getLogEntries();

        // duration
        $logEntriesExpect[0]['args'][0] = $logEntries[0]['args'][0];
        // memory
        $logEntriesExpect[1]['args'][1] = $logEntries[1]['args'][1];

        // \bdk\Debug::varDump('expect', $logEntriesExpect);
        // \bdk\Debug::varDump('actual', $logEntries);
        $this->assertSame($logEntriesExpect, $logEntries);
    }

    public function testTransaction()
    {
        self::$client->beginTransaction();
        self::$client->inTransaction();
        self::$client->commit();
        $logEntriesExpectJson = <<<'EOD'
        [
            {
                "method": "info",
                "args": ["beginTransaction"],
                "meta": {"channel": "general.pdo", "icon": "fa fa-database"}
            },
            {
                "method": "info",
                "args": ["commit"],
                "meta": {"channel": "general.pdo", "icon": "fa fa-database"}
            }
        ]
EOD;
        $this->assertSame(
            \json_decode($logEntriesExpectJson, true),
            $this->getLogEntries()
        );

        self::$client->beginTransaction();
        self::$client->rollback();
        $logEntriesExpectJson = <<<'EOD'
        [
            {
                "method": "info",
                "args": ["beginTransaction"],
                "meta": {"channel": "general.pdo", "icon": "fa fa-database"}
            },
            {
                "method": "info",
                "args": ["rollBack"],
                "meta": {"channel": "general.pdo", "icon": "fa fa-database"}
            }
        ]
EOD;
        $this->assertSame(
            \json_decode($logEntriesExpectJson, true),
            $this->getLogEntries(2)
        );
    }

    public function testDebugOutput()
    {
        self::$client->onDebugOutput(new Event($this->debug));
        $logEntriesExpectJson = <<<'EOD'
        [
            {
                "method": "groupCollapsed",
                "args": ["PDO info", "sqlite"],
                "meta": {"argsAsParams": false, "channel": "general.pdo", "icon": "fa fa-database", "level": "info"}
            },
            {
                "method": "log",
                "args": ["Logged operations: ", 8],
                "meta": {"channel": "general.pdo"}
            },
            {
                "method": "time",
                "args": ["Total time: 2.28 ms"],
                "meta": {"channel": "general.pdo"}
            },
            {
                "method": "log",
                "args": ["Peak memory usage", "280.73 kB"],
                "meta": {"channel": "general.pdo"}
            },
            {
                "method": "log",
                "args": [
                    "Server info",
                    {"Version": "3.36.0"}
                ],
                "meta": {"channel": "general.pdo"}
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.pdo"}
            }
        ]
EOD;
        $logEntriesExpect = \json_decode($logEntriesExpectJson, true);

        $logEntries = $this->getLogEntries(null, 'logSummary/0');
        // duration
        $logEntriesExpect[2]['args'][0] = $logEntries[2]['args'][0];
        // memory
        $logEntriesExpect[3]['args'][1] = $logEntries[3]['args'][1];
        // server info
        $logEntriesExpect[4]['args'][1] = $logEntries[4]['args'][1];
        $this->assertSame($logEntriesExpect, $logEntries);
    }

    public function testMisc()
    {
        $this->assertIsString(self::$client->errorCode());
        $this->assertIsArray(self::$client->errorInfo());
        $this->assertSame("'1'' OR ''1''=''1'' --'", self::$client->quote("1' OR '1'='1' --"));
    }
}
