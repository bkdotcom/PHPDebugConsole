<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\Debug\Abstraction\Type;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\WordPress
 */
class WordPressTest extends DebugTestFramework
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
        self::$plugin = new \bdk\Debug\Framework\WordPress\WordPress();
        self::$plugin->setCfg(array());
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

    public function testLogRequestInfo()
    {
        self::$plugin->logRequestInfo();
        self::assertSame([
            array(
                'method' => 'group',
                'args' => array(
                    'Request / Rewrite',
                ),
                'meta' => array(
                    'channel' => 'WordPress',
                    'level' => 'info',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'Request',
                    '/ding/dong/',
                ),
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'Query String',
                    'foo=bar&baz=qux',
                ),
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'Matched Rewrite Rule',
                    'some_rule',
                ),
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'Matched Rewrite Query',
                    'some_query',
                ),
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'groupEnd',
                'args' => array(),
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testOnBootstrap()
    {
        self::$plugin->onBootstrap(new Event(self::$debug));
        self::assertSame('fa fa-wordpress', $this->debug->getChannel('WordPress')->getCfg('channelIcon'));
    }

    public function testOnOutput()
    {
        self::$plugin->onOutput(new Event(self::$debug));
        self::assertSame([
            array(
                'method' => 'log',
                'args' => ['Query Type', 'Page'],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Query Template', 'template.php'],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Show on Front', 'page'],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Page For Posts', 42],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Page on Front', 69],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Post Type', 'bean'],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Query Arguments', array(
                    'foo' => 'bar',
                )],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => [
                    'Query SQL',
                    array(
                        'attribs' => array(
                            'class' => ['highlight', 'language-sql', 'no-quotes'],
                        ),
                        'brief' => false,
                        'contentType' => 'application/sql',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => false,
                        'type' => 'string',
                        'typeMore' => null,
                        'value' => 'SELECT ' . "\n"
                            . '  * ' . "\n"
                            . 'from ' . "\n"
                            . '  `some_table` ' . "\n"
                            . 'where ' . "\n"
                            . '  id = 42',
                    ),
                ],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => [
                    'Queried Object',
                    array(
                        'cfgFlags' => 29360127 & ~(AbstractObject::METHOD_OUTPUT) & ~(AbstractObject::OBJ_ATTRIBUTE_OUTPUT),
                        'debug' => Abstracter::ABSTRACTION,
                        'debugMethod' => 'log',
                        'inheritsFrom' => 'stdClass',
                        // 'interfacesCollapse' => array(),
                        // 'isLazy' => false,
                        // 'isMaxDepth' => false,
                        // 'isRecursion' => false,
                        'properties' => array(
                            'post_type' => array(
                                'attributes' => array(),
                                'debugInfoExcluded' => false,
                                'declaredLast' => null,
                                'declaredOrig' => null,
                                'declaredPrev' => null,
                                'forceShow' => false,
                                'hooks' => array(),
                                'isDeprecated' => false,
                                'isFinal' => false,
                                'isPromoted' => false,
                                'isReadOnly' => false,
                                'isStatic' => false,
                                'isVirtual' => false,
                                'phpDoc' => array(
                                    'desc' => '',
                                    'summary' => '',
                                ),
                                'type' => null,
                                'value' => 'post',
                                'valueFrom' => 'value',
                                'visibility' => ['public'],
                            ),
                        ),
                        'scopeClass' => 'bdk\Test\Debug\Framework\WordPress\WordPressTest',
                        'type' => Type::TYPE_OBJECT,
                    ),
                ],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
            array(
                'method' => 'log',
                'args' => ['Queried Object Id', 42],
                'meta' => array(
                    'channel' => 'WordPress',
                ),
            ),
        ], $this->helper->deObjectifyData($this->debug->data->get('log')));
    }

    public function testSetCfg()
    {
        // disable
        self::$plugin->setCfg(array(
            'enabled' => false,
        ));
        self::assertSame(array(
            'actions' => array(
                'wp' => [],
            ),
            'filters' => array(),
        ), $GLOBALS['wp_actions_filters']);
        self::$plugin->onOutput(new Event(self::$debug));
        self::assertSame(0, $this->debug->data->get('log/__count__'));

        // enable
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(
                'wp' => [
                    [self::$plugin, 'logRequestInfo'],
                ],
            ),
            'filters' => array(),
        ), $GLOBALS['wp_actions_filters']);

        // no change
        self::$plugin->setCfg('enabled', true);
        self::assertSame(array(
            'actions' => array(
                'wp' => [
                    [self::$plugin, 'logRequestInfo'],
                ],
            ),
            'filters' => array(),
        ), $GLOBALS['wp_actions_filters']);
    }
}
