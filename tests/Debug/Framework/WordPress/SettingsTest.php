<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Framework\WordPress\Settings as WordPressSettings;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\Settings
 * @covers \bdk\Debug\Framework\WordPress\Settings\ControlBuilder
 * @covers \bdk\Debug\Framework\WordPress\Settings\FormProcessor
 */
class SettingsTest extends DebugTestFramework
{
    use AssertionTrait;

    protected static $plugin;

    public static function setUpBeforeClass(): void
    {
        if (!\function_exists('get_option')) {
            require_once __DIR__ . '/mock_wordpress.php';
        }
        \wp_reset_mock();
        self::resetDebug();
        self::$plugin = new WordPressSettings();
        self::$plugin->setCfg('pluginFile', 'phpDebugConsole/phpdebugconsole.php');
    }

    public static function tearDownAfterClass(): void
    {
        \wp_reset_mock();
    }

    public function testAssertGetAssets()
    {
        $assets = self::$plugin->getAssets();
        self::assertSame(['css', 'script'], \array_keys($assets));
        self::assertCount(1, $assets['script']);
        self::assertCount(1, $assets['css']);
    }

    public function testGetSubscriptions()
    {
        self::assertInstanceOf('bdk\PubSub\SubscriberInterface', self::$plugin);
        self::assertSame([
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
        ], self::$plugin->getSubscriptions());
    }

    public function testOnBootstrap()
    {
        self::$plugin->onBootstrap(new Event(self::$debug));
        self::assertInstanceOf('Closure', $GLOBALS['wp_actions_filters']['actions']['admin_menu'][0]);

        $GLOBALS['wp_actions_filters']['actions']['admin_menu'][0]();

        $GLOBALS['wp_actions_filters']['actions']['admin_menu'][0] = 'Closure';
        self::assertSame(array(
            'actions' => [
                'admin_init' => [
                    [self::$plugin, 'registerSettings'],
                ],
                'admin_menu' => [
                    'Closure',
                ],
            ],
            'filters' => [
                'plugin_action_links_phpDebugConsole/phpdebugconsole.php' => [
                    [self::$plugin, 'pluginActionLinks'],
                ],
            ],
        ), $GLOBALS['wp_actions_filters']);

        self::assertEquals([
            [
                'PHPDebugConsole Settings',     // page title
                'PHPDebugConsole',	            // menu title
                'manage_options',	            // capability
                WordPressSettings::PAGE_SLUG_NAME,      // menu slug
                [self::$plugin, 'outputSettingsPage'],	// callable
                null,				            // position
            ],
        ], $GLOBALS['wpFunctionArgs']['add_options_page']);
    }

    /**
     * @depends testRegisterSettings
     */
    public function testOutputSettingsPage()
    {
        \ob_start();
        self::$plugin->outputSettingsPage();
        $output = \ob_get_clean();
        $expect = \file_get_contents(__DIR__ . '/expect/settings_output.html');
        // \bdk\Debug::varDump('expected', $expect);
        // \bdk\Debug::varDump('actual', $output);
        self::assertSame($expect, $output);
    }

    public function testPluginActionLinks()
    {
        $return = self::$plugin->pluginActionLinks(array(
            'a',
            'b',
        ));
        self::assertSame(array(
            '<a href="/wp-admin/options-general.php?page=phpdebugconsole">Settings</a>',
            'a',
            'b',
        ), $return);
    }

    public function testRegisterSettings()
    {
        $GLOBALS['wpReturnVals']['option']['phpdebugconsole'] = array(
            'key' => 'password',
            'i18n' => array(
                'localeFirstChoice' => 'en_US',
            ),
            'enableProfiling' => false,
            'route' => 'auto',
            'plugins' => array(
                'wordpress' => array(
                    'enabled' => true,
                ),
                'wordpressCache' => array(
                    'enabled' => false,
                ),
                'wordpressDb' => array(
                    'enabled' => false,
                ),
                'wordpressHooks' => array(
                    'enabled' => false,
                ),
                'wordpressHttp' => array(
                    'enabled' => false,
                ),
            ),
            'logResponse' => 'auto',
            'logRuntime' => false,
            'waitThrottle' => 60,
        );

        self::$plugin->registerSettings();

        self::assertSame(array(
            array(
                'id' => 'general',
                'page' => 'phpdebugconsole',
                'title' => 'General Settings',
            ),
            array(
                'id' => 'errors',
                'page' => 'phpdebugconsole',
                'title' => 'Error Notification',
            ),
        ), \array_map(static function ($values) {
            $vals = \array_intersect_key($values, \array_flip([
                'id',
                'page',
                'title',
            ]));
            \ksort($vals);
            return $vals;
        }, $GLOBALS['wpFunctionArgs']['add_settings_section']));

        self::assertSame(array(
            array(
                'id' => 'phpdebugconsole_key',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Password',
            ),
            array(
                'id' => 'phpdebugconsole_i18n_localeFirstChoice',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Locale',
            ),
            array(
                'id' => 'phpdebugconsole_route',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Route',
            ),
            array(
                'id' => 'phpdebugconsole_wordpress',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'WordPress',
            ),
            array(
                'id' => 'phpdebugconsole_logEnvInfo',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Log Environment',
            ),
            array(
                'id' => 'phpdebugconsole_logResponse',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Log Response',
            ),
            array(
                'id' => 'phpdebugconsole_logRequestInfo',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Log Request',
            ),
            array(
                'id' => 'phpdebugconsole_logRuntime',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Log Runtime',
            ),

            array(
                'id' => 'phpdebugconsole_enableProfiling',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Enable Profile Method',
            ),

            array(
                'id' => 'phpdebugconsole_maxDepth',
                'page' => 'phpdebugconsole',
                'section' => 'general',
                'title' => 'Max Depth',
            ),

            array(
                'id' => 'phpdebugconsole_waitThrottle',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Throttle / Wait',
            ),
            array(
                'id' => 'phpdebugconsole_enableEmailer',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Enable Email',
            ),
            array(
                'id' => 'phpdebugconsole_emailTo',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Email To',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeDiscord_enabled',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Enable Discord',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeDiscord_webhookUrl',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Webhook URL',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeSlack_enabled',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Enable Slack',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeSlack_webhookUrl',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Webhook URL',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeSlack_token',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Token',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeSlack_channel',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Channel',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeTeams_enabled',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Enable Teams',
            ),
            array(
                'id' => 'phpdebugconsole_plugins_routeTeams_webhookUrl',
                'page' => 'phpdebugconsole',
                'section' => 'errors',
                'title' => 'Webhook URL',
            ),

        ), \array_map(static function ($values) {
            $vals = \array_intersect_key($values, \array_flip([
                'id',
                'page',
                'section',
                'title',
            ]));
            \ksort($vals);
            return $vals;
        }, $GLOBALS['wpFunctionArgs']['add_settings_field']));
    }

    public function testSanitize()
    {
        $return = self::$plugin->sanitize(array(
            'key' => '1234',
            'i18n' => array(
                'localeFirstChoice' => 'en',
            ),
            'bogus' => 'I don\'t belong',
            'enableProfiling' => 'on',
            'route' => 'bogus',
            'logResponse' => 'true',
            'plugins' => array(
                'wordpress' => array(
                    'enabled' => 'on',
                ),
            ),
            'waitThrottle' => '60',
        ));
        // \bdk\Debug::varDump('return', $return);
        self::assertSame(array(
            'key' => '1234',
            'i18n' => array(
                'localeFirstChoice' => 'en',
            ),
            // 'route' => 'route',
            'plugins' => array(
                'wordpress' => array(
                    'enabled' => true,
                ),
                'wordpressCache' => array(
                    'enabled' => false,
                ),
                'wordpressDb' => array(
                    'enabled' => false,
                ),
                'wordpressHooks' => array(
                    'enabled' => false,
                ),
                'wordpressHttp' => array(
                    'enabled' => false,
                ),
                'routeDiscord' => array(
                    'enabled' => false,
                    'webhookUrl' => null,
                    'throttleMin' => 60,
                ),
                'routeSlack' => array(
                    'enabled' => false,
                    'webhookUrl' => null,
                    'token' => null,
                    'channel' => null,
                    'throttleMin' => 60,
                ),
                'routeTeams' => array(
                    'enabled' => false,
                    'webhookUrl' => null,
                    'throttleMin' => 60,
                ),
            ),
            'logResponse' => true,
            'logRuntime' => false,
            'enableProfiling' => true,
            'maxDepth' => null,
            'enableEmailer' => false,
            'emailTo' => null,
            'emailMin' => 60,
        ), $return);
    }
}
