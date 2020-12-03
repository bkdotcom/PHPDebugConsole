<?php

namespace bdk\DebugTests\Collector;

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
        // $count = $count($logEntries);
        $logEntries = \array_slice($logEntries, -8);
        $logEntries = \array_map(function (LogEntry $logEntry) {
            $logEntry = $logEntry->export();
            $logEntry['args'] = \json_decode(\json_encode($logEntry['args']), true);
            \ksort($logEntry['meta']);
            // var_dump($logEntry);
            return $logEntry;
        }, $logEntries);
        // var_dump($logEntries);

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
                        'value' => "SELECT \n  * \nFROM \n  `bob` \nWHERE \n  e < :datetime",
                        'attribs' => array(
                            'class' => 'highlight language-sql',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                        'type' => 'string',
                        'debug' => \bdk\Debug\Abstraction\Abstracter::ABSTRACTION,
                    ),
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => 'no-indent',
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
                                'name' => 'PDO::PARAM_STR',
                                'value' => 2,
                                'type' => 'const',
                                'debug' => \bdk\Debug\Abstraction\Abstracter::ABSTRACTION,
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
