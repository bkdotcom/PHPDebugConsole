<?php

namespace bdk\DebugTests\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\DebugTests\DebugTestFramework;

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
                if ($route instanceof \bdk\Debug\Route\ChromeLogger) {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($route instanceof \bdk\Debug\Route\Firephp) {
                    $event['method'] = 'log';
                    $event['args'] = array('this was a trace');
                } elseif ($route instanceof \bdk\Debug\Route\Html) {
                    $event['return'] = '<li class="m_trace">this was a trace</li>';
                } elseif ($route instanceof \bdk\Debug\Route\Script) {
                    $event['return'] = 'console.log("this was a trace");';
                } elseif ($route instanceof \bdk\Debug\Route\Text) {
                    $event['return'] = 'this was a trace';
                } elseif ($route instanceof \bdk\Debug\Route\Wamp) {
                    $event['method'] = 'log';
                    $event['args'] = array('something completely different');
                    $meta = \array_diff_key($event['meta'], \array_flip(array('caption','inclContext','requestId','sortable','tableInfo')));
                    $event['meta'] = $meta;
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
                        'inclArgs' => true,
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
                        'inclArgs' => true,
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
        $messageEscaped = \htmlspecialchars($message);
        $entry = array(
            'method' => 'alert',
            'args' => array($message),
            'meta' => array(
                'dismissible' => false,
                'level' => 'error',
            ),
        );
        $this->testMethod(
            'alert',
            array(
                $message,
                // level error by default
            ),
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
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"ERROR"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-error m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.log(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #ffbaba;border: 1px solid #d8000c;color: #d8000c;");'),
                'text' => '》[Alert ⦻ error] ' . $message . '《',
                'wamp' => $entry,
            )
        );

        $entry['meta']['level'] = 'info';
        $style = 'padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #d9edf7;border: 1px solid #bce8f1;color: #31708f;';
        $this->testMethod(
            'alert',
            array(
                $message,
                $this->debug->meta('level', 'info'),
            ),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    'info',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"INFO"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-info m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.info(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ℹ info] ' . $message . '《',
                'wamp' => $entry,
            )
        );

        $entry['meta']['level'] = 'success';
        $style = 'padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #dff0d8;border: 1px solid #d6e9c6;color: #3c763d;';
        $this->testMethod(
            'alert',
            array(
                $message,
                $this->debug->meta('level', 'success'),
            ),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    'info',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"INFO"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-success m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.info(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ℹ success] ' . $message . '《',
                'wamp' => $entry,
            )
        );

        $entry['meta']['level'] = 'warn';
        $style = 'padding:5px; line-height:26px; font-size:125%; font-weight:bold;background-color: #fcf8e3;border: 1px solid #faebcc;color: #8a6d3b;';
        $this->testMethod(
            'alert',
            array(
                $message,
                $this->debug->meta('level', 'warn'),
            ),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        '%c' . $message,
                        $style,
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"WARN"},' . \json_encode($message, JSON_UNESCAPED_SLASHES) . ']|',
                'html' => '<div class="alert-warn m_alert" role="alert">' . $messageEscaped . '</div>',
                'script' => \str_replace('%c', '%%c', 'console.log(' . \json_encode('%c' . $message, JSON_UNESCAPED_SLASHES) . ',"' . $style . '");'),
                'text' => '》[Alert ⚠ warn] ' . $message . '《',
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
            false
        );
    }

    /*
        clear() method tested in ClearTest
    */

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
        ), \array_map(function ($logEntry) {
            return $this->logEntryToArray($logEntry, false);
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
                    'method' => 'countReset',
                    'args' => array('foo', 0),
                    'meta' => array(),
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
                    'method' => 'countReset',
                    'args' => array('Counter \'noExisty\' doesn\'t exist.'),
                    'meta' => array(),
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
        $resource = \fopen(__FILE__, 'r');
        $this->testMethod(
            'error',
            array('a string', array(), new \stdClass(), $resource),
            array(
                'entry' => function (LogEntry $logEntry) {
                    $this->assertSame('error', $logEntry['method']);
                    $this->assertSame('a string', $logEntry['args'][0]);
                    $this->assertSame(array(), $logEntry['args'][1]);
                    $this->assertTrue($this->checkAbstractionType($logEntry['args'][2], 'object'));
                    $this->assertTrue($this->checkAbstractionType($logEntry['args'][3], 'resource'));
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
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry['args'][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry['args'][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);
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
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry['args'][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry['args'][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);
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
        table() method tested in TableTest
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
        $this->assertIsFloat($timers['stack'][0]);
        $this->assertIsFloat($timers['labels']['some label'][1]);

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
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array(
                        'time: %f μs',
                    ),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array(
                        'my label: %f %ss',
                    ),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'time',
                        array("blahmy labelblah%f msblah"),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry), 'chromeLogger not same');
                },
                */
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array('blahmy labelblah%f %ssblah'),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
        $this->assertIsFloat($timers['labels']['my label'][0]);
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
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'time',
                        array('time: %f μs'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array('time: %f μs'),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array(
                        'my label: %f %ss',
                    ),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array('blahmy labelblah%f %ssblah'),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('time: ', '%f μs'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => \json_encode(array(
                    'method' => 'timeLog',
                    'args' => array('time: ', '%f μs'),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('my label: ', '%f %ss', array('foo'=>'bar')),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => \json_encode(array(
                    'method' => 'timeLog',
                    'args' => array('my label: ', '%f %ss', array('foo' => 'bar')),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->logEntryToArray($logEntry);
                    $expectFormat = json_encode(array(
                        'timeLog',
                        array('Timer \'bogus\' does not exist'),
                        array(),
                    ));
                    $this->assertStringMatchesFormat($expectFormat, json_encode($logEntry));
                },
                */
                'entry' => \json_encode(array(
                    'method' => 'timeLog',
                    'args' => array('Timer \'bogus\' does not exist'),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
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
                    // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
                    $isObject = $this->checkAbstractionType($logEntry['args'][2], 'object');
                    $isResource = $this->checkAbstractionType($logEntry['args'][3], 'resource');
                    // $this->assertTrue($isArray);
                    $this->assertTrue($isObject);
                    $this->assertTrue($isResource);

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
                'wamp' => false,
            )
        );
    }
}
