<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class LogFilesTest extends DebugTestFramework
{
    public function testOnOutput()
    {
        // debugTestFramework removed the logfiles channel
        // $this->debug->addPlugin($this->debug->pluginLogFiles);

        // test logEnvInfo.files = false
        $this->debug->pluginLogFiles->onOutput();
        $this->assertSame(array(), $this->debug->data->get('log'));

        // test no files
        $this->debug->getChannel('Files')->clear(Debug::CLEAR_SILENT);
        $this->debug->setCfg('logEnvInfo', array('files' => true));
        $this->debug->pluginLogFiles->setFiles(array());
        $this->debug->pluginLogFiles->onOutput();
        $logEntries = $this->debug->data->get('log');
        $logEntries = \array_map(array($this, 'logEntryToArray'), $logEntries);
        $this->assertSame(array(
            array(
                'method' => 'info',
                'args' => array(
                    '0 files required',
                ),
                'meta' => array(
                    'channel' => 'Files',
                ),
            ),
            array(
                'method' => 'info',
                'args' => array(
                    'All files excluded from logging',
                ),
                'meta' => array(
                    'channel' => 'Files',
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
                    'channel' => 'Files',
                ),
            ),
        ), $logEntries);

        // test asTree = true;
        $this->debug->getChannel('Files')->clear(Debug::CLEAR_SILENT);
        $this->debug->pluginLogFiles->setCfg('filesExclude', array(
            '/excludedDir',
            'closure://function',
        ));
        $this->debug->pluginLogFiles->setFiles(array(
            '/var/www/bootstrap.php',
            '/var/www/index.php',
            '/var/www/excludedDir/subdir/file.php',
            'closure://function',
        ));
        $this->debug->pluginLogFiles->onOutput();
        $logEntries = $this->debug->data->get('log');
        $logEntries = \array_map(array($this, 'logEntryToArray'), $logEntries);
        $this->assertSame(array(
            array(
                'method' => 'info',
                'args' => array(
                    '4 files required',
                ),
                'meta' => array(
                    'channel' => 'Files',
                ),
            ),
            array(
                'method' => 'info',
                'args' => array(
                    '2 files logged',
                ),
                'meta' => array(
                    'channel' => 'Files',
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
                                            'class' => array('exclude-count'),
                                        ),
                                        'debug' => Abstracter::ABSTRACTION,
                                        'type' => 'string',
                                        'value' => '1 omitted',
                                    ),
                                ),
                                array(
                                    'attribs' => array(
                                        'data-file' => '/var/www/bootstrap.php',
                                        'class' => array(),
                                    ),
                                    'debug' => Abstracter::ABSTRACTION,
                                    'type' => 'string',
                                    'value' => 'bootstrap.php',
                                ),
                                array(
                                    'attribs' => array(
                                        'data-file' => '/var/www/index.php',
                                        'class' => array(),
                                    ),
                                    'debug' => Abstracter::ABSTRACTION,
                                    'type' => 'string',
                                    'value' => 'index.php',
                                ),
                            ),
                            'closure://function' => array(
                                array(
                                    'attribs' => array(
                                        'class' => array(
                                            'exclude-count',
                                        ),
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
                    'channel' => 'Files',
                    'detectFiles' => true,
                ),
            ),
        ), $logEntries);

        // test asTree = false;
        $this->debug->getChannel('Files')->clear(Debug::CLEAR_SILENT);
        $this->debug->pluginLogFiles->setCfg('asTree', false);
        $this->debug->pluginLogFiles->onOutput();
        $logEntries = $this->debug->data->get('log');
        $logEntries = \array_map(array($this, 'logEntryToArray'), $logEntries);
        // echo json_encode($logEntries, JSON_PRETTY_PRINT) . "\n";
        $this->assertSame(array(
            array(
                'method' => 'info',
                'args' => array(
                    '4 files required',
                ),
                'meta' => array(
                    'channel' => 'Files',
                ),
            ),
            array(
                'method' => 'info',
                'args' => array(
                    '2 files logged',
                ),
                'meta' => array(
                    'channel' => 'Files',
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
                    'channel' => 'Files',
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
                    'channel' => 'Files',
                ),
            ),
        ), $logEntries);
    }
}
