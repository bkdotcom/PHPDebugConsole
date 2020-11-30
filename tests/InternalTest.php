<?php

namespace bdk\DebugTests;

use bdk\Debug\Psr7lite\ServerRequest;

/**
 * PHPUnit tests for Debug class
 */
class InternalTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testErrorStats()
    {
        parent::$allowError = true;

        // 1 / 0;    // warning
        $this->debug->errorHandler->handleError(E_WARNING, 'you have been warned', __FILE__, __LINE__);

        $this->assertSame(array(
            'inConsole' => 1,
            'inConsoleCategories' => 1,
            'notInConsole' => 0,
            'counts' => array(
                'warning' => array(
                    'inConsole' => 1,
                    'notInConsole' => 0,
                )
            ),
        ), $this->debug->errorStats());
    }

    public function testGetInterface()
    {
        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                // 'REQUEST_METHOD' => 'GET',
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('http', $this->debug->getInterface());

        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                // 'REQUEST_METHOD' => 'GET',
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('http ajax', $this->debug->getInterface());

        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                'PATH' => '.',
                'argv' => array('phpunit'),
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('cli', $this->debug->getInterface());

        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                'argv' => array('phpunit'),
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('cli cron', $this->debug->getInterface());
    }

    public function testHasLog()
    {
        $this->assertFalse($this->debug->hasLog());
        $this->debug->log('something');
        $this->assertTrue($this->debug->hasLog());
        $this->debug->clear();
        $this->assertFalse($this->debug->hasLog());
    }

    public function testRequestId()
    {
        $this->assertStringMatchesFormat('%x', $this->debug->requestId());
    }
}
