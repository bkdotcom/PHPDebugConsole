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
        if (PHP_VERSION_ID < 70000) {
            self::markTestSkipped('Wordpress requires PHP 7.0+');
            return;
        }
        parent::setUpBeforeClass();
        if (!\function_exists('get_option')) {
            require_once __DIR__ . '/mock_wordpress.php';
        }
        self::resetDebug();
        $mockPluginFile = \dirname(TEST_DIR) . '/myplugindir/debug-console-php.php';
        $wordpress = new \bdk\Debug\Framework\WordPress\Plugin($mockPluginFile);
        self::$plugin = new WordPressSettings();
        self::$plugin->setCfg('wordpress', $wordpress);
        \wp_reset_mock();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
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
                'plugin_action_links_myplugindir/debug-console-php.php' => [
                    [self::$plugin, 'pluginActionLinks'],
                ],
            ],
        ), $GLOBALS['wp_actions_filters']);

        self::assertEquals([
            [
                'Debug Console for PHP Settings',  // page title
                'DebugConsolePhp',	            // menu title
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
        $output = \trim(\ob_get_clean());
        $expect = \trim(\file_get_contents(__DIR__ . '/expect/settings_output.html'));
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
            '<a href="/wp-admin/options-general.php?page=debugConsoleForPhp">Settings</a>',
            'a',
            'b',
        ), $return);
    }

    public function testRegisterSettings()
    {
        $GLOBALS['wpReturnVals']['option']['debugConsoleForPhp'] = array(
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
                'page' => 'debugConsoleForPhp',
                'title' => 'General Settings',
            ),
            array(
                'id' => 'errors',
                'page' => 'debugConsoleForPhp',
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
                'id' => 'debugConsoleForPhp_password',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Password',
            ),
            array(
                'id' => 'debugConsoleForPhp_previousPasswordHash',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => '',
            ),
            array(
                'id' => 'debugConsoleForPhp_i18n_localeFirstChoice',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Locale',
            ),
            array(
                'id' => 'debugConsoleForPhp_route',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Route',
            ),
            array(
                'id' => 'debugConsoleForPhp_wordpress',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'WordPress',
            ),
            array(
                'id' => 'debugConsoleForPhp_logEnvInfo',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Log Environment',
            ),
            array(
                'id' => 'debugConsoleForPhp_logResponse',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Log Response',
            ),
            array(
                'id' => 'debugConsoleForPhp_logRequestInfo',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Log Request',
            ),
            array(
                'id' => 'debugConsoleForPhp_logRuntime',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Log Runtime',
            ),

            array(
                'id' => 'debugConsoleForPhp_enableProfiling',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Enable Profile Method',
            ),

            array(
                'id' => 'debugConsoleForPhp_maxDepth',
                'page' => 'debugConsoleForPhp',
                'section' => 'general',
                'title' => 'Max Depth',
            ),

            array(
                'id' => 'debugConsoleForPhp_waitThrottle',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Throttle / Wait',
            ),
            array(
                'id' => 'debugConsoleForPhp_enableEmailer',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Enable Email',
            ),
            array(
                'id' => 'debugConsoleForPhp_emailTo',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Email To',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeDiscord_enabled',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Enable Discord',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeDiscord_webhookUrl',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Webhook URL',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeSlack_enabled',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Enable Slack',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeSlack_webhookUrl',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Webhook URL',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeSlack_token',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Token',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeSlack_channel',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Channel',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeTeams_enabled',
                'page' => 'debugConsoleForPhp',
                'section' => 'errors',
                'title' => 'Enable Teams',
            ),
            array(
                'id' => 'debugConsoleForPhp_plugins_routeTeams_webhookUrl',
                'page' => 'debugConsoleForPhp',
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
            'password' => '1234',
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
        self::assertTrue(\password_verify('1234', $return['passwordHash']));
        unset($return['passwordHash']);
        self::assertSame(array(
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
            // 'passwordHash' => 'xxxxxxxx',
        ), $return);
    }
}
