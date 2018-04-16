<?php

/**
 * PHPUnit tests for Debug class
 */
class MethodTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testAlert()
    {
        $message = 'Ballistic missle threat inbound to Hawaii.  Seek immediate shelter.  This is not a drill.';
        $this->methodTest(
            'alert',
            array($message),
            array(
                'html' => '<div class="m_alert"><span class="t_string no-pseudo">'.$message.'</span></div>',
                'text' => '[Alert ⦻ danger] '.$message,
                'script' => 'console.alert("'.$message.'");',
                'firephp' => 'X-Wf-1-1-1-1: 108|[{"Type":"LOG"},"'.$message.'"]|',
            )
        );

        $this->debug->setCfg('collect', false);
        $this->methodTest(
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
        $this->methodTest(
            'assert',
            array(false, 'this is false'),
            array(
                'html' => '<div class="m_assert"><span class="t_string no-pseudo">this is false</span></div>',
                'text' => '≠ this is false',
                'script' => 'console.assert(false,"this is false");',
                'firephp' => 'X-Wf-1-1-1-2: 32|[{"Type":"LOG"},"this is false"]|',
            )
        );

        $this->methodTest(
            'assert',
            array(true, 'this is true... not logged'),
            false
        );

        $this->debug->setCfg('collect', false);
        $this->methodTest(
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
    public function testCount()
    {
        $this->debug->count('count test');			// 1
        for ($i=0; $i<3; $i++) {
            $this->debug->count();
            $this->debug->count('count test');		// 2,3,4
            \bdk\Debug::_count();
        }
        $log = $this->debug->getData('log');
        $this->assertSame(array(
            array('count', array('count', 3), array()),
            array('count', array('count test', 4), array()),
            array('count', array('count', 3), array()),
        ), array_slice($log, -3));

        /*
            Count should be maintained even though collect is off
        */
        $countBefore = count($log);
        $this->debug->setCfg('collect', false);
        $ret = $this->debug->count('count test');   // 5
        $this->assertSame(5, $ret, 'count() return incorrect');
        $log = $this->debug->getData('log');
        $this->assertCount($countBefore, $log, 'Count() logged although collect=false');
    }

    /**
     * Test
     *
     * @return void
     */
    public function testError()
    {
        $resource = fopen(__FILE__, 'r');
        $this->methodTest(
        	'error',
        	array('a string', array(), new stdClass(), $resource),
        	array(
        		'custom' => function ($entry) {
        			$this->assertSame('error', $entry[0]);
        			$this->assertSame('a string', $entry[1][0]);
        			$this->assertSame(array(), $entry[1][1]);
        			$this->assertTrue($this->checkAbstractionType($entry[1][2], 'object'));
        			$this->assertTrue($this->checkAbstractionType($entry[1][3], 'resource'));
        		},
        		'html' => '<div class="m_error" title="%s: line %d"><span class="t_string no-pseudo">a string</span>, <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>, <span class="t_object" data-accessible="public"><span class="t_classname">stdClass</span>
					<dl class="object-inner">
					<dt class="properties">no properties</dt>
					<dt class="methods">no methods</dt>
					</dl>
					</span>, <span class="t_resource">Resource id #%d: stream</span></div>',
        		'text' => '⦻ a string, array(), (object) stdClass
					Properties: none!
					Methods: none!, Resource id #%i: stream',
        		'script' => 'console.error("a string",[],{"___class_name":"stdClass"},"Resource id #%i: stream","%s: line %d");',
        		'firephp' => 'X-Wf-1-1-1-3: 220|[{"Type":"ERROR","File":"%s","Line":%d,"Label":"a string"},[[],{"___class_name":"stdClass"},"Resource id #%d: stream"]]|',
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
        $this->methodTest(
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

        $this->methodTest(
        	'group',
        	array('a','b','c'),
        	array(
        		'entry' => array('group',array('a','b','c'), array()),
        		'custom' => function () {
			        $this->assertSame(array(1,1), $this->debug->getData('groupDepth'));
    			},
        		'html' => '<div class="group-header expanded"><span class="group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label">)</span></div>
					<div class="m_group">',
        		'text' => '▸ a, "b", "c"',
        		'script' => 'console.group("a","b","c");',
        		'firephp' => 'X-Wf-1-1-1-4: 61|[{"Type":"GROUP_START","Collapsed":"false","Label":"a"},null]|',
        	)
        );

        $this->debug->setData('log', array());
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->outputTest(array(
        	'html' => '<div class="debug-content m_group">
				</div>',
        ));

        /*
            Test default label
        */
        $this->methodTest(
        	'group',
        	array(),
        	array(
        		'entry' => array(
        			'group',
        			array(__CLASS__.'->methodTest'),
        			array('isMethodName' => true),
        		),
        		'html' => '<div class="group-header expanded"><span class="group-label"><span class="t_classname">'.__CLASS__.'</span><span class="t_operator">-&gt;</span><span class="method-name">methodTest</span></span></div>
					<div class="m_group">',
        	)
        );

        $this->debug->setData('log', array());
        $testBase->testBasePublic();
        $this->assertSame(array(
            'group',
            array(
                'bdk\DebugTest\TestBase->testBasePublic'
            ),
            array(
                'isMethodName' => true,
            ),
        ), $this->debug->getData('log/0'));

        $this->debug->setData('log', array());
        $test->testBasePublic();
        $this->assertSame(array(
            'group',
            array(
                'bdk\DebugTest\Test->testBasePublic'
            ),
            array(
                'isMethodName' => true,
            ),
        ), $this->debug->getData('log/0'));

        // yes, we call Test... but static method is defined in TestBase
        // .... PHP
        $this->debug->setData('log', array());
        \bdk\DebugTest\Test::testBaseStatic();
        $this->assertSame(array(
            'group',
            array(
                'bdk\DebugTest\TestBase::testBaseStatic'
            ),
            array(
                'isMethodName' => true,
            ),
        ), $this->debug->getData('log/0'));

        // even if called with an arrow
        $this->debug->setData('log', array());
        $test->testBaseStatic();
        $this->assertSame(array(
            'group',
            array(
                'bdk\DebugTest\TestBase::testBaseStatic'
            ),
            array(
                'isMethodName' => true,
            ),
        ), $this->debug->getData('log/0'));

        $this->debug->setCfg('collect', false);
        $this->methodTest(
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
        $this->methodTest(
        	'groupCollapsed',
        	array('a', 'b', 'c'),
        	array(
        		'entry' => array('groupCollapsed', array('a','b','c'), array()),
        		'custom' => function () {
			        $this->assertSame(array(1,1), $this->debug->getData('groupDepth'));
        		},
        	)
        );

        // add a nested gorup that will get removed on output
        $this->debug->groupCollapsed($this->debug->meta('hideIfEmpty'));

        $this->outputTest(array(
        	'html' => '<div class="group-header collapsed"><span class="group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="group-label">)</span></div>
				<div class="m_group">
				</div>',
        ));

        $this->debug->setCfg('collect', false);
		$this->methodTest(
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
        $this->assertSame(array(0,0), $this->debug->getData('groupDepth'));
        $log = $this->debug->getData('log');
        $this->assertCount(2, $log);
        $this->assertSame(array(
            array('group',array('a','b','c'),array()),
            array('groupEnd',array(),array()),
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
        $this->debug->groupCollapsed('level1 (test)');
        $this->debug->groupCollapsed('level2');
        $this->debug->log('left collapsed');
        $this->debug->groupEnd('level2');
        $this->debug->groupCollapsed('level2 (test)');
        $this->debug->groupUncollapse();
        $log = $this->debug->getData('log');
        $this->assertSame('group', $log[0][0]);
        $this->assertSame('groupCollapsed', $log[1][0]);
        $this->assertSame('group', $log[4][0]);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testInfo()
    {
        $resource = fopen(__FILE__, 'r');
        $this->debug->info('a string', array(), new stdClass(), $resource);
        fclose($resource);
        $log = $this->debug->getData('log');
        $logEntry = $log[0];
        $this->assertSame('info', $logEntry[0]);
        $this->assertSame('a string', $logEntry[1][0]);
        // check array abstraction
        // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
        $isObject = $this->checkAbstractionType($logEntry[1][2], 'object');
        $isResource = $this->checkAbstractionType($logEntry[1][3], 'resource');
        // $this->assertTrue($isArray);
        $this->assertTrue($isObject);
        $this->assertTrue($isResource);

        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->info('info message');
        $logAfter = $this->debug->getData('log');
        $this->assertCount(count($logBefore), $logAfter, 'Info() logged although collect=false');
    }

    /**
     * Test
     *
     * @return void
     */
    public function testLog()
    {
        $resource = fopen(__FILE__, 'r');
        $this->debug->log('a string', array(), new stdClass(), $resource);
        fclose($resource);
        $log = $this->debug->getData('log');
        $logEntry = $log[0];
        $this->assertSame('log', $logEntry[0]);
        $this->assertSame('a string', $logEntry[1][0]);
        // check array abstraction
        // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
        $isObject = $this->checkAbstractionType($logEntry[1][2], 'object');
        $isResource = $this->checkAbstractionType($logEntry[1][3], 'resource');
        // $this->assertTrue($isArray);
        $this->assertTrue($isObject);
        $this->assertTrue($isResource);

        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->log('log message');
        $logAfter = $this->debug->getData('log');
        $this->assertCount(count($logBefore), $logAfter, 'Log() logged although collect=false');
    }

    /*
        table() method tested in MethodTableTest
    */

    /**
     * Test
     *
     * @return void
     */
    public function testTrace()
    {
        $this->debug->trace();
        $trace = $this->debug->getData('log/0/1/0');
        $this->assertSame(__FILE__, $trace[0]['file']);
        $this->assertSame(__LINE__ - 3, $trace[0]['line']);
        $this->assertNotTrue(isset($trace[0]['function']));
        $this->assertSame(__CLASS__.'->'.__FUNCTION__, $trace[1]['function']);

        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->trace();
        $logAfter = $this->debug->getData('log');
        $this->assertCount(count($logBefore), $logAfter, 'Trace() logged although collect=false');
    }

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
        $this->debug->timeEnd();            // appends log
        // test stack is now empty
        $this->assertCount(0, $this->debug->getData('timers/stack'));
        $this->debug->timeEnd('my label');  // appends log
        $ret = $this->debug->timeEnd('my label', true);
        $this->assertStringMatchesFormat('%f', $ret);
        // test last timeEnd didn't append log
        $this->assertCount(2, $this->debug->getData('log'));
        $timers = $this->debug->getData('timers');
        $this->assertInternalType('float', $timers['labels']['my label'][0]);
        $this->assertNull($timers['labels']['my label'][1]);
        $this->debug->timeEnd('my label', 'blah%labelblah%timeblah');
        $this->assertStringMatchesFormat('blahmy labelblah%fblah', $this->debug->getData('log/2/1/0'));

        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->timeEnd('my label');
        $logAfter = $this->debug->getData('log');
        $this->assertCount(count($logBefore), $logAfter, 'TimeEnd() logged although collect=false');
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
        $this->debug->timeGet();            // appends log
        // test stack is still 1
        $this->assertCount(1, $this->debug->getData('timers/stack'));
        $this->debug->timeGet('my label');  // appends log
        $ret = $this->debug->timeGet('my label', true);
        // $this->assertInternalType('float', $ret);
        $this->assertStringMatchesFormat('%f', $ret);
        // test last timeEnd didn't append log
        $this->assertCount(2, $this->debug->getData('log'));
        $timers = $this->debug->getData('timers');
        $this->assertSame(0, $timers['labels']['my label'][0]);
        // test not paused
        $this->assertNotNull($timers['labels']['my label'][1]);
        $this->debug->timeGet('my label', 'blah%labelblah%timeblah');
        $this->assertStringMatchesFormat('blahmy labelblah%fblah', $this->debug->getData('log/2/1/0'));

        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->timeGet('my label');
        $logAfter = $this->debug->getData('log');
        $this->assertCount(count($logBefore), $logAfter, 'TimeGet() logged although collect=false');
    }

    /**
     * Test
     *
     * @return void
     */
    public function testWarn()
    {
        $resource = fopen(__FILE__, 'r');
        $this->debug->warn('a string', array(), new stdClass(), $resource);
        fclose($resource);
        $log = $this->debug->getData('log');
        $logEntry = $log[0];
        $this->assertSame('warn', $logEntry[0]);
        $this->assertSame('a string', $logEntry[1][0]);
        // check array abstraction
        // $isArray = $this->checkAbstractionType($logEntry[2], 'array');
        $isObject = $this->checkAbstractionType($logEntry[1][2], 'object');
        $isResource = $this->checkAbstractionType($logEntry[1][3], 'resource');
        // $this->assertTrue($isArray);
        $this->assertTrue($isObject);
        $this->assertTrue($isResource);

        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->warn('warn message');
        $logAfter = $this->debug->getData('log');
        $this->assertCount(count($logBefore), $logAfter, 'Warn() logged although collect=false');
    }
}
