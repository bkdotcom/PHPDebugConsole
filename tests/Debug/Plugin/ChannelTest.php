<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug Channels
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Plugin\Channel
 * @covers \bdk\Debug\Plugin\InternalEvents
 * @covers \bdk\Debug\Plugin\Method\Clear
 * @covers \bdk\Debug\Route\Html
 * @covers \bdk\Debug\Route\Html\ErrorSummary
 * @covers \bdk\Debug\Route\Html\Tabs
 */
class ChannelTest extends DebugTestFramework
{
    protected $debugFoo;
    protected $eventCounter = array();

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->debugFoo = $this->debug->getChannel('foo', array(
            'outputCss' => false,
            'outputScript' => false,
        ));
    }

    public function testInstance()
    {
        self::assertInstanceOf('bdk\\Debug', $this->debug->getChannel('foo'));
    }

    public function testData()
    {
        $info = array();
        $data = $this->genLog($this->debug, null, $info);
        $dataExpect = array(
            'alerts' => array(
                array('alert', array('main: alert'), array('dismissible' => false, 'level' => 'error')),
                array('alert', array('foo: alert'), array('channel' => 'general.foo', 'dismissible' => false, 'level' => 'error')),
            ),
            'log' => array(
                array('log', array('main: log'), array()),
                array('group', array('main: group'), array()),
                array('log', array('main: group / log'), array()),
                array('group', array('foo: group / group'), array('channel' => 'general.foo')),
                array('log', array('main: group / group / log'), array()),
                array('log', array('foo: group / group / log'), array('channel' => 'general.foo')),
                array('log', array('foo: group / group / after summaries'), array('channel' => 'general.foo')),
            ),
            'logSummary' => array(
                0 => array(
                    array('group', array('foo: sum 0 / group 1'), array('channel' => 'general.foo')),
                    array('group', array('main: sum 0 / group 1 / group 2'), array()),
                    array('log', array('main: sum 0 / group 1 / group 2 / log'), array()),
                    array('log', array('foo: sum 0 / group 1 / group 2 / log'), array('channel' => 'general.foo')),
                    array('error', array('main: error'), array(
                        'detectFiles' => true,
                        'evalLine' => null,
                        'file' => __FILE__,
                        'line' => $info['lines'][0],
                        'uncollapse' => true,
                    )),
                    array('error', array('foo: error'), array(
                        'channel' => 'general.foo',
                        'detectFiles' => true,
                        'evalLine' => null,
                        'file' => __FILE__,
                        'line' => $info['lines'][1],
                        'uncollapse' => true,
                    )),
                    array('groupEnd', array(), array()),
                    array('groupEnd', array(), array('channel' => 'general.foo')),
                ),
                1 => array(
                    array('group', array('foo: sum 1 / group 1'), array('channel' => 'general.foo')),
                    array('group', array('main: sum 1 / group 1 / group 2'), array()),
                    array('log', array('main: sum 1 / group 1 / group 2 / log'), array()),
                    array('log', array('foo: sum 1 / group 1 / group 2 / log'), array('channel' => 'general.foo')),
                    array('groupEnd', array(), array()),
                    array('groupEnd', array(), array('channel' => 'general.foo')),
                ),
            ),
            'groupPriorityStack' => array(),
            'groupStacks' => array(
                'main' => array(
                    array('channel' => 'general', 'collect' => true),
                    array('channel' => 'general.foo', 'collect' => true),
                ),
            ),
        );

        // nothing actually cleared (bitmask = 0)
        $data = $this->genLog($this->debug);
        self::assertSame($dataExpect, $data);

        // nothing actually cleared (bitmask = 0)
        $data = $this->genLog($this->debugFoo);
        self::assertSame($dataExpect, $data);

        $dataFooClearedExpect = array(
            'alerts' => array(
                array('alert', array('main: alert'), array('dismissible' => false, 'level' => 'error')),
            ),
            'log' => array(
                array('log', array('main: log'), array()),
                array('group', array('main: group'), array()),
                array('log', array('main: group / log'), array()),
                array('group', array('foo: group / group'), array('channel' => 'general.foo')),
                array('log', array('main: group / group / log'), array()),
                array(
                    'clear',
                    array(
                        'Cleared everything %c(%s)',
                        'background-color:#c0c0c0; padding:0 .33em;',
                        'general.foo',
                    ),
                    array(
                        'bitmask' => Debug::CLEAR_ALL,
                        'channel' => 'general.foo',
                        'evalLine' => null,
                        'file' => __FILE__,
                        'flags' => array(
                            'alerts' => true,
                            'log' => true,
                            'logErrors' => true,
                            'silent' => false,
                            'summary' => true,
                            'summaryErrors' => true,
                        ),
                        'line' => $info['lines'][2],
                    ),
                ),
                array('groupEnd', array(), array('channel' => 'general.foo')),
                array('log', array('foo: group / group / after summaries'), array('channel' => 'general.foo')),
            ),
            'logSummary' => array(
                0 => array(
                    array('group', array('main: sum 0 / group 1 / group 2'), array()),
                    array('log', array('main: sum 0 / group 1 / group 2 / log'), array()),
                    array('error', array('main: error'), array(
                        'detectFiles' => true,
                        'evalLine' => null,
                        'file' => __FILE__,
                        'line' => $info['lines'][0],
                        'uncollapse' => true,
                    )),
                    array('groupEnd', array(), array()),
                ),
                1 => array(
                    array('group', array('foo: sum 1 / group 1'), array('channel' => 'general.foo')),
                    array('group', array('main: sum 1 / group 1 / group 2'), array()),
                    array('log', array('main: sum 1 / group 1 / group 2 / log'), array()),
                    array('groupEnd', array(), array()),
                    array('groupEnd', array(), array('channel' => 'general.foo')),
                ),
            ),
            'groupPriorityStack' => array(),
            'groupStacks' => array(
                'main' => array(
                    array('channel' => 'general', 'collect' => true),
                ),
            ),
        );
        $data = $this->genLog($this->debugFoo, Debug::CLEAR_ALL);
        self::assertSame($dataFooClearedExpect, $data);
    }

    public function testOutput()
    {
        $this->genLog();
        $htmlFoo = <<<EOD
        <div class="debug" data-channel-name-root="general.foo" data-channels="{&quot;general&quot;:{&quot;channels&quot;:{&quot;foo&quot;:{&quot;channels&quot;:{},&quot;options&quot;:{&quot;icon&quot;:null,&quot;show&quot;:true}}},&quot;options&quot;:{&quot;icon&quot;:&quot;fa fa-list-ul&quot;,&quot;show&quot;:true}}}" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null,&quot;tooltip&quot;:true}">
            <header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist"></nav></header>
            <div class="tab-panes">
                <div class="active debug-tab-general-foo tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
                    <div class="tab-body">
                        <div class="alert-error m_alert" data-channel="general.foo" role="alert">foo: alert</div>
                        <ul class="debug-log-summary group-body">
                            <li class="expanded m_group" data-channel="general.foo">
                                <div class="group-header"><span class="font-weight-bold group-label">foo: sum 1 / group 1</span></div>
                                <ul class="group-body">
                                    <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: sum 1 / group 1 / group 2 / log</span></li>
                                </ul>
                            </li>
                            <li class="expanded m_group" data-channel="general.foo">
                                <div class="group-header"><span class="font-weight-bold group-label">foo: sum 0 / group 1</span></div>
                                <ul class="group-body">
                                    <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: sum 0 / group 1 / group 2 / log</span></li>
                                    <li class="m_error" data-channel="general.foo" data-detect-files="true" data-file="%s" data-line="%d"><span class="no-quotes t_string">foo: error</span></li>
                                </ul>
                            </li>
                        </ul>
                        <hr />
                        <ul class="debug-log group-body">
                            <li class="expanded m_group" data-channel="general.foo">
                                <div class="group-header"><span class="font-weight-bold group-label">foo: group / group</span></div>
                                <ul class="group-body">
                                    <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: group / group / log</span></li>
                                    <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: group / group / after summaries</span></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
EOD;
        $html = <<<EOD
        <div class="debug" data-channel-name-root="general" data-channels="{&quot;general&quot;:{&quot;channels&quot;:{&quot;foo&quot;:{&quot;channels&quot;:{},&quot;options&quot;:{&quot;icon&quot;:null,&quot;show&quot;:true}}},&quot;options&quot;:{&quot;icon&quot;:&quot;fa fa-list-ul&quot;,&quot;show&quot;:true}}}" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null,&quot;tooltip&quot;:true}">
            <header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist">%A</nav></header>
            <div class="tab-panes">
                %A<div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
                    <div class="tab-body">
                        <div class="alert-error m_alert" role="alert">main: alert</div>
                        <div class="alert-error m_alert" data-channel="general.foo" role="alert">foo: alert</div>
                        <ul class="debug-log-summary group-body">
                            <li class="expanded m_group" data-channel="general.foo">
                                <div class="group-header"><span class="font-weight-bold group-label">foo: sum 1 / group 1</span></div>
                                <ul class="group-body">
                                    <li class="expanded m_group">
                                        <div class="group-header"><span class="font-weight-bold group-label">main: sum 1 / group 1 / group 2</span></div>
                                        <ul class="group-body">
                                            <li class="m_log"><span class="no-quotes t_string">main: sum 1 / group 1 / group 2 / log</span></li>
                                            <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: sum 1 / group 1 / group 2 / log</span></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                            <li class="m_info"><span class="no-quotes t_string">Built In %f %s</span></li>
                            <li class="m_info"><span class="no-quotes t_string">Peak Memory Usage <span title="Includes debug overhead">?&#x20dd;</span>: %f MB / %s</span></li>
                            <li class="expanded m_group" data-channel="general.foo">
                                <div class="group-header"><span class="font-weight-bold group-label">foo: sum 0 / group 1</span></div>
                                <ul class="group-body">
                                    <li class="expanded m_group">
                                        <div class="group-header"><span class="font-weight-bold group-label">main: sum 0 / group 1 / group 2</span></div>
                                        <ul class="group-body">
                                            <li class="m_log"><span class="no-quotes t_string">main: sum 0 / group 1 / group 2 / log</span></li>
                                            <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: sum 0 / group 1 / group 2 / log</span></li>
                                            <li class="m_error" data-detect-files="true" data-file="%s" data-line="%d"><span class="no-quotes t_string">main: error</span></li>
                                            <li class="m_error" data-channel="general.foo" data-detect-files="true" data-file="%s" data-line="%d"><span class="no-quotes t_string">foo: error</span></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                        <hr />
                        <ul class="debug-log group-body">
                            <li class="m_log"><span class="no-quotes t_string">main: log</span></li>
                            <li class="expanded m_group">
                                <div class="group-header"><span class="font-weight-bold group-label">main: group</span></div>
                                <ul class="group-body">
                                    <li class="m_log"><span class="no-quotes t_string">main: group / log</span></li>
                                    <li class="expanded m_group" data-channel="general.foo">
                                        <div class="group-header"><span class="font-weight-bold group-label">foo: group / group</span></div>
                                        <ul class="group-body">
                                            <li class="m_log"><span class="no-quotes t_string">main: group / group / log</span></li>
                                            <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: group / group / log</span></li>
                                            <li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">foo: group / group / after summaries</span></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            %A</div>
        </div>
EOD;
        $this->eventCounter = array();
        $this->debugFoo->eventManager->subscribe(Debug::EVENT_OUTPUT, function (Event $event) {
            $channelName = $event->getSubject()->getCfg('channelName');
            $this->eventCounter[$channelName . '.debug.output'] = isset($this->eventCounter[$channelName . '.debug.output'])
                ? $this->eventCounter[$channelName . '.debug.output'] + 1
                : 1;
        });
        $this->outputTest(array(
            'html' => $htmlFoo,
        ), $this->debugFoo);
        $this->outputTest(array(
            'html' => $html,
        ), $this->debug);
        self::assertSame(2, $this->eventCounter['general.foo.debug.output']);
    }

    public function testCreateChannel()
    {
        $channelsBack = \bdk\Debug\Utility\Reflection::propGet($this->debug->getPlugin('channel'), 'channels');
        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('channel'), 'channels', array());
        $tabby = $this->debug->getChannel('tabby', array(
            'channelIcon' => 'fa fa-tabby',
            'nested' => false,
        ));
        $this->debug->getChannel('foo.bar', array(
            'channelIcon' => 'fa fa-asterisk',
        ));

        self::assertSame('tabby', $tabby->getCfg('channelName'));

        // $this->debug->varDump($this->debug->getChannels());
        self::assertSame(array(
            'foo',
        ), \array_keys($this->debug->getChannels()));

        self::assertSame(array(
            'general.foo',
            'general.foo.bar',
        ), \array_keys($this->debug->getChannels(true)));

        // $this->debug->varDump($this->debug->getChannels(false, true));
        self::assertSame(array(
            'tabby',
            'foo',
        ), \array_keys($this->debug->getChannels(false, true)));

        // $this->debug->varDump($this->debug->getChannels(true, true));
        self::assertSame(array(
            'tabby',
            'general.foo',
            'general.foo.bar',
        ), \array_keys($this->debug->getChannels(true, true)));

        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('channel'), 'channels', $channelsBack);
    }

    public function testGetChannel()
    {
        $this->debug->setCfg('channels', array(
            'utensil' => array(
                'channelIcon' => 'fa fa-utensil',
                'channels' => array(
                    'fork' => array(
                        'channelIcon' => 'fa fa-fork',
                    ),
                ),
            ),
        ));

        $channelsBack = \bdk\Debug\Utility\Reflection::propGet($this->debug->getPlugin('channel'), 'channels');
        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('channel'), 'channels', array());
        $baz = $this->debug->getChannel('baz');
        $baz->getChannel('general.foo.bar', array(
            'channelIcon' => 'fa fa-asterisk',
        ));
        $foo = $this->debug->getChannel('foo');
        self::assertSame(null, $foo->getCfg('channelIcon'));
        $bar = $foo->getChannel('bar');
        self::assertSame('fa fa-asterisk', $bar->getCfg('channelIcon'));

        $bar = $this->debug->getChannel('general.foo.bar');
        self::assertSame('fa fa-asterisk', $bar->getCfg('channelIcon'));

        $fork = $this->debug->getChannel('utensil.fork');
        self::assertSame('fa fa-fork', $fork->getCfg('channelIcon'));

        \bdk\Debug\Utility\Reflection::propSet($this->debug->getPlugin('channel'), 'channels', $channelsBack);
    }

    protected function genLog($clearer = null, $bitmask = null, &$info = array())
    {
        if (!$clearer) {
            $clearer = $this->debug;
        }
        $this->debug->data->set(array(
            'alerts' => array(),
            'log' => array(),
            'logSummary' => array(),
        ));
        $info = array(
            'lines' => array(),
        );

        $this->debug->log('main: log');
        $this->debug->group('main: group');
            $this->debug->log('main: group / log');
            $this->debugFoo->group('foo: group / group');
                $this->debug->log('main: group / group / log');
                $this->debugFoo->log('foo: group / group / log');
                $this->debug->alert('main: alert');
                $this->debugFoo->alert('foo: alert');
                $this->debugFoo->groupSummary();
                    $this->debugFoo->group('foo: sum 0 / group 1');
                        $this->debug->group('main: sum 0 / group 1 / group 2');
                            $this->debug->log('main: sum 0 / group 1 / group 2 / log');
                            $this->debugFoo->log('foo: sum 0 / group 1 / group 2 / log');
                            $this->debug->error('main: error');
                            $info['lines'][] = __LINE__ - 1;
                            $this->debugFoo->error('foo: error');
                            $info['lines'][] = __LINE__ - 1;
                        $this->debug->groupEnd(); // main: sum 0 / group 1 / group 2
                        $this->debug->groupSummary(1);
                            $this->debugFoo->group('foo: sum 1 / group 1');
                                $this->debug->group('main: sum 1 / group 1 / group 2');
                                    $this->debug->log('main: sum 1 / group 1 / group 2 / log');
                                    $this->debugFoo->log('foo: sum 1 / group 1 / group 2 / log');
                                    $clearer->clear($bitmask);
                                    $info['lines'][] = __LINE__ - 1;
                                $this->debug->groupEnd(); // main sum 1 / group 1 / group 2
                            $this->debugFoo->groupEnd(); // foo sum 1 / group 1
                        $this->debug->groupEnd(); // summary1
                    $this->debugFoo->groupEnd(); // foo sum 0 / group 1
                $this->debugFoo->groupEnd(); // summary 0
                $this->debugFoo->log('foo: group / group / after summaries');
            // $this->debugFoo->groupEnd(); // foo group
        // $this->debug->groupEnd(); // main group

        $data = \array_intersect_key($this->debug->data->get(), \array_flip(array(
            'alerts',
            'log',
            'logSummary',
        )));
        $data = $this->helper->deObjectifyData($data, false);
        $groupStack = self::getSharedVar('groupStack');
        $data['groupPriorityStack'] = self::getSharedVar('reflectionProperties')['groupPriorityStack']->getValue($groupStack);
        $data['groupStacks'] = \array_map(static function ($stack) {
            foreach ($stack as $k2 => $info) {
                $channelName = $info['channel']->getCfg('channelName');
                $stack[$k2]['channel'] = $channelName;
            }
            return $stack;
        }, self::getSharedVar('reflectionProperties')['groupStacks']->getValue($groupStack));
        return $data;
    }
}
