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
    public function testOverrideDefault()
    {
        $closure = function ($event) {
            if ($event['method'] == 'trace') {
                $route = get_class($event['route']);
                if ($route == 'bdk\Debug\Route\ChromeLogger') {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($route == 'bdk\Debug\Route\Firephp') {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($route == 'bdk\Debug\Route\Html') {
                    $event['return'] = '<li class="m_trace">this was a trace</li>';
                } elseif ($route == 'bdk\Debug\Route\Script') {
                    $event['return'] = 'console.log("this was a trace");';
                } elseif ($route == 'bdk\Debug\Route\Text') {
                    $event['return'] = 'this was a trace';
                }
            }
        };
        $this->debug->eventManager->subscribe('debug.outputLogEntry', $closure);
        $this->testMethod(
            'trace',
            array(),
            array(
                'chromeLogger' => array(
                    array('this was a trace'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 35|[{"Type":"LOG"},"this was a trace"]|',
                'html' => '<li class="m_trace">this was a trace</li>',
                'script' => 'console.log("this was a trace");',
                'text' => 'this was a trace',
            )
        );
    }

    /**
     * Test custom method
     */
    public function testCustom()
    {
        $closure = function (LogEntry $logEntry) {
            if ($logEntry['method'] == 'myCustom' && $logEntry['route'] instanceof \bdk\Debug\Route\Html) {
                $lis = array();
                foreach ($logEntry['args'] as $arg) {
                    $lis[] = '<li>'.htmlspecialchars($arg).'</li>';
                }
                $logEntry['return'] = '<li class="m_myCustom"><ul>'.implode('', $lis).'</ul></li>';
            }
        };
        $this->debug->eventManager->subscribe('debug.outputLogEntry', $closure);
        $this->testMethod(
            'myCustom',
            array('How\'s it goin?'),
            array(
                'entry' => array(
                    'myCustom',
                    array('How\'s it goin?'),
                    array(
                        'isCustomMethod' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('How\'s it goin?'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"How\'s it goin?"]|',
                'html' => '<li class="m_myCustom"><ul><li>How\'s it goin?</li></ul></li>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
            )
        );

        /*
            Now test it statically
        */
        Debug::_myCustom('called statically');
        $this->testMethod(
            null,
            array('called statically'),
            array(
                'entry' => array(
                    'myCustom',
                    array('called statically'),
                    array(
                        'isCustomMethod' => true,
                        'statically' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('called statically'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"called statically"]|',
                'html' => '<li class="m_myCustom"><ul><li>called statically</li></ul></li>',
                'script' => 'console.log("called statically");',
                'text' => 'called statically',
            )
        );
    }

    public function testCustomDefault()
    {
        $this->testMethod(
            'myCustom',
            array('How\'s it goin?'),
            array(
                'chromeLogger' => array(
                    array('How\'s it goin?'),
                    null,
                    '',
                ),
                'entry' => array(
                    'myCustom',
                    array('How\'s it goin?'),
                    array(
                        'isCustomMethod' => true,
                    ),
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"How\'s it goin?"]|',
                'html' => '<li class="m_myCustom"><span class="no-quotes t_string">How\'s it goin?</span></li>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
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
        $this->testMethod(
            'alert',
            array($message),
            array(
                'entry' => array(
                    'alert',
                    array($message),
                    array(
                        'dismissible' => false,
                        'level' => 'error',
                    ),
                ),
                'chromeLogger' => array(
                    array(
                        '%c'.$message,
                        'padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #ffbaba;border: 1px solid #d8000c;color: #d8000c;',
                    ),
                    null,
                    '',
                ),
                'html' => '<div class="alert-error m_alert" role="alert">'.$messageEscaped.'</div>',
                'text' => '》[Alert ⦻ error] '.$message.'《',
                'script' => str_replace('%c', '%%c', 'console.log('.json_encode('%c'.$message, JSON_UNESCAPED_SLASHES).',"padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #ffbaba;border: 1px solid #d8000c;color: #d8000c;");'),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"ERROR"},'.json_encode($message, JSON_UNESCAPED_SLASHES).']|',
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
        $this->testMethod(
            'assert',
            array(false, 'this is false'),
            array(
                'entry' => array(
                    'assert',
                    array('this is false'),
                    array(),
                ),
                'chromeLogger' => array(
                    array(false, 'this is false'),
                    null,
                    'assert',
                ),
                'html' => '<li class="m_assert"><span class="no-quotes t_string">this is false</span></li>',
                'text' => '≠ this is false',
                'script' => 'console.assert(false,"this is false");',
                'firephp' => 'X-Wf-1-1-1-2: 32|[{"Type":"LOG"},"this is false"]|',
            )
        );

        // no msg arguments
        $this->testMethod(
            'assert',
            array(false),
            array(
                'entry' => array(
                    'assert',
                    array(
                        'Assertion failed:',
                        $this->file.' (line '.$this->line.')',
                    ),
                    array(
                        'detectFiles' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array(
                        false,
                        'Assertion failed:',
                        $this->file.' (line '.$this->line.')',
                    ),
                    null,
                    'assert',
                ),
                'html' => '<li class="m_assert" data-detect-files="true"><span class="no-quotes t_string">Assertion failed: </span><span class="t_string">'.$this->file.' (line '.$this->line.')</span></li>',
                'text' => '≠ Assertion failed: "'.$this->file.' (line '.$this->line.')"',
                'script' => 'console.assert(false,"Assertion failed:",'.json_encode($this->file.' (line '.$this->line.')', JSON_UNESCAPED_SLASHES).');',
                'firephp' => 'X-Wf-1-1-1-2: %d|[{"Label":"Assertion failed:","Type":"LOG"},'.json_encode($this->file.' (line '.$this->line.')', JSON_UNESCAPED_SLASHES).']|',
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
                'entry' => array(
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
                ),
                'chromeLogger' => array(
                    array('Cleared log (sans errors)'),
                    $this->file.': '.$this->line,
                    '',
                ),
                'html' => '<li class="m_clear" data-file="'.$this->file.'" data-line="'.$this->line.'"><span class="no-quotes t_string">Cleared log (sans errors)</span></li>',
                'text' => '⌦ Cleared log (sans errors)',
                'script' => 'console.log("Cleared log (sans errors)");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":'.json_encode($this->file, JSON_UNESCAPED_SLASHES).',"Line":'.$this->line.',"Type":"LOG"},"Cleared log (sans errors)"]|',
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
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALERTS),
            array(
                'custom' => function () {
                    $this->assertCount(0, $this->debug->getData('alerts'));
                    $this->assertCount(3, $this->debug->getData('logSummary/0'));
                    $this->assertCount(3, $this->debug->getData('logSummary/1'));
                    $this->assertCount(6, $this->debug->getData('log'));
                },
                'entry' => array(
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
                ),
                'chromeLogger' => array(
                    array('Cleared alerts'),
                    $this->file.': '.$this->line,
                    '',
                ),
                'html' => '<li class="m_clear" data-file="'.$this->file.'" data-line="'.$this->line.'"><span class="no-quotes t_string">Cleared alerts</span></li>',
                'text' => '⌦ Cleared alerts',
                'script' => 'console.log("Cleared alerts");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":'.json_encode($this->file, JSON_UNESCAPED_SLASHES).',"Line":'.$this->line.',"Type":"LOG"},"Cleared alerts"]|',
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
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY),
            array(
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
                'entry' => array(
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
                ),
                'chromeLogger' => array(
                    array('Cleared summary (sans errors)'),
                    $this->file.': '.$this->line,
                    '',
                ),
                'html' => '<li class="m_clear" data-file="'.$this->file.'" data-line="'.$this->line.'"><span class="no-quotes t_string">Cleared summary (sans errors)</span></li>',
                'text' => '⌦ Cleared summary (sans errors)',
                'script' => 'console.log("Cleared summary (sans errors)");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":'.json_encode($this->file, JSON_UNESCAPED_SLASHES).',"Line":'.$this->line.',"Type":"LOG"},"Cleared summary (sans errors)"]|',
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
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_LOG_ERRORS),
            array(
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
                'entry' => array(
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
                ),
                'chromeLogger' => array(
                    array('Cleared errors'),
                    $this->file.': '.$this->line,
                    '',
                ),
                'html' => '<li class="m_clear" data-file="'.$this->file.'" data-line="'.$this->line.'"><span class="no-quotes t_string">Cleared errors</span></li>',
                'text' => '⌦ Cleared errors',
                'script' => 'console.log("Cleared errors");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":'.json_encode($this->file, JSON_UNESCAPED_SLASHES).',"Line":'.$this->line.',"Type":"LOG"},"Cleared errors"]|',
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
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALL),
            array(
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
                'entry' => array(
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
                ),
                'chromeLogger' => array(
                    array('Cleared everything'),
                    $this->file.': '.$this->line,
                    '',
                ),
                'html' => '<li class="m_clear" data-file="'.$this->file.'" data-line="'.$this->line.'"><span class="no-quotes t_string">Cleared everything</span></li>',
                'text' => '⌦ Cleared everything',
                'script' => 'console.log("Cleared everything");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":'.json_encode($this->file, JSON_UNESCAPED_SLASHES).',"Line":'.$this->line.',"Type":"LOG"},"Cleared everything"]|',
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
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY | Debug::CLEAR_SUMMARY_ERRORS),
            array(
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
                'entry' => array(
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
                ),
                'chromeLogger' => array(
                    array('Cleared summary (incl errors)'),
                    $this->file.': '.$this->line,
                    '',
                ),
                'html' => '<li class="m_clear" data-file="'.$this->file.'" data-line="'.$this->line.'"><span class="no-quotes t_string">Cleared summary (incl errors)</span></li>',
                'text' => '⌦ Cleared summary (incl errors)',
                'script' => 'console.log("Cleared summary (incl errors)");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":'.json_encode($this->file, JSON_UNESCAPED_SLASHES).',"Line":'.$this->line.',"Type":"LOG"},"Cleared summary (incl errors)"]|',
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
                }
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
        $this->debug->count('count test');          // 1 (0)
        for ($i=0; $i<3; $i++) {
            if ($i > 0) {
                $lines[0] = __LINE__ + 1;
                $this->debug->count();              // 1,2 (3,6)
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
            array('count', array('count',1), array('file'=>__FILE__,'line'=>$lines[1],'statically'=>true)),
            array('count', array('count',1), array('file'=>__FILE__,'line'=>$lines[0])),
            array('count', array('count test', 3), array()),
            array('count', array('count',2), array('file'=>__FILE__,'line'=>$lines[1],'statically'=>true)),
            array('count', array('count',2), array('file'=>__FILE__,'line'=>$lines[0])),
            array('count', array('count test', 4), array()),
            array('count', array('count',3), array('file'=>__FILE__,'line'=>$lines[1],'statically'=>true)),
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
                'html' => '<li class="m_count"><span class="no-quotes t_string">count_inc test</span> = <span class="t_int">3</span></li>',
                'text' => '✚ count_inc test = 3',
                'script' => 'console.log("count_inc test",3);',
                'firephp' => 'X-Wf-1-1-1-3: 43|[{"Label":"count_inc test","Type":"LOG"},3]|',
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
                    __FILE__.': '.$lines[1],
                    '',
                ),
                'html' => '<li class="m_count" data-file="'.__FILE__.'" data-line="'.$lines[1].'"><span class="no-quotes t_string">count</span> = <span class="t_int">1</span></li>',
                'text' => '✚ count = 1',
                'script' => 'console.log("count",1);',
                'firephp' => 'X-Wf-1-1-1-4: %d|[{"File":"'.__FILE__.'","Label":"count","Line":'.$lines[1].',"Type":"LOG"},1]|',
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
                'return' => 5,
                'custom' => function () {
                    $this->assertSame(5, $this->debug->getData('counts/count test'));
                },
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
            )
        );
        $this->testMethod(
            'countReset',
            array('noExisty', Debug::COUNT_NO_OUT),
            array(
                'notLogged' => true,
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
                    __DIR__.'/DebugTestFramework.php: %d',
                    'error',
                )),
                'html' => '<li class="m_error" data-detect-files="true" data-file="'.__DIR__.'/DebugTestFramework.php" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'text' => '⦻ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%i: stream',
                'script' => 'console.error("a string",[],{"___class_name":"stdClass"},"Resource id #%i: stream","%s: line %d");',
                'firephp' => 'X-Wf-1-1-1-3: %d|[{"File":"%s","Label":"a string","Line":%d,"Type":"ERROR"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
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
            false
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroup()
    {

        $test = new \bdk\DebugTest\Test();
        $testBase = new \bdk\DebugTest\TestBase();

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
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label group-label-bold">)</span></div>
                    <ul class="group-body">',
                'text' => '▸ a("b", "c")',
                'script' => 'console.group("a","b","c");',
                'firephp' => 'X-Wf-1-1-1-4: 61|[{"Collapsed":"false","Label":"a","Type":"GROUP_START"},null]|',
            )
        );

        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            // @todo chromeLogger & firephp
            'html' => '<li class="m_log"><span class="no-quotes t_string">before group</span></li>
                <li class="m_log"><span class="no-quotes t_string">after group</span></li>',
            'text' => 'before group
                after group',
            'script' => 'console.log("before group");
                console.log("after group");',
            // 'firephp' => '',
        ));

        /*
            Test default label
        */
        $this->methodWithGroup('foo', 10);
        $this->testMethod(
            array(),    // test last called method
            array(),
            array(
                'entry' => array(
                    'group',
                    array(
                        __CLASS__.'->methodWithGroup',
                        'foo',
                        10
                    ),
                    array(
                        'isFuncName' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array(
                        __CLASS__.'->methodWithGroup',
                        'foo',
                        10,
                    ),
                    null,
                    'group',
                ),
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname">'.__CLASS__.'</span><span class="t_operator">-&gt;</span><span class="t_identifier">methodWithGroup</span>(</span><span class="t_string">foo</span>, <span class="t_int">10</span><span class="group-label group-label-bold">)</span></div>
                    <ul class="group-body">',
                'text' => '▸ '.__CLASS__.'->methodWithGroup("foo", 10)',
                'script' => 'console.group("'.__CLASS__.'->methodWithGroup","foo",10);',
                'firephp' => 'X-Wf-1-1-1-6: %d|[{"Collapsed":"false","Label":"'.__CLASS__.'->methodWithGroup","Type":"GROUP_START"},null]|',
            )
        );

        $this->debug->setData('log', array());
        $testBase->testBasePublic();
        $this->testMethod(
            array('dataPath'=>'log/0'),
            array(),
            array(
                'entry' => array(
                    'group',
                    array(
                        'bdk\DebugTest\TestBase->testBasePublic'
                    ),
                    array(
                        'isFuncName' => true,
                        'statically' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase->testBasePublic'),
                    null,
                    'group',
                ),
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">-&gt;</span><span class="t_identifier">testBasePublic</span></span></div>
                    <ul class="group-body">',
                'text' => '▸ bdk\DebugTest\TestBase->testBasePublic',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase->testBasePublic");',
                'firephp' => 'X-Wf-1-1-1-7: 100|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase->testBasePublic","Type":"GROUP_START"},null]|',
            )
        );

        $this->debug->setData('log', array());
        $test->testBasePublic();
        $this->testMethod(
            array('dataPath'=>'log/0'),
            array(),
            array(
                'entry' => array(
                    'group',
                    array(
                        'bdk\DebugTest\Test->testBasePublic'
                    ),
                    array(
                        'isFuncName' => true,
                        'statically' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\Test->testBasePublic'),
                    null,
                    'group',
                ),
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>Test</span><span class="t_operator">-&gt;</span><span class="t_identifier">testBasePublic</span></span></div>
                    <ul class="group-body">',
                'text' => '▸ bdk\DebugTest\Test->testBasePublic',
                'script' => 'console.group("bdk\\\DebugTest\\\Test->testBasePublic");',
                'firephp' => 'X-Wf-1-1-1-8: 96|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\Test->testBasePublic","Type":"GROUP_START"},null]|',
            )
        );

        // yes, we call Test... but static method is defined in TestBase
        // .... PHP
        $this->debug->setData('log', array());
        \bdk\DebugTest\Test::testBaseStatic();
        $this->testMethod(
            array('dataPath'=>'log/0'),
            array(),
            array(
                'entry' => array(
                    'group',
                    array(
                        'bdk\DebugTest\TestBase::testBaseStatic'
                    ),
                    array(
                        'isFuncName' => true,
                        'statically' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></div>
                    <ul class="group-body">',
                'text' => '▸ bdk\DebugTest\TestBase::testBaseStatic',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase::testBaseStatic");',
                'firephp' => 'X-Wf-1-1-1-9: 100|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase::testBaseStatic","Type":"GROUP_START"},null]|',
            )
        );

        // even if called with an arrow
        $this->debug->setData('log', array());
        $test->testBaseStatic();
        $this->testMethod(
            array('dataPath'=>'log/0'),
            array(),
            array(
                'entry' => array(
                    'group',
                    array(
                        'bdk\DebugTest\TestBase::testBaseStatic'
                    ),
                    array(
                        'isFuncName' => true,
                        'statically' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'html' => '<li class="m_group">
                    <div class="expanded group-header"><span class="group-label group-label-bold"><span class="classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></div>
                    <ul class="group-body">',
                'text' => '▸ bdk\DebugTest\TestBase::testBaseStatic',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase::testBaseStatic");',
                'firephp' => 'X-Wf-1-1-1-10: 100|[{"Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase::testBaseStatic","Type":"GROUP_START"},null]|',
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'group',
            array('not logged'),
            false
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
        $this->testMethod(
            'groupCollapsed',
            array('a', 'b', 'c'),
            array(
                'entry' => array(
                    'groupCollapsed',
                    array('a','b','c'),
                    array(),
                ),
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
            'text' => '▸ a("b", "c")
                after nested group',
            'script' => 'console.groupCollapsed("a","b","c");
                console.log("after nested group");',
            // 'firephp' => '',
        ));

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'groupCollapsed',
            array('not logged'),
            false
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

        $this->testMethod(
            'groupEnd',
            array(),
            array(
                'entry' => array(
                    'groupEnd',
                    array(),
                    array(),
                ),
                'custom' => function () {
                    // $this->assertSame(array(1,1), $this->debug->getData('groupDepth'));
                },
                'chromeLogger' => array(
                    array(),
                    null,
                    'groupEnd',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 27|[{"Type":"GROUP_END"},null]|',
                'html' => '</ul>'."\n".'</li>',
                'script' => 'console.groupEnd();',
                'text' => '',
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
                'html' => '</ul>'."\n".'</li>',
                'script' => 'console.groupEnd();',
                'text' => '',
            )
        );
        $this->testMethod(
            array(
                'dataPath' => 'log/1'
            ),
            array(),
            array(
                'entry' => array(
                    'groupEndValue',
                    array('return', 'foo'),
                    array(),
                ),
                'chromeLogger' => array(
                    array('return', 'foo'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-154: 39|[{"Label":"return","Type":"LOG"},"foo"]|',
                'html' => '<li class="m_groupEndValue"><span class="no-quotes t_string">return</span> = <span class="t_string">foo</span></li>',
                'script' => 'console.log("return","foo");',
                'text' => 'return = "foo"',
            )
        );
    }

    public function testGroupsLeftOpen()
    {
        /*
        Internal debug.output subscribers
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

        $this->debug->eventManager->subscribe('debug.output', function (\bdk\PubSub\Event $event) use (&$onOutputVals) {
            /*
                Nothing has been closed yet
            */
            $debug = $event->getSubject();
            $onOutputVals['groupPriorityStackA'] = $debug->getData('groupPriorityStack');
            $onOutputVals['groupStacksA'] = $debug->getData('groupStacks');
        }, 2);
        $this->debug->eventManager->subscribe('debug.output', function (\bdk\PubSub\Event $event) use (&$onOutputVals) {
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
<div class="debug" data-channel-root="general" data-channels="{}" data-options="{&quot;drawer&quot;:true,&quot;sidebar&quot;:true,&quot;linkFilesTemplateDefault&quot;:null}">
    <header class="debug-menu-bar">PHPDebugConsole</header>
    <div class="debug-body">
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
                'html' => '<li class="m_info"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'text' => 'ℹ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                'script' => 'console.info("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Label":"a string","Type":"INFO"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
            )
        );
        fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'info',
            array('info message'),
            false
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
                'html' => '<li class="m_log"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'text' => 'a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                'script' => 'console.log("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Label":"a string","Type":"LOG"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'streamAnsi' => "a string\e[38;5;245m, \e[0m\e[38;5;45marray\e[38;5;245m(\e[0m\e[38;5;245m)\e[0m\e[38;5;245m, \e[0m\e[1mstdClass\e[22m
                    Properties: none!
                    Methods: none!\e[38;5;245m, \e[0mResource id #%d: stream",
            )
        );
        fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'log',
            array('log message'),
            false
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
        $this->assertInternalType('float', $this->debug->getData('timers/stack/0'));
        $this->assertInternalType('float', $this->debug->getData('timers/labels/some label/1'));

        $this->assertEmpty($this->debug->getData('log'));
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
                    $this->assertCount(0, $this->debug->getData('timers/stack'));
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
                    array("blahmy labelblah%f %ssblah"),
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
            )
        );

        $timers = $this->debug->getData('timers');
        $this->assertInternalType('float', $timers['labels']['my label'][0]);
        $this->assertNull($timers['labels']['my label'][1]);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'timeEnd',
            array('my label'),
            false
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
                    $this->assertCount(1, $this->debug->getData('timers/stack'));
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
            )
        );

        $timers = $this->debug->getData('timers');
        $this->assertSame(0, $timers['labels']['my label'][0]); // timer never paused via timeEnd, accumlated time = 0

        // test not paused
        $this->assertNotNull($timers['labels']['my label'][1]);

        $this->testMethod(
            'timeGet',
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
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry), 'entry as expected');
                },
                */
                'entry' => json_encode(array(
                    'time',
                    array("blahmy labelblah%f msblah"),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'blahmy labelblah%f msblah',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-22: %d|[{"Type":"LOG"},"blahmy labelblah%f msblah"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">blahmy labelblah%f msblah</span></li>',
                'script' => 'console.log("blahmy labelblah%f msblah");',
                'text' => '⏱ blahmy labelblah%f msblah',
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'timeGet',
            array('my label'),
            false
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
            )
        );

        $this->testMethod(
            'timeLog',
            array('my label', array('foo'=>'bar')),
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
                    array('my label: ', '%f %ss', array('foo'=>'bar')),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: ',
                        '%f %ss',
                        array('foo'=>'bar'),
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-169: %d|[{"Label":"my label: ","Type":"LOG"},["%f %ss",{"foo":"bar"}]]|',
                'html' => '<li class="m_timeLog"><span class="no-quotes t_string">my label: </span><span class="t_string">%f %ss</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                    <span class="array-inner">
                    <span class="key-value"><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></span>
                    </span><span class="t_punct">)</span></span></li>',
                'script' => 'console.log("my label: ","%f %ss",{"foo":"bar"});',
                'text' => '⏱ my label: "%f %ss", array(
                    [foo] => "bar"
                    )',
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
            'function1' => __CLASS__.'->'.__FUNCTION__,
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
                    $trace = $this->debug->getData('log/0/args/0');
                    $this->assertSame(array($trace), $logEntry[0]);
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
                    for ($i=1; $i<$count; $i++) {
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
                    $this->assertContains('<caption>trace</caption>'."\n"
                        .'<thead>'."\n"
                        .'<tr><th>&nbsp;</th><th>file</th><th scope="col">line</th><th scope="col">function</th></tr>'."\n"
                        .'</thead>', $logEntry);
                    preg_match_all('#<tr>'
                        .'<th.*?>(.*?)</th>'
                        .'<td.*?>(.*?)</td>'
                        .'<td.*?>(.*?)</td>'
                        .'<td.*?>(.*?)</td>'
                        .'</tr>#is', $logEntry, $matches, PREG_SET_ORDER);
                    $count = count($matches);
                    for ($i=1; $i<$count; $i++) {
                        $valuesExpect = array_merge(array((string) $i), array_values($trace[$i]));
                        $valuesExpect[1] = is_null($valuesExpect[1]) ? 'null' : $valuesExpect[1];
                        $valuesExpect[2] = is_null($valuesExpect[2]) ? 'null' : (string) $valuesExpect[2];
                        $valuesExpect[3] = htmlspecialchars($valuesExpect[3]);
                        $valuesActual = $matches[$i];
                        array_shift($valuesActual);
                        $this->assertSame($valuesExpect, $valuesActual);
                    }
                },
                'script' => function ($logEntry) {
                    $trace = $this->debug->getData('log/0/args/0');
                    preg_match('#console.table\((.+)\);#', $logEntry, $matches);
                    $this->assertSame(json_encode($trace, JSON_UNESCAPED_SLASHES), $matches[1]);
                },
                'text' => function ($logEntry) use ($values) {
                    $trace = $this->debug->getData('log/0/args/0');
                    $expect = 'trace = '.$this->debug->dumpText->dump($trace);
                    $this->assertNotEmpty($trace);
                    $this->assertSame($expect, trim($logEntry));
                },
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'log',
            array('log message'),
            false
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
                    __DIR__.'/DebugTestFramework.php: %d',
                    'warn',
                )),
                'html' => '<li class="m_warn" data-detect-files="true" data-file="'.__DIR__.'/DebugTestFramework.php" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'text' => '⚠ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                'script' => 'console.warn("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream","'.__DIR__.'/DebugTestFramework.php: line %d");',
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"File":"'.__DIR__.'/'.'DebugTestFramework.php","Label":"a string","Line":%d,"Type":"WARN"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
            )
        );
        fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'warn',
            array('warn message'),
            false
        );
    }
}
