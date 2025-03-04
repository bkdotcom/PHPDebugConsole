<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Output
 *
 * @covers \bdk\Debug\Plugin\Method\Output
 */
class OutputTest extends DebugTestFramework
{

    public function testOutput()
    {
        $return = $this->debug->output();
        // Debug::varDump('return', $return);
        self::assertStringMatchesFormat('<div class="debug"%A</div>', $return);
        self::assertTrue($this->debug->data->get('outputSent'));
    }

    public function testOutputDisabled()
    {
        $this->debug->setCfg('output', false);
        $return = $this->debug->output();
        self::assertNull($return);
        self::assertFalse($this->debug->data->get('outputSent'));
    }

    public function testOutputEvent()
    {
        $called = array();
        $foo = $this->debug->getChannel('foo');
        $foo->setCfg(array(
            'outputCss' => false,
            'outputScript' => false,
            'route' => 'html',
        ));
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, static function (Event $event) use (&$called) {
            $channelKey = $event->getSubject()->getCfg('channelKey', Debug::CONFIG_DEBUG);
            $called[] = $channelKey . ' ' . \json_encode($event['isTarget']);
            $event['return'] .= ' ' . $channelKey;
        });
        $foo->eventManager->subscribe(Debug::EVENT_OUTPUT, static function (Event $event) use (&$called) {
            $channelKey = $event->getSubject()->getCfg('channelKey', Debug::CONFIG_DEBUG);
            $called[] = $channelKey . ' ' . \json_encode($event['isTarget']);
            $event['return'] .= ' ' . $channelKey;
        });
        $return = $this->debug->output();
        self::assertSame(array(
            'general.foo false',
            'general true',
        ), $called);
        // Debug::varDump('return', $return);
        self::assertStringMatchesFormat('<div class="debug"%A</div>%Ageneral', $return);

        $called = array();
        $foo->log('hello world');
        $return = $foo->output();
        self::assertSame(array(
            'general false',
            'general.foo true',
        ), $called);
        // Debug::varDump('return', $return);
        self::assertStringMatchesFormat('<div class="debug" data-channel-key-root="general.foo"%A</div>%Ageneral.foo', $return);

        $called = array();
        $foo->setCfg('output', false);
        $return = $this->debug->output();
        self::assertSame(array(
            'general true',
        ), $called);
        self::assertStringMatchesFormat('<div class="debug"%A<li class="m_log" data-channel="general.foo"><span class="no-quotes t_string">hello world</span></li>%A</div>%Ageneral', $return);
    }
}
