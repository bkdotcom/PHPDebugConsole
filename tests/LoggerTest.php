<?php

/**
 * PHPUnit tests for Debug class
 */
class LoggerTest extends DebugTestFramework
{

    public function testEmergency()
    {
        $this->debug->logger->emergency('Emergency broadcast system');
        $info = array('file' => __FILE__, 'line' => __LINE__ - 1);
        $this->assertSame(array(
            'error',
            array('Emergency broadcast system'),
            $info,
        ), $this->debug->getData('log/0'));
    }

    public function testCritical()
    {
        $this->debug->logger->critical('Critical test');
        $info = array('file' => __FILE__, 'line' => __LINE__ - 1);
        $this->assertSame(array(
            'error',
            array('Critical test'),
            $info,
        ), $this->debug->getData('log/__end__'));

        $this->debug->logger->critical('Make an exception', array('exception' => new Exception()));
        $info = array('file' => __FILE__, 'line' => __LINE__ - 1);
        $this->assertSame('error', $this->debug->getData('log/__end__/0'));
        $this->assertSame(array('Make an exception'), $this->debug->getData('log/__end__/1'));
        $this->assertArraySubset($info, $this->debug->getData('log/__end__/2'));
        $backtrace = $this->debug->getData('log/__end__/2/backtrace');
        $this->assertInternalType('array', $backtrace);
    }

    public function testError()
    {
        $this->debug->logger->error('Error test');
        $info = array('file' => __FILE__, 'line' => __LINE__ - 1);
        $this->assertSame(array(
            'error',
            array('Error test'),
            $info,
        ), $this->debug->getData('log/0'));
    }

    public function testWarning()
    {
        $this->debug->logger->warning('You\'ve been warned');
        $info = array('file' => __FILE__, 'line' => __LINE__ - 1);
        $this->assertSame(array(
            'warn',
            array('You\'ve been warned'),
            $info,
        ), $this->debug->getData('log/0'));
    }

    public function testNotice()
    {
        $this->debug->logger->notice('Final Notice');
        $info = array('file' => __FILE__, 'line' => __LINE__ - 1);
        $this->assertSame(array(
            'warn',
            array('Final Notice'),
            $info,
        ), $this->debug->getData('log/0'));
    }

    public function testAlert()
    {
        $this->debug->logger->alert('Alert');
        $this->assertSame(array(
            'Alert',
            array(
                'class' => 'danger',
                'dismissible' => false,
            ),
        ), $this->debug->getData('alerts/0'));
    }

    public function testInfo()
    {
        $this->debug->logger->info('For your information');
        $this->assertSame(array(
            'info',
            array('For your information'),
            array(),
        ), $this->debug->getData('log/0'));
    }

    public function testDebug()
    {
        $this->debug->logger->debug('{adj} debug', array('adj'=>'Awesome'));
        $this->assertSame(array(
            'log',
            array('Awesome debug'),
            array(),
        ), $this->debug->getData('log/0'));
    }
}
