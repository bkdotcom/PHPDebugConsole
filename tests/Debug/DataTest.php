<?php

namespace bdk\Test\Debug;

use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * test data storage & retrieval
 *
 * @covers \bdk\Debug\Data
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
        $this->assertArrayHasKey('log', $this->debug->data->get('/'));
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
        $this->debug->data->set('logDest', 'alerts');
        $this->debug->data->appendLog(new LogEntry($this->debug, 'log', array('append to alerts')));
        $this->assertSame('append to alerts', $this->debug->data->get('alerts/0/args/0'));
    }
}
