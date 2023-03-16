<?php

namespace bdk\Test\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\SimpleCache;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\Debug\Mock\SimpleCache as SimpleCacheMock;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Collector\SimpleCache
 * @covers \bdk\Debug\Collector\SimpleCache\CallInfo
 */
class SimpleCacheTest extends DebugTestFramework
{
    private static $cache;

    public static function setUpBeforeClass(): void
    {
        $simpleCache = new SimpleCacheMock();
        self::$cache = new SimpleCache($simpleCache);
    }

    public static function tearDownAfterClass(): void
    {
        $debug = \bdk\Debug::getInstance();
        $debug->getChannel('SimpleCache')
            ->eventManager->unsubscribe(Debug::EVENT_OUTPUT, array(self::$cache, 'onDebugOutput'));
    }

    public function testCall()
    {
        self::$cache->nonInterfaceMethod();
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'nonInterfaceMethod() took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );

        self::$cache->nonInterfaceMethod('foo', 'bar');
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'nonInterfaceMethod("foo") took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testException()
    {
        try {
            self::$cache->nonInterfaceMethod('throw');
        } catch (\Exception $e) {
        }
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'nonInterfaceMethod("throw") took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testClear()
    {
        self::$cache->clear();
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'clear() took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testDelete()
    {
        self::$cache->delete('deleteName');
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'delete("deleteName") took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testDeleteMultiple()
    {
        self::$cache->deleteMultiple(array('ding','dang'));
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'deleteMultiple(["ding","dang"]) took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testGet()
    {
        self::$cache->get('dang');
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'get("dang") took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testGetMultiple()
    {
        self::$cache->getMultiple(array('ding', 'dang'));
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'getMultiple(["ding","dang"]) took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testGetLoggedActions()
    {
        $loggedActions = self::$cache->getLoggedActions();
        $this->assertContainsOnly(
            'bdk\\Debug\\Collector\\SimpleCache\\CallInfo',
            $loggedActions
        );
        $this->assertSame(array(
            'duration',
            'exception',
            'memoryUsage',
            'method',
            'keyOrKeys',
        ), \array_keys(\reset($loggedActions)->__debugInfo()));
    }

    public function testHas()
    {
        self::$cache->has('hasName');
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'has("hasName") took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testSet()
    {
        self::$cache->set('setName', 'setValue');
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'set("setName") took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testSetMultiple()
    {
        self::$cache->setMultiple(array(
            'ding' => 'foo',
            'dang' => 'bar',
        ));
        $this->testMethod(
            null,
            null,
            array(
                'entry' => \json_encode(array(
                    'method' => 'log',
                    'args' => array(
                        'setMultiple(["ding","dang"]) took %f %s',
                    ),
                    'meta' => array(
                        'channel' => 'general.SimpleCache',
                        'icon' => 'fa fa-cube',
                    ),
                )),
            )
        );
    }

    public function testDebugOutput()
    {
        self::$cache->onDebugOutput(new \bdk\PubSub\Event($this->debug));
        $summaryData = $this->debug->data->get('logSummary/0');
        $this->assertCount(5, $summaryData);
        $this->assertSame('SimpleCache info', $summaryData[0]['args'][0]);
    }
}
