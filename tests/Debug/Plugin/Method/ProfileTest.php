<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug::profile() method
 *
 * @covers \bdk\Debug\Dump\Html\Table
 * @covers \bdk\Debug\Plugin\Method\Profile
 * @covers \bdk\Debug\Utility\Profile
 */
class ProfileTest extends DebugTestFramework
{
    public function testProfile()
    {
        $this->debug->profile();
        $this->a();
        $this->debug->profileEnd();

        $this->testMethod(
            null,
            array(),
            array(
                'custom' => static function (LogEntry $logEntry) {
                    self::assertSame('profileEnd', $logEntry['method']);
                    $data = $logEntry['args'][0];
                    $a = $data['bdk\Test\Debug\Plugin\Method\ProfileTest::a'];
                    $b = $data['bdk\Test\Debug\Plugin\Method\ProfileTest::b'];
                    $c = $data['bdk\Test\Debug\Plugin\Method\ProfileTest::c'];
                    // test a
                    self::assertCount(3, $data);
                    self::assertSame(1, $a['calls']);
                    self::assertGreaterThanOrEqual(0.25 + 0.75 * 2, $a['totalTime']);
                    self::assertLessThan(0.01, $a['ownTime']);
                    // test b
                    self::assertSame(1, $b['calls']);
                    self::assertGreaterThanOrEqual(0.25 + 0.75 * 2, $b['totalTime']);
                    self::assertLessThan(0.25 + 0.01, $b['ownTime']);
                    // test c
                    self::assertSame(2, $c['calls']);
                    self::assertGreaterThanOrEqual(0.75 * 2, $c['totalTime']);
                    self::assertLessThan(0.75 * 2 + 0.01, $c['ownTime']);
                    \ksort($logEntry['meta']);
                    self::assertEquals(array(
                        'caption' => "Profile 'Profile 1' Results",
                        'name' => 'Profile 1',
                        'sortable' => true,
                        'tableInfo' => array(
                            'class' => null,
                            'columns' => array(
                                array(
                                    'key' => 'calls',
                                ),
                                array(
                                    'key' => 'totalTime',
                                ),
                                array(
                                    'total' => $a['ownTime'] + $b['ownTime'] + $c['ownTime'],
                                    'key' => 'ownTime',
                                ),
                            ),
                            'haveObjRow' => false,
                            'indexLabel' => null,
                            'rows' => array(
                                'bdk\Test\Debug\Plugin\Method\ProfileTest::a' => array(
                                    'key' => new Abstraction(Abstracter::TYPE_CALLABLE, array(
                                        'hideType' => true, // don't output 'callable'
                                        'value' => 'bdk\Test\Debug\Plugin\Method\ProfileTest::a',
                                    )),
                                ),
                                'bdk\Test\Debug\Plugin\Method\ProfileTest::b' => array(
                                    'key' => new Abstraction(Abstracter::TYPE_CALLABLE, array(
                                        'hideType' => true, // don't output 'callable'
                                        'value' => 'bdk\Test\Debug\Plugin\Method\ProfileTest::b',
                                    )),
                                ),
                                'bdk\Test\Debug\Plugin\Method\ProfileTest::c' => array(
                                    'key' => new Abstraction(Abstracter::TYPE_CALLABLE, array(
                                        'hideType' => true, // don't output 'callable'
                                        'value' => 'bdk\Test\Debug\Plugin\Method\ProfileTest::c',
                                    )),
                                ),
                            ),
                            'summary' => null,
                        ),
                    ), $logEntry['meta']);
                },
                'chromeLogger' => '[[{"bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::a":{"calls":1,"totalTime":%f,"ownTime":%f},"bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::b":{"calls":1,"totalTime":%f,"ownTime":%f},"bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::c":{"calls":2,"totalTime":%f,"ownTime":%f}}],null,"table"]',
                'firephp' => 'X-Wf-1-1-1-2: %d|[{"Label":"Profile \'Profile 1\' Results","Type":"TABLE"},[["","calls","totalTime","ownTime"],["bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::a",1,%f,%f],["bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::b",1,%f,%f],["bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::c",2,%f,%f]]]|',
                'html' => '<li class="m_profileEnd">
                    <table class="sortable table-bordered">
                    <caption>Profile ' . (PHP_VERSION_ID >= 80100 ? '&#039;Profile 1&#039;' : '\'Profile 1\'') . ' Results</caption>
                    <thead>
                        <tr><th>&nbsp;</th><th scope="col">calls</th><th scope="col">totalTime</th><th scope="col">ownTime</th></tr>
                    </thead>
                    <tbody>
                        <tr><th class="t_callable t_key text-right" scope="row"><span class="classname"><span class="namespace">bdk\Test\Debug\Plugin\Method\</span>ProfileTest</span><span class="t_operator">::</span><span class="t_identifier">a</span></th><td class="t_int">1</td><td class="t_float">%f</td><td class="t_float">%f</td></tr>
                        <tr><th class="t_callable t_key text-right" scope="row"><span class="classname"><span class="namespace">bdk\Test\Debug\Plugin\Method\</span>ProfileTest</span><span class="t_operator">::</span><span class="t_identifier">b</span></th><td class="t_int">1</td><td class="t_float">%f</td><td class="t_float">%f</td></tr>
                        <tr><th class="t_callable t_key text-right" scope="row"><span class="classname"><span class="namespace">bdk\Test\Debug\Plugin\Method\</span>ProfileTest</span><span class="t_operator">::</span><span class="t_identifier">c</span></th><td class="t_int">2</td><td class="t_float">%f</td><td class="t_float">%f</td></tr>
                    </tbody>
                    <tfoot>
                        <tr><td>&nbsp;</td><td></td><td></td><td class="t_float">%f</td></tr>
                    </tfoot>
                    </table>
                    </li>',
                'script' => 'console.table({"bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::a":{"calls":1,"totalTime":%f,"ownTime":%f},"bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::b":{"calls":1,"totalTime":%f,"ownTime":%f},"bdk\\\Test\\\Debug\\\Plugin\\\Method\\\ProfileTest::c":{"calls":2,"totalTime":%f,"ownTime":%f}});',
                'text' => 'Profile \'Profile 1\' Results = array(
                    [bdk\Test\Debug\Plugin\Method\ProfileTest::a] => array(
                        [calls] => 1
                        [totalTime] => %f
                        [ownTime] => %f
                    )
                    [bdk\Test\Debug\Plugin\Method\ProfileTest::b] => array(
                        [calls] => 1
                        [totalTime] => %f
                        [ownTime] => %f
                    )
                    [bdk\Test\Debug\Plugin\Method\ProfileTest::c] => array(
                        [calls] => 2
                        [totalTime] => %f
                        [ownTime] => %f
                    )
                )',
            )
        );
    }

    public function testCollectFalse()
    {
        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'profile',
            array(),
            array(
                'notLogged' => true,
                'return' => $this->debug,
            )
        );
    }

    private function a()
    {
        $this->b();
    }

    private function b()
    {
        $this->c();
        \usleep(1000000 * .25);
        $this->c();
    }

    private function c()
    {
        \usleep(1000000 * .75);
    }

    private function d()
    {
        \usleep(1000000 * .33);
    }
}
