<?php

namespace bdk\Test\Debug\Method;

use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug::time() methods
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\Method\Time
 */
class TimeTest extends DebugTestFramework
{
    /**
     * Test
     *
     * @return void
     */
    public function testTime()
    {
        $this->debug->time();
        $this->debug->time('some label');

        $timers = $this->helper->getPrivateProp($this->debug->stopWatch, 'timers');
        $this->assertIsFloat($timers['stack'][0]);
        $this->assertIsFloat($timers['labels']['some label'][1]);

        $this->assertEmpty($this->debug->data->get('log'));
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
                    $timers = $this->helper->getPrivateProp($this->debug->stopWatch, 'timers');
                    $this->assertCount(0, $timers['stack']);
                },
                'entry' => \json_encode(array(
                    'method' => 'time',
                    'args' => array(
                        'time: %f %s',
                    ),
                    'meta' => array(
                        'return' => false,
                    ),
                )),
                'chromeLogger' => \json_encode(array(
                    array(
                        'time: %f %s',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"time: %f %s"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">time: %f %s</span></li>',
                'script' => 'console.log("time: %f %s");',
                'text' => '⏱ time: %f %s',
                // 'wamp' => @todo
            )
        );
        $this->testMethod(
            'timeEnd',
            array(
                'my label',
                false, // don't log
                // use default 'auto' value for 3rd param
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
                    'meta' => array(
                        'return' => false,
                    ),
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
                    $logEntry = $this->helper->logEntryToArray($logEntry);
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
                    'meta' => array(
                        'return' => false,
                    ),
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

        $timers = $this->helper->getPrivateProp($this->debug->stopWatch, 'timers');
        $this->assertIsFloat($timers['labels']['my label'][0]);
        $this->assertNull($timers['labels']['my label'][1]);

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'timeEnd',
            array('my label'),
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
                    $timers = $this->helper->getPrivateProp($this->debug->stopWatch, 'timers');
                    $this->assertCount(1, $timers['stack']);
                },
                /*
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->helper->logEntryToArray($logEntry);
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
                    'args' => array('time: %f %s'),
                    'meta' => array(
                        'return' => false,
                    ),
                )),
                'chromeLogger' => \json_encode(array(
                    array(
                        'time: %f %s',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"time: %f %s"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">time: %f %s</span></li>',
                'script' => 'console.log("time: %f %s");',
                'text' => '⏱ time: %f %s',
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
                        'my label: %f %s',
                    ),
                    'meta' => array(
                        'return' => false,
                    ),
                )),
                'chromeLogger' => \json_encode(array(
                    array(
                        'my label: %f %s',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-20: %d|[{"Type":"LOG"},"my label: %f %s"]|',
                'html' => '<li class="m_time"><span class="no-quotes t_string">my label: %f %s</span></li>',
                'script' => 'console.log("my label: %f %s");',
                'text' => '⏱ my label: %f %s',
                // 'wamp' => @todo
            )
        );

        $this->testMethod(
            'timeGet',
            array(
                'my label',
                false, // don't log
                true,  // return value
            ),
            array(
                'notLogged' => true,  // not logged because 2nd param = true
                'return' => '%f',
                'wamp' => false,
            )
        );

        $timers = $this->helper->getPrivateProp($this->debug->stopWatch, 'timers');
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
                    'meta' => array(
                        'return' => false,
                    ),
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
                // 'return' => '@todo',
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
                    $logEntry = $this->helper->logEntryToArray($logEntry);
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
                    'args' => array(
                        'time: ',
                        '%f %s',
                    ),
                    'meta' => array(),
                )),
                'chromeLogger' => \json_encode(array(
                    array(
                        'time: ',
                        '%f %s',
                    ),
                    null,
                    '',
                )),
                'firephp' => 'X-Wf-1-1-1-166: %d|[{"Label":"time: ","Type":"LOG"},"%f %s"]|',
                'html' => '<li class="m_timeLog"><span class="no-quotes t_string">time: </span><span class="t_string">%f %s</span></li>',
                'script' => 'console.log("time: ","%f %s");',
                'text' => '⏱ time: "%f %s"',
                // 'wamp' => @todo
            )
        );

        $this->testMethod(
            'timeLog',
            array('my label', array('foo' => 'bar')),
            array(
                /*
                'entry' => function (LogEntry $logEntry) {
                    $logEntry = $this->helper->logEntryToArray($logEntry);
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
                    'args' => array(
                        'my label: ',
                        '%f %ss',
                        array('foo' => 'bar'),
                    ),
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
                    $logEntry = $this->helper->logEntryToArray($logEntry);
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
                'html' => PHP_VERSION_ID >= 80100
                    ? '<li class="m_timeLog"><span class="no-quotes t_string">Timer &#039;bogus&#039; does not exist</span></li>'
                    : '<li class="m_timeLog"><span class="no-quotes t_string">Timer \'bogus\' does not exist</span></li>',
                'script' => 'console.log("Timer \'bogus\' does not exist");',
                'text' => '⏱ Timer \'bogus\' does not exist',
                // 'wamp' => @todo
            )
        );
    }
}
