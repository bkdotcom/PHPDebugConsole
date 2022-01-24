<?php

namespace bdk\Test\Debug\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug Methods
 */
class ClearTest extends DebugTestFramework
{
    /**
     * Test
     *
     * @return void
     */
    public function testClearDefault()
    {
        $entry = array(
            'method' => 'clear',
            'args' => array('Cleared log (sans errors)'),
            'meta' => array(
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
                    $this->assertCount(1, $this->debug->data->get('alerts'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/0'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/1'));
                    $this->assertCount(4, $this->debug->data->get('log'));    // clear-summary gets added
                },
                'entry' => $entry,
                'chromeLogger' => array(
                    array('Cleared log (sans errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared log (sans errors)"]|',
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
                    $this->assertCount(1, $this->debug->data->get('alerts'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/0'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/1'));
                    $this->assertCount(3, $this->debug->data->get('log'));
                    $lastMethod = $this->debug->data->get('log/__end__/method');
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
            'method' => 'clear',
            'args' => array('Cleared alerts'),
            'meta' => array(
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
                    $this->assertCount(0, $this->debug->data->get('alerts'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/0'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/1'));
                    $this->assertCount(6, $this->debug->data->get('log'));
                },
                'chromeLogger' => array(
                    array('Cleared alerts'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared alerts"]|',
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
            'method' => 'clear',
            'args' => array('Cleared summary (sans errors)'),
            'meta' => array(
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
                    $this->assertCount(1, $this->debug->data->get('alerts'));
                    $this->assertCount(1, $this->debug->data->get('logSummary/0'));   // error remains
                    $this->assertCount(2, $this->debug->data->get('logSummary/1'));   // group & error remain
                    $this->assertCount(6, $this->debug->data->get('log'));

                    $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
                    $groupStackCounts = \array_map(function ($stack) {
                        return \count($stack);
                    }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    $this->assertSame(array(
                        'main' => 2,
                        0 => 0,
                        1 => 1,
                    ), $groupStackCounts);
                },
                'chromeLogger' => array(
                    array('Cleared summary (sans errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared summary (sans errors)"]|',
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
            'method' => 'clear',
            'args' => array('Cleared errors'),
            'meta' => array(
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
                    $this->assertCount(1, $this->debug->data->get('alerts'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/0'));
                    $this->assertCount(3, $this->debug->data->get('logSummary/1'));
                    $this->assertCount(5, $this->debug->data->get('log'));

                    $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
                    $groupStackCounts = \array_map(function ($stack) {
                        return \count($stack);
                    }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    $this->assertSame(array(
                        'main' => 2,
                        0 => 1,
                        1 => 1,
                    ), $groupStackCounts);
                },
                'chromeLogger' => array(
                    array('Cleared errors'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared errors"]|',
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
            'method' => 'clear',
            'args' => array('Cleared everything'),
            'meta' => array(
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
                    $this->assertCount(0, $this->debug->data->get('alerts'));
                    $this->assertCount(0, $this->debug->data->get('logSummary/0'));
                    $this->assertCount(1, $this->debug->data->get('logSummary/1'));   // group remains
                    $this->assertCount(3, $this->debug->data->get('log'));    // groups remain
                    $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
                    $groupStackCounts = \array_map(function ($stack) {
                        return \count($stack);
                    }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    $this->assertSame(array(
                        'main' => 2,
                        0 => 0,
                        1 => 1,
                    ), $groupStackCounts);
                },
                'chromeLogger' => array(
                    array('Cleared everything'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared everything"]|',
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
            'method' => 'clear',
            'args' => array('Cleared summary (incl errors)'),
            'meta' => array(
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
                    $this->assertCount(1, $this->debug->data->get('alerts'));
                    $this->assertCount(0, $this->debug->data->get('logSummary/0'));
                    $this->assertCount(1, $this->debug->data->get('logSummary/1'));   // group remains
                    $this->assertCount(6, $this->debug->data->get('log'));
                    $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
                    $groupStackCounts = \array_map(function ($stack) {
                        return \count($stack);
                    }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    $this->assertSame(array(
                        'main' => 2,
                        0 => 0,
                        1 => 1,
                    ), $groupStackCounts);
                },
                'chromeLogger' => array(
                    array('Cleared summary (incl errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared summary (incl errors)"]|',
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
                'custom' => function (LogEntry $logEntry) {
                    $this->assertSame('Cleared log (sans errors)', $logEntry['args'][0]);
                    $this->assertCount(4, $this->debug->data->get('log'));    // clear-summary gets added
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
                'custom' => function (LogEntry $logEntry) {
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
                'custom' => function (LogEntry $logEntry) {
                    $this->assertSame('Cleared alerts, log (sans errors), and summary (sans errors)', $logEntry['args'][0]);
                }
            )
        );
    }

    /**
     * Clear the log and create log entries
     *
     * @return void
     */
    private function clearPrep()
    {
        $this->debug->data->set(array(
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
}
