<?php

namespace bdk\DebugTests;

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

        $container = $this->getPrivateProp($this->debug, 'container');
        $internalEvents = $container['internalEvents'];

        //
        // Test that not emailed if nothing logged
        //
        $internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that emailed if something logged
        //
        $this->debug->log('this is a test');
        $this->debug->log(new \DateTime());
        $this->expectedSubject = 'Debug Log';
        $internalEvents->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        $this->debug->setCfg('emailLog', 'onError');

        //
        // Test that not emailed if no error
        //
        $internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that not emailed for notice
        //
        $undefinedVar;  // notice
        $internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that emailed if there's an error
        //
        // 1 / 0; // warning
        $this->debug->errorHandler->handleError(E_WARNING, 'you have been warned', __FILE__, __LINE__);
        $this->expectedSubject = 'Debug Log: Error';
        $internalEvents->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        //
        // Test that not emailed if disabled
        //
        $this->debug->setCfg('emailLog', false);
        $internalEvents->onShutdownLow();
        $this->assertFalse($this->emailCalled);
    }

    public function emailMock($toAddr, $subject, $body)
    {
        $this->emailCalled = true;
        $this->assertSame($this->debug->getCfg('emailTo'), $toAddr);
        $this->assertSame($this->expectedSubject, $subject);
        // $unserialized = $this->debug->getRoute('email')->unserializeLog($body, $this->debug);
        $unserialized = \bdk\Debug\Utility\SerializeLog::unserialize($body, $this->debug);
        $expect = array(
            'alerts' => $this->debug->data->get('alerts'),
            'log' => $this->debug->data->get('log'),
            'logSummary' => $this->debug->data->get('logSummary'),
            'requestId' => $this->debug->data->get('requestId'),
            'runtime' => $this->debug->data->get('runtime'),
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
