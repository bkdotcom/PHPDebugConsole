<?php

namespace bdk\DebugTests\Collector;

use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class SimpleCacheTest extends DebugTestFramework
{
    private static $cache;

    public static function setUpBeforeClass(): void
    {
        $simpleCache = new \bdk\DebugTests\Mock\SimpleCache();
        self::$cache = new \bdk\Debug\Collector\SimpleCache($simpleCache);
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
                        'get("dang") took %f %s'
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
