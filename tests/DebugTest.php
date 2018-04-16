<?php

/**
 * PHPUnit tests for Debug class
 */
class DebugTest extends DebugTestFramework
{

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
        $this->debug->log('%cLocation:%c <a href="%s">%s</a>', 'font-weight:bold;', '', $location, $location);
        $output = $this->debug->output();
        $outputExpect = '<div class="m_log"><span class="t_string no-pseudo"><span style="font-weight:bold;">Location:</span><span> <a href="http://localhost/?foo=bar&amp;jim=slim">http://localhost/?foo=bar&amp;jim=slim</a></span></span></div>';
        $this->assertContains($outputExpect, $output);
    }
}
