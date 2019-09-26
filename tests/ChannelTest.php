<?php

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
    public function setUp()
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
                array('alert', array('main: alert'), array('class' => 'danger', 'dismissible' => false)),
                array('alert', array('foo: alert'), array('channel' => 'foo', 'class' => 'danger', 'dismissible' => false)),
            ),
            'groupStacks' => array(
                'main' => array(
                    array('channel' => 'general', 'collect' => true),
                    array('channel' => 'foo', 'collect' => true),
                ),
            ),
            'groupPriorityStack' => array(),
            'log' => array(
                array('log', array('main: log'), array()),
                array('group', array('main: group'), array()),
                array('log', array('main: group / log'), array()),
                array('group', array('foo: group / group'), array('channel' => 'foo')),
                array('log', array('main: group / group / log'), array()),
                array('log', array('foo: group / group / log'), array('channel' => 'foo')),
                array('log', array('foo: group / group / after summaries'), array('channel' => 'foo')),
                // array('groupEnd', array(), array('channel' => 'foo')),
                // array('groupEnd', array(), array()),
            ),
            'logSummary' => array(
                0 => array(
                    array('group', array('foo: sum 0 / group 1'), array('channel' => 'foo')),
                    array('group', array('main: sum 0 / group 1 / group 2'), array()),
                    array('log', array('main: sum 0 / group 1 / group 2 / log'), array()),
                    array('log', array('foo: sum 0 / group 1 / group 2 / log'), array('channel' => 'foo')),
                    array('error', array('main: error'), array('file' => '', 'line' => '')),
                    array('error', array('foo: error'), array('channel' => 'foo', 'file' => '', 'line' => '')),
                    array('groupEnd', array(), array()),
                    array('groupEnd', array(), array('channel' => 'foo')),
                ),
                1 => array(
                    array('group', array('foo: sum 1 / group 1'), array('channel' => 'foo')),
                    array('group', array('main: sum 1 / group 1 / group 2'), array()),
                    array('log', array('main: sum 1 / group 1 / group 2 / log'), array()),
                    array('log', array('foo: sum 1 / group 1 / group 2 / log'), array('channel' => 'foo')),
                    array('groupEnd', array(), array()),
                    array('groupEnd', array(), array('channel' => 'foo')),
                ),
            ),
        );
        $data = $this->genLog($this->debug);
        $this->assertSame($dataExpect, $data);

        $data = $this->genLog($this->debugFoo);
        $this->assertSame($dataExpect, $data);

        $dataFooClearedExpect = array(
            'alerts' => array(
                array('alert', array('main: alert'), array('class' => 'danger', 'dismissible' => false)),
                // array('alert', array('foo: alert'), array('channel' => 'foo', 'class' => 'danger', 'dismissible' => false)),
            ),
            'groupStacks' => array(
                'main' => array(
                    array('channel' => 'general', 'collect' => true),
                    // array('channel' => 'foo', 'collect' => true),
                ),
            ),
            'groupPriorityStack' => array(),
            'log' => array(
                array('log', array('main: log'), array()),
                array('group', array('main: group'), array()),
                array('log', array('main: group / log'), array()),
                array('group', array('foo: group / group'), array('channel' => 'foo')),
                array('log', array('main: group / group / log'), array()),
                // array('log', array('foo: group / group / log'), array('channel' => 'foo')),
                array(
                   'clear',
                    array(
                        'Cleared everything %c(%s)',
                        'background-color:#c0c0c0; padding:0 .33em;',
                        'foo',
                    ),
                    array(
                        'channel' => 'foo',
                        'file' => '',
                        'line' => '',
                        'bitmask' => 31,
                        'flags' => array(
                            'alerts' => true,
                            'log' => true,
                            'logErrors' => true,
                            'summary' => true,
                            'summaryErrors' => true,
                            'silent' => false,
                        ),
                    ),
                ),
                array('groupEnd', array(), array('channel' => 'foo')),
                array('log', array('foo: group / group / after summaries'), array('channel' => 'foo')),
                // array('groupEnd', array(), array()),
            ),
            'logSummary' => array(
                0 => array(
                    // array('group', array('foo: sum 0 / group 1'), array('channel' => 'foo')),
                    array('group', array('main: sum 0 / group 1 / group 2'), array()),
                    array('log', array('main: sum 0 / group 1 / group 2 / log'), array()),
                    // array('log', array('foo: sum 0 / group 1 / group 2 / log'), array('channel' => 'foo')),
                    array('error', array('main: error'), array('file' => '', 'line' => '')),
                    // array('error', array('foo: error'), array('channel' => 'foo', 'file' => '', 'line' => '')),
                    array('groupEnd', array(), array()),
                    // array('groupEnd', array(), array('channel' => 'foo')),
                ),
                1 => array(
                    array('group', array('foo: sum 1 / group 1'), array('channel' => 'foo')),
                    array('group', array('main: sum 1 / group 1 / group 2'), array()),
                    array('log', array('main: sum 1 / group 1 / group 2 / log'), array()),
                    // array('log', array('foo: sum 1 / group 1 / group 2 / log'), array('channel' => 'foo')),
                    array('groupEnd', array(), array()),
                    array('groupEnd', array(), array('channel' => 'foo')),
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
        <div class="debug" data-channel-root="general" data-channels="{&quot;foo&quot;:{}}">
            <div class="debug-bar"><h3>Debug Log</h3></div>
            <div class="alert alert-danger" data-channel="foo" role="alert">foo: alert</div>
            <div class="debug-header m_group">
                <div class="expanded group-header" data-channel="foo"><span class="group-label">foo: sum 1 / group 1</span></div>
                <div class="m_group">
                    <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: sum 1 / group 1 / group 2 / log</span></div>
                </div>
                <div class="expanded group-header" data-channel="foo"><span class="group-label">foo: sum 0 / group 1</span></div>
                <div class="m_group">
                    <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: sum 0 / group 1 / group 2 / log</span></div>
                    <div class="m_error" data-channel="foo" title="%s: line %d"><span class="no-pseudo t_string">foo: error</span></div>
                </div>
            </div>
            <div class="debug-content m_group">
                <div class="expanded group-header" data-channel="foo"><span class="group-label">foo: group / group</span></div>
                <div class="m_group">
                    <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: group / group / log</span></div>
                    <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: group / group / after summaries</span></div>
                </div>
            </div>
        </div>
EOD;
        $html = <<<EOD
        <div class="debug" data-channel-root="general" data-channels="{&quot;general&quot;:{},&quot;foo&quot;:{}}">
            <div class="debug-bar"><h3>Debug Log</h3></div>
            <div class="alert alert-danger" role="alert">main: alert</div>
            <div class="alert alert-danger" data-channel="foo" role="alert">foo: alert</div>
            <div class="debug-header m_group">
                <div class="expanded group-header" data-channel="foo"><span class="group-label">foo: sum 1 / group 1</span></div>
                <div class="m_group">
                    <div class="expanded group-header"><span class="group-label">main: sum 1 / group 1 / group 2</span></div>
                    <div class="m_group">
                        <div class="m_log"><span class="no-pseudo t_string">main: sum 1 / group 1 / group 2 / log</span></div>
                        <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: sum 1 / group 1 / group 2 / log</span></div>
                    </div>
                </div>
                <div class="m_info"><span class="no-pseudo t_string">Built In %f sec</span></div>
                <div class="m_info"><span class="no-pseudo t_string">Peak Memory Usage: %f MB / %d %cB</span></div>
                <div class="expanded group-header" data-channel="foo"><span class="group-label">foo: sum 0 / group 1</span></div>
                <div class="m_group">
                    <div class="expanded group-header"><span class="group-label">main: sum 0 / group 1 / group 2</span></div>
                    <div class="m_group">
                        <div class="m_log"><span class="no-pseudo t_string">main: sum 0 / group 1 / group 2 / log</span></div>
                        <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: sum 0 / group 1 / group 2 / log</span></div>
                        <div class="m_error" title="%s: line %d"><span class="no-pseudo t_string">main: error</span></div>
                        <div class="m_error" data-channel="foo" title="%s: line %d"><span class="no-pseudo t_string">foo: error</span></div>
                    </div>
                </div>
            </div>
            <div class="debug-content m_group">
                <div class="m_log"><span class="no-pseudo t_string">main: log</span></div>
                <div class="expanded group-header"><span class="group-label">main: group</span></div>
                <div class="m_group">
                    <div class="m_log"><span class="no-pseudo t_string">main: group / log</span></div>
                    <div class="expanded group-header" data-channel="foo"><span class="group-label">foo: group / group</span></div>
                    <div class="m_group">
                        <div class="m_log"><span class="no-pseudo t_string">main: group / group / log</span></div>
                        <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: group / group / log</span></div>
                        <div class="m_log" data-channel="foo"><span class="no-pseudo t_string">foo: group / group / after summaries</span></div>
                    </div>
                </div>
            </div>
        </div>
EOD;
        $this->eventCounter = array();
        $this->debugFoo->eventManager->subscribe('debug.output', function (Event $event) {
            $channel = $event->getSubject()->getCfg('channelName');
            $this->eventCounter[$channel.'.debug.output'] = isset($this->eventCounter[$channel.'.debug.output'])
                ? $this->eventCounter[$channel.'.debug.output'] + 1
                : 1;
        });
        $this->outputTest(array(
            'html' => $htmlFoo,
        ), $this->debugFoo);
        $this->outputTest(array(
            'html' => $html,
        ), $this->debug);
        $this->assertSame(2, $this->eventCounter['foo.debug.output']);
    }

    protected function genLog(\bdk\Debug $clearer = null, $bitmask = null)
    {
        if (!$clearer) {
            $clearer = $this->debug;
        }
        $this->debug->setData(array(
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
        $data = array_intersect_key($this->debug->getData(), array_flip(array(
            'alerts',
            'groupStacks',
            'groupPriorityStack',
            'log',
            'logSummary',
        )));
        $data = json_encode($data);
        $data = preg_replace('/"(file)":"[^",]+"/', '"$1":""', $data);
        $data = preg_replace('/"(line)":\d+/', '"$1":""', $data);
        return json_decode($data, true);
    }
}
