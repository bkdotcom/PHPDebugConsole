<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\MySqli;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Mysqli debug collector
 *
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\AbstractObjectSubscriber
 * @covers \bdk\Debug\Collector\MySqli
 * @covers \bdk\Debug\Collector\MySqli\MySqliStmt
 * @covers \bdk\Debug\Collector\StatementInfo
 */
class MysqliTest extends DebugTestFramework
{
    private static $client;
    private static $error = false;

    public static function setUpBeforeClass(): void
    {
        $createDb = <<<'EOD'
        CREATE DATABASE IF NOT EXISTS `test`
        /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */
EOD;
        $createTable = <<<'EOD'
        CREATE TABLE IF NOT EXISTS bob (
            k INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            v BLOB,
            t char(32) NOT NULL,
            e datetime NULL DEFAULT NULL,
            ct INT NULL DEFAULT 0
        )
EOD;

        $error = false;
        \set_error_handler(function ($errType, $errMsg, $file, $line) use (&$error) {
            $error = true;
            echo \sprintf('Error %s - %s:%s', $errMsg, $file, $line) . "\n";
        });
        try {
            self::$client = new MySqli(
                \getenv('MYSQL_HOST'),
                \getenv('MYSQL_USERNAME'),
                \getenv('MYSQL_PASSWORD') ?: null,
                \getenv('MYSQL_DATABASE'),
                \getenv('MYSQL_PORT')
            );
        } catch (\Exception $e) {
            $error = true;
            echo __METHOD__ . ' Exception: ' . $e->getMessage() . "\n";
        }

        \restore_error_handler();

        if ($error) {
            self::$error = true;
            return;
        }

        self::$client->query($createDb);
        self::$client->query($createTable);

        \bdk\Test\Debug\Helper::setProp('bdk\\Debug\\Collector\\StatementInfo', 'constants', array());
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$client) {
            $debug = Debug::getInstance();
            $debug->getChannel('MySqli')
                ->eventManager->unsubscribe(Debug::EVENT_OUTPUT, array(self::$client, 'onDebugOutput'));
        }
    }

    public function testConstruct()
    {
        $this->assertPhpClient();

        $client1 = new MySqli(
            \getenv('MYSQL_HOST'),
            \getenv('MYSQL_USERNAME'),
            \getenv('MYSQL_PASSWORD') ?: null,
            \getenv('MYSQL_DATABASE'),
            \getenv('MYSQL_PORT')
        );
        $this->debug->getChannel('MySqli')
            ->eventManager->unsubscribe(Debug::EVENT_OUTPUT, array($client1, 'onDebugOutput'));
        $this->assertTrue($client1->connectionAttempted);

        $client2 = new MySqli();
        $this->debug->getChannel('MySqli')
            ->eventManager->unsubscribe(Debug::EVENT_OUTPUT, array($client2, 'onDebugOutput'));
        $this->assertFalse($client2->connectionAttempted);
    }

    public function testAutocommit()
    {
        $this->assertPhpClient();

        self::$client->autocommit(false);
        $logEntry = $this->helper->logEntryToArray($this->debug->data->get('log/__end__'));
        $this->assertSame(array(
            'method' => 'info',
            'args' => array('autocommit', false),
            'meta' => array(
                'channel' => 'general.MySqli',
            ),
        ), $logEntry);
    }

    public function testExecuteQuery()
    {
        $this->assertPhpClient();
        if (PHP_VERSION_ID < 80200) {
            $this->markTestSkipped('execute_query is php 8.2+');
        }
        $result = self::$client->execute_query(
            'INSERT INTO `bob` (`t`, `e`, `ct`) VALUES (?, ?, ?)',
            [
                'brad was here',
                \gmdate('Y-m-d H:i:s'),
                42,
            ]
        );
        $this->assertTrue($result);
        $logEntriesExpectJson = <<<EOD
        [
            {
                "method": "groupCollapsed",
                "args": ["INSERT INTO `bob`\u2026"],
                "meta": {"boldLabel": false, "channel": "general.MySqli", "icon": "fa fa-database"}
            },
            {
                "method": "log",
                "args": [
                    {
                        "addQuotes": false,
                        "attribs": {
                            "class": [
                                "highlight",
                                "language-sql"
                            ]
                        },
                        "brief": false,
                        "contentType": "application\/sql",
                        "debug": "\u0000debug\u0000",
                        "prettified": true,
                        "prettifiedTag": false,
                        "strlen": null,
                        "type": "string",
                        "typeMore": null,
                        "value": "INSERT INTO `bob` (`t`, `e`, `ct`) \\nVALUES \\n  (?, ?, ?)",
                        "visualWhiteSpace": false
                    }
                ],
                "meta": {
                    "attribs": {"class": ["no-indent"]},
                    "channel": "general.MySqli"
                }
            },
            {
                "method": "log",
                "args": ["parameters", [
                    "brad was here",
                    "%s",
                    42
                ]],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "time",
                "args": ["duration: %s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["memory usage", "%s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["rowCount", 1],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.MySqli"}
            }
        ]
EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries());
    }

    public function testMultiQuery()
    {
        $this->assertPhpClient();

        $query = 'SELECT CURRENT_USER();';
        $query .= 'SELECT `t` from `bob` LIMIT 10';

        self::$client->multi_query($query);
        $line = __LINE__ - 1;
        do {
            $result = self::$client->store_result();
            if ($result) {
                while ($row = $result->fetch_row()) {
                    // printf("%s\n", $row[0]);
                }
            }
        } while (self::$client->more_results() && self::$client->next_result());

        $logEntriesExpectJson = <<<EOD
        [
            {
                "method": "groupCollapsed",
                "args": ["SELECT CURRENT_USER();SELECT `t` from `bob`\u2026"],
                "meta": {"boldLabel": false, "channel": "general.MySqli", "icon": "fa fa-database"}
            },
            {
                "method": "log",
                "args": [
                    {
                        "addQuotes": false,
                        "attribs": {
                            "class": [
                                "highlight",
                                "language-sql"
                            ]
                        },
                        "brief": false,
                        "contentType": "application\/sql",
                        "debug": "\u0000debug\u0000",
                        "prettified": true,
                        "prettifiedTag": false,
                        "strlen": null,
                        "type": "string",
                        "typeMore": null,
                        "value": "SELECT \\n  CURRENT_USER(); \\nSELECT \\n  `t` \\nfrom \\n  `bob` \\nLIMIT \\n  10",
                        "visualWhiteSpace": false
                    }
                ],
                "meta": {
                    "attribs": {"class": ["no-indent"] },
                    "channel": "general.MySqli"
                }
            },
            {
                "method": "time",
                "args": ["duration: %s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["memory usage", "%s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "warn",
                "args": [
                    "The %%cSELECT%%c statement has no %%cWHERE%%c clause and could examine many more rows than intended",
                    "font-family:monospace",
                    "",
                    "font-family:monospace",
                    ""
                ],
                "meta": {
                    "channel": "general.MySqli",
                    "detectFiles": true,
                    "file": "%s",
                    "line": {$line},
                    "uncollapse": false
                }
            },
            {
                "method": "warn",
                "args": ["%%cLIMIT%%c without %%cORDER BY%%c causes non-deterministic results", "font-family:monospace", "", "font-family:monospace", ""],
                "meta": {
                    "channel": "general.MySqli",
                    "detectFiles": true,
                    "file": "%s",
                    "line": {$line},
                    "uncollapse": false
                }
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.MySqli"}
            }
        ]
EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries(9));
    }

    public function testDebugMysqliObj()
    {
        $this->debug->log('mysqli', new \mysqli());
        $logEntry = $this->debug->data->get('log/__end__');
        $this->assertTrue($logEntry instanceof \bdk\Debug\LogEntry);
    }

    public function testPrepareBindExecute()
    {
        $this->assertPhpClient();

        $stmt = self::$client->prepare('INSERT INTO `bob` (`t`, `e`, `ct`) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $text, $datetime, $int);
        $text = 'brad was here';
        $datetime = \gmdate('Y-m-d H:i:s');
        $int = 42;
        $stmt->execute();
        $logEntriesExpectJson = <<<'EOD'
        [
            {
                "method": "groupCollapsed",
                "args": ["INSERT INTO `bob`\u2026"],
                "meta": {"boldLabel": false, "channel": "general.MySqli", "icon": "fa fa-database"}
            },
            {
                "method": "log",
                "args": [
                    {"addQuotes": false, "attribs": {"class": ["highlight", "language-sql"] }, "brief": false, "contentType": "application\/sql", "debug": "\u0000debug\u0000", "prettified": true, "prettifiedTag": false, "strlen": null, "type": "string", "typeMore": null, "value": "INSERT INTO `bob` (`t`, `e`, `ct`) \nVALUES \n  (?, ?, ?)", "visualWhiteSpace": false }
                ],
                "meta": {"attribs": {"class": ["no-indent"] }, "channel": "general.MySqli"}
            },
            {
                "method": "table",
                "args": [
                    [
                        {"value": "brad was here", "type": "s"},
                        {"value": "%s", "type": "s"},
                        {"value": 42, "type": "i"}
                    ]
                ],
                "meta": {"caption": "parameters", "channel": "general.MySqli", "sortable": true, "tableInfo": {"class": null, "columns": [{"key": "value"}, {"key": "type"} ], "haveObjRow": false, "indexLabel": null, "rows": [], "summary": null } }
            },
            {
                "method": "time",
                "args": ["duration: %s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["memory usage", "%s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["rowCount", 1 ],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.MySqli"}
            }
        ]
EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries(8));
    }

    public function testRealQuery()
    {
        $this->assertPhpClient();

        $success = self::$client->real_query('SELECT * from `bob`');
        $line = __LINE__ - 1;
        if ($success) {
            do {
                $result = self::$client->store_result();
                if ($result) {
                    $result->free();
                }
            } while (self::$client->more_results() && self::$client->next_result());
        }
        $logEntriesExpectJson = <<<EOD
        [
            {
                "method": "groupCollapsed",
                "args": ["SELECT * from `bob`"],
                "meta": {"boldLabel": false, "channel": "general.MySqli", "icon": "fa fa-database"}
            },
            {
                "method": "time",
                "args": ["duration: %s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["memory usage", "%s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "warn",
                "args": ["Use %%cSELECT *%%c only if you need all columns from table", "font-family:monospace", ""],
                "meta": {
                    "channel": "general.MySqli",
                    "detectFiles": true,
                    "file": "%s",
                    "line": {$line},
                    "uncollapse": false
                }
            },
            {
                "method": "warn",
                "args": [
                    "The %%cSELECT%%c statement has no %%cWHERE%%c clause and could examine many more rows than intended",
                    "font-family:monospace",
                    "",
                    "font-family:monospace",
                    ""
                ],
                "meta": {
                    "channel": "general.MySqli",
                    "detectFiles": true,
                    "file": "%s",
                    "line": {$line},
                    "uncollapse": false
                }
            },
            {
                "method": "log",
                "args": ["rowCount", -1 ],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.MySqli"}
            }
        ]

EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries(9));
    }

    public function testRealConnect()
    {
        $this->assertPhpClient();

        $result = self::$client->real_connect(
            \getenv('MYSQL_HOST'),
            \getenv('MYSQL_USERNAME'),
            \getenv('MYSQL_PASSWORD') ?: null,
            \getenv('MYSQL_DATABASE'),
            \getenv('MYSQL_PORT')
        );
        $this->assertTrue($result);
    }

    public function testReleaseSavepoint()
    {
        $this->assertPhpClient();

        $result1 = self::$client->begin_transaction();
        $result2 = self::$client->savepoint('Sally');
        $logEntry = $this->helper->logEntryToArray($this->debug->data->get('log/__end__'));
        $this->assertSame(array(
            'method' => 'info',
            'args' => array('savepoint', 'Sally'),
            'meta' => array(
                'channel' => 'general.MySqli',
            ),
        ), $logEntry);
        $result3 = self::$client->release_savepoint('Sally');
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);

        $result4 = self::$client->release_savepoint('Sally');
        $line = __LINE__ - 1;
        if (PHP_VERSION_ID < 70000 || \mysqli_get_client_version() <= 50082) {
            // https://bugs.mysql.com/bug.php?id=26288
            $this->assertTrue($result4);
            return;
        }
        $this->assertFalse($result4);
        $logEntry = $this->helper->logEntryToArray($this->debug->data->get('log/__end__'));
        $this->assertSame(array(
            'method' => 'warn',
            'args' => array('SAVEPOINT Sally does not exist'),
            'meta' => array(
                'channel' => 'general.MySqli',
                'detectFiles' => true,
                'file' => __FILE__,
                'line' => $line,
                'uncollapse' => true,
            ),
        ), $logEntry);
    }

    public function testRollback()
    {
        $this->assertPhpClient();

        self::$client->begin_transaction();
        self::$client->query('INSERT INTO `bob` (`t`) VALUES ("rollback test")');
        self::$client->rollback();

        $logEntriesExpectJson = <<<'EOD'
            [
                {
                    "method": "groupEndValue",
                    "args": [
                        "return",
                        "rolled back"
                    ],
                    "meta": {
                        "channel": "general.MySqli"
                    }
                },
                {
                    "method": "groupEnd",
                    "args": [],
                    "meta": {
                        "channel": "general.MySqli"
                    }
                }
            ]
EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries(2));
    }

    /*
    public function testRollbackError()
    {
        $this->assertPhpClient();

        $result = self::$client->rollback(0, 'Jimbo');

        $logEntries = $this->getLogEntries(2);
        $this->helper->stderr(__FUNCTION__, $result, $logEntries);
        $this->assertFalse($result);
    }
    */

    public function testRollbackName()
    {
        $this->assertPhpClient();

        self::$client->begin_transaction(MYSQLI_TRANS_START_READ_WRITE, 'Sally');
        self::$client->rollback(0, 'Sally');
        $line = __LINE__ - 1;
        $logEntries = $this->getLogEntries(3);
        $this->assertSame(array(
            array(
                'method' => 'warn',
                'args' => array(
                    'passing $name param to %cmysqli::rollback()%c does not %cROLLBACK TO name%c as you would expect!',
                    'font-family: monospace;',
                    '',
                    'font-family: monospace;',
                    '',
                ),
                'meta' => array(
                    'channel' => 'general.MySqli',
                    'detectFiles' => true,
                    'file' => __FILE__,
                    'line' => $line,
                    'uncollapse' => true,
                ),
            ),
            array(
                'method' => 'groupEndValue',
                'args' => array(
                    'return',
                    'rolled back',
                ),
                'meta' => array(
                    'channel' => 'general.MySqli',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.MySqli',
                ),
            ),
        ), $logEntries);
    }

    public function testSavepoint()
    {
        $this->assertPhpClient();

        $result = self::$client->savepoint('Sally');
        $result = self::$client->savepoint('Sally');
        $this->assertTrue($result);
        $logEntry = $this->helper->logEntryToArray($this->debug->data->get('log/__end__'));
        $this->assertSame(array(
            'method' => 'info',
            'args' => array('savepoint', 'Sally'),
            'meta' => array(
                'channel' => 'general.MySqli',
            ),
        ), $logEntry);
    }

    public function testStmtInit()
    {
        $this->assertPhpClient();

        $stmt = self::$client->stmt_init();
        $this->assertInstanceOf('bdk\\Debug\\Collector\\MySqli\\MySqliStmt', $stmt);
    }

    public function testTransaction()
    {
        $this->assertPhpClient();

        self::$client->begin_transaction();
        self::$client->query('INSERT INTO `bob` (`t`) VALUES ("test")');
        self::$client->commit();

        $logEntriesExpectJson = <<<'EOD'
            [
                {
                    "method": "group",
                    "args": ["transaction"],
                    "meta": {"channel": "general.MySqli", "icon": "fa fa-database"}
                },
                {
                    "method": "groupCollapsed",
                    "args": ["INSERT INTO `bob`\u2026"],
                    "meta": {"boldLabel": false, "channel": "general.MySqli", "icon": "fa fa-database"}
                },
                {
                    "method": "log",
                    "args": [
                        {
                            "addQuotes": false,
                            "attribs": {
                                "class": ["highlight", "language-sql"]
                            },
                            "brief": false,
                            "contentType": "application\/sql",
                            "debug": "\u0000debug\u0000",
                            "prettified": true,
                            "prettifiedTag": false,
                            "strlen": null,
                            "type": "string",
                            "typeMore": null,
                            "value": "INSERT INTO `bob` (`t`) \nVALUES \n  (\"test\")",
                            "visualWhiteSpace": false
                        }
                    ],
                    "meta": {"attribs": {"class": ["no-indent"] }, "channel": "general.MySqli"}
                },
                {
                    "method": "time",
                    "args": ["duration: %s"],
                    "meta": {"channel": "general.MySqli"}
                },
                {
                    "method": "log",
                    "args": ["memory usage", "%s"],
                    "meta": {"channel": "general.MySqli"}
                },
                {
                    "method": "log",
                    "args": ["rowCount", 1 ],
                    "meta": {"channel": "general.MySqli"}
                },
                {
                    "method": "groupEnd",
                    "args": [],
                    "meta": {"channel": "general.MySqli"}
                },
                {
                    "method": "groupEndValue",
                    "args": ["return", true ],
                    "meta": {"channel": "general.MySqli"}
                },
                {
                    "method": "groupEnd",
                    "args": [],
                    "meta": {"channel": "general.MySqli"}
                }
            ]
EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries(9));
    }

    public function testTransactionNamed()
    {
        $this->assertPhpClient();

        self::$client->begin_transaction(MYSQLI_TRANS_START_READ_WRITE, 'Billy');
        self::$client->query('INSERT INTO `bob` (`t`) VALUES ("test")');
        self::$client->commit(0, 'Billy');
        $line = __LINE__ - 1;
        $logEntriesExpectJson = <<<EOD
        [
            {
                "method": "group",
                "args": ["transaction", "Billy"],
                "meta": {
                    "channel": "general.MySqli",
                    "icon": "fa fa-database"
                }
            },
            {
                "method": "groupCollapsed",
                "args": ["INSERT INTO `bob`…"],
                "meta": {
                    "boldLabel": false,
                    "channel": "general.MySqli",
                    "icon": "fa fa-database"
                }
            },
            {
                "method": "log",
                "args": [
                    {
                        "addQuotes": false,
                        "attribs": {
                            "class": ["highlight", "language-sql"]
                        },
                        "brief": false,
                        "contentType": "application/sql",
                        "debug": "\u0000debug\u0000",
                        "prettified": true,
                        "prettifiedTag": false,
                        "strlen": null,
                        "type": "string",
                        "typeMore": null,
                        "value": "INSERT INTO `bob` (`t`) \\nVALUES \\n  (\"test\")",
                        "visualWhiteSpace": false
                    }
                ],
                "meta": {
                    "attribs": {
                        "class": ["no-indent"]
                    },
                    "channel": "general.MySqli"
                }
            },
            {
                "method": "time",
                "args": ["duration: %s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["memory usage", "%s"],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "log",
                "args": ["rowCount", 1],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "warn",
                "args": ["passing \$name param to mysqli::commit() does nothing!"],
                "meta": {
                    "channel": "general.MySqli",
                    "detectFiles": true,
                    "file": "%s",
                    "line": $line,
                    "uncollapse": true
                }
            },
            {
                "method": "groupEndValue",
                "args": ["return", true],
                "meta": {"channel": "general.MySqli"}
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": {"channel": "general.MySqli"}
            }
        ]
EOD;
        $this->assertLogEntries($logEntriesExpectJson, $this->getLogEntries());
    }

    public function testDebugOutput()
    {
        $this->assertPhpClient();

        self::$client->onDebugOutput(new Event($this->debug));
        $logEntriesExpectJson = <<<'EOD'
        [
            {
                "method": "groupCollapsed",
                "args": ["MySqli info", "%s"],
                "meta": {"argsAsParams": false, "icon": "fa fa-database", "level": "info"}
            },
            {
                "method": "log",
                "args": ["database", "test"],
                "meta": []
            },
            {
                "method": "log",
                "args": ["logged operations: ", 4],
                "meta": []
            },
            {
                "method": "time",
                "args": ["total time: %s"],
                "meta": []
            },
            {
                "method": "log",
                "args": ["max memory usage", "%s"],
                "meta": []
            },
            {
                "method": "log",
                "args": [
                    "server info",
                    {
                        "Flush tables": 1,
                        "Open tables": 1417,
                        "Opens": 2039,
                        "Queries per second avg": 0.019,
                        "Questions": 28865,
                        "Slow queries": 0,
                        "Threads": 3,
                        "Uptime": 1473778,
                        "Version": "5.7.36"
                    }
                ],
                "meta": []
            },
            {
                "method": "groupEnd",
                "args": [],
                "meta": []
            }
        ]
EOD;
        $logEntriesExpect = \json_decode($logEntriesExpectJson, true);
        $logEntries = $this->getLogEntries(null, 'logSummary/0');
        // total operations
        $logEntriesExpect[2]['args'][1] = $logEntries[2]['args'][1];
        // server info
        $logEntriesExpect[5]['args'][1] = $logEntries[5]['args'][1];
        $this->assertLogEntries($logEntriesExpect, $logEntries);
    }

    protected function assertPhpClient()
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Our MysqliStmt implementation requires PHP 5.6');
        }
        if (self::$error === true) {
            $this->markTestSkipped('Error initiating client');
        }
    }
}
