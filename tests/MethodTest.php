<?php

use bdk\Debug;

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
                $plugin = get_class($event->getSubject());
                if ($plugin == 'bdk\Debug\Output\ChromeLogger') {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($plugin == 'bdk\Debug\Output\Firephp') {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($plugin == 'bdk\Debug\Output\Html') {
                    $event['return'] = '<div class="m_trace">this was a trace</div>';
                } elseif ($plugin == 'bdk\Debug\Output\Script') {
                    $event['return'] = 'console.log("this was a trace");';
                } elseif ($plugin == 'bdk\Debug\Output\Text') {
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
                'html' => '<div class="m_trace">this was a trace</div>',
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
        $closure = function ($event) {
            if ($event['method'] == 'myCustom' && $event->getSubject() instanceof \bdk\Debug\Output\Html) {
                $lis = array();
                foreach ($event['args'] as $arg) {
                    $lis[] = '<li>'.htmlspecialchars($arg).'</li>';
                }
                $event['return'] = '<div class="m_myCustom"><ul>'.implode('', $lis).'</ul></div>';
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
                    array('isCustomMethod' => true),
                ),
                'chromeLogger' => array(
                    array('How\'s it goin?'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"How\'s it goin?"]|',
                'html' => '<div class="m_myCustom"><ul><li>How\'s it goin?</li></ul></div>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
            )
        );

        /*
            Now test it statically
        */
        \bdk\Debug::_myCustom('called statically');
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
                'html' => '<div class="m_myCustom"><ul><li>called statically</li></ul></div>',
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
                    array('isCustomMethod' => true),
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"How\'s it goin?"]|',
                'html' => '<div class="m_myCustom"><span class="no-pseudo t_string">How\'s it goin?</span></div>',
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
        $message = 'Ballistic missle threat inbound to Hawaii.  Seek immediate shelter.  This is not a drill.';
        $this->testMethod(
            'alert',
            array($message),
            array(
                'entry' => array(
                    'alert',
                    array($message),
                    array(
                        'class' => 'danger',
                        'dismissible' => false,
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
                'html' => '<div class="alert alert-danger" role="alert">'.$message.'</div>',
                'text' => '[Alert ⦻ danger] '.$message,
                'script' => str_replace('%c', '%%c', 'console.log("%c'.$message.'","padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #ffbaba;border: 1px solid #d8000c;color: #d8000c;");'),
                'firephp' => 'X-Wf-1-1-1-1: 108|[{"Type":"LOG"},"'.$message.'"]|',
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
                'html' => '<div class="m_assert"><span class="no-pseudo t_string">this is false</span></div>',
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
                    array(),
                ),
                'chromeLogger' => array(
                    array(false, 'Assertion failed:', $this->file.' (line '.$this->line.')'),
                    null,
                    'assert',
                ),
                'html' => '<div class="m_assert"><span class="no-pseudo t_string">Assertion failed: </span><span class="t_string">'.$this->file.' (line '.$this->line.')</span></div>',
                'text' => '≠ Assertion failed: "'.$this->file.' (line '.$this->line.')"',
                'script' => 'console.assert(false,"Assertion failed:","'.trim(json_encode($this->file), '"').' (line '.$this->line.')");',
                'firephp' => 'X-Wf-1-1-1-2: %d|[{"Type":"LOG","Label":"Assertion failed:"},"'.trim(json_encode($this->file), '"').' (line '.$this->line.')"]|',
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
                'html' => '<div class="m_clear" title="'.$this->file.': line '.$this->line.'"><span class="no-pseudo t_string">Cleared log (sans errors)</span></div>',
                'text' => '⌦ Cleared log (sans errors)',
                'script' => 'console.log("Cleared log (sans errors)");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"Type":"LOG","File":'.json_encode($this->file).',"Line":'.$this->line.'},"Cleared log (sans errors)"]|',
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
                    $lastMethod = $this->debug->getData('log/__end__/0');
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
                'html' => '<div class="m_clear" title="'.$this->file.': line '.$this->line.'"><span class="no-pseudo t_string">Cleared alerts</span></div>',
                'text' => '⌦ Cleared alerts',
                'script' => 'console.log("Cleared alerts");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"Type":"LOG","File":'.json_encode($this->file).',"Line":'.$this->line.'},"Cleared alerts"]|',
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
                            array('channel' => 'general', 'collect' => true),
                            array('channel' => 'general', 'collect' => true),
                        ),
                        0 => array(),
                        1 => array(
                            array('channel' => 'general', 'collect' => true),
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
                'html' => '<div class="m_clear" title="'.$this->file.': line '.$this->line.'"><span class="no-pseudo t_string">Cleared summary (sans errors)</span></div>',
                'text' => '⌦ Cleared summary (sans errors)',
                'script' => 'console.log("Cleared summary (sans errors)");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"Type":"LOG","File":'.json_encode($this->file).',"Line":'.$this->line.'},"Cleared summary (sans errors)"]|',
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
                            array('channel' => 'general', 'collect' => true),
                            array('channel' => 'general', 'collect' => true),
                        ),
                        0 => array(
                            array('channel' => 'general', 'collect' => true),
                        ),
                        1 => array(
                            array('channel' => 'general', 'collect' => true),
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
                'html' => '<div class="m_clear" title="'.$this->file.': line '.$this->line.'"><span class="no-pseudo t_string">Cleared errors</span></div>',
                'text' => '⌦ Cleared errors',
                'script' => 'console.log("Cleared errors");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"Type":"LOG","File":'.json_encode($this->file).',"Line":'.$this->line.'},"Cleared errors"]|',
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
                            array('channel' => 'general', 'collect' => true),
                            array('channel' => 'general', 'collect' => true),
                        ),
                        0 => array(),
                        1 => array(
                            array('channel' => 'general', 'collect' => true),
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
                'html' => '<div class="m_clear" title="'.$this->file.': line '.$this->line.'"><span class="no-pseudo t_string">Cleared everything</span></div>',
                'text' => '⌦ Cleared everything',
                'script' => 'console.log("Cleared everything");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"Type":"LOG","File":'.json_encode($this->file).',"Line":'.$this->line.'},"Cleared everything"]|',
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
                            array('channel' => 'general', 'collect' => true),
                            array('channel' => 'general', 'collect' => true),
                        ),
                        0 => array(),
                        1 => array(
                            array('channel' => 'general', 'collect' => true),
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
                'html' => '<div class="m_clear" title="'.$this->file.': line '.$this->line.'"><span class="no-pseudo t_string">Cleared summary (incl errors)</span></div>',
                'text' => '⌦ Cleared summary (incl errors)',
                'script' => 'console.log("Cleared summary (incl errors)");',
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"Type":"LOG","File":'.json_encode($this->file).',"Line":'.$this->line.'},"Cleared summary (incl errors)"]|',
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
                    $this->assertSame('Cleared log (sans errors)', $logEntry[1][0]);
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
                    $this->assertSame('Cleared log (sans errors) and summary (sans errors)', $logEntry[1][0]);
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
                    $this->assertSame('Cleared alerts, log (sans errors), and summary (sans errors)', $logEntry[1][0]);
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
            $this->debug->count('count_inc test', \bdk\Debug::COUNT_NO_OUT);  //  1,2,3, but not logged
            $lines[1] = __LINE__ + 1;
            \bdk\Debug::_count();                   // 1,2,3 (2,5,8)
        }
        $this->debug->log(
            'count_inc test',
            $this->debug->count(
                'count_inc test',
                \bdk\Debug::COUNT_NO_INC | \bdk\Debug::COUNT_NO_OUT // only return
            )
        );
        $this->debug->count('count_inc test', \bdk\Debug::COUNT_NO_INC);  // (9) //  doesn't increment

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
        ), $this->debug->getData('log'));

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
                'html' => '<div class="m_count"><span class="no-pseudo t_string">count_inc test</span> = <span class="t_int">3</span></div>',
                'text' => '✚ count_inc test = 3',
                'script' => 'console.log("count_inc test",3);',
                'firephp' => 'X-Wf-1-1-1-3: 43|[{"Type":"LOG","Label":"count_inc test"},3]|',
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
                'html' => '<div class="m_count" title="'.__FILE__.': line '.$lines[1].'"><span class="no-pseudo t_string">count</span> = <span class="t_int">1</span></div>',
                'text' => '✚ count = 1',
                'script' => 'console.log("count",1);',
                'firephp' => 'X-Wf-1-1-1-4: %d|[{"Type":"LOG","File":"'.str_replace('/', '\\/', __FILE__).'","Line":'.$lines[1].',"Label":"count"},1]|',
            )
        );

        /*
            Test passing flags as first param
        */
        $this->testMethod(
            'count',
            array(\bdk\Debug::COUNT_NO_OUT),
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
                'firephp' => 'X-Wf-1-1-1-70: 32|[{"Type":"LOG","Label":"foo"},0]|',
                'html' => '<div class="m_countReset"><span class="no-pseudo t_string">foo</span> = <span class="t_int">0</span></div>',
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
                'html' => '<div class="m_countReset"><span class="no-pseudo t_string">Counter \'noExisty\' doesn\'t exist.</span></div>',
                'script' => 'console.log("Counter \'noExisty\' doesn\'t exist.");',
                'text' => '✚ Counter \'noExisty\' doesn\'t exist.',
            )
        );
        $this->testMethod(
            'countReset',
            array('noExisty', \bdk\Debug::COUNT_NO_OUT),
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
                    $this->assertSame('error', $entry[0]);
                    $this->assertSame('a string', $entry[1][0]);
                    $this->assertSame(array(), $entry[1][1]);
                    $this->assertTrue($this->checkAbstractionType($entry[1][2], 'object'));
                    $this->assertTrue($this->checkAbstractionType($entry[1][3], 'resource'));
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
                'html' => '<div class="m_error" title="%s: line %d"><span class="no-pseudo t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="t_classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></div>',
                'text' => '⦻ a string, array(), (object) stdClass
                    Properties: none!
                    Methods: none!, Resource id #%i: stream',
                'script' => 'console.error("a string",[],{"___class_name":"stdClass"},"Resource id #%i: stream","%s: line %d");',
                'firephp' => 'X-Wf-1-1-1-3: %d|[{"Type":"ERROR","File":"%s","Line":%d,"Label":"a string"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
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
                'entry' => array('group',array('a','b','c'), array()),
                'custom' => function () {
                    $this->assertSame(array(
                        'main' => array(
                            array('channel' => 'general', 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('a','b','c'),
                    null,
                    'group',
                ),
                'html' => '<div class="expanded group-header"><span class="group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label">)</span></div>
                    <div class="m_group">',
                'text' => '▸ a, "b", "c"',
                'script' => 'console.group("a","b","c");',
                'firephp' => 'X-Wf-1-1-1-4: 61|[{"Type":"GROUP_START","Collapsed":"false","Label":"a"},null]|',
            )
        );

        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            // @todo chromeLogger & firephp
            'html' => '<div class="m_log"><span class="no-pseudo t_string">before group</span></div>
                <div class="m_log"><span class="no-pseudo t_string">after group</span></div>',
            'text' => 'before group
                after group',
            'script' => 'console.log("before group");
                console.log("after group");',
            // 'firephp' => '',
        ));

        /*
            Test default label
        */
        $this->testMethod(
            'group',
            array(),
            array(
                'entry' => array(
                    'group',
                    array(__CLASS__.'->testMethod'),
                    array('isMethodName' => true),
                ),
                'chromeLogger' => array(
                    array(__CLASS__.'->testMethod'),
                    null,
                    'group',
                ),
                'html' => '<div class="expanded group-header"><span class="group-label"><span class="t_classname">'.__CLASS__.'</span><span class="t_operator">-&gt;</span><span class="method-name">testMethod</span></span></div>
                    <div class="m_group">',
                'text' => '▸ '.__CLASS__.'->testMethod',
                'script' => 'console.group("'.__CLASS__.'->testMethod");',
                'firephp' => 'X-Wf-1-1-1-6: 82|[{"Type":"GROUP_START","Collapsed":"false","Label":"'.__CLASS__.'->testMethod"},null]|',
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
                        'statically' => true,
                        'isMethodName' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase->testBasePublic'),
                    null,
                    'group',
                ),
                'html' => '<div class="expanded group-header"><span class="group-label"><span class="t_classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">-&gt;</span><span class="method-name">testBasePublic</span></span></div>
                    <div class="m_group">',
                'text' => '▸ bdk\DebugTest\TestBase->testBasePublic',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase->testBasePublic");',
                'firephp' => 'X-Wf-1-1-1-7: 100|[{"Type":"GROUP_START","Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase->testBasePublic"},null]|',
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
                        'statically' => true,
                        'isMethodName' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\Test->testBasePublic'),
                    null,
                    'group',
                ),
                'html' => '<div class="expanded group-header"><span class="group-label"><span class="t_classname"><span class="namespace">bdk\DebugTest\</span>Test</span><span class="t_operator">-&gt;</span><span class="method-name">testBasePublic</span></span></div>
                    <div class="m_group">',
                'text' => '▸ bdk\DebugTest\Test->testBasePublic',
                'script' => 'console.group("bdk\\\DebugTest\\\Test->testBasePublic");',
                'firephp' => 'X-Wf-1-1-1-8: 96|[{"Type":"GROUP_START","Collapsed":"false","Label":"bdk\\\DebugTest\\\Test->testBasePublic"},null]|',
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
                        'statically' => true,
                        'isMethodName' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'html' => '<div class="expanded group-header"><span class="group-label"><span class="t_classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">::</span><span class="method-name">testBaseStatic</span></span></div>
                    <div class="m_group">',
                'text' => '▸ bdk\DebugTest\TestBase::testBaseStatic',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase::testBaseStatic");',
                'firephp' => 'X-Wf-1-1-1-9: 100|[{"Type":"GROUP_START","Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase::testBaseStatic"},null]|',
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
                        'statically' => true,
                        'isMethodName' => true,
                    ),
                ),
                'chromeLogger' => array(
                    array('bdk\DebugTest\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'html' => '<div class="expanded group-header"><span class="group-label"><span class="t_classname"><span class="namespace">bdk\DebugTest\</span>TestBase</span><span class="t_operator">::</span><span class="method-name">testBaseStatic</span></span></div>
                    <div class="m_group">',
                'text' => '▸ bdk\DebugTest\TestBase::testBaseStatic',
                'script' => 'console.group("bdk\\\DebugTest\\\TestBase::testBaseStatic");',
                'firephp' => 'X-Wf-1-1-1-10: 100|[{"Type":"GROUP_START","Collapsed":"false","Label":"bdk\\\DebugTest\\\TestBase::testBaseStatic"},null]|',
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'group',
            array('not logged'),
            false
        );
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
                'entry' => array('groupCollapsed', array('a','b','c'), array()),
                'custom' => function () {
                    $this->assertSame(array(
                        'main' => array(
                            0 => array('channel' => 'general', 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('a','b','c'),
                    null,
                    'groupCollapsed',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 60|[{"Type":"GROUP_START","Collapsed":"true","Label":"a"},null]|',
                'html' => '<div class="collapsed group-header"><span class="group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label">)</span></div>
                    <div class="m_group">',
                'script' => 'console.groupCollapsed("a","b","c");',
                'text' => '▸ a, "b", "c"',
            )
        );

        // add a nested gorup that will get removed on output
        $this->debug->groupCollapsed($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after nested group');
        $this->outputTest(array(
            'html' => '<div class="collapsed group-header"><span class="group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label">)</span></div>
                <div class="m_group">
                    <div class="m_log"><span class="no-pseudo t_string">after nested group</span></div>
                </div>',
            'text' => '▸ a, "b", "c"
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
        ), $log);

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
                'entry' => array('groupEnd', array(), array()),
                'custom' => function () {
                    // $this->assertSame(array(1,1), $this->debug->getData('groupDepth'));
                },
                'chromeLogger' => array(
                    array(),
                    null,
                    'groupEnd',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 27|[{"Type":"GROUP_END"},null]|',
                'html' => '</div>',
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
                'html' => '</div>',
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
                'firephp' => 'X-Wf-1-1-1-154: 39|[{"Type":"LOG","Label":"return"},"foo"]|',
                'html' => '<div class="m_groupEndValue"><span class="no-pseudo t_string">return</span> = <span class="t_string">foo</span></div>',
                'script' => 'console.log("return","foo");',
                'text' => 'return = "foo"',
            )
        );
    }

    public function testGroupsLeftOpen()
    {
        /*
        Internal debug.output subscribers
            1: Output::onOutput:  closes open groups / remoes hideIfEmpty groups
            0: Internal::onOutput opens and closes groupSummary
            0: Output plugin's onOutput()

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
        }, -2);
        $output = $this->debug->output();

        $this->assertSame(array(1), $onOutputVals['groupPriorityStackA']);
        $this->assertSame(array(
            'main' => array(),
            1 => array(
                array(
                    'channel' => 'general',
                    'collect' => true,
                ),
            ),
        ), $onOutputVals['groupStacksA']);
        $this->assertSame(array(), $onOutputVals['groupPriorityStackB']);
        $this->assertSame(array(
            'main' => array(),
        ), $onOutputVals['groupStacksB']);
        $outputExpect = <<<'EOD'
<div class="debug" data-channel-root="general" data-channels="{}">
    <div class="debug-bar"><h3>Debug Log</h3></div>
    <div class="debug-header m_group">
        <div class="m_log"><span class="no-pseudo t_string">in summary</span></div>
        <div class="expanded group-header"><span class="group-label">inner group opened but not closed</span></div>
        <div class="m_group">
            <div class="m_log"><span class="no-pseudo t_string">in inner</span></div>
        </div>
        <div class="m_info"><span class="no-pseudo t_string">Built In %f sec</span></div>
        <div class="m_info"><span class="no-pseudo t_string">Peak Memory Usage: %f MB / %d %cB</span></div>
    </div>
    <div class="debug-content m_group">
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
        ), $logSummary);
        $log = $this->debug->getData('log');
        $this->assertSame(array(
            array('log',array('I\'m not in the summary'), array()),
            array('log',array('the end'), array()),
        ), $log);
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
        $this->assertSame('group', $log[0][0]); // groupCollapsed converted to group
        $this->assertSame('groupCollapsed', $log[1][0]);
        $this->assertSame('group', $log[5][0]); // groupCollapsed converted to group
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
                    $this->assertSame('info', $logEntry[0]);
                    $this->assertSame('a string', $logEntry[1][0]);
                    // check array abstraction
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry[1][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry[1][3], 'resource');
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
                'html' => '<div class="m_info"><span class="no-pseudo t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="t_classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></div>',
                'text' => 'ℹ a string, array(), (object) stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                'script' => 'console.info("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Type":"INFO","Label":"a string"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
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
                    $this->assertSame('log', $logEntry[0]);
                    $this->assertSame('a string', $logEntry[1][0]);
                    // check array abstraction
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry[1][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry[1][3], 'resource');
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
                'html' => '<div class="m_log"><span class="no-pseudo t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="t_classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></div>',
                'text' => 'a string, array(), (object) stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                'script' => 'console.log("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Type":"LOG","Label":"a string"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
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

    /**
     * Test
     *
     * @return void
     */
    public function testLogSubstitution()
    {
        $location = 'http://localhost/?foo=bar&jim=slim';
        $args = array(
            '%cLocation:%c <a href="%s">%s</a>',
            'font-weight:bold;',
            '',
            $location,
            $location,
        );
        $this->testMethod(
            'log',
            $args,
            array(
                'entry' => array(
                    'log',
                    array(
                        '%cLocation:%c <a href="%s">%s</a>',
                        'font-weight:bold;',
                        '',
                        $location,
                        $location,
                    ),
                    array(),
                ),
                'chromeLogger' => array(
                    $args,
                    null,
                    '',
                ),
                'firephp' => str_replace('%c', '%%c', 'X-Wf-1-1-1-19: 168|[{"Type":"LOG","Label":"%cLocation:%c <a href=\"%s\">%s<\/a>"},'.json_encode(array_slice($args, 1)).']|'),
                'html' => '<div class="m_log"><span class="no-pseudo t_string"><span style="font-weight:bold;">Location:</span><span> <a href="http://localhost/?foo=bar&amp;jim=slim">http://localhost/?foo=bar&amp;jim=slim</a></span></span></div>',
                'script' => str_replace('%c', '%%c', 'console.log('.trim(json_encode($args), '[]').');'),
                'text' => 'Location: "http://localhost/?foo=bar&jim=slim"',
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
                        'time: %f sec',
                    ),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'time: %f sec',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"time: %f sec"]|',
                'html' => '<div class="m_time"><span class="no-pseudo t_string">time: %f sec</span></div>',
                'script' => 'console.log("time: %f sec");',
                'text' => '⏱ time: %f sec',
            )
        );
        $this->testMethod(
            'timeEnd',
            array(
                'my label',
                true,
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
                        'my label: %f sec',
                    ),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: %f sec',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"my label: %f sec"]|',
                'html' => '<div class="m_time"><span class="no-pseudo t_string">my label: %f sec</span></div>',
                'script' => 'console.log("my label: %f sec");',
                'text' => '⏱ my label: %f sec',
            )
        );
        $this->testMethod(
            'timeEnd',
            array(
                'my label',
                'blah%labelblah%timeblah',
            ),
            array(
                'entry' => function ($logEntry) {
                    $expectFormat = json_encode(array(
                        'time',
                        array("blahmy labelblah%fblah"),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry), 'chromeLogger not same');
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'blahmy labelblah%fblah',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-22: 45|[{"Type":"LOG"},"blahmy labelblah%fblah"]|',
                'html' => '<div class="m_time"><span class="no-pseudo t_string">blahmy labelblah%fblah</span></div>',
                'script' => 'console.log("blahmy labelblah%fblah");',
                'text' => '⏱ blahmy labelblah%fblah',
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
                'entry' => function ($logEntry) {
                    $expectFormat = json_encode(array(
                        'time',
                        array('time: %f sec'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'time: %f sec',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"time: %f sec"]|',
                'html' => '<div class="m_time"><span class="no-pseudo t_string">time: %f sec</span></div>',
                'script' => 'console.log("time: %f sec");',
                'text' => '⏱ time: %f sec',
            )
        );

        $this->testMethod(
            'timeGet',
            array('my label'),
            array(
                'entry' => json_encode(array(
                    'time',
                    array(
                        'my label: %f sec',
                    ),
                    array(),
                )),
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: %f sec',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"my label: %f sec"]|',
                'html' => '<div class="m_time"><span class="no-pseudo t_string">my label: %f sec</span></div>',
                'script' => 'console.log("my label: %f sec");',
                'text' => '⏱ my label: %f sec',
            )
        );

        $this->testMethod(
            'timeGet',
            array(
                'my label',
                true,
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
                'blah%labelblah%timeblah',
            ),
            array(
                'entry' => function ($logEntry) {
                    $expectFormat = json_encode(array(
                        'time',
                        array("blahmy labelblah%fblah"),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry), 'entry as expected');
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'blahmy labelblah%fblah',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-22: 45|[{"Type":"LOG"},"blahmy labelblah%fblah"]|',
                'html' => '<div class="m_time"><span class="no-pseudo t_string">blahmy labelblah%fblah</span></div>',
                'script' => 'console.log("blahmy labelblah%fblah");',
                'text' => '⏱ blahmy labelblah%fblah',
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
                'entry' => function ($logEntry) {
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('time: ', '%f sec'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'time: ',
                        '%f sec',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-166: 46|[{"Type":"LOG","Label":"time: "},"%f sec"]|',
                'html' => '<div class="m_timeLog"><span class="no-pseudo t_string">time: </span><span class="t_string">%f sec</span></div>',
                'script' => 'console.log("time: ","%f sec");',
                'text' => '⏱ time: "%f sec"',
            )
        );

        $this->testMethod(
            'timeLog',
            array('my label', array('foo'=>'bar')),
            array(
                'entry' => function ($logEntry) {
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('my label: ', '%f sec', array('foo'=>'bar')),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                'chromeLogger' => json_encode(array(
                    array(
                        'my label: ',
                        '%f sec',
                        array('foo'=>'bar'),
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-169: 66|[{"Type":"LOG","Label":"my label: "},["%f sec",{"foo":"bar"}]]|',
                'html' => '<div class="m_timeLog"><span class="no-pseudo t_string">my label: </span><span class="t_string">%f sec</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                    <span class="array-inner">
                    <span class="key-value"><span class="t_key">foo</span> <span class="t_operator">=&gt;</span> <span class="t_string">bar</span></span>
                    </span><span class="t_punct">)</span></span></div>',
                'script' => 'console.log("my label: ","%f sec",{"foo":"bar"});',
                'text' => '⏱ my label: "%f sec", array(
                    [foo] => "bar"
                    )',
            )
        );

        $this->testMethod(
            'timeLog',
            array('bogus'),
            array(
                'entry' => function ($logEntry) {
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('Timer \'bogus\' does not exist'),
                        array(),
                ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                'chromeLogger' => json_encode(array(
                    array('Timer \'bogus\' does not exist'),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-172: 47|[{"Type":"LOG"},"Timer \'bogus\' does not exist"]|',
                'html' => '<div class="m_timeLog"><span class="no-pseudo t_string">Timer \'bogus\' does not exist</span></div>',
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
                    $trace = $logEntry[1][0];
                    $this->assertSame($values['file0'], $trace[0]['file']);
                    $this->assertSame($values['line0'], $trace[0]['line']);
                    $this->assertInternalType('integer', $trace[0]['line']);
                    $this->assertNotTrue(isset($trace[0]['function']));
                    $this->assertSame($values['function1'], $trace[1]['function']);
                },
                'chromeLogger' => function ($logEntry) {
                    $trace = $this->debug->getData('log/0/1/0');
                    // reorder keys
                    $order = array('function','file','line');
                    foreach ($trace as $i => $row) {
                        uksort($trace[$i], function ($a, $b) use ($order) {
                            $posa = array_search($a, $order);
                            $posb = array_search($b, $order);
                            return $posa < $posb ? -1 : 1;
                        });
                    }
                    $this->assertSame(array($trace), $logEntry[0]);
                    $this->assertSame(null, $logEntry[1]);
                    $this->assertSame('table', $logEntry[2]);
                },
                'firephp' => function ($logEntry) {
                    $trace = $this->debug->getData('log/0/1/0');
                    preg_match('#\|(.+)\|#', $logEntry, $matches);
                    $logEntry = json_decode($matches[1], true);
                    list($logEntryMeta, $logEntryTable) = $logEntry;
                    $this->assertSame(array(
                        'Type' => 'TABLE',
                        'Label' => 'trace',
                    ), $logEntryMeta);
                    $this->assertSame(array(
                        '',
                        'function',
                        'file',
                        'line',
                    ), $logEntryTable[0]);
                    $count = count($logEntryTable);
                    for ($i=1; $i<$count; $i++) {
                        $tracei = $i - 1;
                        $valuesExpect = array(
                            $tracei,
                            isset($trace[$tracei]['function']) ? $trace[$tracei]['function'] : null,
                            $trace[$tracei]['file'],
                            $trace[$tracei]['line'],
                        );
                        $this->assertSame($valuesExpect, $logEntryTable[$i]);
                    }
                },
                'html' => function ($logEntry) {
                    // $this->assertSame('', $logEntry);
                    $trace = $this->debug->getData('log/0/1/0');
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
                    $trace = $this->debug->getData('log/0/1/0');
                    // reorder keys
                    $order = array('function','file','line');
                    foreach ($trace as $i => $row) {
                        uksort($trace[$i], function ($a, $b) use ($order) {
                            $posa = array_search($a, $order);
                            $posb = array_search($b, $order);
                            return $posa < $posb ? -1 : 1;
                        });
                    }
                    preg_match('#console.table\((.+)\);#', $logEntry, $matches);
                    $this->assertSame(json_encode($trace), $matches[1]);
                },
                'text' => function ($logEntry) use ($values) {
                    $trace = $this->debug->getData('log/0/1/0');
                    $expect = 'trace = '.$this->debug->output->text->dump($trace);
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
                    $this->assertSame('warn', $logEntry[0]);
                    $this->assertSame('a string', $logEntry[1][0]);
                    // check array abstraction
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry[1][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry[1][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);

                    $this->assertArrayHasKey('file', $logEntry[2]);
                    $this->assertArrayHasKey('line', $logEntry[2]);
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
                'html' => '<div class="m_warn" title="'.__DIR__.'/DebugTestFramework.php: line %d"><span class="no-pseudo t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="t_classname">stdClass</span>
                    <dl class="object-inner">
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </span>, <span class="t_resource">Resource id #%d: stream</span></div>',
                'text' => '⚠ a string, array(), (object) stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
                'script' => 'console.warn("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream","'.str_replace('/', '\\/', __DIR__.'/').'DebugTestFramework.php: line %d");',
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"Type":"WARN","File":"'.str_replace('/', '\\/', __DIR__.'/').'DebugTestFramework.php","Line":%d,"Label":"a string"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
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
