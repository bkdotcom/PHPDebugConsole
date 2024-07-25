<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug Methods
 *
 * @covers \bdk\Debug\Plugin\Method\Clear
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
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
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared log (sans errors)'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_LOG,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => true,
                    'logErrors' => false,
                    'silent' => false,
                    'summary' => false,
                    'summaryErrors' => false,
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
                    self::assertCount(1, $this->debug->data->get('alerts'));
                    self::assertCount(3, $this->debug->data->get('logSummary/0'));
                    self::assertCount(3, $this->debug->data->get('logSummary/1'));
                    self::assertCount(5, $this->debug->data->get('log'));    // clear-summary gets added
                },
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array('Cleared log (sans errors)'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared log (sans errors)"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared log (sans errors)</span></li>',
                'script' => 'console.log("Cleared log (sans errors)");',
                'text' => '⌦ Cleared log (sans errors)',
                'wamp' => $entryExpect,
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
            array(Debug::CLEAR_SILENT), // CLEAR_LOG will be added as default
            array(
                'custom' => function () {
                    self::assertCount(1, $this->debug->data->get('alerts'));
                    self::assertCount(3, $this->debug->data->get('logSummary/0'));
                    self::assertCount(3, $this->debug->data->get('logSummary/1'));
                    self::assertCount(4, $this->debug->data->get('log'));
                    $lastMethod = $this->debug->data->get('log/__end__/method');
                    self::assertSame('error', $lastMethod);
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
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared alerts'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_ALERTS,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => true,
                    'log' => false,
                    'logErrors' => false,
                    'silent' => false,
                    'summary' => false,
                    'summaryErrors' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALERTS),
            array(
                'entry' => $entryExpect,
                'custom' => function () {
                    self::assertCount(0, $this->debug->data->get('alerts'));
                    self::assertCount(3, $this->debug->data->get('logSummary/0'));
                    self::assertCount(3, $this->debug->data->get('logSummary/1'));
                    self::assertCount(7, $this->debug->data->get('log'));
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
                'wamp' => $entryExpect,
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
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared summary (sans errors)'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_SUMMARY,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => false,
                    'silent' => false,
                    'summary' => true,
                    'summaryErrors' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY),
            array(
                'entry' => $entryExpect,
                'custom' => function () {
                    self::assertCount(1, $this->debug->data->get('alerts'));
                    self::assertCount(1, $this->debug->data->get('logSummary/0'));   // error remains
                    self::assertCount(2, $this->debug->data->get('logSummary/1'));   // group & error remain
                    self::assertCount(7, $this->debug->data->get('log'));

                    $groupStack = self::getSharedVar('groupStack');
                    $groupStackCounts = \array_map(static function ($stack) {
                        return \count($stack);
                    }, self::getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    self::assertSame(array(
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
                'wamp' => $entryExpect,
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
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared errors'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_LOG_ERRORS,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => true,
                    'silent' => false,
                    'summary' => false,
                    'summaryErrors' => false,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_LOG_ERRORS),
            array(
                'entry' => $entryExpect,
                'custom' => function () {
                    self::assertCount(1, $this->debug->data->get('alerts'));
                    self::assertCount(3, $this->debug->data->get('logSummary/0'));
                    self::assertCount(3, $this->debug->data->get('logSummary/1'));
                    self::assertCount(5, $this->debug->data->get('log'));

                    $groupStack = self::getSharedVar('groupStack');
                    $groupStackCounts = \array_map(static function ($stack) {
                        return \count($stack);
                    }, self::getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    self::assertSame(array(
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
                'wamp' => $entryExpect,
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
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared everything'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_ALL,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => true,
                    'log' => true,
                    'logErrors' => true,
                    'silent' => false,
                    'summary' => true,
                    'summaryErrors' => true,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_ALL),
            array(
                'entry' => $entryExpect,
                'custom' => function () {
                    self::assertCount(0, $this->debug->data->get('alerts'));
                    self::assertCount(0, $this->debug->data->get('logSummary/0'));
                    self::assertCount(1, $this->debug->data->get('logSummary/1'));   // group remains
                    self::assertCount(3, $this->debug->data->get('log'));    // groups remain
                    $groupStack = self::getSharedVar('groupStack');
                    $groupStackCounts = \array_map(static function ($stack) {
                        return \count($stack);
                    }, self::getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    self::assertSame(array(
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
                'wamp' => $entryExpect,
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
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared summary (incl errors)'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_SUMMARY | Debug::CLEAR_SUMMARY_ERRORS,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => false,
                    'silent' => false,
                    'summary' => true,
                    'summaryErrors' => true,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY | Debug::CLEAR_SUMMARY_ERRORS),
            array(
                'entry' => $entryExpect,
                'custom' => function () {
                    self::assertCount(1, $this->debug->data->get('alerts'));
                    self::assertCount(0, $this->debug->data->get('logSummary/0'));
                    self::assertCount(1, $this->debug->data->get('logSummary/1'));   // group remains
                    self::assertCount(7, $this->debug->data->get('log'));
                    $groupStack = self::getSharedVar('groupStack');
                    $groupStackCounts = \array_map(static function ($stack) {
                        return \count($stack);
                    }, self::getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    self::assertSame(array(
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
                'wamp' => $entryExpect,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testClearSummaryErrors()
    {
        $entryExpect = array(
            'method' => 'clear',
            'args' => array('Cleared summary errors'),
            'meta' => array(
                'bitmask' => Debug::CLEAR_SUMMARY_ERRORS,
                'evalLine' => null,
                'file' => $this->file,
                'flags' => array(
                    'alerts' => false,
                    'log' => false,
                    'logErrors' => false,
                    'silent' => false,
                    'summary' => false,
                    'summaryErrors' => true,
                ),
                'line' => $this->line,
            ),
        );
        $this->clearPrep();
        $this->testMethod(
            'clear',
            array(Debug::CLEAR_SUMMARY_ERRORS),
            array(
                'entry' => $entryExpect,
                'custom' => function () {
                    self::assertCount(1, $this->debug->data->get('alerts'));
                    self::assertCount(2, $this->debug->data->get('logSummary/0'));
                    self::assertCount(2, $this->debug->data->get('logSummary/1'));   // group remains
                    self::assertCount(7, $this->debug->data->get('log'));
                    $groupStack = self::getSharedVar('groupStack');
                    $groupStackCounts = \array_map(static function ($stack) {
                        return \count($stack);
                    }, self::getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
                    self::assertSame(array(
                        'main' => 2,
                        0 => 1,
                        1 => 1,
                    ), $groupStackCounts);
                },
                'chromeLogger' => array(
                    array('Cleared summary errors'),
                    $this->file . ': ' . $this->line,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-14: %d|[{"File":' . \json_encode($this->file, JSON_UNESCAPED_SLASHES) . ',"Line":' . $this->line . ',"Type":"LOG"},"Cleared summary errors"]|',
                'html' => '<li class="m_clear" data-file="' . $this->file . '" data-line="' . $this->line . '"><span class="no-quotes t_string">Cleared summary errors</span></li>',
                'script' => 'console.log("Cleared summary errors");',
                'text' => '⌦ Cleared summary errors',
                'wamp' => $entryExpect,
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
                    self::assertSame('Cleared log (sans errors)', $logEntry['args'][0]);
                    self::assertCount(5, $this->debug->data->get('log'));    // clear-summary gets added
                },
                'return' => $this->debug,
                'wamp' => array(
                    'clear',
                    array(
                        'Cleared log (sans errors)',
                    ),
                    array(
                        'bitmask' => Debug::CLEAR_LOG,
                        'evalLine' => null,
                        'file' => $this->file,
                        'flags' => array(
                            'alerts' => false,
                            'log' => true,
                            'logErrors' => false,
                            'silent' => false,
                            'summary' => false,
                            'summaryErrors' => false,
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
                'custom' => static function (LogEntry $logEntry) {
                    self::assertSame('Cleared log (sans errors) and summary (sans errors)', $logEntry['args'][0]);
                },
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
                'custom' => static function (LogEntry $logEntry) {
                    self::assertSame('Cleared alerts, log (sans errors), and summary (sans errors)', $logEntry['args'][0]);
                },
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
        self::$allowError = true;

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
        $this->debug->errorHandler->handleError(
            E_USER_ERROR,
            'some user error',
            __FILE__,
            __LINE__
        );
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
