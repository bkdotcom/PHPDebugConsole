<?php

use bdk\PubSub\Event;
use bdk\PubSub\Manager;
use bdk\PubSub\SubscriberInterface;

/**
 * PHPUnit tests for Debug class
 */
class ManagerTest extends \PHPUnit\Framework\TestCase
{
    /* Some pseudo events */
    const preFoo = 'pre.foo';
    const postFoo = 'post.foo';
    const preBar = 'pre.bar';
    const postBar = 'post.bar';

    /**
     * @var Manager
     */
    private $manager;

    private $subscriber;

    public function setUp()
    {
        $this->manager = $this->createManager();
        $this->subscriber = new TestSubscriber();
    }

    public function tearDown()
    {
        $this->manager = null;
        $this->subscriber = null;
    }

    protected function createManager()
    {
        return new Manager();
    }

    public function testInitialState()
    {
        $this->assertEquals(array(), $this->manager->getSubscribers());
        $this->assertFalse($this->manager->hasSubscribers(self::preFoo));
        $this->assertFalse($this->manager->hasSubscribers(self::postFoo));
    }

    public function testSubscribe()
    {
        $this->manager->subscribe('pre.foo', array($this->subscriber, 'preFoo'));
        $this->manager->subscribe('post.foo', array($this->subscriber, 'postFoo'));
        $this->assertTrue($this->manager->hasSubscribers());
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->assertTrue($this->manager->hasSubscribers(self::postFoo));
        $this->assertCount(1, $this->manager->getSubscribers(self::preFoo));
        $this->assertCount(1, $this->manager->getSubscribers(self::postFoo));
        $this->assertCount(2, $this->manager->getSubscribers());
    }

    public function testGetListenersSortsByPriority()
    {
        $subscriber1 = new TestSubscriber();
        $subscriber2 = new TestSubscriber();
        $subscriber3 = new TestSubscriber();
        $subscriber1->name = '1';
        $subscriber2->name = '2';
        $subscriber3->name = '3';

        $this->manager->subscribe('pre.foo', array($subscriber1, 'preFoo'), -10);
        $this->manager->subscribe('pre.foo', array($subscriber2, 'preFoo'), 10);
        $this->manager->subscribe('pre.foo', array($subscriber3, 'preFoo'));

        $expected = array(
            array($subscriber2, 'preFoo'),
            array($subscriber3, 'preFoo'),
            array($subscriber1, 'preFoo'),
        );

        $this->assertSame($expected, $this->manager->getSubscribers('pre.foo'));
    }

    public function testGetAllListenersSortsByPriority()
    {
        $subscriber1 = new TestSubscriber();
        $subscriber2 = new TestSubscriber();
        $subscriber3 = new TestSubscriber();
        $subscriber4 = new TestSubscriber();
        $subscriber5 = new TestSubscriber();
        $subscriber6 = new TestSubscriber();

        $this->manager->subscribe('pre.foo', $subscriber1, -10);
        $this->manager->subscribe('pre.foo', $subscriber2);
        $this->manager->subscribe('pre.foo', $subscriber3, 10);
        $this->manager->subscribe('post.foo', $subscriber4, -10);
        $this->manager->subscribe('post.foo', $subscriber5);
        $this->manager->subscribe('post.foo', $subscriber6, 10);

        $expected = array(
            'pre.foo' => array($subscriber3, $subscriber2, $subscriber1),
            'post.foo' => array($subscriber6, $subscriber5, $subscriber4),
        );

        $this->assertSame($expected, $this->manager->getSubscribers());
    }

    /*
    public function testGetListenerPriority()
    {
        $subscriber1 = new TestSubscriber();
        $subscriber2 = new TestSubscriber();

        $this->manager->subscribe('pre.foo', $subscriber1, -10);
        $this->manager->subscribe('pre.foo', $subscriber2);

        $this->assertSame(-10, $this->manager->getListenerPriority('pre.foo', $subscriber1));
        $this->assertSame(0, $this->manager->getListenerPriority('pre.foo', $subscriber2));
        $this->assertNull($this->manager->getListenerPriority('pre.bar', $subscriber2));
        $this->assertNull($this->manager->getListenerPriority('pre.foo', function () {}));
    }
    */

    public function testPublish()
    {
        $this->manager->subscribe('pre.foo', array($this->subscriber, 'preFoo'));
        $this->manager->subscribe('post.foo', array($this->subscriber, 'postFoo'));
        $this->manager->publish(self::preFoo);
        $this->assertTrue($this->subscriber->preFooInvoked);
        $this->assertFalse($this->subscriber->postFooInvoked);
        $this->assertInstanceOf('bdk\PubSub\Event', $this->manager->publish('noevent'));
        $this->assertInstanceOf('bdk\PubSub\Event', $this->manager->publish(self::preFoo));
        $event = new Event();
        $return = $this->manager->publish(self::preFoo, $event);
        $this->assertSame($event, $return);
    }

    public function testPublishForClosure()
    {
        $invoked = 0;
        $subscriber = function () use (&$invoked) {
            ++$invoked;
        };
        $this->manager->subscribe('pre.foo', $subscriber);
        $this->manager->subscribe('post.foo', $subscriber);
        $this->manager->publish(self::preFoo);
        $this->assertEquals(1, $invoked);
    }

    public function testStopEventPropagation()
    {
        $otherListener = new TestSubscriber();

        // postFoo() stops the propagation, so only one subscriber should
        // be executed
        // Manually set priority to enforce $this->subscriber to be called first
        $this->manager->subscribe('post.foo', array($this->subscriber, 'postFoo'), 10);
        $this->manager->subscribe('post.foo', array($otherListener, 'preFoo'));
        $this->manager->publish(self::postFoo);
        $this->assertTrue($this->subscriber->postFooInvoked);
        $this->assertFalse($otherListener->postFooInvoked);
    }

    public function testPublishByPriority()
    {
        $invoked = array();
        $subscriber1 = function () use (&$invoked) {
            $invoked[] = '1';
        };
        $subscriber2 = function () use (&$invoked) {
            $invoked[] = '2';
        };
        $subscriber3 = function () use (&$invoked) {
            $invoked[] = '3';
        };
        $this->manager->subscribe('pre.foo', $subscriber1, -10);
        $this->manager->subscribe('pre.foo', $subscriber2);
        $this->manager->subscribe('pre.foo', $subscriber3, 10);
        $this->manager->publish(self::preFoo);
        $this->assertEquals(array('3', '2', '1'), $invoked);
    }

    public function testUnsubscribe()
    {
        $this->manager->subscribe('pre.bar', $this->subscriber);
        $this->assertTrue($this->manager->hasSubscribers(self::preBar));
        $this->manager->unsubscribe('pre.bar', $this->subscriber);
        $this->assertFalse($this->manager->hasSubscribers(self::preBar));
        $this->manager->unsubscribe('notExists', $this->subscriber);
    }

    public function testAddSubscriberInterface()
    {
        $eventSubscriber = new TestSubscriberInterface();
        $this->manager->addSubscriberInterface($eventSubscriber);
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->assertTrue($this->manager->hasSubscribers(self::postFoo));
    }

    public function testAddSubscriberInterfaceWithPriorities()
    {
        $eventSubscriber = new TestSubscriberInterface();
        $this->manager->addSubscriberInterface($eventSubscriber);

        $eventSubscriber = new TestSubscriberInterfaceWithPriorities();
        $this->manager->addSubscriberInterface($eventSubscriber);

        $subscribers = $this->manager->getSubscribers('pre.foo');
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->assertCount(2, $subscribers);
        $this->assertInstanceOf('TestSubscriberInterfaceWithPriorities', $subscribers[0][0]);
    }

    public function testAddSubscriberInterfaceWithMultipleSubscribers()
    {
        $eventSubscriber = new TestSubscriberInterfaceWithMultipleSubscribers();
        $this->manager->addSubscriberInterface($eventSubscriber);

        $subscribers = $this->manager->getSubscribers('pre.foo');
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->assertCount(2, $subscribers);
        $this->assertEquals('preFoo2', $subscribers[0][1]);
    }

    public function testRemoveSubscriberInterface()
    {
        $eventSubscriber = new TestSubscriberInterface();
        $this->manager->addSubscriberInterface($eventSubscriber);
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->assertTrue($this->manager->hasSubscribers(self::postFoo));
        $this->manager->removeSubscriberInterface($eventSubscriber);
        $this->assertFalse($this->manager->hasSubscribers(self::preFoo));
        $this->assertFalse($this->manager->hasSubscribers(self::postFoo));
    }

    public function testRemoveSubscriberInterfaceWithPriorities()
    {
        $eventSubscriber = new TestSubscriberInterfaceWithPriorities();
        $this->manager->addSubscriberInterface($eventSubscriber);
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->manager->removeSubscriberInterface($eventSubscriber);
        $this->assertFalse($this->manager->hasSubscribers(self::preFoo));
    }

    public function testRemoveSubscriberInterfaceWithMultipleSubscribers()
    {
        $eventSubscriber = new TestSubscriberInterfaceWithMultipleSubscribers();
        $this->manager->addSubscriberInterface($eventSubscriber);
        $this->assertTrue($this->manager->hasSubscribers(self::preFoo));
        $this->assertCount(2, $this->manager->getSubscribers(self::preFoo));
        $this->manager->removeSubscriberInterface($eventSubscriber);
        $this->assertFalse($this->manager->hasSubscribers(self::preFoo));
    }

    public function testEventReceivesTheManagerInstanceAsArgument()
    {
        $subscriber = new TestWithManager();
        $this->manager->subscribe('test', array($subscriber, 'foo'));
        $this->assertNull($subscriber->name);
        $this->assertNull($subscriber->manager);
        $this->manager->publish('test');
        $this->assertEquals('test', $subscriber->name);
        $this->assertSame($this->manager, $subscriber->manager);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=62976
     *
     * This bug affects:
     *  - The PHP 5.3 branch for versions < 5.3.18
     *  - The PHP 5.4 branch for versions < 5.4.8
     *  - The PHP 5.5 branch is not affected
     */
    public function testWorkaroundForPhpBug62976()
    {
        $manager = $this->createManager();
        $manager->subscribe('bug.62976', new CallableClass());
        $manager->unsubscribe('bug.62976', function () {});
        $this->assertTrue($manager->hasSubscribers('bug.62976'));
    }

    public function testHasListenersWhenAddedCallbackListenerIsRemoved()
    {
        $subscriber = function () {};
        $this->manager->subscribe('foo', $subscriber);
        $this->manager->unsubscribe('foo', $subscriber);
        $this->assertFalse($this->manager->hasSubscribers());
    }

    public function testGetListenersWhenAddedCallbackListenerIsRemoved()
    {
        $subscriber = function () {};
        $this->manager->subscribe('foo', $subscriber);
        $this->manager->unsubscribe('foo', $subscriber);
        $this->assertSame(array(), $this->manager->getSubscribers());
    }

    public function testHasListenersWithoutEventsReturnsFalseAfterHasListenersWithEventHasBeenCalled()
    {
        $this->assertFalse($this->manager->hasSubscribers('foo'));
        $this->assertFalse($this->manager->hasSubscribers());
    }

    public function testHasListenersIsLazy()
    {
        $called = 0;
        $subscriber = array(function () use (&$called) { ++$called; }, 'onFoo');
        $this->manager->subscribe('foo', $subscriber);
        $this->assertTrue($this->manager->hasSubscribers());
        $this->assertTrue($this->manager->hasSubscribers('foo'));
        $this->assertSame(0, $called);
    }

    public function testPublishLazyListener()
    {
        $called = 0;
        $factory = function () use (&$called) {
            ++$called;

            return new TestWithManager();
        };
        $this->manager->subscribe('foo', array($factory, 'foo'));
        $this->assertSame(0, $called);
        $this->manager->publish('foo', new Event());
        $this->manager->publish('foo', new Event());
        $this->assertSame(1, $called);
    }

    public function testRemoveFindsLazyListeners()
    {
        $test = new TestWithManager();
        $factory = function () use ($test) { return $test; };

        $this->manager->subscribe('foo', array($factory, 'foo'));
        $this->assertTrue($this->manager->hasSubscribers('foo'));
        $this->manager->unsubscribe('foo', array($test, 'foo'));
        $this->assertFalse($this->manager->hasSubscribers('foo'));

        $this->manager->subscribe('foo', array($test, 'foo'));
        $this->assertTrue($this->manager->hasSubscribers('foo'));
        $this->manager->unsubscribe('foo', array($factory, 'foo'));
        $this->assertFalse($this->manager->hasSubscribers('foo'));
    }

    /*
    public function testPriorityFindsLazyListeners()
    {
        $test = new TestWithManager();
        $factory = function () use ($test) { return $test; };

        $this->manager->subscribe('foo', array($factory, 'foo'), 3);
        $this->assertSame(3, $this->manager->getListenerPriority('foo', array($test, 'foo')));
        $this->manager->unsubscribe('foo', array($factory, 'foo'));

        $this->manager->subscribe('foo', array($test, 'foo'), 5);
        $this->assertSame(5, $this->manager->getListenerPriority('foo', array($factory, 'foo')));
    }
    */

    public function testGetLazyListeners()
    {
        $test = new TestWithManager();
        $factory = function () use ($test) { return $test; };

        $this->manager->subscribe('foo', array($factory, 'foo'), 3);
        $this->assertSame(array(array($test, 'foo')), $this->manager->getSubscribers('foo'));

        $this->manager->unsubscribe('foo', array($test, 'foo'));
        $this->manager->subscribe('bar', array($factory, 'foo'), 3);
        $this->assertSame(array('bar' => array(array($test, 'foo'))), $this->manager->getSubscribers());
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}

class TestSubscriber
{
    public $preFooInvoked = false;
    public $postFooInvoked = false;

    /* Subscribe methods */

    public function preFoo(Event $e)
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(Event $e)
    {
        $this->postFooInvoked = true;

        $e->stopPropagation();
    }
}

class TestWithManager
{
    public $name;
    public $manager;

    public function foo(Event $e, $name, $manager)
    {
        $this->name = $name;
        $this->manager = $manager;
    }
}

class TestSubscriberInterface implements SubscriberInterface
{
    public function getSubscriptions()
    {
        return array('pre.foo' => 'preFoo', 'post.foo' => 'postFoo');
    }
}

class TestSubscriberInterfaceWithPriorities implements SubscriberInterface
{
    public function getSubscriptions()
    {
        return array(
            'pre.foo' => array('preFoo', 10),
            'post.foo' => array('postFoo'),
        );
    }
}

class TestSubscriberInterfaceWithMultipleSubscribers implements SubscriberInterface
{
    public function getSubscriptions()
    {
        return array('pre.foo' => array(
            array('preFoo1'),
            array('preFoo2', 10),
        ));
    }
}
