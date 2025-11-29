<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for LogFiles plugin
 *
 * @covers \bdk\Debug\Plugin\LogFiles
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class LogFilesTest extends DebugTestFramework
{
    public function testOnOutput()
    {
        // debugTestFramework removed the logfiles channel

        // test logEnvInfo.files = false
        $logFiles = $this->debug->getPlugin('logFiles');
        $event = new Event($this->debug);

        $logFiles->onOutput($event);
        self::assertSame(array(), $this->debug->data->get('log'));

        // test no files
        $this->debug->getChannel('files', array('nested' => false))->clear(Debug::CLEAR_SILENT);
        $this->debug->setCfg('logEnvInfo', array('files' => true));
        $logFiles->setFiles(array(
            'path/to/file.php',
        ));
        $logFiles->setCfg('filesExclude', array(
            'path/to/file.php',
        ));
        $logFiles->onOutput($event);
        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);
        self::assertSame(array(
            array(
                'method' => 'info',
                'args' => array(
                    'files required',
                    1,
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
            array(
                'method' => 'info',
                'args' => array(
                    'All files excluded from logging',
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'See %clogFiles.filesExclude%c config',
                    'font-family: monospace;',
                    '',
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
        ), $logEntries);

        // test asTree = true;
        $this->debug->getChannel('files')->clear(Debug::CLEAR_SILENT);
        $logFiles->setCfg('filesExclude', array(
            '/excludedDir',
            'closure://function',
        ));
        $logFiles->setFiles(array(
            '/var/www/bootstrap.php',
            '/var/www/index.php',
            '/var/www/excludedDir/subdir/file.php',
            'closure://function',
        ));
        $logFiles->onOutput($event);
        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);
        self::assertSame(array(
            array(
                'method' => 'info',
                'args' => array(
                    'files required',
                    4,
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
            array(
                'method' => 'info',
                'args' => array(
                    'files logged',
                    2,
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    array(
                        'debug' => Abstracter::ABSTRACTION,
                        'options' => array(
                            'asFileTree' => true,
                            'expand' => true,
                        ),
                        'type' => 'array',
                        'value' => array(
                            '/var/www' => array(
                                'excludedDir' => array(
                                    array(
                                        'attribs' => array(
                                            'class' => ['exclude-count'],
                                        ),
                                        'debug' => Abstracter::ABSTRACTION,
                                        'type' => 'string',
                                        'value' => '1 omitted',
                                    ),
                                ),
                                array(
                                    'attribs' => array(
                                        'class' => [],
                                        'data-file' => '/var/www/bootstrap.php',
                                    ),
                                    'debug' => Abstracter::ABSTRACTION,
                                    'type' => 'string',
                                    'value' => 'bootstrap.php',
                                ),
                                array(
                                    'attribs' => array(
                                        'class' => [],
                                        'data-file' => '/var/www/index.php',
                                    ),
                                    'debug' => Abstracter::ABSTRACTION,
                                    'type' => 'string',
                                    'value' => 'index.php',
                                ),
                            ),
                            'closure://function' => array(
                                array(
                                    'attribs' => array(
                                        'class' => ['exclude-count'],
                                    ),
                                    'debug' => Abstracter::ABSTRACTION,
                                    'type' => 'string',
                                    'value' => '1 omitted',
                                ),
                            ),
                        ),
                    ),
                ),
                'meta' => array(
                    'channel' => 'files',
                    'detectFiles' => true,
                ),
            ),
        ), $logEntries);

        // test asTree = false;
        $this->debug->getChannel('files')->clear(Debug::CLEAR_SILENT);
        $logFiles->setCfg('asTree', false);
        $logFiles->onOutput($event);
        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);
        // echo json_encode($logEntries, JSON_PRETTY_PRINT) . "\n";
        self::assertSame(array(
            array(
                'method' => 'info',
                'args' => array(
                    'files required',
                    4,
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
            array(
                'method' => 'info',
                'args' => array(
                    'files logged',
                    2,
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    array(
                        '/var/www/bootstrap.php',
                        '/var/www/index.php',
                    ),
                ),
                'meta' => array(
                    'channel' => 'files',
                    'detectFiles' => true,
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    '2 excluded files',
                    array(
                        '/var/www/excludedDir' => 1,
                        'closure://function' => 1,
                    ),
                ),
                'meta' => array(
                    'channel' => 'files',
                ),
            ),
        ), $logEntries);
    }
}
