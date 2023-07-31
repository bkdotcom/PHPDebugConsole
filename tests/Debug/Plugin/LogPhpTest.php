<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\LogPhp;
use bdk\HttpMessage\ServerRequest;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for LogPhp plugin
 *
 * @covers \bdk\Debug\Plugin\LogPhp
 * @covers \bdk\Debug\ServiceProvider
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class LogPhpTest extends DebugTestFramework
{
    public function testLogPhpInfo()
    {
        $serverParams = \array_merge($this->debug->serverRequest->getServerParams(), array(
            'CONTENT_LENGTH' => 1234,
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'REQUEST_METHOD' => 'POST',
        ));
        $this->debug->setCfg(array(
            'logEnvInfo' => true,
            'serviceProvider' => array(
                'serverRequest' => new ServerRequest(
                    'POST',
                    null,
                    $serverParams
                ),
            ),
        ));
        $logPhp = $this->debug->getPlugin('logPhp');

        $logPhp->onPluginInit(new Event($this->debug));

        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertSame(array('PHP Version', PHP_VERSION), $logEntries[0]['args']);
        $found = null;
        foreach ($logEntries as $logEntry) {
            if ($logEntry['args'][0] === '$_SERVER') {
                $found = $logEntry;
            }
        }
        self::assertEquals(
            \array_intersect_key($serverParams, $found['args'][1]),
            \array_intersect_key($found['args'][1], $serverParams)
        );

        $this->debug->data->set('log', array());
        unset(
            $serverParams['CONTENT_LENGTH'],
            $serverParams['CONTENT_TYPE'],
            $serverParams['REQUEST_METHOD']
        );
        $this->debug->setCfg(array(
            'logEnvInfo' => true,
            'logServerKeys' => array(),
            'extensionsCheck' => array('bogusExtension'),
            'logRequestInfo' => true,
            'serviceProvider' => array(
                'serverRequest' => new ServerRequest(
                    'GET',
                    null,
                    $serverParams
                ),
            ),
        ));
        $logPhp->onPluginInit(new Event($this->debug));
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        $found = array(
            'server' => false,
            'bogusExtension' => false,
        );
        foreach ($logEntries as $logEntry) {
            if ($found['server'] === false && $logEntry['args'][0] === '$_SERVER') {
                $found['server'] = true;
            }
            if ($found['server'] === false && $logEntry['args'][0] === 'bogusExtension extension is not loaded') {
                $found['bogusExtension'] = true;
            }
        }
        self::assertSame(array(
            'server' => false,
            'bogusExtension' => true,
        ), $found);
    }

    public function testLogPhpInfoEr()
    {
        $logPhp = new LogPhp();

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
        $log = \array_map(static function (LogEntry $logEntry) {
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
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                ),
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'uncollapse' => true,
                    'file' => null,
                    'line' => null,
                    'channel' => 'php',
                ),
            ),
        );
        self::assertSame($expect, $log);

        /*
            Test debug != all (but has same value as error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT);
        $refMethod->invoke($logPhp);
        $log = $this->debug->data->get('log');
        $log = \array_slice($log, -2);
        $log = \array_map(static function (LogEntry $logEntry) {
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
                ),
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
                ),
            ),
        );
        self::assertSame($expect, $log);

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
        $log = \array_map(static function (LogEntry $logEntry) {
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
                ),
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
                ),
            ),
        );
        self::assertSame($expect, $log);

        /*
            Reset
        */
        \error_reporting(E_ALL | E_STRICT);
        $this->debug->setCfg('errorReporting', E_ALL | E_STRICT);
        $this->debug->setCfG('logEnvInfo.errorReporting', false);
    }
}
