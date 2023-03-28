<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\PubSub\Manager as EventManager;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\AbstractDebug
 * @covers \bdk\Debug\Internal
 */
class DebugTest extends DebugTestFramework
{
    protected $debugBackup = array();

    public function testNoComposer()
    {
        $output = array();
        $returnVal = 0;
        \exec('php ' . __DIR__ . '/noComposer.php', $output, $returnVal);
        $this->assertSame(0, $returnVal, 'Failed to init Debug without composer');
    }

    public function testBootstrap()
    {
        $debug = new Debug(array(
            'container' => array(),
            'serviceProvider' => 'invalid',
        ));
        $this->assertInstanceOf('bdk\\Debug', $debug);
    }

    public function testGetDefaultRoute()
    {
        $GLOBALS['collectedHeaders'] = array(
            array('Content-Type: text/html', false),
        );
        $route = $this->debug->internal->getDefaultRoute();
        $this->assertSame('html', $route);

        $GLOBALS['collectedHeaders'] = array(
            array('Content-Type: image/jpeg', false),
        );
        $route = $this->debug->internal->getDefaultRoute();
        $this->assertSame('serverLog', $route);

        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', null, array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            )),
        ));

        $route = $this->debug->internal->getDefaultRoute();
        $this->assertSame('serverLog', $route);
    }

    public function testMagicGet()
    {
        $this->assertNull($this->debug->noSuchProp);
    }

    public function testMagicIsset()
    {
        $this->assertTrue(isset($this->debug->errorHandler));
    }

    public function testMagicCall()
    {
        $this->destroyDebug();
        $this->assertFalse(\bdk\Debug::_getCfg('collect'));
        $this->restoreDebug();
    }

    public function testOnBootstrap()
    {
        $args = array();
        $count = 0;
        $debug = new Debug(array(
            'logResponse' => false,
            'onBootstrap' => function (\bdk\PubSub\Event $event) use (&$args, &$count) {
                $count++;
                $args = \func_get_args();
            }
        ));
        $this->assertSame(1, $count);
        $this->assertInstanceOf('bdk\PubSub\Event', $args[0]);
        $this->assertSame($debug, $args[0]->getSubject());
    }

    public function testOnConfig()
    {
        $this->debug->setCfg(array());
        $this->assertTrue(true);
    }

    public function testOnCfgRoute()
    {
        $containerRef = new \ReflectionProperty($this->debug, 'container');
        $containerRef->setAccessible(true);
        $container = $containerRef->getValue($this->debug);
        unset($container['routeFirephp']);

        $this->debug->setCfg('route', new \bdk\Debug\Route\Firephp($this->debug));
        $this->assertInstanceOf('bdk\\Debug\\Route\\Firephp', $container['routeFirephp']);
        $this->debug->obEnd();
    }

    public function testPublishBubbleEvent()
    {
        $val = new \bdk\Debug\Abstraction\Abstraction('someCustomValueType', array('foo' => '<b>bar&baz</b>'));
        $expect = '<span class="t_someCustomValueType"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
\t<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">&lt;b&gt;bar&amp;baz&lt;/b&gt;</span></li>
\t<li><span class="t_key">type</span><span class="t_operator">=&gt;</span><span class="t_string">someCustomValueType</span></li>
\t<li><span class="t_key">value</span><span class="t_operator">=&gt;</span><span class="t_null">null</span></li>
</ul><span class="t_punct">)</span></span></span>';
        $expect = \str_replace('\\t', "\t", $expect);
        $dumped = $this->debug->getDump('html')->valDumper->dump($val);
        $this->assertSame($expect, $dumped);
    }

    public function testPhpError()
    {
        parent::$allowError = true;
        \array_pop(\explode('-', 'strict-error'));   // Only variables should be passed by reference
        $lastError = $this->debug->errorHandler->get('lastError');
        $errCat = \version_compare(PHP_VERSION, '7.0', '>=')
            ? 'notice'
            : 'strict';
        $errMsg = 'Only variables should be passed by reference';
        $args = \version_compare(PHP_VERSION, '7.0', '>=')
            ? array(
                'Notice:',
                $errMsg,
                __FILE__ . ' (line ' . $lastError['line'] . ')',
            )
            : array(
                'Strict:',
                $errMsg,
                __FILE__ . ' (line ' . $lastError['line'] . ')',
            );
        $this->testMethod(null, array(), array(
            'entry' => array(
                'method' => 'warn',
                'args' => $args,
                'meta' => array(
                    'channel' => 'general.phpError',
                    'context' => null,
                    'detectFiles' => true,
                    'errorCat' => $errCat,
                    'errorHash' => $lastError['hash'],
                    'errorType' => \version_compare(PHP_VERSION, '7.0', '>=') ? E_NOTICE : E_STRICT,
                    'file' => __FILE__,
                    'line' => $lastError['line'],
                    'sanitize' => true,
                    'isSuppressed' => false,
                    'uncollapse' => true,
                    'trace' => $lastError['backtrace'],
                ),
            ),
            'html' => '<li class="error-' . $errCat . ' m_warn" data-channel="general.phpError" data-detect-files="true">'
                . '<span class="no-quotes t_string">' . $args[0] . ' </span>'
                . '<span class="t_string">' . $errMsg . '</span>, '
                . '<span class="t_string">' . $args[2] . '</span>'
                . '</li>',
        ));
    }

    /**
     * Assert that calling \bdk\Debug::_setCfg() before an instance has been instantiated creates an instance
     *
     * This is a bit tricky to test.. need to clear currant static instance...
     *    a 2nd instance will get created
     *    need to remove all the eventListeners created for 2nd instance
     *       errorHandler subscribers will be on the existing eventManager,
     *       all other subscribers will be on a new eventManager
     *
     * @return void
     */
    public function testInitViaStatic()
    {
        $this->destroyDebug();

        // explicitly set route, so that stream/cli output does not initiate
        Debug::_setCfg(array(
            'collect' => true,
            'initViaSetCfg' => true,
            'logResponse' => false,
            'output' => true,
            'route' => 'html',
        ));
        $this->assertSame(true, Debug::getInstance()->getCfg('initViaSetCfg'));

        /*
            The new debug instance got a new eventManager
            Lets clear all of its subscribers
        */
        $eventManager = Debug::getInstance()->eventManager;
        foreach ($eventManager->getSubscribers() as $eventName => $subs) {
            foreach ($subs as $sub) {
                $eventManager->unsubscribe($eventName, $sub);
            }
        }

        $this->restoreDebug();

        $this->destroyDebug();
        Debug::_setCfg('collect', false);
        $this->assertFalse(Debug::getInstance()->getCfg('collect'));
        $this->restoreDebug();
    }

    /**
     * Test that errorHandler onShutdown occurs before internal onShutdown
     *
     * @return void
     */
    public function testShutDownSubscribers()
    {
        $subscribers = \array_map(function ($val) {
            if (\is_array($val)) {
                return array(\get_class($val[0]), $val[1]);
            }
            if ($val instanceof \Closure) {
                $abs = $this->debug->abstracter->crate($val);
                return 'Closure(' . $abs['properties']['debug.file']['value'] . ')';
            }
            return \gettype($val);
        }, $this->debug->eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN));
        $subscribersExpect = array(
            array('bdk\Debug\InternalEvents', 'onShutdownHigh'),
            array('bdk\ErrorHandler', 'onShutdown'),
            array('bdk\Debug\Method\Group', 'onShutdown'),
            array('bdk\Debug\InternalEvents', 'onShutdownHigh2'),
            'Closure(' . TEST_DIR . '/bootstrap.php)',
            array('bdk\Debug\InternalEvents', 'onShutdownLow'),
            array('bdk\Debug\Route\Wamp', 'onShutdown'),
        );
        $this->assertSame($subscribersExpect, $subscribers);
    }

    /*
        getCfg tested in ConfigTest
    */

    public function testMeta()
    {
        /*
            Test cfg shortcut...
        */
        $this->assertSame(array(
            'cfg' => array('foo' => 'bar'),
            'debug' => Debug::META,
        ), $this->debug->meta('cfg', array('foo' => 'bar')));
        $this->assertSame(array(
            'cfg' => array('foo' => 'bar'),
            'debug' => Debug::META,
        ), $this->debug->meta('cfg', 'foo', 'bar'));
        $this->assertSame(array(
            'cfg' => array('foo' => true),
            'debug' => Debug::META,
        ), $this->debug->meta('cfg', 'foo'));
        // invalid cfg val... empty meta
        $this->assertSame(array(
            'debug' => Debug::META,
        ), $this->debug->meta('cfg'));
        /*
            non cfg shortcut
        */
        $this->assertSame(array(
            'foo' => 'bar',
            'debug' => Debug::META,
        ), $this->debug->meta(array('foo' => 'bar')));
        $this->assertSame(array(
            'foo' => 'bar',
            'debug' => Debug::META,
        ), $this->debug->meta('foo', 'bar'));
        $this->assertSame(array(
            'foo' => true,
            'debug' => Debug::META,
        ), $this->debug->meta('foo'));
    }

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testOutput()
    {
        $counts = array(
            'bdk\\Debug\\Route\\ChromeLogger' => 0,
            'bdk\\Debug\\Route\\Email' => 0,
            'bdk\\Debug\\Route\\Firephp' => 0,
            'bdk\\Debug\\Route\\Html' => 0,
            'bdk\\Debug\\Route\\Script' => 0,
            'bdk\\Debug\\Route\\ServerLog' => 0,
            'bdk\\Debug\\Route\\Stream' => 0,
            'bdk\\Debug\\Route\\Text' => 0,
            'bdk\\Debug\\Route\\Wamp' => 0,
        );
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, function (LogEntry $logEntry) use (&$counts) {
            if ($logEntry->getMeta('countMe')) {
                $route = \get_class($logEntry['route']);
                if ($route === 'bdk\\Debug\\Route\\Html') {
                    // echo \print_r(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20), true) . "\n";
                }
                echo \sprintf(
                    '%s %s %s %s',
                    Debug::EVENT_OUTPUT_LOG_ENTRY,
                    $route,
                    $logEntry['method'],
                    \json_encode($logEntry['args'])
                ) . "\n";
                // echo \json_encode(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10), JSON_PRETTY_PRINT) . "\n";
                $counts[$route] ++;
            }
        });
        // $this->debug->setCfg('route', 'ChromeLogger');
        // $this->debug->obEnd();

        $this->debugFoo = $this->debug->getChannel('foo');
        $this->debugFoo->log('test this', $this->debug->meta('countMe'));

        // echo 'debug route class = ' . print_r(get_class($this->debug->getCfg('route')), true) . "\n";
        // echo 'debugfoo route class = ' . json_encode($this->debugFoo->getCfg('route')) . "\n";

        $this->debugFoo->output();
        echo \print_r($counts) . "\n";
    }
    */

    /*
        setCfg tested in ConfigTest
    */

    public function testServiceProviderToArray()
    {
        $this->debug->setCfg('serviceProvider', static function (\bdk\Container $container) {
            $container['foo'] = 'bar';
        });
        self::assertSame('bar', $this->debug->foo);

        $this->debug->setCfg('serviceProvider', new \bdk\Test\Debug\Fixture\ServiceProvider());
        self::assertSame('bar2', $this->debug->foo);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSetErrorCaller()
    {
        $this->setErrorCallerHelper();
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'groupDepth' => 0,
        ), $errorCaller);

        // this will use maximum debug_backtrace depth
        \call_user_func(array($this, 'setErrorCallerHelper'), true);
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __LINE__ - 4,
            'groupDepth' => 0,
        ), $errorCaller);
    }

    private function setErrorCallerHelper($static = false)
    {
        if ($static) {
            Debug::_setErrorCaller();
            return;
        }
        $this->debug->setErrorCaller();
    }

    /**
     * clear/backup some non-accessible things
     *
     * @return void
     */
    protected function destroyDebug()
    {
        $this->debugBackup = array(
            'debug' => array(),
            'eventManager' => array(),
        );

        $debugRef = new \ReflectionClass($this->debug);
        $debugProps = $debugRef->getProperties(\ReflectionProperty::IS_STATIC);
        foreach ($debugProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $this->debugBackup['debug'][$name] = $prop->getValue();
            $newVal = \is_array($this->debugBackup['debug'][$name])
                ? array()
                : null;
            $prop->setValue($newVal);
        }

        /*
            Backup eventManager data
        */
        $eventManagerRef = new \ReflectionClass($this->debug->eventManager);
        $eventManagerProps = $eventManagerRef->getProperties();
        foreach ($eventManagerProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $this->debugBackup['eventManager'][$name] = $prop->getValue($this->debug->eventManager);
        }
    }

    /**
     * Restore non-accessible things
     *
     * @return void
     */
    protected function restoreDebug()
    {
        $debugRef = new \ReflectionClass($this->debug);
        $debugProps = $debugRef->getProperties(\ReflectionProperty::IS_STATIC);
        foreach ($debugProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $prop->setValue($this->debugBackup['debug'][$name]);
        }

        /*
            Restore eventManager data
        */
        $eventManagerRef = new \ReflectionClass($this->debug->eventManager);
        $eventManagerProps = $eventManagerRef->getProperties();
        foreach ($eventManagerProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $prop->setValue($this->debug->eventManager, $this->debugBackup['eventManager'][$name]);
        }
    }
}
