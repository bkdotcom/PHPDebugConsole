<?php

namespace bdk\Test\PubSub;

use bdk\PubSub\Event;
use bdk\PubSub\Manager;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for PubSub/Manager
 *
 * @covers \bdk\PubSub\AbstractManager
 * @covers \bdk\PubSub\InterfaceManager
 * @covers \bdk\PubSub\Manager
 * @uses   \bdk\PubSub\Event
 */
class ManagerTest extends TestCase
{
    /*
        Some pseudo events
    */
    const PRE_FOO = 'pre.foo';
    const POST_FOO = 'post.foo';
    const PRE_BAR = 'pre.bar';
    const POST_BAR = 'post.bar';

    /**
     * @var Manager
     */
    private $manager;

    private $testSubscriber;

    /**
     * This method is called before a test is executed.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->manager = $this->createManager();
        $this->testSubscriber = new Fixture\Subscriber();
    }

    /**
     * This method is called after a test is executed.
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->manager = null;
        $this->testSubscriber = null;
    }

    public function testShutdownEvent()
    {
        $output = array();
        $returnVal = 0;
        \exec('php ' . __DIR__ . '/shutdownEvent.php', $output, $returnVal);
        self::assertSame(
            'shutdown: bdk\PubSub\Event php.shutdown bdk\PubSub\Manager',
            \implode('', $output),
            'shutdown event test failed'
        );
        self::assertSame(0, $returnVal);
    }

    public function testAssertCallable()
    {
        $this->manager->subscribe(self::PRE_FOO, array(function () {
            // this closure lazy-loads the subscriber object
            return $this->testSubscriber;
        }, 'preFoo'), PHP_INT_MAX);

        $caughtException = false;
        try {
            $this->manager->subscribe(self::PRE_FOO, 42);
        } catch (\InvalidArgumentException $e) {
            $caughtException = true;
        }
        self::assertTrue($caughtException);
    }

    public function testEventReceivesSubscriberReturn()
    {
        $this->manager->subscribe('foo', static function () {
            return 'return value';
        });

        // populate return
        $event = $this->manager->publish('foo', null, array(
            'return' => null,
        ));
        self::assertSame('return value', $event['return']);

        // don't populate if already populated
        $event = $this->manager->publish('foo', null, array(
            'return' => 0,
        ));
        self::assertSame(0, $event['return']);

        // don't populate if return isn't defined
        $event = $this->manager->publish('foo', null, array(
        ));
        self::assertSame(null, $event['return']);
    }

    public function testInvokable()
    {
        $str = 'testInvokable1';
        $invokable = new Fixture\Invokable($str);
        $this->manager->subscribe($str, $invokable);
        \ob_start();
        $this->manager->publish($str);
        $output = \ob_get_clean();
        self::assertSame($str, $output);

        $str = 'testInvokable2';
        $this->manager->subscribe($str, array(static function () use ($str) {
            // this closure lazy-loads the subscriber object
            return new Fixture\Invokable($str);
        }), PHP_INT_MAX);
        \ob_start();
        $this->manager->publish($str);
        $output = \ob_get_clean();
        self::assertSame($str, $output);
    }

    public function testInitialState()
    {
        self::assertEquals(array(), $this->manager->getSubscribers());
        self::assertFalse($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertFalse($this->manager->hasSubscribers(self::POST_FOO));
    }

    public function testSubscribe()
    {
        $this->manager->subscribe(self::PRE_FOO, array($this->testSubscriber, 'preFoo'));
        $this->manager->subscribe(self::POST_FOO, array($this->testSubscriber, 'postFoo'));
        self::assertTrue($this->manager->hasSubscribers());
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertTrue($this->manager->hasSubscribers(self::POST_FOO));
        self::assertCount(1, $this->manager->getSubscribers(self::PRE_FOO));
        self::assertCount(1, $this->manager->getSubscribers(self::POST_FOO));
        self::assertCount(2, $this->manager->getSubscribers());
    }

    public function testSubscribeOnce()
    {
        $callable1 = array($this->testSubscriber, 'preFoo');
        $callable2 = array($this->testSubscriber, 'test');
        $this->manager->subscribe(self::PRE_FOO, $callable2, 0, false);
        $this->manager->subscribe(self::PRE_FOO, $callable1, 0, true);
        self::assertSame(array(
            array(
                'callable' => $callable2,
                'onlyOnce' => false,
                'priority' => 0,
            ),
            array(
                'callable' => $callable1,
                'onlyOnce' => true,
                'priority' => 0,
            ),
        ), $this->manager->getSubscribers(self::PRE_FOO));
        $this->manager->publish(self::PRE_FOO);
        self::assertSame(1, $this->testSubscriber->preFooInvoked);
        self::assertSame(array(
            array(
                'callable' => $callable2,
                'onlyOnce' => false,
                'priority' => 0,
            ),
        ), $this->manager->getSubscribers(self::PRE_FOO));
    }

    public function testSubscribeFromSubscriber()
    {
        $called = array();
        $this->manager->subscribe('eventa', static function (Event $e, $eventName, Manager $manager) use (&$called) {
            $called[] ='eventa1';
            $manager->subscribe('eventb', static function (Event $e, $eventName, Manager $manager) use (&$called) {
                $called[] = 'eventb1';
                $manager->subscribe('eventa', static function (Event $e) use (&$called) {
                    $called[] = 'eventa2';
                });
                $manager->subscribe('eventa', static function (Event $e) use (&$called) {
                    $called[] = 'eventa3';
                }, 1);
            });
            $manager->publish('eventb');
        });
        $this->manager->publish('eventa');
        self::assertSame(array(
            'eventa1',
            'eventb1',
            'eventa3',
            'eventa2',
        ), $called);
    }

    public function testGetListenersSortsByPriority()
    {
        $subscriber1 = new Fixture\Subscriber();
        $subscriber2 = new Fixture\Subscriber();
        $subscriber3 = new Fixture\Subscriber();
        $subscriber1->name = '1';
        $subscriber2->name = '2';
        $subscriber3->name = '3';

        $this->manager->subscribe(self::PRE_FOO, array($subscriber1, 'preFoo'), -10);
        $this->manager->subscribe(self::PRE_FOO, array($subscriber2, 'preFoo'), 10);
        $this->manager->subscribe(self::PRE_FOO, array($subscriber3, 'preFoo'));

        $expected = array(
            array(
                'callable' => array($subscriber2, 'preFoo'),
                'onlyOnce' => false,
                'priority' => 10,
            ),
            array(
                'callable' => array($subscriber3, 'preFoo'),
                'onlyOnce' => false,
                'priority' => 0,
            ),
            array(
                'callable' => array($subscriber1, 'preFoo'),
                'onlyOnce' => false,
                'priority' => -10,
            ),
        );

        self::assertSame($expected, $this->manager->getSubscribers(self::PRE_FOO));
    }

    public function testGetAllListenersSortsByPriority()
    {
        $subscriber1 = array(new Fixture\Subscriber(), 'preFoo');
        $subscriber2 = array(new Fixture\Subscriber(), 'preFoo');
        $subscriber3 = array(new Fixture\Subscriber(), 'preFoo');
        $subscriber4 = array(new Fixture\Subscriber(), 'postFoo');
        $subscriber5 = array(new Fixture\Subscriber(), 'postFoo');
        $subscriber6 = array(new Fixture\Subscriber(), 'postFoo');

        $this->manager->subscribe(self::PRE_FOO, $subscriber1, -10);
        $this->manager->subscribe(self::PRE_FOO, $subscriber2);
        $this->manager->subscribe(self::PRE_FOO, $subscriber3, 10);
        $this->manager->subscribe(self::POST_FOO, $subscriber4, -10);
        $this->manager->subscribe(self::POST_FOO, $subscriber5);
        $this->manager->subscribe(self::POST_FOO, $subscriber6, 10);

        $expected = array(
            self::PRE_FOO => array(
                array(
                    'callable' => $subscriber3,
                    'onlyOnce' => false,
                    'priority' => 10,
                ),
                array(
                    'callable' => $subscriber2,
                    'onlyOnce' => false,
                    'priority' => 0,
                ),
                array(
                    'callable' => $subscriber1,
                    'onlyOnce' => false,
                    'priority' => -10,
                ),
            ),
            self::POST_FOO => array(
                array(
                    'callable' => $subscriber6,
                    'onlyOnce' => false,
                    'priority' => 10,
                ),
                array(
                    'callable' => $subscriber5,
                    'onlyOnce' => false,
                    'priority' => 0,
                ),
                array(
                    'callable' => $subscriber4,
                    'onlyOnce' => false,
                    'priority' => -10,
                ),
            ),
        );

        self::assertSame($expected, $this->manager->getSubscribers());
    }

    /*
    public function testGetListenerPriority()
    {
        $subscriber1 = new Fixture\Subscriber();
        $subscriber2 = new Fixture\Subscriber();

        $this->manager->subscribe('pre.foo', $subscriber1, -10);
        $this->manager->subscribe('pre.foo', $subscriber2);

        self::assertSame(-10, $this->manager->getListenerPriority('pre.foo', $subscriber1));
        self::assertSame(0, $this->manager->getListenerPriority('pre.foo', $subscriber2));
        self::assertNull($this->manager->getListenerPriority('pre.bar', $subscriber2));
        self::assertNull($this->manager->getListenerPriority('pre.foo', function () {}));
    }
    */

    public function testPublish()
    {
        $this->manager->subscribe(self::PRE_FOO, array($this->testSubscriber, 'preFoo'));
        $this->manager->subscribe(self::POST_FOO, array($this->testSubscriber, 'postFoo'));
        $this->manager->publish(self::PRE_FOO);
        self::assertSame(1, $this->testSubscriber->preFooInvoked);
        self::assertSame(0, $this->testSubscriber->postFooInvoked);
        self::assertInstanceOf('bdk\\PubSub\\Event', $this->manager->publish('noevent'));
        self::assertInstanceOf('bdk\\PubSub\\Event', $this->manager->publish(self::PRE_FOO));
        $event = new Event();
        $return = $this->manager->publish(self::PRE_FOO, $event);
        self::assertSame($event, $return);
    }

    public function testPublishForClosure()
    {
        $invoked = 0;
        $subscriber = static function () use (&$invoked) {
            ++$invoked;
        };
        $this->manager->subscribe(self::PRE_FOO, $subscriber);
        $this->manager->subscribe(self::POST_FOO, $subscriber);
        $this->manager->publish(self::PRE_FOO);
        self::assertEquals(1, $invoked);
    }

    public function testStopEventPropagation()
    {
        $otherListener = new Fixture\Subscriber();

        // postFoo() stops the propagation, so only one subscriber should
        // be executed
        // Manually set priority to enforce $this->testSubscriber to be called first
        $this->manager->subscribe(self::POST_FOO, array($this->testSubscriber, 'postFoo'), 10);
        $this->manager->subscribe(self::POST_FOO, array($otherListener, 'preFoo'));
        $this->manager->publish(self::POST_FOO);
        self::assertSame(1, $this->testSubscriber->postFooInvoked);
        self::assertSame(0, $otherListener->postFooInvoked);
    }

    public function testPublishByPriority()
    {
        $invoked = array();
        $subscriber1 = static function () use (&$invoked) {
            $invoked[] = '1';
        };
        $subscriber2 = static function () use (&$invoked) {
            $invoked[] = '2';
        };
        $subscriber3 = static function () use (&$invoked) {
            $invoked[] = '3';
        };
        $this->manager->subscribe(self::PRE_FOO, $subscriber1, -10);
        $this->manager->subscribe(self::PRE_FOO, $subscriber2);
        $this->manager->subscribe(self::PRE_FOO, $subscriber3, 10);
        $this->manager->publish(self::PRE_FOO);
        self::assertEquals(array('3', '2', '1'), $invoked);
    }

    public function testUnsubscribe()
    {
        $this->manager->subscribe(self::PRE_BAR, array($this->testSubscriber, 'preFoo'));
        self::assertTrue($this->manager->hasSubscribers(self::PRE_BAR));
        $this->manager->unsubscribe(self::PRE_BAR, array($this->testSubscriber, 'preFoo'));
        self::assertFalse($this->manager->hasSubscribers(self::PRE_BAR));
        $this->manager->unsubscribe('notExists', array($this->testSubscriber, 'preFoo'));
    }

    public function testUnubscribeFromSubscriber()
    {
        $called = array();
        $callables = array(
            'eventa2' => static function () use (&$called) {
                $called[] = 'eventa2';
            },
            'eventa3' => static function () use (&$called) {
                $called[] = 'eventa3';
            },
            'eventa4' => static function () use (&$called) {
                $called[] = 'eventa4';
            },
        );
        $this->manager->subscribe('eventa', static function (Event $e, $eventName, Manager $manager) use (&$called, $callables) {
            $called[] = 'eventa1';
            $manager->subscribe('eventb', static function (Event $e, $eventName, Manager $manager) use (&$called, $callables) {
                $called[] = 'eventb1';
                $manager->unsubscribe('eventa', $callables['eventa3']);
                $manager->unsubscribe('eventa', $callables['eventa2']);
            });
            $manager->publish('eventb');
        });
        $this->manager->subscribe('eventa', $callables['eventa2']);
        $this->manager->subscribe('eventa', $callables['eventa3']);
        $this->manager->subscribe('eventa', $callables['eventa4']);
        $this->manager->publish('eventa');
        self::assertSame(array(
            'eventa1',
            'eventb1',
            'eventa4',
        ), $called);
    }

    public function testAddSubscriberInterface()
    {
        $eventSubscriber = new Fixture\SubscriberInterfaceTest();
        $this->manager->addSubscriberInterface($eventSubscriber);
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertTrue($this->manager->hasSubscribers(self::POST_FOO));
    }

    public function testAddSubscriberInterfaceWithClosure()
    {
        $called = array();
        $eventSubscriber = new Fixture\SubscriberInterfaceTest();
        $eventSubscriber->getSubscriptionsReturn = array(
            'dingle' => static function (Event $event) use (&$called) {
                $called[] = 'dingle';
            },
            'berry' => array(
                static function (Event $event) use (&$called) {
                    $called[] = 'berry1';
                },
                array(static function (Event $event) use (&$called) {
                    // higher priority / only called once
                    $called[] = 'berry2';
                }, 1, true),
            ),
        );
        $this->manager->addSubscriberInterface($eventSubscriber);
        $this->manager->publish('dingle');
        $this->manager->publish('berry');
        self::assertSame(array(
            'dingle',
            'berry2',
            'berry1',
        ), $called);
        self::assertCount(1, $this->manager->getSubscribers('berry'));
    }

    public function testAddSubscriberInterfaceWithPriorities()
    {
        $eventSubscriber = new Fixture\SubscriberInterfaceTest();
        $this->manager->addSubscriberInterface($eventSubscriber);

        $eventSubscriber = new Fixture\SubscriberInterfaceWithPriorities();
        $this->manager->addSubscriberInterface($eventSubscriber);

        $subscribers = $this->manager->getSubscribers(self::PRE_FOO);
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertCount(2, $subscribers);
        self::assertInstanceOf('bdk\\Test\\PubSub\\Fixture\\SubscriberInterfaceWithPriorities', $subscribers[0]['callable'][0]);
    }

    public function testAddSubscriberInterfaceWithMultipleSubscribers()
    {
        $eventSubscriber = new Fixture\SubscriberInterfaceWithMultipleSubscribers();
        $this->manager->addSubscriberInterface($eventSubscriber);

        $subscribers = $this->manager->getSubscribers(self::PRE_FOO);
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertCount(2, $subscribers);
        self::assertEquals('preFoo2', $subscribers[0]['callable'][1]);
    }

    public function testSubscriberInterfaceException()
    {
        $caughtException = false;
        try {
            $eventSubscriber = new Fixture\SubscriberInterfaceTest();
            $eventSubscriber->getSubscriptionsReturn = new \stdClass();
            $this->manager->addSubscriberInterface($eventSubscriber);
        } catch (\RuntimeException $e) {
            $caughtException = true;
            self::assertSame('Expected array from bdk\\Test\\PubSub\\Fixture\\SubscriberInterfaceTest::getSubscriptions().  Got stdClass', $e->getMessage());
        }
        self::assertTrue($caughtException, 'Expected RuntimeException');
    }

    public function testSubscriberInterfaceException2()
    {
        $caughtException = false;
        try {
            $eventSubscriber = new Fixture\SubscriberInterfaceTest();
            $eventSubscriber->getSubscriptionsReturn = array(
                'eventName' => false,
            );
            $this->manager->addSubscriberInterface($eventSubscriber);
        } catch (\RuntimeException $e) {
            $caughtException = true;
            self::assertSame('bdk\\Test\\PubSub\\Fixture\\SubscriberInterfaceTest::getSubscriptions():  Unexpected subscriber(s) defined for eventName', $e->getMessage());
        }
        self::assertTrue($caughtException, 'Expected RuntimeException');
    }

    public function testSubscriberInterfaceException3()
    {
        $caughtException = false;
        try {
            $eventSubscriber = new Fixture\SubscriberInterfaceTest();
            $eventSubscriber->getSubscriptionsReturn = array(
                'eventName' => array(
                    false,
                ),
            );
            $this->manager->addSubscriberInterface($eventSubscriber);
        } catch (\RuntimeException $e) {
            $caughtException = true;
            self::assertSame('bdk\\Test\\PubSub\\Fixture\\SubscriberInterfaceTest::getSubscriptions():  Unexpected subscriber(s) defined for eventName', $e->getMessage());
        }
        self::assertTrue($caughtException, 'Expected RuntimeException');
    }

    public function testRemoveSubscriberInterface()
    {
        $eventSubscriber = new Fixture\SubscriberInterfaceTest();
        $this->manager->addSubscriberInterface($eventSubscriber);
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertTrue($this->manager->hasSubscribers(self::POST_FOO));
        $this->manager->removeSubscriberInterface($eventSubscriber);
        self::assertFalse($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertFalse($this->manager->hasSubscribers(self::POST_FOO));
    }

    public function testRemoveSubscriberInterfaceWithPriorities()
    {
        $eventSubscriber = new Fixture\SubscriberInterfaceWithPriorities();
        $this->manager->addSubscriberInterface($eventSubscriber);
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        $this->manager->removeSubscriberInterface($eventSubscriber);
        self::assertFalse($this->manager->hasSubscribers(self::PRE_FOO));
    }

    public function testRemoveSubscriberInterfaceWithMultipleSubscribers()
    {
        $eventSubscriber = new Fixture\SubscriberInterfaceWithMultipleSubscribers();
        $this->manager->addSubscriberInterface($eventSubscriber);
        self::assertTrue($this->manager->hasSubscribers(self::PRE_FOO));
        self::assertCount(2, $this->manager->getSubscribers(self::PRE_FOO));
        $this->manager->removeSubscriberInterface($eventSubscriber);
        self::assertFalse($this->manager->hasSubscribers(self::PRE_FOO));
    }

    public function testEventReceivesTheManagerInstanceAsArgument()
    {
        $subscriber = new Fixture\WithManager();
        $this->manager->subscribe('test', array($subscriber, 'foo'));
        self::assertNull($subscriber->name);
        self::assertNull($subscriber->manager);
        $this->manager->publish('test');
        self::assertSame('test', $subscriber->name);
        self::assertSame($this->manager, $subscriber->manager);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=62976
     *
     * This bug affects:
     *  - The PHP 5.3 branch for versions < 5.3.18
     *  - The PHP 5.4 branch for versions < 5.4.8
     *  - The PHP 5.5 branch is not affected
     *
     * @return void
     */
    public function testWorkaroundForPhpBug62976()
    {
        $manager = $this->createManager();
        $manager->subscribe('bug.62976', new Fixture\CallableClass());
        $manager->unsubscribe('bug.62976', static function () {
        });
        self::assertTrue($manager->hasSubscribers('bug.62976'));
    }

    public function testHasListenersWhenAddedCallbackListenerIsRemoved()
    {
        $subscriber = static function () {
        };
        $this->manager->subscribe('foo', $subscriber);
        $this->manager->unsubscribe('foo', $subscriber);
        self::assertFalse($this->manager->hasSubscribers());
    }

    public function testGetListenersWhenAddedCallbackListenerIsRemoved()
    {
        $subscriber = static function () {
        };
        $this->manager->subscribe('foo', $subscriber);
        $this->manager->unsubscribe('foo', $subscriber);
        self::assertSame(array(), $this->manager->getSubscribers());
    }

    public function testHasListenersWithoutEventsReturnsFalseAfterHasListenersWithEventHasBeenCalled()
    {
        self::assertFalse($this->manager->hasSubscribers('foo'));
        self::assertFalse($this->manager->hasSubscribers());
    }

    public function testHasListenersIsLazy()
    {
        $called = 0;
        $subscriber = array(static function () use (&$called) {
            ++$called;
        }, 'onFoo');
        $this->manager->subscribe('foo', $subscriber);
        self::assertTrue($this->manager->hasSubscribers());
        self::assertTrue($this->manager->hasSubscribers('foo'));
        self::assertSame(0, $called);
    }

    public function testPublishLazyListener()
    {
        $called = 0;
        $factory = static function () use (&$called) {
            ++$called;
            return new Fixture\Subscriber();
        };
        $this->manager->subscribe(self::PRE_FOO, array($factory, 'preFoo'));
        self::assertSame(0, $called);
        $this->manager->publish(self::PRE_FOO, new Event());
        $this->manager->publish(self::PRE_FOO, new Event());
        self::assertSame(1, $called);
    }

    public function testRemoveFindsLazyListeners()
    {
        $test = new Fixture\Subscriber();
        $factory = static function () use ($test) {
            return $test;
        };

        $this->manager->subscribe('foo', array($factory, 'test'));
        self::assertTrue($this->manager->hasSubscribers('foo'));
        $this->manager->unsubscribe('foo', array($test, 'test'));
        self::assertFalse($this->manager->hasSubscribers('foo'));

        $this->manager->subscribe('foo', array($test, 'test'));
        self::assertTrue($this->manager->hasSubscribers('foo'));
        $this->manager->unsubscribe('foo', array($factory, 'test'));
        self::assertFalse($this->manager->hasSubscribers('foo'));
    }

    /*
    public function testPriorityFindsLazyListeners()
    {
        $test = new Fixture\WithManager();
        $factory = function () use ($test) { return $test; };

        $this->manager->subscribe('foo', array($factory, 'foo'), 3);
        self::assertSame(3, $this->manager->getListenerPriority('foo', array($test, 'foo')));
        $this->manager->unsubscribe('foo', array($factory, 'foo'));

        $this->manager->subscribe('foo', array($test, 'foo'), 5);
        self::assertSame(5, $this->manager->getListenerPriority('foo', array($factory, 'foo')));
    }
    */

    public function testGetLazyListeners()
    {
        $test = new Fixture\Subscriber();
        $factory = static function () use ($test) {
            return $test;
        };

        $this->manager->subscribe('foo', array($factory, 'test'), 3);
        self::assertSame(array(
            array(
                'callable' => array($test, 'test'),
                'onlyOnce' => false,
                'priority' => 3,
            ),
        ), $this->manager->getSubscribers('foo'));

        $this->manager->unsubscribe('foo', array($test, 'test'));

        $this->manager->subscribe('bar', array($factory, 'test'), 3);
        self::assertSame(array(
            'bar' => array(
                array(
                    'callable' => array($test, 'test'),
                    'onlyOnce' => false,
                    'priority' => 3,
                ),
            ),
        ), $this->manager->getSubscribers());
    }

    protected function createManager()
    {
        return new Manager();
    }
}
