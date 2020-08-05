<?php

use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * PHPUnit tests for Debug Methods
 */
class MethodTest extends DebugTestFramework
{

    /**
     * Test overriding a core method
     */
    public function testOverrideOutput()
    {
        $closure = function ($event) {
            if ($event['method'] === 'trace') {
                $route = $event['route'];
                if ($route instanceof bdk\Debug\Route\ChromeLogger) {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($route instanceof bdk\Debug\Route\Firephp) {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($route instanceof bdk\Debug\Route\Html) {
                    $event['return'] = '<li class="m_trace">this was a trace</li>';
                } elseif ($route instanceof bdk\Debug\Route\Script) {
                    $event['return'] = 'console.log("this was a trace");';
                } elseif ($route instanceof bdk\Debug\Route\Text) {
                    $event['return'] = 'this was a trace';
                } elseif ($route instanceof bdk\Debug\Route\Wamp) {
                    $event['method'] = 'log';
                    $event['args'] = array('something completely different');
                    $meta = array_diff_key($event['meta'], array_flip(array('columns','caption','inclContext')));
                    $event['meta'] = $meta;
                }
            }
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $closure);
        $this->testMethod(
            'trace',
            array(),
            array(
                'entry' => function ($logEntry) {
                    // we're doing the custom stuff via Debug::EVENT_OUTPUT_LOG_ENTRY, so logEntry should still be trace
                    $this->assertSame('trace', $logEntry['method']);
                    $this->assertInternalType('array', $logEntry['args'][0]);
                    $this->assertSame(array(
                        'caption' => 'trace',
                        'columns' => array('file','line','function'),
                        'detectFiles' => true,
                        'inclContext' => false,
                    ), $logEntry['meta']);
                },
                'chromeLogger' => array(
                    array('this was a trace'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 35|[{"Type":"LOG"},"this was a trace"]|',
                'html' => '<li class="m_trace">this was a trace</li>',
                'script' => 'console.log("this was a trace");',
                'text' => 'this was a trace',
                'wamp' => array(
                    'log',
                    array('something completely different'),
                    array(
                        'detectFiles' => true,
                        'foundFiles' => array(),
                    ),
                ),
            )
        );
    }

    /**
     * Test custom method
     */
    public function testCustom()
    {
        $closure = function (LogEntry $logEntry) {
            if ($logEntry['method'] === 'myCustom' && $logEntry['route'] instanceof \bdk\Debug\Route\Html) {
                $lis = array();
                foreach ($logEntry['args'] as $arg) {
                    $lis[] = '<li>' . htmlspecialchars($arg) . '</li>';
                }
                $logEntry['return'] = '<li class="m_myCustom"><ul>' . implode('', $lis) . '</ul></li>';
            }
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $closure);
        $entry = array(
            'myCustom',
            array('How\'s it goin?'),
            array(
                'isCustomMethod' => true,
            ),
        );
        $this->testMethod(
            'myCustom',
            array('How\'s it goin?'),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('How\'s it goin?'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"How\'s it goin?"]|',
                'html' => '<li class="m_myCustom"><ul><li>How\'s it goin?</li></ul></li>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
                'wamp' => $entry,
            )
        );

        /*
            Now test it statically
        */
        Debug::_myCustom('called statically');
        $entry = array(
            'myCustom',
            array('called statically'),
            array(
                'isCustomMethod' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            null,
            array('called statically'),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('called statically'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"called statically"]|',
                'html' => '<li class="m_myCustom"><ul><li>called statically</li></ul></li>',
                'script' => 'console.log("called statically");',
                'text' => 'called statically',
                'wamp' => $entry,
            )
        );
    }

    public function testCustomDefault()
    {
        $entry = array(
            'myCustom',
            array('How\'s it goin?'),
            array(
                'isCustomMethod' => true,
            ),
        );
        $this->testMethod(
            'myCustom',
            array('How\'s it goin?'),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('How\'s it goin?'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"How\'s it goin?"]|',
                'html' => '<li class="m_myCustom"><span class="no-quotes t_string">How\'s it goin?</span></li>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testAlert()
    {
        $message = 'Ballistic missle threat inbound to Hawaii.  <b>Seek immediate shelter</b>.  This is not a drill.';
        $messageEscaped = htmlspecialchars($message);
        $entry = array(
            'alert',
            array($message),
            array(
                'dismissible' => false,
                'level' => 'error',
            ),
        );
        $this->testMethod(
            'alert',
            array($message),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        'padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #ffbaba;border: 1px solid #d8000c;color: #d8000c;',
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"ERROR"},' . json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-error m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => str_replace('%c', '%%c', 'console.log(' . json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #ffbaba;border: 1px solid #d8000c;color: #d8000c;");'),
                'text' => '》[Alert ⦻ error] ' . $message . '《',
                'wamp' => $entry,
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'alert',
            array($message),
            false
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testAssert()
    {
        $entry = array(
            'assert',
            array('this is false'),
            array(),
        );
        $this->testMethod(
            'assert',
            array(false, 'this is false'),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(false, 'this is false'),
                    null,
                    'assert',
                ),
                'firephp' => 'X-Wf-1-1-1-2: 32|[{"Type":"LOG"},"this is false"]|',
                'html' => '<li class="m_assert"><span class="no-quotes t_string">this is false</span></li>',
                'script' => 'console.assert(false,"this is false");',
                'text' => '≠ this is false',
                'wamp' => $entry,
            )
        );

        // no msg arguments
        $entry = array(
            'assert',
            array(
                'Assertion failed:',
                $this->file . ' (line ' . $this->line . ')',
            ),
            array(
                'detectFiles' => true,
            ),
        );
        $this->testMethod(
            'assert',
            array(false),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        false,
                        'Assertion failed:',
                        $this->file . ' (line ' . $this->line . ')',
                    ),
                    null,
                    'assert',
                ),
                'firephp' => 'X-Wf-1-1-1-2: %d|[{"Label":"Assertion failed:","Type":"LOG"},' . json_encode($this->file . ' (line ' . $this->line . ')', JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<li class="m_assert" data-detect-files="true"><span class="no-quotes t_string">Assertion failed: </span><span class="t_string">' . $this->file . ' (line ' . $this->line . ')</span></li>',
                'script' => 'console.assert(false,"Assertion failed:",' . json_encode($this->file . ' (line ' . $this->line . ')', JSON_UNESCAPED_SLASHES) . ');',
                'text' => '≠ Assertion failed: "' . $this->file . ' (line ' . $this->line . ')"',
                'wamp' => $this->debug->utility->arrayMergeDeep($entry, array(
                    2 => array('foundFiles' => array()),
                )),
            )
        );

        $this->testMethod(
            'assert',
            array(true, 'this is true... not logged'),
            false
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'assert',
            array(false, 'falsey'),
            false
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearDefault()
    {
        $entry = array(
            'clear',
            array('Cleared log (sans errors)'),
            array(
                'bitmask' => Debug::CLEAR_LOG,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => true,
                    'logErrors' => false,
                    'summary' => false,
                    'summaryErrors' => false,
                    'silent' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(),
            array(
                'custom' => function () {
                    $this->assertCount(1, $this->debug->getData('alerts'));
                    $this->assertCount(3, $this->debug->getData('logSummary/0'));
                    $this->assertCount(3, $this->debug->getData('logSummary/1'));
                    $this->assertCount(4, $this->debug->getData('log'));    // clear-summary gets added
                },
                'entry' => $entry,
                'chromeLogger' => array(
                    array('Cleared log (sans errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared log (sans errors)"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared log (sans errors)</span></li>',
                'script' => 'console.log("Cleared log (sans errors)");',
                'text' => '⌦ Cleared log (sans errors)',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearLogSilent()
    {
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_LOG | Debug::CLEAR_SILENT),
            array(
                'custom' => function () {
                    $this->assertCount(1, $this->debug->getData('alerts'));
                    $this->assertCount(3, $this->debug->getData('logSummary/0'));
                    $this->assertCount(3, $this->debug->getData('logSummary/1'));
                    $this->assertCount(3, $this->debug->getData('log'));
                    $lastMethod = $this->debug->getData('log/__end__/method');
                    $this->assertSame('warn', $lastMethod);
                },
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearAlerts()
    {
        $entry = array(
            'clear',
            array('Cleared alerts'),
            array(
                'bitmask' => Debug::CLEAR_ALERTS,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => true,
                    'log' => false,
                    'logErrors' => false,
                    'summary' => false,
                    'summaryErrors' => false,
                    'silent' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALERTS),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertCount(0, $this->debug->getData('alerts'));
                    $this->assertCount(3, $this->debug->getData('logSummary/0'));
                    $this->assertCount(3, $this->debug->getData('logSummary/1'));
                    $this->assertCount(6, $this->debug->getData('log'));
                },
                'chromeLogger' => array(
                    array('Cleared alerts'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared alerts"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared alerts</span></li>',
                'script' => 'console.log("Cleared alerts");',
                'text' => '⌦ Cleared alerts',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearSummary()
    {
        $entry = array(
            'clear',
            array('Cleared summary (sans errors)'),
            array(
                'bitmask' => Debug::CLEAR_SUMMARY,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => false,
                    'summary' => true,
                    'summaryErrors' => false,
                    'silent' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertCount(1, $this->debug->getData('alerts'));
                    $this->assertCount(1, $this->debug->getData('logSummary/0'));   // error remains
                    $this->assertCount(2, $this->debug->getData('logSummary/1'));   // group & error remain
                    $this->assertCount(6, $this->debug->getData('log'));
                    $this->assertSame(array(
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                        0 => array(),
                        1 => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('Cleared summary (sans errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared summary (sans errors)"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared summary (sans errors)</span></li>',
                'script' => 'console.log("Cleared summary (sans errors)");',
                'text' => '⌦ Cleared summary (sans errors)',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearErrors()
    {
        $entry = array(
            'clear',
            array('Cleared errors'),
            array(
                'bitmask' => Debug::CLEAR_LOG_ERRORS,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => true,
                    'summary' => false,
                    'summaryErrors' => false,
                    'silent' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_LOG_ERRORS),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertCount(1, $this->debug->getData('alerts'));
                    $this->assertCount(3, $this->debug->getData('logSummary/0'));
                    $this->assertCount(3, $this->debug->getData('logSummary/1'));
                    $this->assertCount(5, $this->debug->getData('log'));
                    $this->assertSame(array(
                        // 0 => array(1, 1),
                        // 1 => array(1, 1),
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                        0 => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                        1 => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('Cleared errors'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared errors"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared errors</span></li>',
                'script' => 'console.log("Cleared errors");',
                'text' => '⌦ Cleared errors',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearAll()
    {
        $entry = array(
            'clear',
            array('Cleared everything'),
            array(
                'bitmask' => Debug::CLEAR_ALL,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => true,
                    'log' => true,
                    'logErrors' => true,
                    'summary' => true,
                    'summaryErrors' => true,
                    'silent' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALL),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertCount(0, $this->debug->getData('alerts'));
                    $this->assertCount(0, $this->debug->getData('logSummary/0'));
                    $this->assertCount(1, $this->debug->getData('logSummary/1'));   // group remains
                    $this->assertCount(3, $this->debug->getData('log'));    // groups remain
                    $this->assertSame(array(
                        // 0 => array(0, 0),
                        // 1 => array(1, 1),
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                        0 => array(),
                        1 => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('Cleared everything'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared everything"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared everything</span></li>',
                'script' => 'console.log("Cleared everything");',
                'text' => '⌦ Cleared everything',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearSummaryInclErrors()
    {
        $entry = array(
            'clear',
            array('Cleared summary (incl errors)'),
            array(
                'bitmask' => Debug::CLEAR_SUMMARY | Debug::CLEAR_SUMMARY_ERRORS,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => false,
                    'summary' => true,
                    'summaryErrors' => true,
                    'silent' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY | Debug::CLEAR_SUMMARY_ERRORS),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertCount(1, $this->debug->getData('alerts'));
                    $this->assertCount(0, $this->debug->getData('logSummary/0'));
                    $this->assertCount(1, $this->debug->getData('logSummary/1'));   // group remains
                    $this->assertCount(6, $this->debug->getData('log'));
                    $this->assertSame(array(
                        // 0 => array(0, 0),
                        // 1 => array(1, 1),
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                        0 => array(),
                        1 => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('Cleared summary (incl errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared summary (incl errors)"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared summary (incl errors)</span></li>',
                'script' => 'console.log("Cleared summary (incl errors)");',
                'text' => '⌦ Cleared summary (incl errors)',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearCollectFalse()
    {
        /*
            assert cleared & logged even if collect is false
        */
        $this->clearPrep();
        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'clear',
            array(),
            array(
                'custom' => function ($logEntry) {
                    $this->assertSame('Cleared log (sans errors)', $logEntry['args'][0]);
                    $this->assertCount(4, $this->debug->getData('log'));    // clear-summary gets added
                },
                'wamp' => array(
                    'clear',
                    array(
                        'Cleared log (sans errors)'
                    ),
                    array(
                        'bitmask' => Debug::CLEAR_LOG,
                        'file' => $this->file,
                        'flags' => array(
                            'alerts' => false,
                            'log' => true,
                            'logErrors' => false,
                            'summary' => false,
                            'summaryErrors' => false,
                            'silent' => false,
                        ),
                        'line' => $this->line,
                    ),
                ),
            )
        );
        $this->debug->setCfg('collect', true);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearLogAndSummary()
    {
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_LOG | Debug::CLEAR_SUMMARY),
            array(
                'custom' => function ($logEntry) {
                    $this->assertSame('Cleared log (sans errors) and summary (sans errors)', $logEntry['args'][0]);
                }
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearAlertsLogSummary()
    {
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALERTS | Debug::CLEAR_LOG | Debug::CLEAR_SUMMARY),
            array(
                'custom' => function ($logEntry) {
                    $this->assertSame('Cleared alerts, log (sans errors), and summary (sans errors)', $logEntry['args'][0]);
                }
            )
        );
    }

    private function clearPrep()
    {
        $this->debug->setData(array(
            'alerts' => array(),
            'log' => array(),
            'logSummary' => array(),
        ));
        $this->debug->alert('alert');
        $this->debug->log('log');
        $this->debug->group('a');
        $this->debug->group('a1');
        $this->debug->warn('nested error');
        $this->debug->log('not an error');
        $this->debug->groupSummary();
        $this->debug->log('summary 0 stuff');
        $this->debug->group('summary 0 group');
        $this->debug->warn('summary 0 warn');
        $this->debug->groupSummary(1);
        $this->debug->log('summary 1 stuff');
        $this->debug->group('summary 1 group');
        $this->debug->warn('summary 1 warn');
    }

    /**
     * Test
     *
     * @return void
     */
    public function testCount()
    {
        $lines = array();
        $this->debug->count('count test');     // 1 (0)
        for ($i = 0; $i < 3; $i++) {
            if ($i > 0) {
                $lines[0] = __LINE__ + 1;
                $this->debug->count();         // 1,2 (3,6)
            }
            $this->debug->count('count test');      // 2,3,4 (1,4,7)
            $this->debug->count('count_inc test', Debug::COUNT_NO_OUT);  //  1,2,3, but not logged
            $lines[1] = __LINE__ + 1;
            Debug::_count();                   // 1,2,3 (2,5,8)
        }
        $this->debug->log(
            'count_inc test',
            $this->debug->count(
                'count_inc test',
                Debug::COUNT_NO_INC | Debug::COUNT_NO_OUT // only return
            )
        );
        $this->debug->count('count_inc test', Debug::COUNT_NO_INC);  // (9) //  doesn't increment

        $this->assertSame(array(
            array('count', array('count test',1), array()),
            array('count', array('count test',2), array()),
            array('count', array('count',1), array('file' => __FILE__,'line' => $lines[1],'statically' => true)),
            array('count', array('count',1), array('file' => __FILE__,'line' => $lines[0])),
            array('count', array('count test', 3), array()),
            array('count', array('count',2), array('file' => __FILE__,'line' => $lines[1],'statically' => true)),
            array('count', array('count',2), array('file' => __FILE__,'line' => $lines[0])),
            array('count', array('count test', 4), array()),
            array('count', array('count',3), array('file' => __FILE__,'line' => $lines[1],'statically' => true)),
            array('log', array('count_inc test', 3), array()),
            array('count', array('count_inc test',3), array()),
        ), array_map(function ($logEntry) {
            return $this->logEntryToArray($logEntry);
        }, $this->debug->getData('log')));

        // test label provided output
        $this->testMethod(
            null,
            array(),
            array(
                'chromeLogger' => array(
                    array('count_inc test', 3),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-3: 43|[{"Label":"count_inc test","Type":"LOG"},3]|',
                'html' => '<li class="m_count"><span class="no-quotes t_string">count_inc test</span> = <span class="t_int">3</span></li>',
                'script' => 'console.log("count_inc test",3);',
                'text' => '✚ count_inc test = 3',
                'wamp' => array(
                    'count',
                    array(
                        'count_inc test',
                        3,
                    ),
                ),
            )
        );

        // test no label provided output
        $this->testMethod(
            array(
                'dataPath' => 'log/2'
            ),
            array(),
            array(
                'chromeLogger' => array(
                    array('count', 1),
                    __FILE__ . ': ' . $lines[1],
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-4: %d|[{"File":"' . __FILE__ . '","Label":"count","Line":' . $lines[1] . ',"Type":"LOG"},1]|',
                'html' => '<li class="m_count" data-file="' . __FILE__ . '" data-line="' . $lines[1] . '"><span class="no-quotes t_string">count</span> = <span class="t_int">1</span></li>',
                'script' => 'console.log("count",1);',
                'text' => '✚ count = 1',
                'wamp' => array(
                    'messageIndex' => 2,
                    'count',
                    array(
                        'count',
                        1,
                    ),
                    array(
                        'file' => __FILE__,
                        'line' => $lines[1],
                        'statically' => true,
                    )
                ),
            )
        );

        /*
            Test passing flags as first param
        */
        $this->testMethod(
            'count',
            array(Debug::COUNT_NO_OUT),
            array(
                'notLogged' => true,
                'return' => 1,
            )
        );

        /*
            Count should still increment and return even though collect is off
        */
        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'count',
            array('count test'),
            array(
                'notLogged' => true,
                // 'return' => 5,
                // 'custom' => function () {
                    // $this->assertSame(5, $this->debug->getData('counts/count test'));
                // },
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testCountReset()
    {
        $this->debug->count('foo');
        $this->debug->count('foo');
        $this->testMethod(
            'countReset',
            array('foo'),
            array(
                'entry' => array(
                    'countReset',
                    array('foo', 0),
                    array(),
                ),
                'chromeLogger' => array(
                    array('foo', 0),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-70: 32|[{"Label":"foo","Type":"LOG"},0]|',
                'html' => '<li class="m_countReset"><span class="no-quotes t_string">foo</span> = <span class="t_int">0</span></li>',
                'script' => 'console.log("foo",0);',
                'text' => '✚ foo = 0',
                'wamp' => array(
                    'countReset',
                    array('foo', 0),
                    array(),
                ),
            )
        );
        $this->testMethod(
            'countReset',
            array('noExisty'),
            array(
                'entry' => array(
                    'countReset',
                    array('Counter \'noExisty\' doesn\'t exist.'),
                    array(),
                ),
                'chromeLogger' => array(
                    array('Counter \'noExisty\' doesn\'t exist.'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-73: 52|[{"Type":"LOG"},"Counter \'noExisty\' doesn\'t exist."]|',
                'html' => '<li class="m_countReset"><span class="no-quotes t_string">Counter \'noExisty\' doesn\'t exist.</span></li>',
                'script' => 'console.log("Counter \'noExisty\' doesn\'t exist.");',
                'text' => '✚ Counter \'noExisty\' doesn\'t exist.',
                'wamp' => array(
                    'countReset',
                    array('Counter \'noExisty\' doesn\'t exist.'),
                    array(),
                ),
            )
        );
        $this->testMethod(
            'countReset',
            array('noExisty', Debug::COUNT_NO_OUT),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testError()
    {
        $resource = fopen(__FILE__, 'r');
        $this->testMethod(
            'error',
            array('a string', array(), new stdClass(), $resource),
            array(
                'entry' => function ($entry) {
                    $this->assertSame('error', $entry['method']);
                    $this->assertSame('a string', $entry['args'][0]);
                    $this->assertSame(array(), $entry['args'][1]);
                    $this->assertTrue($this->checkAbstractionType($entry['args'][2], 'object'));
                    $this->assertTrue($this->checkAbstractionType($entry['args'][3], 'resource'));
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'a string',
                        array(),
                        array(
                            '___class_name' => 'stdClass',
                        ),
                        'Resource id #%d: stream',
                    ),
                    __DIR__ . '/DebugTestFramework.php: %d',
                    'error',
                )),
                'firephp' => 'X-Wf-1-1-1-3: %d|[{"File":"%s","Label":"a string","Line":%d,"Type":"ERROR"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_error" data-detect-files="true" data-file="' . __DIR__ . '/DebugTestFramework.php" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.error("a string",[],{"___class_name":"stdClass"},"Resource id #%i: stream","%s: line %d");',
                'text' => '⦻ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%i: stream',
            )
        );
        fclose($resource);

        /*
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => $errLine,
        ), $logEntry[2]);
        */

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'error',
            array('error message'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroup()
    {

        // $test = new \bdk\DebugTest\Test();
        // $testBase = new \bdk\DebugTest\TestBase();

        $this->testMethod(
            'group',
            array('a','b','c'),
            array(
                'entry' => array(
                    'group',
                    array('a','b','c'),
                    array(),
                ),
                'custom' => function () {
                    $this->assertSame(array(
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('a','b','c'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-4: 61|[{"Collapsed":"false","Label":"a","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label group-label-bold">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("a","b","c");',
                'text' => '▸ a("b", "c")',
                'wamp' => array(
                    'group',
                    array('a','b','c'),
                    array(),
                ),
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'group',
            array('not logged'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    public function testGroupHideIfEmpty()
    {
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            // @todo chromeLogger & firephp
            // 'firephp' => '',
            'html' => '<li class="m_log"><span class="no-quotes t_string">before group</span></li>
                <li class="m_log"><span class="no-quotes t_string">after group</span></li>',
            'script' => 'console.log("before group");
                console.log("after group");',
            'text' => 'before group
                after group',
        ));

        /*
            hideIfEmpty group containing log entry
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->log('something');
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.group("group");
                console.log("something");
                console.groupEnd();
                console.log("after group");',
        ));

        /*
            hideIfEmtpy group containing empty group
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->group('inner group empty');
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.group("group");
                console.group("inner group empty");
                console.groupEnd();
                console.groupEnd();
                console.log("after group");',
        ));

        /*
            hideIfEmtpy group containing hideIfEmty group
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->group('inner group empty', $this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("after group");',
        ));
    }

    public function testGroupUngroup()
    {
        $this->debug->log('before group');
        $this->debug->group('shazam', $this->debug->meta('ungroup'));
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("shazam");
                console.log("after group");',
        ));

        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group('shazam', $this->debug->meta('ungroup'));
        $this->debug->log('shazam2');
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("shazam2");
                console.log("after group");',
        ));
    }

    public function testGroupNoArgs()
    {
        $test = new \bdk\DebugTest\Test();
        $testBase = new \bdk\DebugTest\TestBase();

        /*
            Test default label
        */
        $this->methodWithGroup('foo', 10);
        $entry = array(
            'group',
            array(
                __CLASS__ . '->methodWithGroup',
                'foo',
                10
            ),
            array(
                'isFuncName' => true,
            ),
        );
        $this->testMethod(
            array(),    // test last called method
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        __CLASS__ . '->methodWithGroup',
                        'foo',
                        10,
                    ),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-6: %d|[{"Collapsed":"false","Label":"' . __CLASS__ . '->methodWithGroup","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname">' . __CLASS__ . '</span><span class="t_operator">-&gt;</span><span class="t_identifier">methodWithGroup</span>(</span><span class="t_string">foo</span>, <span class="t_int">10</span><span class="group-label group-label-bold">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("' . __CLASS__ . '->methodWithGroup","foo",10);',
                'text' => '▸ ' . __CLASS__ . '->methodWithGroup("foo", 10)',
                'wamp' => $entry,
            )
        );

        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $testBase->testBasePublic();
        $entry = array(
            'group',
            array(
                'bdk\DebugTest\TestBase->testBasePublic'
            ),
            array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase->testBasePublic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-7: 100|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase->testBasePublic","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">-&gt;</span><span class="t_identifier">testBasePublic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase->testBasePublic");',
                'text' => '▸ bdk\DebugTest\TestBase->testBasePublic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );

        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $test->testBasePublic();
        $entry = array(
            'group',
            array(
                'bdk\DebugTest\Test->testBasePublic'
            ),
            array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTest\Test->testBasePublic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-8: 96|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\Test->testBasePublic","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>Test</span><span class="t_operator">-&gt;</span><span class="t_identifier">testBasePublic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTest\\\Test->testBasePublic");',
                'text' => '▸ bdk\DebugTest\Test->testBasePublic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );

        // yes, we call Test... but static method is defined in TestBase
        // .... PHP
        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        \bdk\DebugTest\Test::testBaseStatic();
        $entry = array(
            'group',
            array(
                'bdk\DebugTest\TestBase::testBaseStatic'
            ),
            array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-9: 100|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase::testBaseStatic","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase::testBaseStatic");',
                'text' => '▸ bdk\DebugTest\TestBase::testBaseStatic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );

        // even if called with an arrow
        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $test->testBaseStatic();
        $entry = array(
            'group',
            array(
                'bdk\DebugTest\TestBase::testBaseStatic'
            ),
            array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-10: 100|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase::testBaseStatic","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase::testBaseStatic");',
                'text' => '▸ bdk\DebugTest\TestBase::testBaseStatic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );
    }

    public function testGroupNotArgsAsParams()
    {
        $entry = array(
            'group',
            array('a',10),
            array(
                'argsAsParams' => false,
            ),
        );
        $this->testMethod(
            'group',
            array(
                'a',
                10,
                $this->debug->meta('argsAsParams', false),
            ),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('a',10),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-4: 61|[{"Collapsed":"false","Label":"a","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold">a:</span> <span class="t_int">10</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("a",10);',
                'text' => '▸ a: 10',
                'wamp' => $entry,
            )
        );
    }

    private function methodWithGroup()
    {
        $this->debug->group();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroupCollapsed()
    {
        $entry = array(
            'groupCollapsed',
            array('a','b','c'),
            array(),
        );
        $this->testMethod(
            'groupCollapsed',
            array('a', 'b', 'c'),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertSame(array(
                        'main' => array(
                            0 => array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('a','b','c'),
                    null,
                    'groupCollapsed',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 60|[{"Collapsed":"true","Label":"a","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="collapsed group-header"><span class="group-label group-label-bold">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label group-label-bold">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.groupCollapsed("a","b","c");',
                'text' => '▸ a("b", "c")',
                'wamp' => $entry,
            )
        );

        // add a nested group that will get removed on output
        $this->debug->groupCollapsed($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after nested group');
        $this->outputTest(array(
            'html' => '<li class="m_group">
                <div class="collapsed group-header"><span class="group-label group-label-bold">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label group-label-bold">)</span></div>
                <ul class="group-body">
                    <li class="m_log"><span class="no-quotes t_string">after nested group</span></li>
                </ul>',
            'script' => 'console.groupCollapsed("a","b","c");
                console.log("after nested group");',
            'text' => '▸ a("b", "c")
                after nested group',
            // 'firephp' => '',
        ));

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'groupCollapsed',
            array('not logged'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroupEnd()
    {
        /*
            Create & close a group
        */
        $this->debug->group('a', 'b', 'c');
        $this->debug->groupEnd();
        $this->assertSame(array(
            'main' => array(),
        ), $this->debug->getData('groupStacks'));
        $log = $this->debug->getData('log');
        $this->assertCount(2, $log);
        $this->assertSame(array(
            array('group', array('a','b','c'), array()),
            array('groupEnd', array(), array()),
        ), array_map(function ($logEntry) {
            return $this->logEntryToArray($logEntry);
        }, $log));

        // reset log
        $this->debug->setData('log', array());

        // create a group, turn off collect, close
        // (group should remain open)
        $this->debug->group('new group');
        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->groupEnd();
        $logAfter = $this->debug->getData('log');
        $this->assertSame($logBefore, $logAfter, 'GroupEnd() logged although collect=false');

        // turn collect back on and close the group
        $this->debug->setCfg('collect', true);
        $this->debug->groupEnd(); // close the open group
        $this->assertCount(2, $this->debug->getData('log'));

        // nothing to close!
        $this->debug->groupEnd(); // close the open group
        $this->assertCount(2, $this->debug->getData('log'));

        $entry = array(
            'groupEnd',
            array(),
            array(),
        );
        $this->testMethod(
            'groupEnd',
            array(),
            array(
                'entry' => $entry,
                'custom' => function () {
                    // $this->assertSame(array(1,1), $this->debug->getData('groupDepth'));
                },
                'chromeLogger' => array(
                    array(),
                    null,
                    'groupEnd',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 27|[{"Type":"GROUP_END"},null]|',
                'html' => '</ul>' . "\n" . '</li>',
                'script' => 'console.groupEnd();',
                'text' => '',
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroupEndWithVal()
    {
        $this->debug->group('my group');
        $this->testMethod(
            'groupEnd',
            array('foo'),
            array(
                'chromeLogger' => array(
                    array(),
                    null,
                    'groupEnd',
                ),
                'firephp' => 'X-Wf-1-1-1-151: 27|[{"Type":"GROUP_END"},null]|',
                'html' => '</ul>' . "\n" . '</li>',
                'script' => 'console.groupEnd();',
                'text' => '',
                'wamp' => array(
                    'groupEnd',
                    array(),
                    array(),
                ),
            )
        );

        $entry = array(
            'groupEndValue',
            array('return', 'foo'),
            array(),
        );
        $this->testMethod(
            array(
                'dataPath' => 'log/1'
            ),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('return', 'foo'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-154: 39|[{"Label":"return","Type":"LOG"},"foo"]|',
                'html' => '<li class="m_groupEndValue"><span class="no-quotes t_string">return</span> = <span class="t_string">foo</span></li>',
                'script' => 'console.log("return","foo");',
                'text' => 'return = "foo"',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );
    }

    public function testGroupsLeftOpen()
    {
        /*
        Internal Debug::EVENT_OUTPUT subscribers
             1: Internal::onOutput:  closes open groups / remoes hideIfEmpty groups
                onOutputCleanup
                    closeOpenGroups
                    removeHideIfEmptyGroups
                    uncollapseErrors
                onOutputLogRuntime
             0: Routes & plugins
            -1: Internal::onOutputHeaders

        This also tests that the values returned by getData have been dereferenced
        */

        $this->debug->groupSummary(1);
            $this->debug->log('in summary');
            $this->debug->group('inner group opened but not closed');
                $this->debug->log('in inner');
        $onOutputVals = array();

        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, function (\bdk\PubSub\Event $event) use (&$onOutputVals) {
            /*
                Nothing has been closed yet
            */
            $debug = $event->getSubject();
            $onOutputVals['groupPriorityStackA'] = $debug->getData('groupPriorityStack');
            $onOutputVals['groupStacksA'] = $debug->getData('groupStacks');
        }, 2);
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, function (\bdk\PubSub\Event $event) use (&$onOutputVals) {
            /*
                At this point, log has been output.. all groups have been closed
            */
            $debug = $event->getSubject();
            $onOutputVals['groupPriorityStackB'] = $debug->getData('groupPriorityStack');
            $onOutputVals['groupStacksB'] = $debug->getData('groupStacks');
        }, -1);
        $output = $this->debug->output();

        $this->assertSame(array(1), $onOutputVals['groupPriorityStackA']);
        $this->assertSame(array(
            'main' => array(),
            1 => array(
                array(
                    'channel' => $this->debug,
                    'collect' => true,
                ),
            ),
        ), $onOutputVals['groupStacksA']);
        $this->assertSame(array(), $onOutputVals['groupPriorityStackB']);
        $this->assertSame(array(
            'main' => array(),
        ), $onOutputVals['groupStacksB']);
        $outputExpect = <<<'EOD'
<div class="debug" data-channel-name-root="general" data-channels="{&quot;general&quot;:{&quot;options&quot;:{&quot;icon&quot;:null,&quot;show&quot;:true},&quot;channels&quot;:{}}}" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null}">
    <header class="debug-menu-bar">PHPDebugConsole<nav role="tablist"></nav></header>
    <div class="debug-tabs">
        <div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
            <div class="tab-body">
                <ul class="debug-log-summary group-body">
                    <li class="m_log"><span class="no-quotes t_string">in summary</span></li>
                    <li class="m_group">
                        <div class="expanded group-header"><span class="group-label group-label-bold">inner group opened but not closed</span></div>
                        <ul class="group-body">
                            <li class="m_log"><span class="no-quotes t_string">in inner</span></li>
                        </ul>
                    </li>
                    <li class="m_info"><span class="no-quotes t_string">Built In %f %ss</span></li>
                    <li class="m_info"><span class="no-quotes t_string">Peak Memory Usage <span title="Includes debug overhead">?&#x20dd;</span>: %f MB / %d %cB</span></li>
                </ul>
                <ul class="debug-log group-body"></ul>
            </div>
        </div>
    </div>
</div>
EOD;
        $outputExpect = preg_replace('#^\s+#m', '', $outputExpect);
        $this->assertStringMatchesFormat($outputExpect, $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroupSummary()
    {
        $this->debug->groupSummary();
        $this->debug->group('group inside summary');
        $this->debug->log('I\'m in the summary!');
        $this->debug->groupEnd();
        $this->debug->log('I\'m still in the summary!');
        $this->debug->groupEnd();
        $this->debug->log('I\'m not in the summary');
        $this->debug->setCfg('collect', false);
        $this->debug->groupSummary();   // even though collection is off, we're still start a summary group
        $this->debug->log('I\'m not logged');
        $this->debug->setCfg('collect', true);
        $this->debug->log('I\'m staying in the summary!');
        $this->debug->setCfg('collect', false);
        $this->debug->groupEnd();   // even though collection is off, we're still closing summary
        $this->debug->setCfg('collect', true);
        $this->debug->log('the end');

        $logSummary = $this->debug->getData('logSummary/0');
        $this->assertSame(array(
            array('group',array('group inside summary'), array()),
            array('log',array('I\'m in the summary!'), array()),
            array('groupEnd',array(), array()),
            array('log',array('I\'m still in the summary!'), array()),
            array('log',array('I\'m staying in the summary!'), array()),
        ), array_map(function ($logEntry) {
            return $this->logEntryToArray($logEntry);
        }, $logSummary));
        $log = $this->debug->getData('log');
        $this->assertSame(array(
            array('log',array('I\'m not in the summary'), array()),
            array('log',array('the end'), array()),
        ), array_map(function ($logEntry) {
            return $this->logEntryToArray($logEntry);
        }, $log));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroupUncollapse()
    {
        $this->debug->groupCollapsed('level1 (test)');  // 0
        $this->debug->groupCollapsed('level2');         // 1
        $this->debug->log('left collapsed');            // 2
        $this->debug->groupEnd('level2');               // 3 & 4
        $this->debug->groupCollapsed('level2 (test)');  // 5
        $this->debug->groupUncollapse();
        $log = $this->debug->getData('log');
        $this->assertSame('group', $log[0]['method']); // groupCollapsed converted to group
        $this->assertSame('groupCollapsed', $log[1]['method']);
        $this->assertSame('group', $log[5]['method']); // groupCollapsed converted to group
        $this->assertCount(6, $log);    // assert that entry not added
    }

    /**
     * Test
     *
     * @return void
     */
    public function testInfo()
    {
        $resource = fopen(__FILE__, 'r');
        $this->testMethod(
            'info',
            array('a string', array(), new stdClass(), $resource),
            array(
                'entry' => function ($logEntry) {
                    $this->assertSame('info', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry['args'][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry['args'][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'a string',
                        array(),
                        array(
                            '___class_name' => 'stdClass',
                        ),
                        'Resource id #%d: stream',
                    ),
                    null,
                    'info',
                )),
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Label":"a string","Type":"INFO"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_info"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.info("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'text' => 'ℹ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                // 'wamp' @todo
            )
        );
        fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'info',
            array('info message'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testLog()
    {
        $resource = fopen(__FILE__, 'r');
        $this->testMethod(
            'log',
            array('a string', array(), new stdClass(), $resource),
            array(
                'entry' => function ($logEntry) {
                    $this->assertSame('log', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry['args'][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry['args'][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'a string',
                        array(),
                        array(
                            '___class_name' => 'stdClass',
                        ),
                        'Resource id #%d: stream',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Label":"a string","Type":"LOG"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.log("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'streamAnsi' => "a string\e[38;5;245m, \e[0m\e[38;5;45marray\e[38;5;245m(\e[0m\e[38;5;245m)\e[0m\e[38;5;245m, \e[0m\e[1mstdClass\e[22m
                    Properties: none!
                    Methods: none!\e[38;5;245m, \e[0mResource id #%d: stream",
                'text' => 'a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
            )
        );
        fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'log',
            array('log message'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /*
        table() method tested in MethodTableTest
    */

    /**
     * Test
     *
     * @return void
     */
    public function testTime()
    {
        $this->debug->time();
        $this->debug->time('some label');

        $timers = $this->getPrivateProp($this->debug->stopWatch, 'timers');
        $this->assertInternalType('float', $timers['stack'][0]);
        $this->assertInternalType('float', $timers['labels']['some label'][1]);

        $this->assertEmpty($this->debug->getData('log'));
        $this->assertEmpty($this->debug->getRoute('wamp')->wamp->messages);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTimeEnd()
    {
        $this->debug->time();
        $this->debug->time('my label');

        $this->testMethod(
            'timeEnd',
            array(),
            array(
                'custom' => function () {
                    $timers = $this->getPrivateProp($this->debug->stopWatch, 'timers');
                    $this->assertCount(0, $timers['stack']);
                },
                'entry' => json_encode(array(
                    'time',
                    array(
                        'time: %f μs',
                    ),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'time: %f μs',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"time: %f μs"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">time: %f μs</span></li>',
                'script' => 'console.log("time: %f μs");',
                'text' => '⏱ time: %f μs',
                // 'wamp' => @todo
            )
        );
        $this->testMethod(
            'timeEnd',
            array(
                'my label',
                $this->debug->meta('silent'),
            ),
            array(
                'return' => '%f',
                'notLogged' => true,    // not logged because 2nd param = true
                'wamp' => false,
            )
        );
        $this->testMethod(
            'timeEnd',
            array(
                'my label',
            ),
            array(
                'entry' => json_encode(array(
                    'time',
                    array(
                        'my label: %f %ss',
                    ),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: %f %ss',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"my label: %f %ss"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">my label: %f %ss</span></li>',
                'script' => 'console.log("my label: %f %ss");',
                'text' => '⏱ my label: %f %ss',
                // 'wamp' => @todo
            )
        );
        $this->testMethod(
            'timeEnd',
            array(
                'my label',
                $this->debug->meta('template', 'blah%labelblah%timeblah'),
            ),
            array(
                /*
                'entry' => function ($logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'time',
                        array("blahmy labelblah%f msblah"),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry), 'chromeLogger not same');
                },
                */
                'entry' => json_encode(array(
                    'time',
                    array('blahmy labelblah%f %ssblah'),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'blahmy labelblah%f %ssblah',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-22: %d|[{"Type":"LOG"},"blahmy labelblah%f %ssblah"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">blahmy labelblah%f %ssblah</span></li>',
                'script' => 'console.log("blahmy labelblah%f %ssblah");',
                'text' => '⏱ blahmy labelblah%f %ssblah',
                // 'wamp' => @todo
            )
        );

        $timers = $this->getPrivateProp($this->debug->stopWatch, 'timers');
        $this->assertInternalType('float', $timers['labels']['my label'][0]);
        $this->assertNull($timers['labels']['my label'][1]);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'timeEnd',
            array('my label'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTimeGet()
    {

        $this->debug->time();
        $this->debug->time('my label');

        $this->testMethod(
            'timeGet',
            array(),
            array(
                'custom' => function () {
                    // test stack is still 1
                    $timers = $this->getPrivateProp($this->debug->stopWatch, 'timers');
                    $this->assertCount(1, $timers['stack']);
                },
                /*
                'entry' => function ($logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'time',
                        array('time: %f μs'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => json_encode(array(
                    'time',
                    array('time: %f μs'),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'time: %f μs',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"time: %f μs"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">time: %f μs</span></li>',
                'script' => 'console.log("time: %f μs");',
                'text' => '⏱ time: %f μs',
                // 'wamp' => @todo
            )
        );

        $this->testMethod(
            'timeGet',
            array('my label'),
            array(
                'entry' => json_encode(array(
                    'time',
                    array(
                        'my label: %f %ss',
                    ),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: %f %ss',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"my label: %f %ss"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">my label: %f %ss</span></li>',
                'script' => 'console.log("my label: %f %ss");',
                'text' => '⏱ my label: %f %ss',
                // 'wamp' => @todo
            )
        );

        $this->testMethod(
            'timeGet',
            array(
                'my label',
                $this->debug->meta('silent'),
            ),
            array(
                'notLogged' => true,  // not logged because 2nd param = true
                'return' => '%f',
                'wamp' => false,
            )
        );

        $timers = $this->getPrivateProp($this->debug->stopWatch, 'timers');
        $this->assertSame(0, $timers['labels']['my label'][0]);
        // test not paused
        $this->assertNotNull($timers['labels']['my label'][1]);

        $this->testMethod(
            'timeGet',
            array(
                'my label',
                $this->debug->meta('template', 'blah%labelblah%timeblah'),
            ),
            array(
                'entry' => json_encode(array(
                    'time',
                    array('blahmy labelblah%f %ssblah'),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'blahmy labelblah%f %ssblah',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-22: %d|[{"Type":"LOG"},"blahmy labelblah%f %ssblah"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">blahmy labelblah%f %ssblah</span></li>',
                'script' => 'console.log("blahmy labelblah%f %ssblah");',
                'text' => '⏱ blahmy labelblah%f %ssblah',
                // 'wamp' => @todo
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'timeGet',
            array('my label'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTimeLog()
    {
        $this->debug->time();
        $this->debug->time('my label');

        $this->testMethod(
            'timeLog',
            array(),
            array(
                /*
                'entry' => function ($logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('time: ', '%f μs'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => json_encode(array(
                    'timeLog',
                    array('time: ', '%f μs'),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'time: ',
                        '%f μs',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-166: %d|[{"Label":"time: ","Type":"LOG"},"%f μs"]|',
                'html' => '<li class="m_timeLog"><span class="no-quotes t_string">time: </span><span class="t_string">%f μs</span></li>',
                'script' => 'console.log("time: ","%f μs");',
                'text' => '⏱ time: "%f μs"',
                // 'wamp' => @todo
            )
        );

        $this->testMethod(
            'timeLog',
            array('my label', array('foo' => 'bar')),
            array(
                /*
                'entry' => function ($logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('my label: ', '%f %ss', array('foo'=>'bar')),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => json_encode(array(
                    'timeLog',
                    array('my label: ', '%f %ss', array('foo' => 'bar')),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: ',
                        '%f %ss',
                        array('foo' => 'bar'),
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-169: %d|[{"Label":"my label: ","Type":"LOG"},["%f %ss",{"foo":"bar"}]]|',
                'html' => '<li class="m_timeLog"><span class="no-quotes t_string">my label: </span><span class="t_string">%f %ss</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                    <ul class="array-inner list-unstyled">
                        <li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>
                    </ul><span class="t_punct">)</span></span></li>',
                'script' => 'console.log("my label: ","%f %ss",{"foo":"bar"});',
                'text' => '⏱ my label: "%f %ss", array(
                    [foo] => "bar"
                    )',
                // 'wamp' => @todo
            )
        );

        $this->testMethod(
            'timeLog',
            array('bogus'),
            array(
                /*
                'entry' => function ($logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('Timer \'bogus\' does not exist'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => json_encode(array(
                    'timeLog',
                    array('Timer \'bogus\' does not exist'),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array('Timer \'bogus\' does not exist'),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-172: 47|[{"Type":"LOG"},"Timer \'bogus\' does not exist"]|',
                'html' => '<li class="m_timeLog"><span class="no-quotes t_string">Timer \'bogus\' does not exist</span></li>',
                'script' => 'console.log("Timer \'bogus\' does not exist");',
                'text' => '⏱ Timer \'bogus\' does not exist',
                // 'wamp' => @todo
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTrace()
    {
        $this->debug->trace();
        $values = array(
            'file0' => __FILE__,
            'line0' => __LINE__ - 3,
            'function1' => __CLASS__ . '->' . __FUNCTION__,
        );

        $this->testMethod(
            array(
                'dataPath' => 'log/0'
            ),
            array(),
            array(
                'custom' => function ($logEntry) use ($values) {
                    $trace = $logEntry['args'][0];
                    $this->assertSame($values['file0'], $trace[0]['file']);
                    $this->assertSame($values['line0'], $trace[0]['line']);
                    $this->assertInternalType('integer', $trace[0]['line']);
                    $this->assertNotTrue(isset($trace[0]['function']));
                    $this->assertSame($values['function1'], $trace[1]['function']);
                },
                'chromeLogger' => function ($logEntry) {
                    $trace = \array_map(function ($row) {
                        $keys = array('file','line','function');
                        $row = \array_intersect_key($row, \array_flip($keys));
                        uksort($row, function ($key1, $key2) use ($keys) {
                            return array_search($key1, $keys) > array_search($key2, $keys)
                                ? 1
                                : -1;
                        });
                        return $row;
                    }, $this->debug->getData('log/0/args/0'));
                    $chromeTable = $logEntry[0][0];
                    $this->assertSame($trace, $chromeTable);
                    $this->assertSame(null, $logEntry[1]);
                    $this->assertSame('table', $logEntry[2]);
                },
                'firephp' => function ($logEntry) {
                    $trace = $this->debug->getData('log/0/args/0');
                    preg_match('#\|(.+)\|#', $logEntry, $matches);
                    $logEntry = json_decode($matches[1], true);
                    list($logEntryMeta, $logEntryTable) = $logEntry;
                    $this->assertSame(array(
                        'Label' => 'trace',
                        'Type' => 'TABLE',
                    ), $logEntryMeta);
                    $this->assertSame(array(
                        '',
                        'file',
                        'line',
                        'function',
                    ), $logEntryTable[0]);
                    $count = count($logEntryTable);
                    for ($i = 1; $i < $count; $i++) {
                        $tracei = $i - 1;
                        $valuesExpect = array(
                            $tracei,
                            $trace[$tracei]['file'],
                            $trace[$tracei]['line'],
                            isset($trace[$tracei]['function']) ? $trace[$tracei]['function'] : null,
                        );
                        $this->assertSame($valuesExpect, $logEntryTable[$i]);
                    }
                },
                'html' => function ($logEntry) {
                    // $this->assertSame('', $logEntry);
                    $trace = $this->debug->getData('log/0/args/0');
                    $this->assertContains('<caption>trace</caption>' . "\n"
                        . '<thead>' . "\n"
                        . '<tr><th>&nbsp;</th><th>file</th><th scope="col">line</th><th scope="col">function</th></tr>' . "\n"
                        . '</thead>', $logEntry);
                    $matches = array();
                    preg_match_all('#<tr>'
                        . '<th.*?>(.*?)</th>'
                        . '<td.*?>(.*?)</td>'
                        . '<td.*?>(.*?)</td>'
                        . '<td.*?>(.*?)</td>'
                        . '</tr>#is', $logEntry, $matches, PREG_SET_ORDER);
                    $count = count($matches);
                    for ($i = 1; $i < $count; $i++) {
                        $keys = array('file','line','function');
                        $row = array_intersect_key($trace[$i], array_flip($keys));
                        uksort($row, function ($key1, $key2) use ($keys) {
                            return array_search($key1, $keys) > array_search($key2, $keys)
                                ? 1
                                : -1;
                        });
                        $trace[$i] = $row;
                        $valuesExpect = array_merge(array((string) $i), array_values($trace[$i]));
                        $valuesExpect[1] = is_null($valuesExpect[1]) ? 'null' : $valuesExpect[1];
                        $valuesExpect[2] = is_null($valuesExpect[2]) ? 'null' : (string) $valuesExpect[2];
                        $valuesExpect[3] = $this->debug->getDump('html')->markupIdentifier($valuesExpect[3], 'span', array(), true);
                        $valuesActual = $matches[$i];
                        array_shift($valuesActual);
                        $this->assertSame($valuesExpect, $valuesActual);
                    }
                },
                'script' => function ($logEntry) {
                    $trace = array_map(function ($row) {
                        $keys = array('file','line','function');
                        $row = array_intersect_key($row, array_flip($keys));
                        uksort($row, function ($key1, $key2) use ($keys) {
                            return array_search($key1, $keys) > array_search($key2, $keys)
                                ? 1
                                : -1;
                        });
                        return $row;
                    }, $this->debug->getData('log/0/args/0'));
                    preg_match('#console.table\((.+)\);#', $logEntry, $matches);
                    $this->assertSame(json_encode($trace, JSON_UNESCAPED_SLASHES), $matches[1]);
                },
                'text' => function ($logEntry) use ($values) {
                    $trace = array_map(function ($row) {
                        $keys = array('file','line','function');
                        $row = array_intersect_key($row, array_flip($keys));
                        uksort($row, function ($key1, $key2) use ($keys) {
                            return array_search($key1, $keys) > array_search($key2, $keys)
                                ? 1
                                : -1;
                        });
                        return $row;
                    }, $this->debug->getData('log/0/args/0'));
                    $expect = 'trace = ' . $this->debug->getDump('text')->dump($trace);
                    $this->assertNotEmpty($trace);
                    $this->assertSame($expect, trim($logEntry));
                },
                // 'wamp' => @todo
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'log',
            array('log message'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testWarn()
    {
        $resource = fopen(__FILE__, 'r');
        $this->testMethod(
            'warn',
            array('a string', array(), new stdClass(), $resource),
            array(
                'entry' => function ($logEntry) {
                    $this->assertSame('warn', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry['args'][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry['args'][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);

                    $this->assertArrayHasKey('file', $logEntry['meta']);
                    $this->assertArrayHasKey('line', $logEntry['meta']);
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'a string',
                        array(),
                        array(
                            '___class_name' => 'stdClass',
                        ),
                        'Resource id #%d: stream',
                    ),
                    __DIR__ . '/DebugTestFramework.php: %d',
                    'warn',
                )),
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"File":"' . __DIR__ . '/' . 'DebugTestFramework.php","Label":"a string","Line":%d,"Type":"WARN"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_warn" data-detect-files="true" data-file="' . __DIR__ . '/DebugTestFramework.php" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.warn("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream","' . __DIR__ . '/DebugTestFramework.php: line %d");',
                'text' => '⚠ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                // 'wamp' => @todo
            )
        );
        fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'warn',
            array('warn message'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }
}
