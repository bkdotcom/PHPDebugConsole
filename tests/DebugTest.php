<?php

/**
 * PHPUnit tests for Debug class
 */
class DebugTest extends DebugTestFramework
{

    /**
     * Assert that calling \bdk\Debug::_setCfg() before an instance has been instantiated creates an instance
     *
     * This is a bit tricky to test.. need to clear currant static instance...
     *    a 2nd instance will get created
     *    need to remove all the eventListeners created for 2nd instance
     *
     * @return void
     */
    public function testInitViaStatic()
    {
        $debugReflection = new reflectionClass($this->debug);
        $debugProps = $debugReflection->getProperties(ReflectionProperty::IS_STATIC);
        $debugBackup = array();
        foreach ($debugProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $debugBackup[$name] = $prop->getValue();
            $newVal = is_array($debugBackup[$name])
                ? array()
                : null;
            $prop->setValue($newVal);
        }

        $eventManagerReflection = new reflectionClass($this->debug->eventManager);
        $eventManagerProps = $eventManagerReflection->getProperties();
        $eventManagerBackup = array();
        foreach ($eventManagerProps as $prop) {
            $prop->setAccessible(true);
            $name = $prop->getName();
            $eventManagerBackup[$name] = $prop->getValue($this->debug->eventManager);
        }

        \bdk\Debug::_setCfg(array('collect'=>true, 'output'=>false, 'initViaSetCfg'=>true));
        $this->assertSame(true, \bdk\Debug::getInstance()->getCfg('initViaSetCfg'));

        $em = \bdk\Debug::getInstance()->eventManager;
        foreach ($em->getSubscribers() as $eventName => $subs) {
            foreach ($subs as $sub) {
                $em->unsubscribe($eventName, $sub);
            }
        }

        /*
            Restore static properties
        */
        foreach ($debugProps as $prop) {
            // $prop->setAccessible(true);
            $name = $prop->getName();
            $prop->setValue($debugBackup[$name]);
        }
        foreach ($eventManagerProps as $prop) {
            $name = $prop->getName();
            $prop->setValue($this->debug->eventManager, $eventManagerBackup[$name]);
        }
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
        $this->assertSame('onShutdown', $subscribers[1][1]);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetCfg()
    {
        $this->assertSame('visibility', $this->debug->getCfg('objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter.objectSort'));
        $this->assertSame('visibility', $this->debug->getCfg('abstracter/objectSort'));

        $abstracterKeys = array('collectConstants', 'collectMethods', 'objectsExclude', 'objectSort', 'useDebugInfo');
        $debugKeys = array('collect', 'file', 'key', 'output', 'errorMask', 'emailFunc', 'emailLog', 'emailTo', 'logEnvInfo', 'logServerKeys', 'onLog',);

        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter')));
        $this->assertSame($abstracterKeys, array_keys($this->debug->getCfg('abstracter/*')));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg()));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug')));
        $this->assertSame($debugKeys, array_keys($this->debug->getCfg('debug/*')));
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

    /**
     * Test
     *
     * @return void
     */
    public function testSetCfg()
    {
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

    public function testSubstitution()
    {
        $location = 'http://localhost/?foo=bar&jim=slim';
        $this->testMethod(
            'log',
            array('%cLocation:%c <a href="%s">%s</a>', 'font-weight:bold;', '', $location, $location),
            array(
                'html' => '<div class="m_log"><span class="t_string no-pseudo"><span style="font-weight:bold;">Location:</span><span> <a href="http://localhost/?foo=bar&amp;jim=slim">http://localhost/?foo=bar&amp;jim=slim</a></span></span></div>',
            )
        );
    }
}
