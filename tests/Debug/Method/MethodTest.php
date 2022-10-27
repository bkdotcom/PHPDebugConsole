<?php

namespace bdk\Test\Debug\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler\Error;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug Methods
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\AbstractDebug
 * @covers \bdk\Debug\Abstraction\AbstractObjectProperties
 * @covers \bdk\Debug\AbstractComponent
 * @covers \bdk\Debug\LogEntry
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\BaseValue
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\TextAnsi
 * @covers \bdk\Debug\Dump\TextAnsiValue
 * @covers \bdk\Debug\Dump\TextValue
 * @covers \bdk\Debug\ServiceProvider
 * @covers \bdk\Debug\Method\Helper
 * @covers \bdk\Debug\Route\AbstractRoute
 * @covers \bdk\Debug\Route\ChromeLogger
 * @covers \bdk\Debug\Route\Firephp
 * @covers \bdk\Debug\Route\ServerLog
 * @covers \bdk\Debug\Route\Script
 * @covers \bdk\Debug\Route\Stream
 * @covers \bdk\Debug\Route\Text
 * @covers \bdk\Debug\Route\WampCrate
 */
class MethodTest extends DebugTestFramework
{
    /**
     * Test overriding a core method
     */
    public function testOverrideOutput()
    {
        $closure = function (LogEntry $logEntry) {
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
                    $meta = \array_diff_key($logEntry['meta'], \array_flip(array('caption','inclContext','requestId','sortable','tableInfo')));
                    $logEntry['meta'] = $meta;
                }
            }
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, $closure);
        $this->testMethod(
            'trace',
            array(),
            array(
                'entry' => function (LogEntry $logEntry) {
                    // we're doing the custom stuff via Debug::EVENT_OUTPUT_LOG_ENTRY, so logEntry should still be trace
                    $this->assertSame('trace', $logEntry['method']);
                    $this->assertIsArray($logEntry['args'][0]);
                    $this->assertSame(array(
                        'caption' => 'trace',
                        'detectFiles' => true,
                        'inclArgs' => false,
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
                            'summary' => null,
                        ),
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
                        'inclArgs' => false,
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
        $closure = function (LogEntry $logEntry) {
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
        Debug::_myCustom('called statically');
        $entry = array(
            'method' => 'myCustom',
            'args' => array('called statically'),
            'meta' => array(
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

    public function testMethodSetsCfg()
    {
        $this->debug->log(new \bdk\Test\Debug\Fixture\Test(), $this->debug->meta('cfg', 'methodCollect', false));
        $methodCollect = $this->debug->data->get('log/__end__/args/0/cfgFlags') & \bdk\Debug\Abstraction\AbstractObject::METHOD_COLLECT;
        $this->assertSame(0, $methodCollect);
        $this->assertCount(3, $this->debug->data->get('log/__end__/args/0/methods'));
        $this->assertTrue($this->debug->getCfg('methodCollect'));
    }

    public function testMethodDefaultArgNotOptional()
    {
        // covers AbstractDebug::getMethodDefaultArgs
        $refProp = new \ReflectionProperty('bdk\Debug\AbstractDebug', 'methodDefaultArgs');
        $refProp->setAccessible(true);
        $refProp->setValue(array());
        $this->debug->alert('test');
        $this->assertSame('alert', $this->debug->data->get('alerts/__end__/method'));
    }

    /*
        alert tested in AlertTest.php
    */

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
            array(true, 'this is true... not logged'),
            false
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

    /*
        clear() method tested in ClearTest
    */

    /*
        count() method tested in CountTest
    */

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
                'entry' => function (LogEntry $logEntry) {
                    $this->assertSame('error', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    $this->assertSame(array(), $logEntry['args'][1]);
                    $this->assertAbstractionType($logEntry['args'][2], 'object');
                    $this->assertAbstractionType($logEntry['args'][3], 'resource');
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
                    \realpath(__DIR__ . '/../DebugTestFramework.php') . ': %d',
                    'error',
                )),
                'firephp' => 'X-Wf-1-1-1-3: %d|[{"File":"%s","Label":"a string","Line":%d,"Type":"ERROR"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_error" data-detect-files="true" data-file="' . \realpath(__DIR__ . '/../DebugTestFramework.php') . '" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    ' . (PHP_VERSION_ID >= 80200
                        ? '<dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname">AllowDynamicProperties</span></dd>'
                        : ''
                    ) . '
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.error("a string",[],{"___class_name":"stdClass"},"Resource id #%i: stream","%s: line %d");',
                'text' => '⦻ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%i: stream',
                'streamAnsi' => "\e[38;5;9m⦻ a string\e[38;5;245m, \e[38;5;9m\e[38;5;45marray\e[38;5;245m(\e[38;5;9m\e[38;5;245m)\e[38;5;9m\e[38;5;245m, \e[38;5;9m\e[1mstdClass\e[22m\e[0m
                    \e[38;5;9m  Properties: none!\e[0m
                    \e[38;5;9m  Methods: none!\e[38;5;245m, \e[38;5;9mResource id #%d: stream\e[0m",
            )
        );
        \fclose($resource);

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
                'return' => $this->debug,
                'wamp' => false,
            )
        );
    }

    /*
        group() methods tested in GroupTest
    */

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
                    $this->assertSame('info', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // $this->assertAbstractionType($logEntry[2], 'array');
                    $this->assertAbstractionType($logEntry['args'][2], 'object');
                    $this->assertAbstractionType($logEntry['args'][3], 'resource');
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
                'html' => '<li class="m_info"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    ' . (PHP_VERSION_ID >= 80200
                        ? '<dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname">AllowDynamicProperties</span></dd>'
                        : ''
                    ) . '
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
                'entry' => function (LogEntry $logEntry) {
                    $this->assertSame('log', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // $this->assertAbstractionType($logEntry[2], 'array');
                    $this->assertAbstractionType($logEntry['args'][2], 'object');
                    $this->assertAbstractionType($logEntry['args'][3], 'resource');
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
                'html' => '<li class="m_log"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    ' . (PHP_VERSION_ID >= 80200
                        ? '<dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname">AllowDynamicProperties</span></dd>'
                        : ''
                    ) . '
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
                    $this->assertSame(array(
                        'method' => 'error',
                        'args' => array(
                            'Warning:',
                            'this is a warning',
                            __FILE__ . ' (line 42)',
                        ),
                        'meta' => array(
                            'channel' => 'general.phpError',
                            'context' => null,
                            'detectFiles' => true,
                            'errorCat' => 'warning',
                            'errorHash' => $logEntry->getMeta('errorHash'),
                            'errorType' => 2,
                            'file' => __FILE__,
                            'isSuppressed' => false,
                            'line' => 42,
                            'sanitize' => true,
                            'trace' => null,
                            'uncollapse' => true,
                        ),
                    ), $this->helper->logEntryToArray($logEntry));
                }
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

    public function testMeta()
    {
        // test invalid args
        $this->assertSame(array(
            'debug' => Debug::META,
        ), $this->debug->meta(false));
    }

    /*
        table() method tested in TableTest
    */

    /*
        time(), timeEnd, timeGet, timeLog
    */

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
                'entry' => function (LogEntry $logEntry) {
                    $this->assertSame('warn', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    // check array abstraction
                    // $this->assertAbstractionType($logEntry[2], 'array');
                    $this->assertAbstractionType($logEntry['args'][2], 'object');
                    $this->assertAbstractionType($logEntry['args'][3], 'resource');

                    $this->assertArrayHasKey('file', $logEntry['meta']);
                    $this->assertArrayHasKey('line', $logEntry['meta']);
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
                    \realpath(__DIR__ . '/../DebugTestFramework.php') . ': %d',
                    'warn',
                )),
                'firephp' => 'X-Wf-1-1-1-5: %d|[{"File":"' . \realpath(__DIR__ . '/../DebugTestFramework.php') . '","Label":"a string","Line":%d,"Type":"WARN"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
                'html' => '<li class="m_warn" data-detect-files="true" data-file="' . \realpath(__DIR__ . '/../DebugTestFramework.php') . '" data-line="%d"><span class="no-quotes t_string">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <div class="t_object" data-accessible="public"><span class="classname">stdClass</span>
                    <dl class="object-inner">
                    ' . (PHP_VERSION_ID >= 80200
                        ? '<dt class="attributes">attributes</dt>
                            <dd class="attribute"><span class="classname">AllowDynamicProperties</span></dd>'
                        : ''
                    ) . '
                    <dt class="properties">no properties</dt>
                    <dt class="methods">no methods</dt>
                    </dl>
                    </div>, <span class="t_resource">Resource id #%d: stream</span></li>',
                'script' => 'console.warn("a string",[],{"___class_name":"stdClass"},"Resource id #%d: stream","' . \realpath(__DIR__ . '/../DebugTestFramework.php') . ': line %d");',
                'text' => '⚠ a string, array(), stdClass
                    Properties: none!
                    Methods: none!, Resource id #%d: stream',
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
