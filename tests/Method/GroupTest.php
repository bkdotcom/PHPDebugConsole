<?php

namespace bdk\DebugTests\Method;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\DebugTests\DebugTestFramework;
use bdk\PubSub\Event;

/**
 * PHPUnit tests for Debug Group Methods
 */
class GroupTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testGroup()
    {

        // $test = new \bdk\DebugTests\Fixture\Test();
        // $testBase = new \bdk\DebugTests\Fixture\TestBase();

        $this->testMethod(
            'group',
            array('a','b','c'),
            array(
                'entry' => array(
                    'method' => 'group',
                    'args' => array('a','b','c'),
                    'meta' => array(),
                ),
                'custom' => function () {
                    $this->assertSame(array(
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('a','b','c'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-4: 61|[{"Collapsed":"false","Label":"a","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="font-weight-bold group-label">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("a","b","c");',
                'text' => '▸ a("b", "c")',
                'wamp' => array(
                    'group',
                    array('a','b','c'),
                    array(),
                ),
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'group',
            array('not logged'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    public function testGroupHideIfEmpty()
    {
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            // @todo chromeLogger & firephp
            // 'firephp' => '',
            'html' => '<li class="m_log"><span class="no-quotes t_string">before group</span></li>
                <li class="m_log"><span class="no-quotes t_string">after group</span></li>',
            'script' => 'console.log("before group");
                console.log("after group");',
            'text' => 'before group
                after group',
        ));

        /*
            hideIfEmpty group containing log entry
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->log('something');
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'html' => '<li class="m_log"><span class="no-quotes t_string">before group</span></li>
                <li class="expanded hide-if-empty m_group">
                    <div class="group-header"><span class="font-weight-bold group-label">group</span></div>
                    <ul class="group-body">
                        <li class="m_log"><span class="no-quotes t_string">something</span></li>
                    </ul>
                </li>
                <li class="m_log"><span class="no-quotes t_string">after group</span></li>',
            'script' => 'console.log("before group");
                console.group("group");
                console.log("something");
                console.groupEnd();
                console.log("after group");',
        ));

        /*
            hideIfEmtpy group containing empty group
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->group('inner group empty');
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.group("group");
                console.group("inner group empty");
                console.groupEnd();
                console.groupEnd();
                console.log("after group");',
        ));

        /*
            hideIfEmtpy group containing hideIfEmty group
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group($this->debug->meta('hideIfEmpty'));
        $this->debug->group('inner group empty', $this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("after group");',
        ));
    }

    public function testGroupUngroup()
    {

        // basic no children
        $this->debug->log('before group');
        $this->debug->group('shazam', $this->debug->meta('ungroup'));
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("shazam");
                console.log("after group");',
        ));

        // single child
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group('shazam', $this->debug->meta('ungroup'));
        $this->debug->log('shazam2');
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("shazam2");
                console.log("after group");',
        ));


        // single child (nested group)
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group('ungroup', $this->debug->meta('ungroup'));
        $this->debug->group('nested');
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.group("ungroup");
                console.group("nested");
                console.groupEnd();
                console.groupEnd();
                console.log("after group");',
        ));

        // single child (nested hideIfEmpty group)
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group('ungroup', $this->debug->meta('ungroup'));
        $this->debug->group('nested', $this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("ungroup");
                console.log("after group");',
        ));


        // Two children (log-entry + hideIfEmpty group)
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group('ungroup', $this->debug->meta('ungroup'));
        $this->debug->log('child entry');
        $this->debug->group('nested', $this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("child entry");
                console.log("after group");',
        ));

        /*
            nested ungroups
        */
        $this->debug->setData('log', array());
        $this->debug->log('before group');
        $this->debug->group('ungroup me!', $this->debug->meta('ungroup'));
        $this->debug->group('inner group', $this->debug->meta('ungroup'));
        $this->debug->log('inner most log entry');
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('after group');
        $this->outputTest(array(
            'script' => 'console.log("before group");
                console.log("inner most log entry");
                console.log("after group");',
        ));
    }

    public function testGroupNoArgs()
    {
        $test = new \bdk\DebugTests\Fixture\Test();
        $testBase = new \bdk\DebugTests\Fixture\TestBase();

        /*
            Test default label
        */
        $this->methodWithGroup('foo', 10);
        $entry = array(
            'method' => 'group',
            'args' => array(
                __CLASS__ . '->methodWithGroup',
                'foo',
                10
            ),
            'meta' => array(
                'isFuncName' => true,
            ),
        );
        $classEncoded = \trim(\json_encode(__CLASS__), '"');
        $this->testMethod(
            array(),    // test last called method
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array(
                        __CLASS__ . '->methodWithGroup',
                        'foo',
                        10,
                    ),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-6: %d|[{"Collapsed":"false","Label":"' . $classEncoded . '->methodWithGroup","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">' . __NAMESPACE__ . '\</span>GroupTest</span><span class="t_operator">-&gt;</span><span class="t_identifier">methodWithGroup</span>(</span><span class="t_string">foo</span>, <span class="t_int">10</span><span class="font-weight-bold group-label">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("' . $classEncoded . '->methodWithGroup","foo",10);',
                'text' => '▸ ' . __CLASS__ . '->methodWithGroup("foo", 10)',
                'wamp' => $entry,
            )
        );

        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $testBase->testBasePublic();
        $entry = array(
            'method' => 'group',
            'args' => array(
                'bdk\DebugTests\Fixture\TestBase->testBasePublic'
            ),
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTests\Fixture\TestBase->testBasePublic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-7: 110|[{"Collapsed":"false","Label":"bdk\\\DebugTests\\\Fixture\\\TestBase->testBasePublic","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\DebugTests\Fixture\</span>TestBase</span><span class="t_operator">-&gt;</span><span class="t_identifier">testBasePublic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTests\\\Fixture\\\TestBase->testBasePublic");',
                'text' => '▸ bdk\DebugTests\Fixture\TestBase->testBasePublic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );

        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $test->testBasePublic();
        $entry = array(
            'method' => 'group',
            'args' => array(
                'bdk\DebugTests\Fixture\Test->testBasePublic'
            ),
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTests\Fixture\Test->testBasePublic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-8: 106|[{"Collapsed":"false","Label":"bdk\\\DebugTests\\\Fixture\\\Test->testBasePublic","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\DebugTests\Fixture\</span>Test</span><span class="t_operator">-&gt;</span><span class="t_identifier">testBasePublic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTests\\\Fixture\\\Test->testBasePublic");',
                'text' => '▸ bdk\DebugTests\Fixture\Test->testBasePublic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );

        // yes, we call Test... but static method is defined in TestBase
        // .... PHP
        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        \bdk\DebugTests\Fixture\Test::testBaseStatic();
        $entry = array(
            'method' => 'group',
            'args' => array(
                'bdk\DebugTests\Fixture\TestBase::testBaseStatic'
            ),
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTests\Fixture\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-9: 110|[{"Collapsed":"false","Label":"bdk\\\DebugTests\\\Fixture\\\TestBase::testBaseStatic","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\DebugTests\Fixture\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTests\\\Fixture\\\TestBase::testBaseStatic");',
                'text' => '▸ bdk\DebugTests\Fixture\TestBase::testBaseStatic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );

        // even if called with an arrow
        $this->debug->setData('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $test->testBaseStatic();
        $entry = array(
            'method' => 'group',
            'args' => array(
                'bdk\DebugTests\Fixture\TestBase::testBaseStatic'
            ),
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('bdk\DebugTests\Fixture\TestBase::testBaseStatic'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-10: 110|[{"Collapsed":"false","Label":"bdk\\\DebugTests\\\Fixture\\\TestBase::testBaseStatic","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\DebugTests\Fixture\</span>TestBase</span><span class="t_operator">::</span><span class="t_identifier">testBaseStatic</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\DebugTests\\\Fixture\\\TestBase::testBaseStatic");',
                'text' => '▸ bdk\DebugTests\Fixture\TestBase::testBaseStatic',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );
    }

    public function testGroupNotArgsAsParams()
    {
        $entry = array(
            'method' => 'group',
            'args' => array('a',10),
            'meta' => array(
                'argsAsParams' => false,
            ),
        );
        $this->testMethod(
            'group',
            array(
                'a',
                10,
                $this->debug->meta('argsAsParams', false),
            ),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('a',10),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-4: 61|[{"Collapsed":"false","Label":"a","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label">a:</span> <span class="t_int">10</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("a",10);',
                'text' => '▸ a: 10',
                'wamp' => $entry,
            )
        );
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGroupCollapsed()
    {
        $entry = array(
            'method' => 'groupCollapsed',
            'args' => array('a','b','c'),
            'meta' => array(),
        );
        $this->testMethod(
            'groupCollapsed',
            array('a', 'b', 'c'),
            array(
                'entry' => $entry,
                'custom' => function () {
                    $this->assertSame(array(
                        'main' => array(
                            0 => array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->debug->getData('groupStacks'));
                },
                'chromeLogger' => array(
                    array('a','b','c'),
                    null,
                    'groupCollapsed',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 60|[{"Collapsed":"true","Label":"a","Type":"GROUP_START"},null]|',
                'html' => '<li class="m_group">
                    <div class="group-header"><span class="font-weight-bold group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="font-weight-bold group-label">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.groupCollapsed("a","b","c");',
                'text' => '▸ a("b", "c")',
                'wamp' => $entry,
            )
        );

        // add a nested group that will get removed on output
        $this->debug->groupCollapsed($this->debug->meta('hideIfEmpty'));
        $this->debug->groupEnd();
        $this->debug->log('after nested group');
        $this->outputTest(array(
            'html' => '<li class="m_group">
                <div class="group-header"><span class="font-weight-bold group-label">a(</span><span class="t_string">b</span>, <span class="t_string">c</span><span class="font-weight-bold group-label">)</span></div>
                <ul class="group-body">
                    <li class="m_log"><span class="no-quotes t_string">after nested group</span></li>
                </ul>',
            'script' => 'console.groupCollapsed("a","b","c");
                console.log("after nested group");',
            'text' => '▸ a("b", "c")
                after nested group',
            // 'firephp' => '',
        ));

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'groupCollapsed',
            array('not logged'),
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
        ), \array_map(function (LogEntry $logEntry) {
            return $this->logEntryToArray($logEntry, false);
        }, $log));

        // reset log
        $this->debug->setData('log', array());

        // create a group, turn off collect, close
        // (group should remain open)
        $this->debug->group('new group');
        $logBefore = $this->debug->getData('log');
        $this->debug->setCfg('collect', false);
        $this->debug->groupEnd();
        $logAfter = $this->debug->getData('log');
        $this->assertSame($logBefore, $logAfter, 'groupEnd() logged although collect=false');

        // turn collect back on and close the group
        $this->debug->setCfg('collect', true);
        $this->debug->groupEnd(); // close the open group
        $this->assertCount(2, $this->debug->getData('log'));

        // nothing to close!
        $this->debug->groupEnd(); // close the open group
        $this->assertCount(2, $this->debug->getData('log'));

        $entry = array(
            'method' => 'groupEnd',
            'args' => array(),
            'meta' => array(),
        );
        $this->testMethod(
            'groupEnd',
            array(),
            array(
                'entry' => $entry,
                'custom' => function () {
                    // $this->assertSame(array(1,1), $this->debug->getData('groupDepth'));
                },
                'chromeLogger' => array(
                    array(),
                    null,
                    'groupEnd',
                ),
                'firephp' => 'X-Wf-1-1-1-1: 27|[{"Type":"GROUP_END"},null]|',
                'html' => '</ul>' . "\n" . '</li>',
                'script' => 'console.groupEnd();',
                'text' => '',
                'wamp' => false,
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
                'html' => '</ul>' . "\n" . '</li>',
                'script' => 'console.groupEnd();',
                'text' => '',
                'wamp' => array(
                    'groupEnd',
                    array(),
                    array(),
                ),
            )
        );

        $entry = array(
            'method' => 'groupEndValue',
            'args' => array('return', 'foo'),
            'meta' => array(),
        );
        $this->testMethod(
            array(
                'dataPath' => 'log/1'
            ),
            array(),
            array(
                'entry' => $entry,
                'chromeLogger' => array(
                    array('return', 'foo'),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-154: 39|[{"Label":"return","Type":"LOG"},"foo"]|',
                'html' => '<li class="m_groupEndValue"><span class="no-quotes t_string">return</span> = <span class="t_string">foo</span></li>',
                'script' => 'console.log("return","foo");',
                'text' => 'return = "foo"',
                'wamp' => $entry + array('messageIndex' => 0),
            )
        );
    }

    public function testGroupsLeftOpen()
    {
        /*
        Internal Debug::EVENT_OUTPUT subscribers
             1: InternalEvents::onOutput:  closes open groups / remoes hideIfEmpty groups
                onOutputCleanup
                    closeOpenGroups
                    removeHideIfEmptyGroups
                    uncollapseErrors
                onOutputLogRuntime
             0: Routes & plugins
            -1: Internal::onOutputHeaders

        This also tests that the values returned by getData have been dereferenced
        */

        $this->debug->groupSummary(1);
            $this->debug->log('in summary');
            $this->debug->group('inner group opened but not closed');
                $this->debug->log('in inner');
        $onOutputVals = array();

        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, function (Event $event) use (&$onOutputVals) {
            /*
                Nothing has been closed yet
            */
            $debug = $event->getSubject();
            $onOutputVals['groupPriorityStackA'] = $debug->getData('groupPriorityStack');
            $onOutputVals['groupStacksA'] = $debug->getData('groupStacks');
        }, 2);
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, function (Event $event) use (&$onOutputVals) {
            /*
                At this point, log has been output.. all groups have been closed
            */
            $debug = $event->getSubject();
            $onOutputVals['groupPriorityStackB'] = $debug->getData('groupPriorityStack');
            $onOutputVals['groupStacksB'] = $debug->getData('groupStacks');
        }, -1);

        $output = $this->debug->output();

        $this->assertSame(array(1), $onOutputVals['groupPriorityStackA']);
        $this->assertSame(array(
            'main' => array(),
            1 => array(
                array(
                    'channel' => $this->debug,
                    'collect' => true,
                ),
            ),
        ), $onOutputVals['groupStacksA']);
        $this->assertSame(array(), $onOutputVals['groupPriorityStackB']);
        $this->assertSame(array(
            'main' => array(),
        ), $onOutputVals['groupStacksB']);
        $outputExpect = <<<'EOD'
<div class="debug" data-channel-name-root="general" data-channels="{&quot;general&quot;:{&quot;options&quot;:{&quot;icon&quot;:&quot;fa fa-list-ul&quot;,&quot;show&quot;:true},&quot;channels&quot;:{}}}" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null,&quot;tooltip&quot;:true}">
    <header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist"></nav></header>
    <div class="tab-panes">
        <div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
            <div class="tab-body">
                <ul class="debug-log-summary group-body">
                    <li class="m_log"><span class="no-quotes t_string">in summary</span></li>
                    <li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label">inner group opened but not closed</span></div>
                        <ul class="group-body">
                            <li class="m_log"><span class="no-quotes t_string">in inner</span></li>
                        </ul>
                    </li>
                    <li class="m_info"><span class="no-quotes t_string">Built In %f %ss</span></li>
                    <li class="m_info"><span class="no-quotes t_string">Peak Memory Usage <span title="Includes debug overhead">?&#x20dd;</span>: %f MB / %d %cB</span></li>
                </ul>
                <ul class="debug-log group-body"></ul>
            </div>
        </div>
    </div>
</div>
EOD;
        $outputExpect = \preg_replace('#^\s+#m', '', $outputExpect);
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
        ), \array_map(function (LogEntry $logEntry) {
            return $this->logEntryToArray($logEntry, false);
        }, $logSummary));
        $log = $this->debug->getData('log');
        $this->assertSame(array(
            array('log',array('I\'m not in the summary'), array()),
            array('log',array('the end'), array()),
        ), \array_map(function (LogEntry $logEntry) {
            return $this->logEntryToArray($logEntry, false);
        }, $log));
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
        $this->assertSame('group', $log[0]['method']); // groupCollapsed converted to group
        $this->assertSame('groupCollapsed', $log[1]['method']);
        $this->assertSame('group', $log[5]['method']); // groupCollapsed converted to group
        $this->assertCount(6, $log);    // assert that entry not added
    }

    private function methodWithGroup()
    {
        $this->debug->group();
    }
}
