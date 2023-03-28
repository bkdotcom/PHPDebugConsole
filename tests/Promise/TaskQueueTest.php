<?php

namespace bdk\Test\Promise;

use bdk\Promise\TaskQueue;
use bdk\Test\Promise\PropertyHelper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers bdk\Promise\TaskQueue
 */
class TaskQueueTest extends TestCase
{
    protected $classes = array(
        'TaskQueue' => 'bdk\\Promise\\TaskQueue',
    );

    public function testConstruct()
    {
        $queue = new TaskQueue(false);
        $this->assertInstanceOf($this->classes['TaskQueue'], $queue);
    }

    public function testDisableShutdown()
    {
        $queue = new TaskQueue();
        $this->assertTrue(PropertyHelper::get($queue, 'enableShutdown'));
        $queue->disableShutdown();
        $this->assertFalse(PropertyHelper::get($queue, 'enableShutdown'));

        $called = array();
        $queue->add(static function () use (&$called) {
            $called[] = 'a';
        });

        $refMethod = new ReflectionMethod($queue, 'onShutdown');
        $refMethod->setAccessible(true);
        $refMethod->invoke($queue);

        $this->assertSame(array(), $called);
    }

    public function testKnowsIfEmpty()
    {
        $queue = new TaskQueue(false);
        $this->assertTrue($queue->isEmpty());
    }

    public function testKnowsIfFull()
    {
        $queue = new TaskQueue(false);
        $queue->add(static function () {
        });
        $this->assertFalse($queue->isEmpty());
    }

    public function testExecutesTasksInOrder()
    {
        $queue = new TaskQueue(false);
        $called = array();
        $queue->add(static function () use (&$called) {
            $called[] = 'a';
        });
        $queue->add(static function () use (&$called) {
            $called[] = 'b';
        });
        $queue->add(static function () use (&$called) {
            $called[] = 'c';
        });
        $queue->run();
        $this->assertSame(['a', 'b', 'c'], $called);
    }

    public function testOnShutdown()
    {
        $queue = new TaskQueue();
        $called = array();
        $queue->add(static function () use (&$called) {
            $called[] = 'a';
        });
        $queue->add(static function () use (&$called) {
            $called[] = 'b';
        });
        $queue->add(static function () use (&$called) {
            $called[] = 'c';
        });
        $refMethod = new ReflectionMethod($queue, 'onShutdown');
        $refMethod->setAccessible(true);
        $refMethod->invoke($queue);
        $this->assertSame(['a', 'b', 'c'], $called);
    }
}
