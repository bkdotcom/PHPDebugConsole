<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\ObjectCache
 */
class ObjectCacheTest extends DebugTestFramework
{
    use AssertionTrait;

    protected static $plugin;

    public static function setUpBeforeClass(): void
    {
        if (PHP_VERSION_ID < 70000) {
            self::markTestSkipped('Wordpress requires PHP 7.0+');
            return;
        }
        parent::setUpBeforeClass();
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
                        'debug' => Abstracter::ABSTRACTION,
                        'footer' => [
                            array(
                                'html' => '',
                                'value' => null,
                            ),
                            '20 B',
                        ],
                        'header' => [
                            '',
                            'size',
                        ],
                        'meta' => array(
                            'class' => null,
                            'columns' => [
                                array(
                                    'attribs' => array(
                                        'class' => ['t_key'],
                                        'scope' => 'row',
                                    ),
                                    'key' => \bdk\Table\Factory::KEY_INDEX,
                                    'tagName' => 'th',
                                ),
                                array(
                                    'attribs' => array('class' => ['no-quotes']),
                                    'key' => 'size',
                                ),
                            ],
                            'haveObjectRow' => false,
                            'sortable' => true,
                        ),
                        'rows' => [
                            [
                                'baz',
                                '10 B',
                            ],
                            [
                                'foo',
                                '10 B',
                            ],
                        ],
                        'type' => 'table',
                        'value' => null,
                    ),
                ],
                'meta' => array(
                    'channel' => 'cache',
                ),
            ),
        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }
}
