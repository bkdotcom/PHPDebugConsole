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
    public function testConstruct()
    {
        $debug = new \bdk\Debug();
        $this->assertInstanceOf('bdk\\Debug\\Data', $debug->data);
    }

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
        // $this->assertSame(null, $this->debug->data->get('log/0/method/notArray'));
        $this->assertArrayHasKey('log', $this->debug->data->get('/'));
        $this->assertSame(array(), $this->debug->data->get('logSummary'));
    }

    public function testAppendGroup()
    {
        $this->debug->groupSummary();
        $this->debug->info('not in group');
        $this->debug->group('group inside summary', $this->debug->meta('id', 'groupId'));
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->log('I got appended to summary group', $this->debug->meta('appendGroup', 'groupId'));
        $this->assertSame(array(
            array(
                'method' => 'info',
                'args' => array('not in group'),
                'meta' => array(),
            ),
            'groupId' => array(
                'method' => 'group',
                'args' => array('group inside summary'),
                'meta' => array(
                    'attribs' => array(
                        'id' => 'groupId',
                        'class' => array(),
                    ),
                ),
            ),
            array(
                'method' => 'log',
                'args' => array('I got appended to summary group'),
                'meta' => array('appendGroup' => 'groupId'),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(),
            ),
        ), $this->helper->deObjectifyData($this->debug->data->get('logSummary/0')));
        $this->assertSame(array(), $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testAppendGroupNotClosed()
    {
        $this->debug->group('my group', $this->debug->meta('id', 'groupId'));
        $this->debug->log('Appended to end I am', $this->debug->meta(array(
            'appendGroup' => 'groupId',
            'id' => 'logEntryId',
        )));
        $this->assertSame(array(
            'groupId' => array(
                'method' => 'group',
                'args' => array('my group'),
                'meta' => array(
                    'attribs' => array(
                        'id' => 'groupId',
                        'class' => array(),
                    ),
                ),
            ),
            'logEntryId' => array(
                'method' => 'log',
                'args' => array('Appended to end I am'),
                'meta' => array(
                    'appendGroup' => 'groupId',
                    'attribs' => array(
                        'id' => 'logEntryId',
                        'class' => array(),
                    ),
                ),
            ),
        ), $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testAppendGroupNotFound()
    {
        $this->debug->group('my group');
        $this->debug->groupEnd();
        $this->debug->log('I dont get appended to group', $this->debug->meta('appendGroup', 'noSuchGroupId'));
        $this->assertSame(array(
            array(
                'method' => 'group',
                'args' => array('my group'),
                'meta' => array(),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(),
            ),
            array(
                'method' => 'log',
                'args' => array('I dont get appended to group'),
                'meta' => array('appendGroup' => 'noSuchGroupId'),
            ),
        ), $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    /**
     * FindLogEntry is only used by appendGroup....
     * Since Alerrts can't have groups, this tree branch isn't used
     *
     * @return void
     */
    public function testFindLogEntryAlert()
    {
        $this->debug->alert('Alerticus!', $this->debug->meta('id', 'alertId'));
        $refMethod = new \ReflectionMethod($this->debug->data, 'findLogEntry');
        $refMethod->setAccessible(true);
        $this->assertSame('alerts', $refMethod->invoke($this->debug->data, 'alertId'));
    }

    public function testSetData()
    {
        $this->debug->data->set('log/0', array('info', array('foo'), array()));
        $this->assertSame(1, $this->debug->data->get('log/__count__'));
        $this->assertSame('foo', $this->debug->data->get('log/0/1/0'));

        $this->debug->data->set(array(
            'log' => array(
                array('info', array('bar'), array()),
            ),
        ));
        $this->assertSame(1, $this->debug->data->get('log/__count__'));
        $this->assertSame('bar', $this->debug->data->get('log/0/1/0'));
        $this->debug->data->set('logDest', 'alerts');
        $this->debug->data->appendLog(new LogEntry($this->debug, 'log', array('append to alerts')));
        $this->assertSame('append to alerts', $this->debug->data->get('alerts/0/args/0'));
    }

    /**
     * codebase currently doesn't ever use this... but it *could*
     *
     * @return void
     */
    public function testSetLogDestAutoSummary()
    {
        $this->debug->groupSummary();  // put ourself in a summary
        $this->debug->data->set('logDest', 'auto');
        $this->debug->data->appendLog(new LogEntry($this->debug, 'log', array('testSetLogDestAutoSummary')));
        self::assertSame('testSetLogDestAutoSummary', $this->debug->data->get('logSummary/0/0/args/0'));
    }
}
