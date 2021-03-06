<?php

namespace bdk\DebugTests;

use bdk\Debug;
use bdk\PubSub\Manager as EventManager;

/**
 * PHPUnit tests for Debug class
 */
class DebugTest extends DebugTestFramework
{

    protected $debugBackup = array();

    public function testNoDebug()
    {
        $output = array();
        $returnVal = 0;
        \exec('php ' . __DIR__ . '/noComposer.php', $output, $returnVal);
        $this->assertSame(0, $returnVal, 'Failed to init Debug without composer');
    }

    public function testOnBootstrap()
    {
        $args = array();
        $count = 0;
        $debug = new Debug(array(
            'onBootstrap' => function (\bdk\PubSub\Event $event) use (&$args, &$count) {
                $count++;
                $args = \func_get_args();
            }
        ));
        $this->assertSame(1, $count);
        $this->assertInstanceOf('bdk\PubSub\Event', $args[0]);
        $this->assertSame($debug, $args[0]->getSubject());
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
                'Runtime Notice (E_STRICT):',
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
    }

    /**
     * Test that errorHandler onShutdown occurs before internal onShutdown
     *
     * @return void
     */
    public function testShutDownSubscribers()
    {
        $subscribers = \array_map(function ($val) {
            return array(\get_class($val[0]), $val[1]);
        }, $this->debug->eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN));
        $subscribersExpect = array(
            array('bdk\ErrorHandler', 'onShutdown'),
            array('bdk\Debug\InternalEvents', 'onShutdownHigh'),
            // array('bdk\Debug\Plugin\LogReqRes', 'logResponse'),
            array('bdk\Debug\InternalEvents', 'onShutdownHigh2'),
            array('bdk\Debug\InternalEvents', 'onShutdownLow'),
            array('bdk\Debug\Route\Wamp', 'onShutdown'),
        );
        $this->assertSame($subscribersExpect, $subscribers);
    }

    /*
        getCfg tested in ConfigTest
    */

    public function testGetData()
    {
        $this->debug->info('token log entry 1');
        $this->debug->warn('token log entry 2');
        $this->assertArrayHasKey('log', $this->debug->getData());
        $this->assertSame(2, $this->debug->getData('log/__count__'));
        $this->assertSame('info', $this->debug->getData('log.0.method'));
        $this->assertSame('warn', $this->debug->getData('log/1/method'));
        $this->assertSame('warn', $this->debug->getData('log/__end__/method'));
        $this->assertSame(null, $this->debug->getData('log/bogus'));
        $this->assertSame(null, $this->debug->getData('log/bogus/more'));
        $this->assertSame(null, $this->debug->getData('log/0/method/notArray'));
    }

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
    public function testOutput()
    {
    }

    /*
        setCfg tested in ConfigTest
    */

    public function testSetData()
    {
        $this->debug->setData('log/0', array('info', array('foo'), array()));
        $this->assertSame(1, $this->debug->getData('log/__count__'));
        $this->assertSame('foo', $this->debug->getData('log/0/1/0'));

        $this->debug->setData(array(
            'log' => array(
                array('info', array('bar'), array()),
            )
        ));
        $this->assertSame(1, $this->debug->getData('log/__count__'));
        $this->assertSame('bar', $this->debug->getData('log/0/1/0'));
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

    public function testErrorCallerCleared()
    {
        $this->debug->group('test');
        $this->debug->setErrorCaller(array('file' => 'test', 'line' => 42));
        $this->debug->groupEnd();
        $this->assertSame(array(), $this->debug->errorHandler->get('errorCaller'));

        $this->debug->groupSummary();
        $this->debug->setErrorCaller(array('file' => 'test', 'line' => 42));
        $this->debug->groupEnd();
        $this->assertSame(array(), $this->debug->errorHandler->get('errorCaller'));
    }

    private function setErrorCallerHelper($static = false)
    {
        if ($static) {
            Debug::_setErrorCaller();
        } else {
            $this->debug->setErrorCaller();
        }
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
