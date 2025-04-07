<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\Debug\Abstraction\AbstractObject;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\PubSub\Manager as EventManager;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug
 * @covers \bdk\Debug\AbstractDebug
 */
class DebugTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    protected $debugBackup = array();

    public function testNoComposer()
    {
        $output = array();
        $returnVal = 0;
        \exec('php ' . __DIR__ . '/noComposer.php', $output, $returnVal);
        self::assertSame(0, $returnVal, 'Failed to init Debug without composer');
    }

    public function testBootstrap()
    {
        $this->expectException('InvalidArgumentException');
        $debug = new Debug(array(
            'container' => array(),
            'serviceProvider' => 'invalid',
        ));
        self::assertInstanceOf('bdk\\Debug', $debug);
    }

    public function testMagicGet()
    {
        self::assertNull($this->debug->noSuchProp);
    }

    public function testMagicIsset()
    {
        self::assertTrue(isset($this->debug->errorHandler));
    }

    public function testMagicCall()
    {
        $this->destroyDebug();
        self::assertFalse(\bdk\Debug::_getCfg('collect'));
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
        self::assertSame(1, $count);
        self::assertInstanceOf('bdk\PubSub\Event', $args[0]);
        self::assertSame($debug, $args[0]->getSubject());
    }

    public function testOnConfig()
    {
        $this->debug->setCfg(array());
        self::assertTrue(true);
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
        self::assertSame($expect, $dumped);
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
                    // 'context' => null,
                    'detectFiles' => true,
                    'errorCat' => $errCat,
                    'errorHash' => $lastError['hash'],
                    'errorType' => \version_compare(PHP_VERSION, '7.0', '>=') ? E_NOTICE : E_STRICT,
                    // 'evalLine' => null,
                    'file' => __FILE__,
                    'isSuppressed' => false,
                    'line' => $lastError['line'],
                    'sanitize' => true,
                    // 'trace' => $lastError['backtrace'],
                    'uncollapse' => true,
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
     * Assert that calling \bdk\Debug::setCfg() before an instance has been instantiated creates an instance
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
        self::assertSame(true, Debug::getInstance()->getCfg('initViaSetCfg'));

        /*
            The new debug instance got a new eventManager
            Lets clear all of its subscribers
        */
        $eventManager = Debug::getInstance()->eventManager;
        foreach ($eventManager->getSubscribers() as $eventName => $eventSubscribers) {
            foreach ($eventSubscribers as $subInfo) {
                $eventManager->unsubscribe($eventName, $subInfo['callable']);
            }
        }

        $this->restoreDebug();

        $this->destroyDebug();
        Debug::_setCfg('collect', false);
        self::assertFalse(Debug::getInstance()->getCfg('collect'));
        $this->restoreDebug();
    }

    /**
     * Test that errorHandler onShutdown occurs before internal onShutdown
     *
     * @return void
     */
    public function testShutDownSubscribers()
    {
        $subscribers = \array_map(function ($subInfo) {
            $callable = $subInfo['callable'];
            if (\is_array($callable)) {
                return array(\get_class($callable[0]), $callable[1]);
            }
            if ($callable instanceof \Closure) {
                $abs = $this->debug->abstracter->crate($callable);
                return 'Closure(' . $abs['properties']['debug.file']['value'] . ')';
            }
            return \gettype($callable);
        }, $this->debug->eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN));
        $subscribersExpect = array(
            array('bdk\ErrorHandler', 'onShutdown'),
            array('bdk\Debug\Plugin\InternalEvents', 'onShutdownHigh'),
            array('bdk\Debug\Plugin\Method\GroupCleanup', 'onShutdown'),
            array('bdk\Debug\Plugin\Runtime', 'onShutdown'),
            array('bdk\Debug\Plugin\InternalEvents', 'onShutdownHigh2'),
            array('bdk\Debug\Plugin\InternalEvents', 'onShutdownLow'),
            array('bdk\Debug\Route\Wamp', 'onShutdown'),
            'Closure(' . TEST_DIR . '/bootstrap.php)',
        );
        self::assertSame($subscribersExpect, $subscribers);
    }

    /*
        getCfg tested in ConfigTest
    */

    public function testMeta()
    {
        /*
            Test cfg shortcut...
        */
        self::assertSame(array(
            'cfg' => array('foo' => 'bar'),
            'debug' => Debug::META,
        ), $this->debug->meta('cfg', array('foo' => 'bar')));
        self::assertSame(array(
            'cfg' => array('foo' => 'bar'),
            'debug' => Debug::META,
        ), $this->debug->meta('cfg', 'foo', 'bar'));
        self::assertSame(array(
            'cfg' => array('foo' => true),
            'debug' => Debug::META,
        ), $this->debug->meta('cfg', 'foo'));
        // invalid cfg val... empty meta
        self::assertSame(array(
            'debug' => Debug::META,
        ), $this->debug->meta('cfg'));
        // test invalid args
        self::assertSame(array(
            'debug' => Debug::META,
        ), $this->debug->meta(false));
        /*
            non cfg shortcut
        */
        self::assertSame(array(
            'foo' => 'bar',
            'debug' => Debug::META,
        ), $this->debug->meta(array('foo' => 'bar')));
        self::assertSame(array(
            'foo' => 'bar',
            'debug' => Debug::META,
        ), $this->debug->meta('foo', 'bar'));
        self::assertSame(array(
            'foo' => true,
            'debug' => Debug::META,
        ), $this->debug->meta('foo'));
    }

    public function testMetaCfg()
    {
        $this->debug->log(new \bdk\Test\Debug\Fixture\TestObj(), $this->debug->meta('cfg', 'methodCollect', false));
        $methodCollect = $this->debug->data->get('log/__end__/args/0/cfgFlags') & AbstractObject::METHOD_COLLECT;
        self::assertSame(0, $methodCollect);
        self::assertTrue($this->debug->getCfg('methodCollect'));
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

        $this->debug->setCfg('serviceProvider', new \bdk\Container(array('foo' => 'bar3')));
        self::assertSame('bar3', $this->debug->foo);

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('toRawValues expects Container, ServiceProviderInterface, callable, or key->value array. stdClass provided');
        $this->debug->setCfg('serviceProvider', (object) array('foo' => 'bar4'));
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
        self::assertSame(array(
            'evalLine' => null,
            'file' => __FILE__,
            'line' => __LINE__ - 5,
            'groupDepth' => 0,
        ), $errorCaller);

        // this will use maximum debug_backtrace depth
        \call_user_func(array($this, 'setErrorCallerHelper'), true);
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        self::assertSame(array(
            'evalLine' => null,
            'file' => __FILE__,
            'line' => __LINE__ - 5,
            'groupDepth' => 0,
        ), $errorCaller);
    }

    private function setErrorCallerHelper($static = false)
    {
        if ($static) {
            Debug::setErrorCaller();
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
            $prop->setValue(null, $newVal);
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
            $prop->setValue(null, $this->debugBackup['debug'][$name]);
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
