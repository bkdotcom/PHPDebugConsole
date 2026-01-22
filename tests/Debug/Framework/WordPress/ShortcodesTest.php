<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\Shortcodes
 */
class ShortcodesTest extends DebugTestFramework
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
        self::$plugin = new \bdk\Debug\Framework\WordPress\Shortcodes();
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

    public function testOnOutput()
    {
        self::$plugin->onOutput(new Event(self::$debug));
        $phpDoc = $this->debug->phpDoc->getParsed('wpEmbed::shortcode()');
        $phpDoc['since'] = [
            array('desc' => '', 'version' => 2.9),
        ];
        $params = \preg_replace('/\{\s*(.*?)\s*\}/s', '$1', $phpDoc['param'][0]['desc']);
        $params = \preg_replace('/@type\s+/', '', $params);

        self::assertSame(array(
            'method' => 'table',
            'args' => array(
                array(
                    'caption' => 'shortcodes',
                    'debug' => Abstracter::ABSTRACTION,
                    'header' => [
                        'shortcode',
                        'callable',
                        'links',
                    ],
                    'meta' => array(
                        'class' => null,
                        'columns' => [
                            array(
                                'attribs' => array(
                                    'class' => [ "t_key" ],
                                    'scope' => "row"
                                ),
                                'key' => \bdk\Table\Factory::KEY_INDEX,
                                'tagName' => 'th',
                            ),
                            array(
                                'key' => 'callable',
                            ),
                            array(
                                'key' => 'links',
                            ),
                        ],
                        'haveObjectRow' => false,
                        'sortable' => true,
                    ),
                    'rows' => [
                        [
                            'embed',
                            array(
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Type::TYPE_IDENTIFIER,
                                'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                                'value' => 'wpEmbed::shortcode',
                            ),
                            '<a href="http://codex.wordpress.org/Embed_Shortcode" target="_blank" title="Codex documentation"><i class="fa fa-external-link"></i></a> <a href="#" data-toggle="#shortcode_embed_doc" title="handler phpDoc"><i class="fa fa-code"></i></a>',
                        ],
                        array(
                            'attribs' => array(
                                'id' => 'shortcode_embed_doc',
                                'style' => 'display: none;',
                            ),
                            'children' => [
                                array(
                                    'attribs' => array(
                                        'colspan' => 3,
                                    ),
                                    'value' => array(
                                        'addQuotes' => false,
                                        'brief' => false,
                                        'debug' => Abstracter::ABSTRACTION,
                                        'sanitize' => false,
                                        'type' => Type::TYPE_STRING,
                                        'typeMore' => null,
                                        'value' => $phpDoc['return']['desc'] . "\n\n"
                                            . $params . "\n\n"
                                            . 'Since:' . "\n"
                                            . \implode("\n", \array_map(static function ($since) {
                                                return \trim($since['version'] . ' ' . $since['desc']);
                                            }, $phpDoc['since'] ?: [])),
                                        'visualWhiteSpace' => false,
                                    ),
                                ),
                            ],
                        ), // end row 2
                    ],
                    'type' => 'table',
                    'value' => null,
                ),
            ),
            'meta' => array(
                'channel' => 'WordPress',
            ),
        ), $this->helper->logEntryToArray($this->debug->data->get('log/__end__')));
    }

    /*
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
    */
}
