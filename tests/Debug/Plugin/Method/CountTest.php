<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug::count() method
 *
 * @covers \bdk\Debug\Plugin\Method\Count
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class CountTest extends DebugTestFramework
{
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
            Debug::count();                   // 1,2,3 (2,5,8)
        }
        $this->debug->log(
            'count_inc test',
            $this->debug->count(
                'count_inc test',
                Debug::COUNT_NO_INC | Debug::COUNT_NO_OUT // only return
            )
        );
        $this->debug->count('count_inc test', Debug::COUNT_NO_INC);  // (9) //  doesn't increment

        self::assertSame(array(
            array('count', array('count test',1), array()),
            array('count', array('count test',2), array()),
            array('count', array('count',1), array(
                'evalLine' => null,
                'file' => __FILE__,
                'line' => $lines[1],
            )),
            array('count', array('count',1), array(
                'evalLine' => null,
                'file' => __FILE__,
                'line' => $lines[0],
            )),
            array('count', array('count test', 3), array()),
            array('count', array('count',2), array(
                'evalLine' => null,
                'file' => __FILE__,
                'line' => $lines[1],
            )),
            array('count', array('count',2), array(
                'evalLine' => null,
                'file' => __FILE__,
                'line' => $lines[0],
            )),
            array('count', array('count test', 4), array()),
            array('count', array('count',3), array(
                'evalLine' => null,
                'file' => __FILE__,
                'line' => $lines[1],
            )),
            array('log', array('count_inc test', 3), array()),
            array('count', array('count_inc test',3), array()),
        ), $this->helper->deObjectifyData($this->debug->data->get('log'), false));

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
                        'evalLine' => null,
                        'file' => __FILE__,
                        'line' => $lines[1],
                    ),
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
                    // self::assertSame(5, $this->debug->data->get('counts/count test'));
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
                'html' => PHP_VERSION_ID >= 80100
                    ? '<li class="m_countReset"><span class="no-quotes t_string">Counter &#039;noExisty&#039; doesn&#039;t exist.</span></li>'
                    : '<li class="m_countReset"><span class="no-quotes t_string">Counter \'noExisty\' doesn\'t exist.</span></li>',
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
            array(Debug::COUNT_NO_OUT),
            array(
                'notLogged' => true,
                'wamp' => false,
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
}
