<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\ObjectCache
 */
class ObjectCacheTest extends DebugTestFramework
{
    protected static $plugin;

    public static function setUpBeforeClass(): void
    {
        if (!\function_exists('get_option')) {
            require_once __DIR__ . '/mock_wordpress.php';
        }
        wp_reset_mock();
        self::resetDebug();
        self::$plugin = new \bdk\Debug\Framework\WordPress\ObjectCache();
        // self::$plugin->onBootstrap(new Event(self::$debug));
    }

    public function testGetSubscriptions()
    {
        self::assertInstanceOf('bdk\PubSub\SubscriberInterface', self::$plugin);
        self::assertSame([
            Debug::EVENT_OUTPUT => 'onOutput',
        ], self::$plugin->getSubscriptions());
    }

    public function testOnOutput()
    {
        $GLOBALS['wp_object_cache'] = (object) array(
            'cache' => array(
                'foo' => 'bar',
                'baz' => 'qux',
            ),
            'cache_hits' => 69,
            'cache_misses' => 42,
        );

        self::$plugin->onOutput(new Event(self::$debug));
        self::assertSame([
            array(
                'method' => 'log',
                'args' => ['Cache Hits', 69],
                'meta' => array(
                    'channel' => 'cache',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Cache Misses', 42],
                'meta' => array(
                    'channel' => 'cache',
                ),
            ),
            array(
                'method' => 'table',
                'args' => [
                    array(
                        'baz' => array(
                            'size' => '10 B',
                        ),
                        'foo' => array(
                            'size' => '10 B',
                        ),
                    ),
                ],
                'meta' => array(
                    'channel' => 'cache',
                    'sortable' => true,
                    'tableInfo' => array(
                        'class' => null,
                        'columns' => [
                            array(
                                'attribs' => array(
                                    'class' => ['no-quotes'],
                                ),
                                'total' => '20 B',
                                'key' => 'size',
                            ),
                        ],
                        'haveObjRow' => false,
                        'indexLabel' => null,
                        'rows' => array(),
                        'summary' => '',
                    ),
                ),
            ),
        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }
}
