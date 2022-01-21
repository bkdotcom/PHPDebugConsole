<?php

namespace bdk\DebugTests\Plugin;

use bdk\Debug\LogEntry;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class LogPhpTest extends DebugTestFramework
{
    public function testLogPhpInfoEr()
    {

        $logPhp = new \bdk\Debug\Plugin\LogPhp();

        $refMethod = new \ReflectionMethod($logPhp, 'logPhpEr');
        $refMethod->setAccessible(true);

        $this->debug->addPlugin($logPhp);

        $this->debug->setCfg('logEnvInfo.errorReporting', true);

        /*
            Test error_reporting != "all" but debug is "all"
        */
        \error_reporting(E_ALL & ~E_STRICT);
        $refMethod->invoke($logPhp);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'method' => 'warn',
                'args' => array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                        . 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                    'channel' => 'php',
                ),
            ),
        ));

        /*
            Test debug != all (= "system")
        */
        $this->debug->setCfg('errorReporting', 'system');
        $refMethod->invoke($logPhp);
        $log = $this->debug->data->get('log');
        $log = \array_slice($log, -2);
        $log = \array_map(function (LogEntry $logEntry) {
            return $logEntry->export();
        }, $log);
        // echo 'log = ' . \json_encode($log, JSON_PRETTY_PRINT) . "\n";
        $expect =  array(
            array(
                'method' => 'warn',
                'args' => array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;'
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                )
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)'
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                )
            )
        );
        $this->assertSame($expect, $log);

        /*
            Test debug != all (but has same value as error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT);
        $refMethod->invoke($logPhp);
        $log = $this->debug->data->get('log');
        $log = \array_slice($log, -2);
        $log = \array_map(function (LogEntry $logEntry) {
            return $logEntry->export();
        }, $log);
        $expect =  array(
            array(
                'method' => 'warn',
                'args' => array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                )
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'PHPDebugConsole\'s errorHandler is also using a errorReporting value of `%cE_ALL & ~E_STRICT%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                )
            )
        );
        $this->assertSame($expect, $log);

        /*
            Test debug != all (value different than error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT & ~E_DEPRECATED);
        $refMethod->invoke($logPhp);
        /*
        $this->testMethod(null, array(), array(
            'entry' => array(
                'method' => 'warn',
                'args' => array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                        . 'PHPDebugConsole\'s errorHandler is using a errorReporting value of `%cE_ALL & ~E_STRICT & ~E_DEPRECATED%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
        ));
        */
        $log = $this->debug->data->get('log');
        $log = \array_slice($log, -2);
        $log = \array_map(function (LogEntry $logEntry) {
            return $logEntry->export();
        }, $log);
        $expect =  array(
            array(
                'method' => 'warn',
                'args' => array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                )
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'PHPDebugConsole\'s errorHandler is using a errorReporting value of `%cE_ALL & ~E_STRICT & ~E_DEPRECATED%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                )
            )
        );
        $this->assertSame($expect, $log);

        /*
            Reset
        */
        \error_reporting(E_ALL | E_STRICT);
        $this->debug->setCfg('errorReporting', E_ALL | E_STRICT);
        $this->debug->setCfG('logEnvInfo.errorReporting', false);
    }
}
