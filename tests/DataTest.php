<?php

namespace bdk\DebugTests;

use bdk\DebugTests\DebugTestFramework;

/**
 * test data storage & retrieval
 */
class DataTest extends DebugTestFramework
{
    public function testGetData()
    {
        $this->debug->info('token log entry 1');
        $this->debug->warn('token log entry 2');
        $this->assertArrayHasKey('log', $this->debug->data->get());
        $this->assertSame(2, $this->debug->data->get('log/__count__'));
        $this->assertSame('info', $this->debug->data->get('log.0.method'));
        $this->assertSame('warn', $this->debug->data->get('log/1/method'));
        $this->assertSame('warn', $this->debug->data->get('log/__end__/method'));
        $this->assertSame(null, $this->debug->data->get('log/bogus'));
        $this->assertSame(null, $this->debug->data->get('log/bogus/more'));
        $this->assertSame(null, $this->debug->data->get('log/0/method/notArray'));
    }

    public function testSetData()
    {
        $this->debug->data->set('log/0', array('info', array('foo'), array()));
        $this->assertSame(1, $this->debug->data->get('log/__count__'));
        $this->assertSame('foo', $this->debug->data->get('log/0/1/0'));

        $this->debug->data->set(array(
            'log' => array(
                array('info', array('bar'), array()),
            )
        ));
        $this->assertSame(1, $this->debug->data->get('log/__count__'));
        $this->assertSame('bar', $this->debug->data->get('log/0/1/0'));
    }
}
