<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Plugin\Manager;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Debug\Plugin\Manager
 */
class ManagerTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    protected static $debug;
    protected static $manager;

    public static function setUpBeforeClass(): void
    {
        static::$debug = Debug::getInstance();
        static::$manager = new Manager();
        static::$manager->setDebug(static::$debug);
        static::$manager->onBootstrap();
    }

    public static function tearDownAfterClass(): void
    {
        \array_map(static function ($plugin) {
            // \bdk\Debug::varDump('removing', \bdk\Debug\Utility\Php::getDebugType($plugin));
            static::$manager->removePlugin($plugin);
        }, static::$manager->getPlugins());
    }

    public function testAddInvalid()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('addPlugin expects \bdk\Debug\AssetProviderInterface and/or \bdk\PubSub\SubscriberInterface.  stdClass provided');
        self::$manager->addPlugin(new \stdClass());
    }

    public function testRemoveNotExist()
    {
        $return = self::$manager->removePlugin('bogus');
        self::assertEquals(static::$debug, $return);
    }

    public function testRemoveInvalidArg()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('removePlugin expects plugin name or object.  bool provided');
        self::$manager->removePlugin(true);
    }

    public function testGetInvalid()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('getPlugin expects a string. int provided');
        self::$manager->getPlugin(69);
    }

    public function testGetNotExist()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('getPlugin(bogus) - no such plugin');
        self::$manager->getPlugin('bogus');
    }

    public function testAddPluginInterface()
    {
        $plugin = new Manager();
        $return = self::$manager->addPlugin($plugin);
        self::assertEquals(static::$debug, $return);
        self::assertEquals(static::$debug, \bdk\Debug\Utility\Reflection::propGet($plugin, 'debug'));

        $return = self::$manager->addPlugin($plugin);
        self::assertEquals(static::$debug, $return);
    }

    public function testAddRemoveAssetProvider()
    {
        $plugin = new \bdk\Debug\Plugin\Highlight();

        // our debug singleton may already have registered highlight assetprovider
        // remove so we can see that it gets added
        static::$debug->getRoute('html')->removeAssetProvider($plugin);

        $assetsBefore =static::$debug->getRoute('html')->getAssets();
        $return = self::$manager->addPlugin($plugin, 'highlight');
        self::assertEquals(static::$debug, $return);
        $assetsAfter = static::$debug->getRoute('html')->getAssets();
        $assetsNew = array(
            'css' => \array_diff($assetsAfter['css'], $assetsBefore['css']),
            'script' => \array_diff($assetsAfter['script'], $assetsBefore['script']),
        );
        self::assertCount(2, $assetsNew['css']);
        self::assertCount(2, $assetsNew['script']);

        self::$manager->removePlugin('highlight');
        $assetsAfter = static::$debug->getRoute('html')->getAssets();
        self::assertEmpty(\array_merge(
            \array_intersect($assetsNew['css'], $assetsAfter['css']),
            \array_intersect($assetsNew['script'], $assetsAfter['script'])
        ));
    }

    public function testAddRemoveSubscriberInterface()
    {
        $called = array(
            'bootstrap' => 0,
            'pluginInit' => 0,
        );
        $plugin = new \bdk\Test\Debug\Fixture\Subscriber(array(
            Debug::EVENT_BOOTSTRAP => static function (Event $event) use (&$called) {
                $called['bootstrap'] ++;
            },
            Debug::EVENT_PLUGIN_INIT => static function (Event $event) use (&$called) {
                $called['pluginInit'] ++;
            },
        ));
        $return = self::$manager->addPlugin($plugin, 'test');
        self::assertEquals(static::$debug, $return);
        self::assertSame(array(
            'bootstrap' => 1,
            'pluginInit' => 1,
        ), $called);

        $return = self::$manager->removePlugin($plugin);
        self::assertEquals(static::$debug, $return);
        self::assertSame(array(
            'bootstrap' => 1,
            'pluginInit' => 1,
        ), $called);
    }

    public function testAddRoute()
    {
        $GLOBALS['turd'] = true;
        $container = \bdk\Debug\Utility\Reflection::propGet(static::$debug, 'container');
        unset($container['serverLog']);
        $route = new \bdk\Debug\Route\ServerLog(static::$debug);
        self::assertFalse(self::$debug->data->get('isObCache'));
        $return = self::$manager->addPlugin($route);
        self::assertEquals(static::$debug, $return);
        self::assertEquals($route, self::$debug->routeServerLog);
        self::assertTrue(self::$debug->data->get('isObCache'));
        self::$debug->obEnd();
    }

    public function testAddPlugins()
    {
        self::$manager->addPlugins(array(
            'logFiles' => array(
                'class' => 'bdk\Debug\Plugin\LogFiles',
                'foo' => 'bar',
            ),
            'test' => 'bdk\Test\Debug\Fixture\Subscriber',
            'test2' => new \bdk\Test\Debug\Fixture\Subscriber(),
        ));
        self::assertSame('bar', self::$manager->getPlugin('logFiles')->getCfg('foo'));
    }

    public function testAddPluginsNoClass()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('plugins[highlight]: missing "class" value');
        self::$manager->addPlugins(array(
            'highlight' => array(),
        ));
    }

    public function testAddPluginsInvalid()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('plugins[highlight]: addPlugin expects \bdk\Debug\AssetProviderInterface and/or \bdk\PubSub\SubscriberInterface.  stdClass provided');
        self::$manager->addPlugins(array(
            'highlight' => new \stdClass(),
        ));
    }

    public function testGetPlugins()
    {
        $plugins = static::$manager->getPlugins();
        self::assertIsArray($plugins);
    }
}
