<?php

/**
 * PHPUnit tests for Debug class
 */
class LoggerTest extends DebugTestFramework
{

    public function testEmergency()
    {
        $this->debug->logger->emergency('Emergency broadcast system');
        $meta = array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'psr3level' => 'emergency',
        );
        $this->assertSame(array(
            'error',
            array('Emergency broadcast system'),
            $meta,
        ), $this->debug->getData('log/0'));
    }

    public function testCritical()
    {
        $this->debug->logger->critical('Critical test');
        $metaExpect = array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'psr3level' => 'critical',
        );
        $this->assertSame(array(
            'error',
            array('Critical test'),
            $metaExpect,
        ), $this->debug->getData('log/__end__'));

        $this->debug->logger->critical('Make an exception', array(
            'exception' => new Exception(),
            'file' => 'file',
            'foo' => 'bar',
        ));
        $metaSubset = array(
            'file' => 'file',
            'line' => __LINE__ - 6, // line of Exception
        );
        $metaActual = $this->debug->getData('log/__end__/2');
        $this->assertSame('error', $this->debug->getData('log/__end__/0'));
        $this->assertSame('Make an exception', $this->debug->getData('log/__end__/1/0'));
        // should just contain exception & foo...  file gets moved to meta
        $this->assertCount(2, $this->debug->getData('log/__end__/1/1'));
        $this->assertArraySubset(array(
            'foo'=>'bar',
        ), $this->debug->getData('log/__end__/1/1'));
        $this->assertArraySubset(array(
            'className'=>'Exception',
            'debug' => \bdk\Debug\Abstracter::ABSTRACTION,
            'type' => 'object',
        ), $this->debug->getData('log/__end__/1/1/exception'));
        $this->assertArraySubset($metaSubset, $metaActual);
        $backtrace = $this->debug->getData('log/__end__/2/backtrace');
        $this->assertInternalType('array', $backtrace);
    }

    public function testError()
    {
        $this->debug->logger->error('Error test');
        $meta = array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'psr3level' => 'error',
        );
        $this->assertSame(array(
            'error',
            array('Error test'),
            $meta,
        ), $this->debug->getData('log/0'));
    }

    public function testWarning()
    {
        $this->debug->logger->warning('You\'ve been warned');
        $meta = array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'psr3level' => 'warning',
        );
        $this->assertSame(array(
            'warn',
            array('You\'ve been warned'),
            $meta,
        ), $this->debug->getData('log/0'));
    }

    public function testNotice()
    {
        $this->debug->logger->notice('Final Notice');
        $meta = array(
            'file' => __FILE__,
            'line' => __LINE__ - 3,
            'psr3level' => 'notice',
        );
        $this->assertSame(array(
            'warn',
            array('Final Notice'),
            $meta,
        ), $this->debug->getData('log/0'));
    }

    public function testAlert()
    {
        $this->debug->logger->alert('Alert');
        $this->assertSame(array(
            'alert',
            array('Alert'),
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
        $this->debug->logger->debug('{adj} debugging', array('adj'=>'Awesome','foo'=>'bar'));
        $this->assertSame(array(
            'log',
            array(
                'Awesome debugging',
                array('foo'=>'bar'),
            ),
            array(),
        ), $this->debug->getData('log/0'));
    }
}
