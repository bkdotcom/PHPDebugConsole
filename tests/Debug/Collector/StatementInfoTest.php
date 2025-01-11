<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Collector\StatementInfoLogger;
use bdk\Debug\Utility\Reflection;
use bdk\Test\Debug\DebugTestFramework;
use Exception;

/**
 * @covers \bdk\Debug\Collector\StatementInfo
 * @covers \bdk\Debug\Collector\StatementInfoLogger
 * @covers \bdk\Debug\Utility\Sql
 * @covers \bdk\Debug\Utility\SqlQueryAnalysis
 */
class StatementInfoTest extends DebugTestFramework
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

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Reflection::propSet('bdk\Debug\Collector\StatementInfoLogger', 'constants', array());
    }

    public function testConstruct()
    {
        $sql = 'SELECT `first_name`, `last_name`, `password` FROM `users` u'
            . ' LEFT JOIN `user_info` ui ON ui.user_id = u.id'
            . ' WHERE u.username = ?'
            . ' LIMIT 1';
        $params = array('bkent');
        $types = array('s');
        $info = new StatementInfo($sql, $params, $types);
        $exception = new Exception('it broke', 666);
        $info->end($exception, 1);
        $info->setMemoryUsage(123);
        $this->assertIsFloat($info->duration);
        $this->assertSame(123, $info->memoryUsage);
        $this->assertSame(666, $info->errorCode);
        $this->assertSame('it broke', $info->errorMessage);
        $debugInfo = $info->__debugInfo();
        $this->assertInstanceOf('Exception', $debugInfo['exception']);
        $this->assertIsFloat($debugInfo['duration']);
        $this->assertSame(123, $debugInfo['memoryUsage']);
        $this->assertSame($params, $debugInfo['params']);
        $this->assertSame($types, $debugInfo['types']);
        $this->assertSame(1, $debugInfo['rowCount']);
        $this->assertSame($sql, $sql);
    }

    public function testAppendLog()
    {
        $sql = 'SELECT `first_name`, `last_name`, `password` FROM `users` u'
            . ' LEFT JOIN `user_info` ui ON ui.user_id = u.id'
            . ' WHERE u.username = ?'
            . ' LIMIT 1';
        $params = array('bkent');
        $types = array('s');
        $info = new StatementInfo($sql, $params, $types);
        $exception = new Exception('it broke', 666);
        $info->end($exception, 1);
        $info->setDuration(0.0123);

        $statementInfoLogger = new StatementInfoLogger($this->debug);
        $statementInfoLogger->log($info);

        $logEntries = $this->getLogEntries();
        // echo \json_encode($logEntries, JSON_PRETTY_PRINT);
        $logEntriesExpectJson = <<<'EOD'
        {
            "statementInfo1": {
                "method": "group",
                "args": ["SELECT `first_name`, `last_name`, `password` FROM `users` (\u2026) WHERE u.username = 'bkent'\u2026"],
                "meta": {
                    "attribs": {
                        "id": "statementInfo1",
                        "class": []
                    },
                    "boldLabel": false, "icon": "fa fa-list-ul"
                }
            },
            "0": {
                "method": "log",
                "args": [
                    {
                        "attribs": {
                            "class": ["highlight", "language-sql", "no-quotes"]
                        },
                        "brief": false,
                        "contentType": "application\/sql",
                        "debug": "\u0000debug\u0000",
                        "prettified": true,
                        "prettifiedTag": false,
                        "type": "string",
                        "typeMore": null,
                        "value": "SELECT \n  `first_name`, \n  `last_name`, \n  `password` \nFROM \n  `users` u \n  LEFT JOIN `user_info` ui ON ui.user_id = u.id \nWHERE \n  u.username = ? \nLIMIT \n  1"
                    }
                ],
                "meta": {
                    "attribs": {
                        "class": ["no-indent"]
                    }
                }
            },
            "1": {
                "method": "table",
                "args": [
                    [
                        {"value": "bkent", "type": "s"}
                    ]
                ],
                "meta": {
                    "caption": "parameters",
                    "sortable": true,
                    "tableInfo": {
                        "class": null,
                        "columns": [
                            {"key": "value"},
                            {"key": "type"}
                        ],
                        "haveObjRow": false,
                        "indexLabel": null,
                        "rows": [],
                        "summary": ""
                    }
                }
            },
            "2": {
                "method": "time",
                "args": ["duration: 12.3 ms"],
                "meta": []
            },
            "3": {
                "method": "log",
                "args": ["memory usage", "6.13 kB"],
                "meta": []
            },
            "4": {
                "method": "warn",
                "args": [
                    "%cLIMIT%c without %cORDER BY%c causes non-deterministic results",
                    "font-family:monospace",
                    "",
                    "font-family:monospace",
                    ""
                ],
                "meta": {
                    "detectFiles": true,
                    "file": "\/Users\/bkent\/Dropbox\/htdocs\/common\/vendor\/bdk\/PHPDebugConsole\/tests\/Debug\/Collector\/StatementInfoTest.php",
                    "line": 53,
                    "uncollapse": false
                }
            },
            "5": {
                "method": "warn",
                "args": [
                    "Exception: it broke (code 666)"
                ],
                "meta": {
                    "detectFiles": true,
                    "file": "\/Users\/bkent\/Dropbox\/htdocs\/common\/vendor\/bdk\/PHPDebugConsole\/tests\/Debug\/Collector\/StatementInfoTest.php",
                    "line": 53,
                    "uncollapse": true
                }
            },
            "6": {
                "method": "groupEnd",
                "args": [],
                "meta": []
            }
        }
EOD;
        $logEntriesExpect = \json_decode($logEntriesExpectJson, true);
        // duration
        // $logEntriesExpect[3]['args'][0] = $logEntries[3]['args'][0];
        // memory usage
        $logEntriesExpect[3]['args'][1] = $logEntries[3]['args'][1];
        $logEntriesExpect[4]['meta']['file'] = $logEntries[4]['meta']['file'];
        $logEntriesExpect[4]['meta']['line'] = $logEntries[4]['meta']['line'];
        $logEntriesExpect[5]['meta']['file'] = $logEntries[5]['meta']['file'];
        $logEntriesExpect[5]['meta']['line'] = $logEntries[5]['meta']['line'];
        // \bdk\Debug::varDump('expect', $logEntriesExpect);
        // \bdk\Debug::varDump('actual', $logEntries);
        $this->assertSame($logEntriesExpect, $logEntries);
    }
}
