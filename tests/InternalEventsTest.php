<?php

use bdk\Debug;

/**
 * PHPUnit tests for Debug class
 */
class InternalEventsTest extends DebugTestFramework
{

    private $emailCalled = false;
    private $expectedSubject = '';

    /**
     * Test
     *
     * @return void
     */
    public function testEmailLog()
    {

        parent::$allowError = true;

        $this->debug->setCfg(array(
            'emailLog' => 'always',
            'emailTo' => 'test@email.com', // need an email address to email to!
            'output' => false,  // email only sent if not outputing
            'emailFunc' => array($this, 'emailMock'),
        ));

        //
        // Test that not emailed if nothing logged
        //
        $this->debug->internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that emailed if something logged
        //
        $this->debug->log('this is a test');
        $this->debug->log(new \DateTime());
        $this->expectedSubject = 'Debug Log';
        $this->debug->internalEvents->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        $this->debug->setCfg('emailLog', 'onError');

        //
        // Test that not emailed if no error
        //
        $this->debug->internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that not emailed for notice
        //
        $undefinedVar;  // notice
        $this->debug->internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that emailed if there's an error
        //
        1 / 0; // warning
        $this->expectedSubject = 'Debug Log: Error';
        $this->debug->internalEvents->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        //
        // Test that not emailed if disabled
        //
        $this->debug->setCfg('emailLog', false);
        $this->debug->internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);
    }

    public function emailMock($toAddr, $subject, $body)
    {
        $this->emailCalled = true;
        $this->assertSame($this->debug->getCfg('emailTo'), $toAddr);
        $this->assertSame($this->expectedSubject, $subject);
        $unserialized = $this->debug->routeEmail->unserializeLog($body, $this->debug);
        $expect = array(
            'alerts' => $this->debug->getData('alerts'),
            'log' => $this->debug->getData('log'),
            'logSummary' => $this->debug->getData('logSummary'),
            'requestId' => $this->debug->getData('requestId'),
            'runtime' => $this->debug->getData('runtime'),
            'rootChannel' => $this->debug->getCfg('channelName'),
            'channels' => \array_map(function (Debug $channel) {
                return array(
                    'channelIcon' => $channel->getCfg('channelIcon'),
                    'channelShow' => $channel->getCfg('channelShow'),
                );
            }, $this->debug->getChannels(true)),
            'config' => array(
                'logRuntime' => $this->debug->getCfg('logRuntime'),
            ),
            'version' => Debug::VERSION,
        );
        $this->assertEquals(
            $this->deObjectifyData($expect)['logSummary'],
            $this->deObjectifyData($unserialized)['logSummary']
        );
    }
}
