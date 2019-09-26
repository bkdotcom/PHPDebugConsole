<?php

/**
 * PHPUnit tests for Debug class
 */
class DebugTest extends DebugTestFramework
{

    protected $debugBackup = array();

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

        $debugRef = new reflectionClass($this->debug);
        $debugProps = $debugRef->getProperties(ReflectionProperty::IS_STATIC);
        foreach ($debugProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $this->debugBackup['debug'][$name] = $prop->getValue();
            $newVal = is_array($this->debugBackup['debug'][$name])
                ? array()
                : null;
            $prop->setValue($newVal);
        }

        /*
            Backup eventManager data
        */
        $eventManagerRef = new reflectionClass($this->debug->eventManager);
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
        $debugRef = new reflectionClass($this->debug);
        $debugProps = $debugRef->getProperties(ReflectionProperty::IS_STATIC);
        foreach ($debugProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $prop->setValue($this->debugBackup['debug'][$name]);
        }

        /*
            Restore eventManager data
        */
        $eventManagerRef = new reflectionClass($this->debug->eventManager);
        $eventManagerProps = $eventManagerRef->getProperties();
        foreach ($eventManagerProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $prop->setValue($this->debug->eventManager, $this->debugBackup['eventManager'][$name]);
        }
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

        \bdk\Debug::_setCfg(array('collect'=>true, 'output'=>true, 'initViaSetCfg'=>true));
        $this->assertSame(true, \bdk\Debug::getInstance()->getCfg('initViaSetCfg'));

        /*
            The new debug instance got a new eventManager
            Lets clear all of its subscribers
        */
        $eventManager = \bdk\Debug::getInstance()->eventManager;
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
        $subscribers = $this->debug->eventManager->getSubscribers('php.shutdown');
        $this->assertSame($this->debug->errorHandler, $subscribers[0][0]);
        $this->assertSame('onShutdown', $subscribers[0][1]);
        $this->assertSame($this->debug->internal, $subscribers[1][0]);
        $this->assertSame('onShutdownHigh', $subscribers[1][1]);
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
        $this->assertSame('info', $this->debug->getData('log.0.0'));
        $this->assertSame('warn', $this->debug->getData('log/1/0'));
        $this->assertSame('warn', $this->debug->getData('log/__end__/0'));
        $this->assertSame(null, $this->debug->getData('log/bogus'));
        $this->assertSame(null, $this->debug->getData('log/bogus/more'));
        $this->assertSame(null, $this->debug->getData('log/0/0/notArray'));
    }

    public function testMeta()
    {
        /*
            Test cfg shortcut...
        */
        $this->assertSame(array(
            'cfg'=>array('foo'=>'bar'),
            'debug'=>\bdk\Debug::META,
        ), $this->debug->meta('cfg', array('foo'=>'bar')));
        $this->assertSame(array(
            'cfg'=>array('foo'=>'bar'),
            'debug'=>\bdk\Debug::META,
        ), $this->debug->meta('cfg', 'foo', 'bar'));
        $this->assertSame(array(
            'cfg'=>array('foo'=>true),
            'debug'=>\bdk\Debug::META,
        ), $this->debug->meta('cfg', 'foo'));
        // invalid cfg val... empty meta
        $this->assertSame(array(
            'debug'=>\bdk\Debug::META,
        ), $this->debug->meta('cfg'));
        /*
            non cfg shortcut
        */
        $this->assertSame(array(
            'foo' => 'bar',
            'debug'=>\bdk\Debug::META,
        ), $this->debug->meta(array('foo'=>'bar')));
        $this->assertSame(array(
            'foo' => 'bar',
            'debug'=>\bdk\Debug::META,
        ), $this->debug->meta('foo', 'bar'));
        $this->assertSame(array(
            'foo' => true,
            'debug'=>\bdk\Debug::META,
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
        call_user_func(array($this, 'setErrorCallerHelper'), true);
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
        $this->debug->setErrorCaller(array('file'=>'test','line'=>42));
        $this->debug->groupEnd();
        $this->assertSame(array(), $this->debug->errorHandler->get('errorCaller'));

        $this->debug->groupSummary();
        $this->debug->setErrorCaller(array('file'=>'test','line'=>42));
        $this->debug->groupEnd();
        $this->assertSame(array(), $this->debug->errorHandler->get('errorCaller'));
    }

    private function setErrorCallerHelper($static = false)
    {
        if ($static) {
            \bdk\Debug::_setErrorCaller();
        } else {
            $this->debug->setErrorCaller();
        }
    }
}
