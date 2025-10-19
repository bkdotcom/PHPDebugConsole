<?php

namespace bdk\Test\Debug\Framework\WordPress;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * @covers \bdk\Debug\Framework\WordPress\Plugin
 */
class PluginTest extends DebugTestFramework
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
        self::$plugin = new \bdk\Debug\Framework\WordPress\Plugin(__DIR__ . '/phpDebugConsolePlugin.php');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testOnInit()
    {
        self::$plugin->onInit();
        self::$plugin->debug->setCfg('output', false);
    }

    public function testOnShutdown()
    {
        $outputEvent = null;
        self::$plugin->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, function (Event $event) use (&$outputEvent) {
            $outputEvent = $event;
        });
        self::$plugin->debug->setCfg(array(
            'output' => true,
            'route' => 'html',
        ));
        \ob_start();
        self::$plugin->onShutdown();
        $output = \ob_get_clean();
        // \bdk\Debug::varDump(
            // 'onShutdown output',
            // $output
        // );
        self::assertInstanceOf('bdk\PubSub\Event', $outputEvent);
        self::assertNotEmpty($output);
    }

    public function testPluginBasename()
    {
        self::assertSame('tests/Debug/Framework/WordPress/phpDebugConsolePlugin.php', self::$plugin->pluginBasename());
    }
}
