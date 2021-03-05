<?php

namespace bdk\DebugTests\Collector;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class PdoTest extends DebugTestFramework
{

    static private $pdo;

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
        self::$pdo = new \bdk\Debug\Collector\Pdo($pdoBase);
        self::$pdo->exec($createTableSql);
    }

    public function testExecute()
    {
        $statement = self::$pdo->prepare('SELECT *
            FROM `bob`
            WHERE e < :datetime');
        $datetime = '2020-12-04 22:00:00';
        $line = __LINE__ + 2;
        $statement->bindParam(':datetime', $datetime, \PDO::PARAM_STR);
        $statement->execute();

        $logEntries = $this->debug->getData('log');
        $logEntries = \array_slice($logEntries, -8);
        $logEntries = \array_map(function (LogEntry $logEntry) {
            return $this->logEntryToArray($logEntry);
        }, $logEntries);

        $logEntriesExpect = array(
            array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'SELECT * FROM `bob`…',
                ),
                'meta' => array(
                    'boldLabel' => false,
                    'channel' => 'general.PDO',
                    'icon' => 'fa fa-database',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-sql'),
                        ),
                        'contentType' => 'application/sql',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => false,
                        'strlen' => null,
                        'type' => Abstracter::TYPE_STRING,
                        'typeMore' => null,
                        'value' => "SELECT \n  * \nFROM \n  `bob` \nWHERE \n  e < :datetime",
                        'visualWhiteSpace' => false,
                    ),
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => array('no-indent'),
                    ),
                    'channel' => 'general.PDO',
                ),
            ),
            array(
                'method' => 'table',
                'args' => array(
                    array(
                        ':datetime' => array(
                            'value' => $datetime,
                            'type' => array(
                                'debug' => Abstracter::ABSTRACTION,
                                'name' => 'PDO::PARAM_STR',
                                'type' => Abstracter::TYPE_CONST,
                                'value' => 2,
                            ),
                        ),
                    ),
                ),
                'meta' => array(
                    'caption' => 'parameters',
                    'channel' => 'general.PDO',
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
                        'summary' => null,
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
                    'channel' => 'general.PDO',
                ),
            ),
            array(
                'asString' => true,
                'method' => 'log',
                'args' => array(
                    'memory usage',
                    '%d B',
                ),
                'meta' => array(
                    'channel' => 'general.PDO',
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
                    'channel' => 'general.PDO',
                    'detectFiles' => true,
                    'file' => __FILE__,
                    'line' => $line,
                    'uncollapse' => false,
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'rowCount',
                    0,
                ),
                'meta' => array(
                    'channel' => 'general.PDO',
                )
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'general.PDO',
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
        /*
        $this->testMethod(
            null,
            null,
            array(
                'entry' => function (LogEntry $logEntry) {
                    var_dump($logEntry->getValues());
                },
            )
        );
        */
    }
}
