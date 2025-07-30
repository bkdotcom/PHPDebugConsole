<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\WpDb
 */
class WpDbTest extends DebugTestFramework
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
        \wp_reset_mock();
        self::resetDebug();
        self::$plugin = new \bdk\Debug\Framework\WordPress\WpDb();
        self::$plugin->onBootstrap(new Event(self::$debug));
    }

    public function testGetSubscriptions()
    {
        self::assertInstanceOf('bdk\PubSub\SubscriberInterface', self::$plugin);
        self::assertSame([
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_OUTPUT => 'onOutput',
        ], self::$plugin->getSubscriptions());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testOnBootstrap()
    {
        // wp_reset_mock();
        self::$plugin->onBootstrap(new Event(self::$debug));
    }

    public function testonQuery()
    {
        self::$plugin->onQuery(array('foo' => 'bar'), 'SELECT `something` from `dingus` where id = 42', 0.02);
        self::assertSame([
            'statementInfo1' => array(
                'method' => 'groupCollapsed',
                'args' => array(
                    'SELECT `something` from `dingus` WHERE id = 42',
                ),
                'meta' => array(
                    'attribs' => array(
                        'id' => 'statementInfo1',
                        'class' => array(),
                    ),
                    'boldLabel' => false,
                    'channel' => 'general.db',
                    'icon' => 'fa fa-database',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    array(
                        'attribs' => array(
                            'class' => array( 'highlight','language-sql','no-quotes' )
                        ),
                        'brief' => false,
                        'contentType' => 'application/sql',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => false,
                        'type' => 'string',
                        'typeMore' => null,
                        'value' => 'SELECT ' . "\n"
                            . '  `something` ' . "\n"
                            . 'from ' . "\n"
                            . '  `dingus` ' . "\n"
                            . 'where ' . "\n"
                            . '  id = 42',
                    ),
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => ['no-indent'],
                    ),
                    'channel' => 'general.db',
                ),
            ),
            array(
                'method' => 'time',
                'args' => array(
                    'duration: 20 ms',
                ),
                'meta' => array(
                    'channel' => 'general.db',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => [],
                'meta' => array(
                    'channel' => 'general.db',
                ),
            ),
        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testOnOutput()
    {
        self::$plugin->onOutput(new Event(self::$debug));
        self::assertSame([
            [
                array(
                    'method' => 'groupCollapsed',
                    'args' => ['MySqli'],
                    'meta' => array(
                        'argsAsParams' => false,
                        'channel' => 'general.db',
                        'icon' => 'fa fa-database',
                        'level' => 'info',
                    ),
                ),
                array(
                    'method' => 'log',
                    'args' => [
                        'Connection string',
                        'mysql://user:█████████@hosty:3306/some_db',
                    ],
                    'meta' => array(
                        'channel' => 'general.db',
                        'redact' => true,
                    ),
                ),
                array(
                    'method' => 'log',
                    'args' => [
                        'Logged operations: ',
                        1,
                    ],
                    'meta' => array(
                        'channel' => 'general.db',
                    ),
                ),
                array(
                    'method' => 'time',
                    'args' => [
                        'Total time: 20 ms',
                    ],
                    'meta' => array(
                        'channel' => 'general.db',
                    ),
                ),
                array(
                    'method' => 'log',
                    'args' => [
                        'Server info',
                        array(
                            'Flush tables' => 1,
                            'Open tables' => 123,
                            'Opens' => 123,
                            'Queries per second avg' => 0.005,
                            'Questions' => 1234,
                            'Slow queries' => 0,
                            'Threads' => 1,
                            'Uptime' => 123456,
                            'Version' => '5.7.21',
                        ),
                    ],
                    'meta' => array(
                        'channel' => 'general.db',
                    ),
                ),
                array(
                    'method' => 'groupEnd',
                    'args' => array(),
                    'meta' => array(
                        'channel' => 'general.db',
                    ),
                ),
            ],
        ], $this->helper->deObjectifyData($this->debug->data->get('logSummary')));
    }

    public function testSetCfg()
    {
        // disable
        self::$plugin->setCfg(array(
            'enabled' => false,
        ));
        self::assertSame(array(
            'actions' => array(),
            'filters' => array(
                'log_query_custom_data' => [],
            ),
        ), $GLOBALS['wp_actions_filters']);
        self::$plugin->onOutput(new Event(self::$debug));
        self::assertSame(0, $this->debug->data->get('log/__count__'));

        // enable
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(),
            'filters' => array(
                'log_query_custom_data' => [
                    [self::$plugin, 'onQuery'],
                ],
            ),
        ), $GLOBALS['wp_actions_filters']);

        // no change
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(),
            'filters' => array(
                'log_query_custom_data' => [
                    [self::$plugin, 'onQuery'],
                ],
            ),
        ), $GLOBALS['wp_actions_filters']);
    }
}
