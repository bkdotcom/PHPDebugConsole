<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\Hooks
 */
class HooksTest extends DebugTestFramework
{
    use AssertionTrait;

    protected static $plugin;

    public static function setUpBeforeClass(): void
    {
        if (!\function_exists('get_option')) {
            require_once __DIR__ . '/mock_wordpress.php';
        }
        wp_reset_mock();
        self::resetDebug();
        self::$plugin = new \bdk\Debug\Framework\WordPress\Hooks();
        // self::$plugin->onBootstrap(new Event(self::$debug));
    }

    public function testGetSubscriptions()
    {
        self::assertInstanceOf('bdk\PubSub\SubscriberInterface', self::$plugin);
        self::assertSame([
            // Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_OUTPUT => 'onOutput',
        ], self::$plugin->getSubscriptions());
    }

    /*
    public function testOnBootstrap()
    {
        wp_reset_mock();
        self::$plugin->onBootstrap(new Event(self::$debug));
        $objectHash = \spl_object_hash(self::$plugin);
        self::assertSame(array(
            'all' => [[$objectHash, 'onHook']],
        ), $GLOBALS['wp_actions_filters']['actions']);
        self::assertSame([], $GLOBALS['wp_actions_filters']['filters']);
    }
    */

    /**
     * @doesNotPerformAssertions
     *
     * @dataProvider providerOnHook
     */
    public function testOnHook($hook, $isFilter = false)
    {
        $isFilter
            ? $this->apply_filter($hook)
            : self::$plugin->onHook($hook);
    }

    public function testOnOutput()
    {
        self::$plugin->onOutput(new Event(self::$debug));
        self::assertSame(array(
            'method' => 'table',
            'args' => [
                array(
                    'bar' => array(
                        'isFilter' => false,
                        'count' => 1,
                    ),
                    'baz' => array(
                        'isFilter' => false,
                        'count' => 1,
                    ),
                    'dang' => array(
                        'isFilter' => true,
                        'count' => 1,
                    ),
                    'ding' => array(
                        'isFilter' => true,
                        'count' => 1,
                    ),
                    'dong' => array(
                        'isFilter' => true,
                        'count' => 1,
                    ),
                    'foo' => array(
                        'isFilter' => false,
                        'count' => 3,
                    ),
                ),
            ],
            'meta' => array(
                'caption' => 'Hooks',
                'channel' => 'hooks',
                'sortable' => true,
                'tableInfo' => array(
                    'class' => null,
                    'columns' => [
                        array(
                            'attribs' => array(
                                'class' => ['text-center'],
                            ),
                            'falseAs' => '',
                            'trueAs' => '<i class="fa fa-check"></i>',
                            'key' => 'isFilter',
                        ),
                        array(
                            'key' => 'count',
                            'total' => 8,
                        ),
                    ],
                    'haveObjRow' => false,
                    'indexLabel' => null,
                    'rows' => array(),
                    'summary' => '',
                ),
            ),
        ), $this->helper->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    public function testSetCfg()
    {
        // disable
        self::$plugin->setCfg(array(
            'enabled' => false,
        ));
        self::assertSame(array(
            'actions' => array(
                'all' => [],
            ),
            'filters' => array(),
        ), $GLOBALS['wp_actions_filters']);

        // enable
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(
                'all' => [
                    [self::$plugin, 'onHook'],
                ],
            ),
            'filters' => array(),
        ), $GLOBALS['wp_actions_filters']);

        // no change
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(
                'all' => [
                    [self::$plugin, 'onHook'],
                ],
            ),
            'filters' => array(),
        ), $GLOBALS['wp_actions_filters']);
    }

    public static function providerOnHook()
    {
        return array(
            ['foo'],
            ['ding', true],
            ['bar'],
            ['foo'],
            ['dang', true],
            ['baz'],
            ['dong', true],
            ['foo'],
        );
    }

    private function apply_filter($hook)
    {
        self::$plugin->onHook($hook);
    }
}
