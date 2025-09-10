<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler\Error;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug Methods
 *
 * @covers \bdk\Debug\AbstractComponent
 * @covers \bdk\Debug\AbstractDebug
 * @covers \bdk\Debug\Abstraction\Object\Properties
 * @covers \bdk\Debug\Abstraction\Object\PropertiesInstance
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\LogEntry
 * @covers \bdk\Debug\Plugin\Method\Basic
 * @covers \bdk\Debug\Route\AbstractRoute
 * @covers \bdk\Debug\Route\ChromeLogger
 * @covers \bdk\Debug\Route\Firephp
 * @covers \bdk\Debug\Route\Script
 * @covers \bdk\Debug\Route\ServerLog
 * @covers \bdk\Debug\Route\Stream
 * @covers \bdk\Debug\Route\Text
 * @covers \bdk\Debug\Route\WampCrate
 * @covers \bdk\Debug\ServiceProvider
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class BasicTest extends DebugTestFramework
{
    /**
     * @doesNotPerformAssertions
     */
    public function testBootstrap()
    {
        $this->debug->removePlugin($this->debug->getPlugin('methodBasic'));
        $this->debug->addPlugin(new \bdk\Debug\Plugin\Method\Basic(), 'methodBasic');
    }

    /**
     * Test overriding a core method
     */
    public function testOverrideOutput()
    {
        $closure = static function (LogEntry $logEntry) {
            if ($logEntry['method'] === 'trace') {
                $route = $logEntry['route'];
                if ($route instanceof \bdk\Debug\Route\ChromeLogger) {
                    $logEntry['method'] = 'log';
                    $logEntry['args'] = array('this was a trace');
                } elseif ($route instanceof \bdk\Debug\Route\Firephp) {
                    $logEntry['method'] = 'log';
                    $logEntry['args'] = array('this was a trace');
                } elseif ($route instanceof \bdk\Debug\Route\Html) {
                    $logEntry['return'] = '<li class="m_trace">this was a trace</li>';
                } elseif ($route instanceof \bdk\Debug\Route\Script) {
                    $logEntry['return'] = 'console.log("this was a trace");';
                } elseif ($route instanceof \bdk\Debug\Route\Text) {
                    $logEntry['return'] = 'this was a trace';
                } elseif ($route instanceof \bdk\Debug\Route\Wamp) {
                    $logEntry['method'] = 'log';
                    $logEntry['args'] = array('something completely different');
                    $meta = \array_diff_key($logEntry['meta'], \array_flip(['caption', 'inclContext', 'limit', 'requestId', 'sortable', 'tableInfo']));
                    $logEntry['meta'] = $meta;
                }
            }
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $closure);
        $this->testMethod(
            'trace',
            array(),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    // we're doing the custom stuff via Debug::EVENT_OUTPUT_LOG_ENTRY, so logEntry should still be trace
                    self::assertSame('trace', $logEntry['method']);
                    self::assertIsArray($logEntry['args'][0]);
                    $filepaths = \array_column($logEntry['args'][0], 'file');
                    $metaExpect = array(
                        'caption' => 'trace',
                        'detectFiles' => true,
                        'inclArgs' => false,
                        'inclInternal' => false,
                        'limit' => 0,
                        'sortable' => false,
                        'tableInfo' => array(
                            'class' => null,
                            'columns' => array(
                                array('key' => 'file'),
                                array('key' => 'line'),
                                array('key' => 'function'),
                            ),
                            'haveObjRow' => false,
                            'indexLabel' => null,
                            'rows' => array(),
                            'summary' => '',
                            'commonRowInfo' => array(
                                'commonFilePrefix' => \bdk\Debug\Utility\StringUtil::commonPrefix($filepaths),
                            ),
                        ),
                    );
                    self::assertSame($metaExpect, $logEntry['meta']);
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
                        'inclArgs' => false,
                        'inclInternal' => false,
                        'format' => 'raw',
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
        $closure = static function (LogEntry $logEntry) {
            if ($logEntry['method'] === 'myCustom' && $logEntry['route'] instanceof \bdk\Debug\Route\Html) {
                $lis = array();
                foreach ($logEntry['args'] as $arg) {
                    $lis[] = '<li>' . \htmlspecialchars($arg) . '</li>';
                }
                $logEntry['return'] = '<li class="m_myCustom"><ul>' . \implode('', $lis) . '</ul></li>';
            }
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $closure);
        $entry = array(
            'method' => 'myCustom',
            'args' => array('How\'s it goin?'),
            'meta' => array(
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
                'html' => PHP_VERSION_ID >= 80100
                    ? '<li class="m_myCustom"><ul><li>How&#039;s it goin?</li></ul></li>'
                    : '<li class="m_myCustom"><ul><li>How\'s it goin?</li></ul></li>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
                'wamp' => $entry,
            )
        );

        /*
            Now test it statically
        */
        Debug::myCustom('called statically');
        $entry = array(
            'method' => 'myCustom',
            'args' => array('called statically'),
            'meta' => array(
                'isCustomMethod' => true,
                // 'statically' => true,
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
            'method' => 'myCustom',
            'args' => array('How\'s it goin?'),
            'meta' => array(
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
                'html' => PHP_VERSION_ID >= 80100
                    ? '<li class="m_myCustom"><span class="no-quotes t_string">How&#039;s it goin?</span></li>'
                    : '<li class="m_myCustom"><span class="no-quotes t_string">How\'s it goin?</span></li>',
                'script' => 'console.log("How\'s it goin?");',
                'text' => 'How\'s it goin?',
                'wamp' => $entry,
            )
        );
    }

    public function testLogEntryWithCfg()
    {
        $this->debug->log('hello', Debug::meta('cfg', 'stringMaxLen', 3));
        self::assertSame('hel', $this->debug->data->get('log/__end__/args/0/value'));
        self::assertNull($this->debug->data->get('log/__end__/meta/cfg'));
        self::assertNotSame(3, $this->debug->getCfg('stringMaxLen')['other']);

        $closure = static function (LogEntry $logEntry) {
            $logEntry['appendLog'] = false;
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, $closure);
        $this->debug->log('foo');
        $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, $closure);
        self::assertNotSame('foo', $this->debug->data->get('log/__end__/args/0'));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testAssert()
    {
        $entry = array(
            'method' => 'assert',
            'args' => array('this is false'),
            'meta' => array(),
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
            'method' => 'assert',
            'args' => array(
                'Assertion failed:',
                $this->file . ' (line ' . $this->line . ')',
            ),
            'meta' => array(
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
                'firephp' => 'X-Wf-1-1-1-2: %d|[{"Label":"Assertion failed:","Type":"LOG"},' . \json_encode($this->file . ' (line ' . $this->line . ')', JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<li class="m_assert" data-detect-files="true"><span class="no-quotes t_string">Assertion failed: </span><span class="t_string">' . $this->file . ' (line ' . $this->line . ')</span></li>',
                'script' => 'console.assert(false,"Assertion failed:",' . \json_encode($this->file . ' (line ' . $this->line . ')', JSON_UNESCAPED_SLASHES) . ');',
                'text' => '≠ Assertion failed: "' . $this->file . ' (line ' . $this->line . ')"',
                'wamp' => $this->debug->arrayUtil->mergeDeep($entry, array(
                    'meta' => array('foundFiles' => array()),
                )),
            )
        );

        $this->testMethod(
            'assert',
            array(true, 'this is true... not logged')
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'assert',
            array(false, 'falsey'),
            array(
                'notLogged' => true,
                'return' => $this->debug,
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
        $resource = \fopen(__FILE__, 'r');
        $this->testMethod(
            'error',
            array('a string', array(), new \stdClass(), $resource),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    self::assertSame('error', $logEntry['method']);
                    self::assertSame('a string', $logEntry['args'][0]);
                    self::assertSame(array(), $logEntry['args'][1]);
                    self::assertAbstractionType($logEntry['args'][2], 'object');
                    self::assertAbstractionType($logEntry['args'][3], 'resource');
                },
                'chromeLogger' => \json_encode(array(
                    array(
                        'a string',
                        array(),
                        array(
                            '___class_name' => 'stdClass',
                        ),
                        'Resource id #%d: stream',
                    ),
                    \realpath(__DIR__ . '/../../DebugTestFramework.php') . ': %d',
                    'error',
                )),
                'firephp' => 'X-Wf-1-1-1-3: %d|[{"File":"%s","Label":"a string","Line":%d,"Type":"ERROR"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_error" data-detect-files="true" data-file="' . \realpath(__DIR__ . '/../../DebugTestFramework.php') . '" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object"><span class="t_identifier" data-type-more="className"><span class="classname">stdClass</span></span><span class="t_punct">()</span></div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.error("a string",[],{"___class_name":"stdClass"},"Resource id #%i: stream","%s: line %d");',
                'text' => '⦻ a string, array(), stdClass(), Resource id #%i: stream',
                'streamAnsi' => "\e[38;5;9m⦻ a string\e[38;5;245m, \e[38;5;9m\e[38;5;45marray\e[38;5;245m(\e[38;5;9m\e[38;5;245m)\e[38;5;9m\e[38;5;245m, \e[38;5;9m\e[1mstdClass\e[22m\e[38;5;245m()\e[38;5;9m\e[38;5;245m, \e[38;5;9mResource id #%d: stream\e[0m",
            )
        );
        \fclose($resource);

        /*
        self::assertSame(array(
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
                'return' => $this->debug,
                'wamp' => false,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testInfo()
    {
        $resource = \fopen(__FILE__, 'r');
        $this->testMethod(
            'info',
            array('a string', array(), new \stdClass(), $resource),
            array(
                'entry' => function (LogEntry $logEntry) {
                    self::assertSame('info', $logEntry['method']);
                    self::assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // self::assertAbstractionType($logEntry[2], 'array');
                    self::assertAbstractionType($logEntry['args'][2], 'object');
                    self::assertAbstractionType($logEntry['args'][3], 'resource');
                },
                'chromeLogger' => \json_encode(array(
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
                'html' => '<li class="m_info"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object"><span class="t_identifier" data-type-more="className"><span class="classname">stdClass</span></span><span class="t_punct">()</span></div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.info("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'text' => 'ℹ a string, array(), stdClass(), Resource id #%d: stream',
                // 'wamp' @todo
            )
        );
        \fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'info',
            array('info message'),
            array(
                'notLogged' => true,
                'return' => $this->debug,
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
        $resource = \fopen(__FILE__, 'r');
        $this->testMethod(
            'log',
            array('a string', array(), new \stdClass(), $resource),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    self::assertSame('log', $logEntry['method']);
                    self::assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // self::assertAbstractionType($logEntry[2], 'array');
                    self::assertAbstractionType($logEntry['args'][2], 'object');
                    self::assertAbstractionType($logEntry['args'][3], 'resource');
                },
                'chromeLogger' => \json_encode(array(
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
                'html' => '<li class="m_log"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object"><span class="t_identifier" data-type-more="className"><span class="classname">stdClass</span></span><span class="t_punct">()</span></div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.log("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream");',
                'streamAnsi' => "a string\e[38;5;245m, \e[0m\e[38;5;45marray\e[38;5;245m(\e[0m\e[38;5;245m)\e[0m\e[38;5;245m, \e[0m\e[1mstdClass\e[22m\e[38;5;245m()\e[0m\e[38;5;245m, \e[0mResource id #%d: stream",
                'text' => 'a string, array(), stdClass(), Resource id #%d: stream',
            )
        );
        \fclose($resource);

        $this->testMethod(
            'log',
            array(),
            array(
                'html' => '<li class="m_log"></li>',
            )
        );

        $this->testMethod(
            'log',
            array(
                new Error($this->debug->errorHandler, array(
                    'type' => E_WARNING,
                    'message' => 'this is a warning',
                    'file' => __FILE__,
                    'line' => 42,
                )),
            ),
            array(
                'entry' => function (LogEntry $logEntry) {
                    self::assertSame(array(
                        'method' => 'error',
                        'args' => array(
                            'Warning:',
                            'this is a warning',
                            __FILE__ . ' (line 42)',
                        ),
                        'meta' => array(
                            'channel' => 'general.phpError',
                            // 'context' => null,
                            'detectFiles' => true,
                            'errorCat' => 'warning',
                            'errorHash' => $logEntry->getMeta('errorHash'),
                            'errorType' => 2,
                            // 'evalLine' => null,
                            'file' => __FILE__,
                            'isSuppressed' => false,
                            'line' => 42,
                            'sanitize' => true,
                            // 'trace' => null,
                            'uncollapse' => true,
                        ),
                    ), $this->helper->logEntryToArray($logEntry));
                },
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'log',
            array('log message'),
            array(
                'notLogged' => true,
                'return' => $this->debug,
                'wamp' => false,
            )
        );
    }

    public function testVarDump()
    {
        $stream = \fopen('php://output', 'w');
        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('methodBasic'), 'cliOutputStream', $stream);
        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('methodBasic'), 'hasColorSupport', true);
        $this->testMethod(
            'varDump',
            array(
                'foo',
            ),
            array(
                'output' => \str_replace('\e', "\e", '\e[38;5;250m"\e[0mfoo\e[38;5;250m"\e[0m'),
            )
        );
        $this->testMethod(
            'varDump',
            array(
                'values',
                array(
                    'false' => false,
                    'int' => 42,
                    'null' => null,
                    'true' => true,
                ),
            ),
            array(
                'output' => \str_replace('\e', "\e", '\e[38;5;250m"\e[0mvalues\e[38;5;250m"\e[0m = \e[38;5;45marray\e[38;5;245m(\e[0m
                        \e[38;5;245m[\e[38;5;83mfalse\e[38;5;245m]\e[38;5;224m => \e[0m\e[91mfalse\e[0m
                        \e[38;5;245m[\e[38;5;83mint\e[38;5;245m]\e[38;5;224m => \e[0m\e[96m42\e[0m
                        \e[38;5;245m[\e[38;5;83mnull\e[38;5;245m]\e[38;5;224m => \e[0m\e[38;5;250mnull\e[0m
                        \e[38;5;245m[\e[38;5;83mtrue\e[38;5;245m]\e[38;5;224m => \e[0m\e[32mtrue\e[0m
                    \e[38;5;245m)\e[0m
                '),
            )
        );

        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('methodBasic'), 'isCli', false);
        $this->testMethod(
            'varDump',
            array(
                'foo',
            ),
            array(
                'output' => '<pre style="margin:.25em;">&quot;foo&quot;</pre>',
            )
        );
        $this->testMethod(
            'varDump',
            array(
                'val1',
                'val2',
                'val3',
            ),
            array(
                'output' => '<pre style="margin:.25em;">&quot;val1&quot;, &quot;val2&quot;, &quot;val3&quot;</pre>',
            )
        );
        $this->testMethod(
            'varDump',
            array(
                'values',
                array(
                    'false' => false,
                    'int' => 42,
                    'null' => null,
                    'true' => true,
                ),
            ),
            array(
                'output' => '<pre style="margin:.25em;">&quot;values&quot; = array(
                    [false] =&gt; false
                    [int] =&gt; 42
                    [null] =&gt; null
                    [true] =&gt; true
                )</pre>',
            )
        );
        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('methodBasic'), 'isCli', true);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testWarn()
    {
        $resource = \fopen(__FILE__, 'r');
        $this->testMethod(
            'warn',
            array('a string', array(), new \stdClass(), $resource),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    self::assertSame('warn', $logEntry['method']);
                    self::assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // self::assertAbstractionType($logEntry[2], 'array');
                    self::assertAbstractionType($logEntry['args'][2], 'object');
                    self::assertAbstractionType($logEntry['args'][3], 'resource');

                    self::assertArrayHasKey('file', $logEntry['meta']);
                    self::assertArrayHasKey('line', $logEntry['meta']);
                },
                'chromeLogger' => \json_encode(array(
                    array(
                        'a string',
                        array(),
                        array(
                            '___class_name' => 'stdClass',
                        ),
                        'Resource id #%d: stream',
                    ),
                    \realpath(__DIR__ . '/../../DebugTestFramework.php') . ': %d',
                    'warn',
                )),
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"File":"' . \realpath(__DIR__ . '/../../DebugTestFramework.php') . '","Label":"a string","Line":%d,"Type":"WARN"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_warn" data-detect-files="true" data-file="' . \realpath(__DIR__ . '/../../DebugTestFramework.php') . '" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object"><span class="t_identifier" data-type-more="className"><span class="classname">stdClass</span></span><span class="t_punct">()</span></div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.warn("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream","' . \realpath(__DIR__ . '/../../DebugTestFramework.php') . ': line %d");',
                'text' => '⚠ a string, array(), stdClass(), Resource id #%d: stream',
                // 'wamp' => @todo
            )
        );
        \fclose($resource);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'warn',
            array('warn message'),
            array(
                'notLogged' => true,
                'return' => $this->debug,
                'wamp' => false,
            )
        );
    }
}
