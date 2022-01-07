<?php

namespace bdk\DebugTests;

use bdk\Debug;
use bdk\PubSub\Event;

/**
 * PHPUnit tests for Debug Channels
 */
class ChannelTest extends DebugTestFramework
{
    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->debugFoo = $this->debug->getChannel('foo');
        $this->debugFoo->setCfg(array(
            'outputCss' => false,
            'outputScript' => false,
        ));
    }

    public function testInstance()
    {
        $this->assertInstanceOf('bdk\\Debug', $this->debugFoo);
    }

    public function testData()
    {
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
                    array('error', array('main: error'), array('detectFiles' => true, 'file' => '', 'line' => '', 'uncollapse' => true,)),
                    array('error', array('foo: error'), array('channel' => 'general.foo', 'detectFiles' => true, 'file' => '', 'line' => '', 'uncollapse' => true,)),
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

        $data = $this->genLog($this->debug);
        $this->assertSame($dataExpect, $data);

        $data = $this->genLog($this->debugFoo);
        $this->assertSame($dataExpect, $data);

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
                        'bitmask' => 31,
                        'channel' => 'general.foo',
                        'file' => '',
                        'flags' => array(
                            'alerts' => true,
                            'log' => true,
                            'logErrors' => true,
                            'summary' => true,
                            'summaryErrors' => true,
                            'silent' => false,
                        ),
                        'line' => '',
                    ),
                ),
                array('groupEnd', array(), array('channel' => 'general.foo')),
                array('log', array('foo: group / group / after summaries'), array('channel' => 'general.foo')),
            ),
            'logSummary' => array(
                0 => array(
                    array('group', array('main: sum 0 / group 1 / group 2'), array()),
                    array('log', array('main: sum 0 / group 1 / group 2 / log'), array()),
                    array('error', array('main: error'), array('detectFiles' => true, 'file' => '', 'line' => '', 'uncollapse' => true)),
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
        $data = $this->genLog($this->debugFoo, \bdk\Debug::CLEAR_ALL);
        $this->assertSame($dataFooClearedExpect, $data);
    }

    public function testOutput()
    {
        $this->genLog();
        $htmlFoo = <<<EOD
        <div class="debug" data-channel-name-root="general.foo" data-channels="{&quot;general&quot;:{&quot;options&quot;:{&quot;icon&quot;:null,&quot;show&quot;:true},&quot;channels&quot;:{&quot;foo&quot;:{&quot;options&quot;:{&quot;icon&quot;:null,&quot;show&quot;:true},&quot;channels&quot;:{}}}}}" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null,&quot;tooltip&quot;:true}">
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
        <div class="debug" data-channel-name-root="general" data-channels="{&quot;general&quot;:{&quot;options&quot;:{&quot;icon&quot;:&quot;fa fa-list-ul&quot;,&quot;show&quot;:true},&quot;channels&quot;:{&quot;foo&quot;:{&quot;options&quot;:{&quot;icon&quot;:null,&quot;show&quot;:true},&quot;channels&quot;:{}}}}}" data-options="{&quot;drawer&quot;:true,&quot;linkFilesTemplateDefault&quot;:null,&quot;tooltip&quot;:true}">
            <header class="debug-bar debug-menu-bar">PHPDebugConsole<nav role="tablist"></nav></header>
            <div class="tab-panes">
                <div class="active debug-tab-general tab-pane tab-primary" data-options="{&quot;sidebar&quot;:true}" role="tabpanel">
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
                            <li class="m_info"><span class="no-quotes t_string">Peak Memory Usage <span title="Includes debug overhead">?&#x20dd;</span>: %f MB / %d %cB</span></li>
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
            </div>
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
        $this->assertSame(2, $this->eventCounter['general.foo.debug.output']);
    }

    protected function genLog(Debug $clearer = null, $bitmask = null)
    {
        if (!$clearer) {
            $clearer = $this->debug;
        }
        $this->debug->data->set(array(
            'alerts' => array(),
            'log' => array(),
            'logSummary' => array(),
        ));

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
                            $this->debugFoo->error('foo: error');
                        $this->debug->groupEnd(); // main: sum 0 / group 1 / group 2
                        $this->debug->groupSummary(1);
                            $this->debugFoo->group('foo: sum 1 / group 1');
                                $this->debug->group('main: sum 1 / group 1 / group 2');
                                    $this->debug->log('main: sum 1 / group 1 / group 2 / log');
                                    $this->debugFoo->log('foo: sum 1 / group 1 / group 2 / log');
                                    $clearer->clear($bitmask);
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
        foreach (array('alerts','log','logSummary') as $what) {
            if ($what === 'logSummary') {
                foreach ($data['logSummary'] as $i => $group) {
                    foreach ($group as $i2 => $v2) {
                        $export = $v2->export();
                        \ksort($export['meta']);
                        $data['logSummary'][$i][$i2] = \array_values($export);
                    }
                }
            } else {
                foreach ($data[$what] as $i => $v) {
                    $export = $v->export();
                    \ksort($export['meta']);
                    $data[$what][$i] = \array_values($export);
                }
            }
            $temp = \json_encode($data[$what]);
            $temp = \preg_replace('/"(file)":"[^",]+"/', '"$1":""', $temp);
            $temp = \preg_replace('/"(line)":\d+/', '"$1":""', $temp);
            $data[$what] = \json_decode($temp, true);
        }
        $data['groupPriorityStack'] = $this->getSharedVar('reflectionProperties')['groupPriorityStack']->getValue($this->debug->methodGroup);
        $data['groupStacks'] = \array_map(function ($stack) {
            foreach ($stack as $k2 => $info) {
                $channelName = $info['channel']->getCfg('channelName');
                $stack[$k2]['channel'] = $channelName;
            }
            return $stack;
        }, $this->getSharedVar('reflectionProperties')['groupStacks']->getValue($this->debug->methodGroup));
        return $data;
    }
}
