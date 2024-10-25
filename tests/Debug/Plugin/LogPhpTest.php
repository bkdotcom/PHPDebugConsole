<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\LogPhp;
use bdk\HttpMessage\ServerRequestExtended as ServerRequest;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;
use PHP_CodeSniffer\Tokenizers\PHP;

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
    public function testLogPhpInfo1()
    {
        \bdk\Test\Debug\Mock\Php::$memoryLimit = '128M';
        \bdk\Test\Debug\Mock\Php::$iniFiles = array('/path/to/php.ini');
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
        \bdk\Debug\Utility\Reflection::propSet($logPhp, 'iniValues', array(
            'dateTimezone' => 'America/Chicago',
        ));
        $logPhp->onBootstrap(new Event($this->debug));

        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertStringMatchesFormat(
            \json_encode(['PHP Version', PHP_VERSION . '%s']),
            \json_encode($logEntries[0]['args'])
        );

        $found = array(
            'dateTimezone' => null,
            'iniFiles' => null,
            'memoryLimit' => null,
            'server' => null,
        );
        foreach ($logEntries as $logEntry) {
            if ($logEntry['args'][0] === 'memory_limit') {
                $found['memoryLimit'] = $logEntry;
            } elseif ($logEntry['args'][0] === '$_SERVER') {
                $found['server'] = $logEntry;
            } elseif ($logEntry['args'][0] === 'ini location') {
                $found['iniFiles'] = $logEntry;
            } elseif ($logEntry['args'][0] === 'date.timezone') {
                $found['dateTimezone'] = true;
            }
        }
        self::assertSame('128 MB', $found['memoryLimit']['args'][1]);
        self::assertEquals(
            \array_intersect_key($serverParams, $found['server']['args'][1]),
            \array_intersect_key($found['server']['args'][1], $serverParams)
        );
        self::assertTrue($found['dateTimezone']);
        self::assertSame(array(
            'method' => 'log',
            'args' => array(
                'ini location',
                '/path/to/php.ini',
            ),
            'meta' => array(
                'channel' => 'php',
                'detectFiles' => true,
            ),
        ), $found['iniFiles']);
    }

    public function testLogPhpInfo2()
    {
        \bdk\Test\Debug\Mock\Php::$memoryLimit = '-1';
        \bdk\Test\Debug\Mock\Php::$iniFiles = array(
            '/path/to/php.ini',
            '/path/to/ext-xdebug.ini',
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
                    array()
                ),
            ),
        ));
        $logPhp = $this->debug->getPlugin('logPhp');
        \bdk\Debug\Utility\Reflection::propSet($logPhp, 'iniValues', array(
            'dateTimezone' => '',
        ));
        $logPhp->onBootstrap(new Event($this->debug));
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        $found = array(
            'bogusExtension' => false,
            'dateTimezone' => false,
            'iniFiles' => false,
            'memoryLimitWarning' => false,
            'server' => false,
        );
        foreach ($logEntries as $logEntry) {
            if ($found['server'] === false && $logEntry['args'][0] === '$_SERVER') {
                $found['server'] = true;
            } elseif ($found['bogusExtension'] === false && $logEntry['args'][0] === 'bogusExtension extension is not loaded') {
                $found['bogusExtension'] = true;
            } elseif ($logEntry['method'] === 'assert' && $logEntry['args'][0] === '%cmemory_limit%c: should not be -1 (no limit)') {
                $found['memoryLimitWarning'] = true;
            } elseif ($logEntry['args'][0] === 'ini files') {
                $found['iniFiles'] = $logEntry;
            } elseif ($logEntry['method'] === 'assert' && $logEntry['args'][0] === '%cdate.timezone%c is not set') {
                $found['dateTimezone'] = true;
            }
        }
        self::assertSame(array(
            'bogusExtension' => true,
            'dateTimezone' => true,
            'iniFiles' => array(
                'method' => 'log',
                'args' => array(
                    'ini files',
                    array(
                        'debug' => Abstracter::ABSTRACTION,
                        'options' => array(
                            'showListKeys' => false,
                        ),
                        'type' => 'array',
                        'value' => array(
                            '/path/to/php.ini',
                            '/path/to/ext-xdebug.ini',
                        ),
                    ),
                ),
                'meta' => array(
                    'channel' => 'php',
                    'detectFiles' => true,
                ),
            ),
            'memoryLimitWarning' => true,
            'server' => false,
        ), $found);
    }

    public function testLogPhpInfoEr()
    {
        $logPhp = new LogPhp();
        \bdk\Debug\Utility\Reflection::propSet($logPhp, 'debug', $this->debug->getChannel('php', array('nested' => false)));

        $refMethod = new \ReflectionMethod($logPhp, 'logPhpEr');
        $refMethod->setAccessible(true);

        $this->debug->addPlugin($logPhp);

        $this->debug->setCfg('logEnvInfo.errorReporting', true);

        /*
            Test error_reporting != "all" but debug is "all"
        */
        \error_reporting(E_ALL & ~E_NOTICE);
        $refMethod->invoke($logPhp);
        $current = PHP_VERSION_ID >= 80000
            ? 'E_ALL & ~E_NOTICE'
            : '( E_ALL | E_STRICT ) & ~E_NOTICE';
        $preferred = PHP_VERSION_ID >= 80000
            ? 'E_ALL'
            : 'E_ALL | E_STRICT';
        $this->testMethod(null, array(), array(
            'entry' => array(
                'method' => 'warn',
                'args' => array(
                    'PHP\'s %cerror_reporting%c is set to `%c' . $current . '%c` rather than `%c' . $preferred . '%c`' . "\n"
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
                    'channel' => 'php',
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
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
                    'PHP\'s %cerror_reporting%c is set to `%c' . $current . '%c` rather than `%c' . $preferred . '%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
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
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                    'channel' => 'php',
                ),
            ),
        );
        self::assertSame($expect, $log);

        /*
            Test debug != all (but has same value as error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_NOTICE);
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
                    'PHP\'s %cerror_reporting%c is set to `%c' . $current . '%c` rather than `%c' . $preferred . '%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                    'channel' => 'php',
                ),
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'PHPDebugConsole\'s errorHandler is also using a errorReporting value of `%c' . $current . '%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                    'channel' => 'php',
                ),
            ),
        );
        self::assertSame($expect, $log);

        /*
            Test debug != all (value different than error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_NOTICE & ~E_DEPRECATED);
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
                    'PHP\'s %cerror_reporting%c is set to `%c' . $current . '%c` rather than `%c' . $preferred . '%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                    'channel' => 'php',
                ),
            ),
            array(
                'method' => 'warn',
                'args' => array(
                    'PHPDebugConsole\'s errorHandler is using a errorReporting value of `%c' . $current . ' & ~E_DEPRECATED%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                'meta' => array(
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                    'channel' => 'php',
                ),
            ),
        );
        self::assertSame($expect, $log);

        /*
            Reset
        */
        \error_reporting(E_ALL);
        $this->debug->setCfg('errorReporting', E_ALL);
        $this->debug->setCfG('logEnvInfo.errorReporting', false);
    }
}
