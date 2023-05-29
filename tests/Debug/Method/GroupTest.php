<?php

namespace bdk\Test\Debug\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\Debug\Fixture;
use bdk\Test\Debug\Mock;
use bdk\Test\PolyFill\ExpectExceptionTrait;

function myFunctionThatCallsGroup()
{
    Debug::_group();
    Debug::_groupEnd();
}

/**
 * PHPUnit tests for Debug Group Methods
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Method\Group
 * @covers \bdk\Debug\Method\GroupStack
 *
 * @covers \bdk\Debug\Route\Firephp
 * @covers \bdk\Debug\Route\Script
 */
class GroupTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    /**
     * Test
     *
     * @return void
     */
    public function testGroup()
    {
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
                    $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
                    $this->assertSame(array(
                        'main' => array(
                            array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
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
                'return' => $this->debug,
                'wamp' => false,
            )
        );
    }

    public function testGroupArgs()
    {
        $obj = (object) array('foo' => 'bar');
        $objToString = new Fixture\TestObj('toStringVal');
        $dateTime = new \DateTime('now');  // stringified
        $this->debug->group(
            'string',
            42,
            null,
            false,
            $obj,
            $objToString,
            $dateTime
        );
        $logEntry = $this->debug->data->get('log/0');
        $logEntryArray = $this->helper->logEntryToArray($logEntry);
        $cfgAbsBak = $this->debug->abstracter->setCfg(array(
            'brief' => true,
            'caseCollect' => false,
            'constCollect' => false,
            'methodCollect' => false,
            'objAttributeCollect' => false,
            'propAttributeCollect' => false,
            'toStringOutput' => false,
        ));
        $objExpect = $this->helper->crate($this->debug->abstracter->crate($obj, 'group'));
        $this->debug->abstracter->setCfg($cfgAbsBak);
        /*
        $this->assertSame(array(
            'method' => 'group',
            'args' => array(
                'string',
                42,
                null,
                false,
                $objExpect,
                'toStringVal',
                $dateTime->format(\DateTime::ISO8601),
            ),
            'meta' => array(),
        ), $logEntry);
        */
        $this->assertSame('string', $logEntryArray['args'][0]);
        $this->assertSame(42, $logEntryArray['args'][1]);
        $this->assertSame(null, $logEntryArray['args'][2]);
        $this->assertSame(false, $logEntryArray['args'][3]);
        $this->assertSame($objExpect, $logEntryArray['args'][4]);
        $this->assertTrue(($logEntryArray['args'][5]['cfgFlags'] & AbstractObject::BRIEF) === AbstractObject::BRIEF);
        // phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $this->assertSame(array(
            'visibility' => 'public',
            'returnValue' => 'toStringVal',
        ), $logEntry['args'][5]['methods']['__toString']);
        $this->assertTrue(($logEntryArray['args'][6]['cfgFlags'] & AbstractObject::BRIEF) === AbstractObject::BRIEF);
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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
        $this->debug->data->set('log', array());
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

    public function testGroupAutoArgs()
    {
        $parent = new Fixture\CallerInfoParent();
        $child = new Fixture\CallerInfoChild();

        $someClosure = function ($arg) {
            \bdk\Debug::_group();
            \bdk\Debug::_groupEnd();
        };

        /*
            Test default label
        */
        $child->extendMe('foo', 10);
        $this->assertSame(array(
            array(
                'method' => 'group',
                'args' => [
                    'bdk\\Test\\Debug\\Fixture\\CallerInfoChild->extendMe',
                    'foo',
                    10,
                ],
                'meta' => array(
                    'isFuncName' => true,
                    'statically' => true,
                ),
            ),
            array(
                'method' => 'group',
                'args' => [
                    'bdk\\Test\\Debug\\Fixture\\CallerInfoParent->extendMe',
                ],
                'meta' => array(
                    'isFuncName' => true,
                    'statically' => true,
                ),
            ),
        ), $this->helper->deObjectifyData(\array_slice($this->debug->data->get('log'), 0, 2)));

        $this->debug->data->set('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $parent->extendMe('foo', 10);
        $entryExpect = array(
            'method' => 'group',
            'args' => [
                'bdk\\Test\\Debug\\Fixture\\CallerInfoParent->extendMe',
                'foo',
                10,
            ],
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->testMethod(
            array('dataPath' => 'log/0'),
            array(),
            array(
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array('bdk\\Test\\Debug\\Fixture\\CallerInfoParent->extendMe', 'foo', 10),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-7: 113|[{"Collapsed":"false","Label":"bdk\\\Test\\\Debug\\\Fixture\\\CallerInfoParent->extendMe","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>CallerInfoParent</span><span class="t_operator">-&gt;</span><span class="t_identifier">extendMe</span>(</span><span class="t_string">foo</span>, <span class="t_int">10</span><span class="font-weight-bold group-label">)</span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\Test\\\Debug\\\Fixture\\\CallerInfoParent->extendMe","foo",10);',
                'text' => '▸ bdk\Test\Debug\Fixture\CallerInfoParent->extendMe("foo", 10)',
                'wamp' => $entryExpect + array('messageIndex' => 0),
            )
        );

        $this->debug->data->set('log', array());
        $child->inherited('foo', 10);
        $entryExpect = array(
            'method' => 'group',
            'args' => [
                'bdk\\Test\\Debug\\Fixture\\CallerInfoChild->inherited',
                'foo',
                10,
            ],
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->assertSame($entryExpect, $this->helper->logEntryToArray($this->debug->data->get('log/0')));

        $this->debug->data->set('log', array());
        $parent->inherited('foo', 10);
        $entryExpect = array(
            'method' => 'group',
            'args' => [
                'bdk\\Test\\Debug\\Fixture\\CallerInfoParent->inherited',
                'foo',
                10,
            ],
            'meta' => array(
                'isFuncName' => true,
                'statically' => true,
            ),
        );
        $this->assertSame($entryExpect, $this->helper->logEntryToArray($this->debug->data->get('log/0')));

        $this->debug->data->set('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        Fixture\CallerInfoChild::staticParent();
        $entryExpect = array(
            'method' => 'group',
            'args' => array(
                'bdk\Test\Debug\Fixture\CallerInfoParent::staticParent'
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
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array('bdk\Test\Debug\Fixture\CallerInfoParent::staticParent'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-9: 117|[{"Collapsed":"false","Label":"bdk\\\Test\\\Debug\\\Fixture\\\CallerInfoParent::staticParent","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>CallerInfoParent</span><span class="t_operator">::</span><span class="t_identifier">staticParent</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\Test\\\Debug\\\Fixture\\\CallerInfoParent::staticParent");',
                'text' => '▸ bdk\Test\Debug\Fixture\CallerInfoParent::staticParent',
                'wamp' => $entryExpect + array('messageIndex' => 0),
            )
        );

        // even if called with an arrow
        $this->debug->data->set('log', array());
        $this->debug->getRoute('wamp')->wamp->messages = array();
        $child->staticParent();
        $entryExpect = array(
            'method' => 'group',
            'args' => array(
                'bdk\Test\Debug\Fixture\CallerInfoParent::staticParent'
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
                'entry' => $entryExpect,
                'chromeLogger' => array(
                    array('bdk\Test\Debug\Fixture\CallerInfoParent::staticParent'),
                    null,
                    'group',
                ),
                'firephp' => 'X-Wf-1-1-1-10: 117|[{"Collapsed":"false","Label":"bdk\\\Test\\\Debug\\\Fixture\\\CallerInfoParent::staticParent","Type":"GROUP_START"},null]|',
                'html' => '<li class="expanded m_group">
                    <div class="group-header"><span class="font-weight-bold group-label"><span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>CallerInfoParent</span><span class="t_operator">::</span><span class="t_identifier">staticParent</span></span></div>
                    <ul class="group-body">',
                'script' => 'console.group("bdk\\\Test\\\Debug\\\Fixture\\\CallerInfoParent::staticParent");',
                'text' => '▸ bdk\Test\Debug\Fixture\CallerInfoParent::staticParent',
                'wamp' => $entryExpect + array('messageIndex' => 0),
            )
        );

        $this->debug->data->set('log', array());
        $child->sensitiveParam('swordfish', 'thousand island');
        $entryExpect = array(
            'method' => 'group',
            'args' => array(
                'bdk\Test\Debug\Fixture\CallerInfoChild->sensitiveParam',
                PHP_VERSION_ID >= 80200
                    ? array(
                        'brief' => true,
                        'debug' => Abstracter::ABSTRACTION,
                        'strlen' => null,
                        'type' => Abstracter::TYPE_STRING,
                        'typeMore' => null,
                        'value' => '█████████',
                    )
                    : 'swordfish',
                'thousand island'
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
                'entry' => $entryExpect,
            )
        );

        $this->debug->data->set('log', array());
        $someClosure('flapjack');
        $entryExpect = array(
            'method' => 'group',
            'args' => array(
                '{closure}',
                'flapjack'
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
                'entry' => $entryExpect,
            )
        );
    }

    public function testGroupAutoArgs2()
    {
        myfunctionThatCallsGroup();
        $logEntries = $this->debug->data->get('log');

        $this->assertSame(array(
            'group - ' . __NAMESPACE__ . '\myFunctionThatCallsGroup',
            'groupEnd',
        ), $this->briefLogEntries());
        $this->debug->data->set('log', array());

        // test no backtrace info available when auto-populating group args
        $child = new Fixture\CallerInfoChild();
        $backtraceBackup = $this->debug->backtrace;
        $this->debug->setCfg('serviceProvider', array(
            'backtrace' => new Mock\Backtrace(),
        ));
        $this->debug->backtrace->setReturn(array(
            'function' => null,
            'file' => null,
            'line' => null,
        ));
        $child->extendMe();
        $this->assertSame(array(
            'group - group',
            'group - group',
            'groupEnd',
            'groupEnd',
        ), $this->briefLogEntries());
        $this->debug->setCfg('serviceProvider', array(
            'backtrace' => $backtraceBackup,
        ));
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
                    $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
                    $this->assertSame(array(
                        'main' => array(
                            0 => array('channel' => $this->debug, 'collect' => true),
                        ),
                    ), $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
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
    public function testGroupEnd()
    {
        /*
            Create & close a group
        */
        $this->debug->group('a', 'b', 'c');
        $this->debug->groupEnd();
        $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];
        $this->assertSame(array(
            'main' => array(),
        ), $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
        $log = $this->debug->data->get('log');
        $this->assertCount(2, $log);
        $this->assertSame(array(
            array('group', array('a','b','c'), array()),
            array('groupEnd', array(), array()),
        ), $this->helper->deObjectifyData($log, false));

        // reset log
        $this->debug->data->set('log', array());

        // create a group, turn off collect, close
        // (group should remain open)
        $this->debug->group('new group');
        $logBefore = $this->debug->data->get('log');
        $this->debug->setCfg('collect', false);
        $this->debug->groupEnd();
        $logAfter = $this->debug->data->get('log');
        $this->assertSame($logBefore, $logAfter, 'groupEnd() logged although collect=false');

        // turn collect back on and close the group
        $this->debug->setCfg('collect', true);
        $this->debug->groupEnd(); // close the open group
        $this->assertCount(2, $this->debug->data->get('log'));

        // nothing to close!
        $this->debug->groupEnd(); // close the open group
        $this->assertCount(2, $this->debug->data->get('log'));

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
        $groupStack = $this->getSharedVar('reflectionProperties')['groupStack'];

        $this->debug->groupSummary(1);
            $this->debug->log('in summary');
            $this->debug->group('inner group opened but not closed');
                $this->debug->log('in inner');

        /*
            collect some info before outputing
            confirm nothing has been closed yet
        */
        $onOutputVals['groupPriorityStackA'] = $this->getSharedVar('reflectionProperties')['groupPriorityStack']->getValue($groupStack);
        $onOutputVals['groupStacksA'] = \array_map(function ($stack) {
            return \count($stack);
        }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, function (Event $event) use (&$onOutputVals, $groupStack) {
            // At this point, log has been output.. all groups have been closed
            $debug = $event->getSubject();
            $onOutputVals['groupPriorityStackB'] = $this->getSharedVar('reflectionProperties')['groupPriorityStack']->getValue($groupStack);
            $onOutputVals['groupStacksB'] = \array_map(function ($stack) {
                return \count($stack);
            }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
        }, -1);

        $output = $this->debug->output();

        $this->assertSame(array(1), $onOutputVals['groupPriorityStackA']);
        $this->assertSame(array(
            'main' => 0,
            1 => 1,
        ), $onOutputVals['groupStacksA']);
        $this->assertSame(array(), $onOutputVals['groupPriorityStackB']);
        $this->assertSame(array(
            'main' => 0,
        ), $onOutputVals['groupStacksB']);
        $outputExpect = <<<'EOD'
<div class="debug" data-channel-name-root="general" data-channels="%s" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null,&quot;tooltip&quot;:true}">
    <header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist">%A</nav></header>
    <div class="tab-panes">
        %A<div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
            <div class="tab-body">
                <ul class="debug-log-summary group-body">
                    <li class="m_log"><span class="no-quotes t_string">in summary</span></li>
                    <li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label">inner group opened but not closed</span></div>
                        <ul class="group-body">
                            <li class="m_log"><span class="no-quotes t_string">in inner</span></li>
                        </ul>
                    </li>
                    <li class="m_info"><span class="no-quotes t_string">Built In %f %s</span></li>
                    <li class="m_info"><span class="no-quotes t_string">Peak Memory Usage <span title="Includes debug overhead">?&#x20dd;</span>: %f MB / %d %cB</span></li>
                %A</ul>
                <ul class="debug-log group-body"></ul>
            </div>
        </div>
    %A</div>
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

        $logSummary = $this->debug->data->get('logSummary/0');
        $this->assertSame(array(
            array('group', array('group inside summary'), array()),
            array('log', array('I\'m in the summary!'), array()),
            array('groupEnd', array(), array()),
            array('log', array('I\'m still in the summary!'), array()),
            array('log', array('I\'m staying in the summary!'), array()),
        ), $this->helper->deObjectifyData($logSummary, false));
        $log = $this->debug->data->get('log');
        $this->assertSame(array(
            array('log',array('I\'m not in the summary'), array()),
            array('log',array('the end'), array()),
        ), $this->helper->deObjectifyData($log, false));
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
        $log = $this->debug->data->get('log');
        $this->assertSame('group', $log[0]['method']); // groupCollapsed converted to group
        $this->assertSame('groupCollapsed', $log[1]['method']);
        $this->assertSame('group', $log[5]['method']); // groupCollapsed converted to group
        $this->assertCount(6, $log);    // assert that entry not added

        $this->debug->data->set('log', array());
        $this->debug->groupCollapsed('some group');
        $this->debug->setCfg('collect', false);
        $this->debug->groupUncollapse();
        $this->assertSame(array(
            'method' => 'groupCollapsed',
            'args' => array('some group'),
            'meta' => array(),
        ), $this->helper->logEntryToArray($this->debug->data->get('log/0')));
    }

    public function testInaccessableMethod()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('bdk\Debug\Method\Group::bogus is inaccessable');
        $this->debug->methodGroup->bogus();
    }

    public function testGroupEndClearsErrorCaller()
    {
        $errorCaller = array(
            'file' => '/path/to/file/to/blame.php',
            'line' => 42,
            'groupDepth' => 1,
        );
        $this->debug->group('testGroup');
        $this->debug->setErrorCaller($errorCaller);
        $this->assertSame($errorCaller, $this->debug->errorHandler->get('errorCaller'));
        $this->debug->groupEnd();
        $this->assertSame(array(), $this->debug->errorHandler->get('errorCaller'));

        $this->debug->groupSummary();
        $this->debug->setErrorCaller($errorCaller);
        $this->assertSame($errorCaller, $this->debug->errorHandler->get('errorCaller'));
        $this->debug->groupEnd();
        $this->assertSame(array(), $this->debug->errorHandler->get('errorCaller'));
    }

    public function testGetSubscriptions()
    {
        $this->assertSame(array(
            Debug::EVENT_OUTPUT => array('onOutput', PHP_INT_MAX),
            EventManager::EVENT_PHP_SHUTDOWN => array('onShutdown', PHP_INT_MAX),
        ), $this->debug->methodGroup->getSubscriptions());
    }

    public function testOnOutput()
    {
        $this->debug->group('left open');

        // test that subchannel's doesn't process data if ('isTarget') isn't passed in event
        $someChannel = $this->debug->getChannel('bob');
        $someChannel->methodGroup->onOutput(new Event($someChannel));
        $this->assertCount(1, $someChannel->data->get('log'));

        $this->debug->output();
        $this->assertCount(2, $this->debug->data->get('log'));
    }

    public function testUncollapseErrors()
    {
        $this->debug->log('logEntry');
        $this->debug->groupCollapsed('a');
        $this->debug->info('in group a');
        $this->debug->groupCollapsed('a.1');
        $this->debug->error('meh', $this->debug->meta('uncollapse', false));
        $this->debug->groupEnd();
        $this->debug->groupCollapsed('a.2');
        $this->debug->error('oh noes');
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->output();
        $this->assertSame(array(
            'log - logEntry',
            'group - a',
            'info - in group a',
            'groupCollapsed - a.1',
            'error - meh',
            'groupEnd',
            'group - a.2',
            'error - oh noes',
            'groupEnd',
            'groupEnd',
        ), $this->briefLogEntries());
    }

    public function testOnShutdown()
    {
        $this->debug->setCfg(array(
            'output' => false,
        ));
        $this->debug->group('left unopen');
        $this->debug->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);
        $this->assertCount(2, $logEntries);
        $this->assertSame('groupEnd', $logEntries[1]['method']);
    }

    public function testCloseOpenNotCalledTwice()
    {
        $this->debug->setCfg(array(
            'output' => false,
        ));
        $this->debug->group('left unopen');
        $this->debug->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
        $logEntries = $this->debug->data->get('log');
        $this->assertCount(2, $logEntries);
        $this->assertSame('groupEnd', $logEntries[1]['method']);

        $this->debug->group('group added after shutdown');
        $this->debug->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
        $logEntries = $this->debug->data->get('log');
        $this->assertCount(3, $logEntries);
    }

    private function methodWithGroup()
    {
        $this->debug->group();
    }

    private function briefLogEntries($logEntries = null)
    {
        $logEntries = $logEntries !== null
            ? $logEntries
            : $this->debug->data->get('log');
        return \array_map(function (LogEntry $logEntry) {
            $entry = \implode(
                ' - ',
                array(
                    $logEntry['method'],
                    \implode(', ', $logEntry['args']),
                )
            );
            return \rtrim($entry, '- ');
        }, $logEntries);
    }
}
