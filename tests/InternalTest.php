<?php

/**
 * Run with --process-isolation option
 */

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;

/**
 * PHPUnit tests for Debug class
 */
class InternalTest extends DebugTestFramework
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
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that emailed if something logged
        //
        $this->debug->log('this is a test');
        $this->debug->log(new \DateTime());
        $this->expectedSubject = 'Debug Log';
        $this->debug->internal->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        $this->debug->setCfg('emailLog', 'onError');

        //
        // Test that not emailed if no error
        //
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that not emailed for notice
        //
        $undefinedVar;  // notice
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        //
        // Test that emailed if there's an error
        //
        1 / 0; // warning
        $this->expectedSubject = 'Debug Log: Error';
        $this->debug->internal->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        //
        // Test that not emailed if disabled
        //
        $this->debug->setCfg('emailLog', false);
        $this->debug->internal->onShutdownLow();
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
            'version' => \bdk\Debug::VERSION,
        );
        // var_dump($this->deObjectify($expect)['logSummary']);
        // $this->assertEquals($this->deObjectify($expect), $this->deObjectify($unserialized));
        // var_dump($this->deObjectify($expect)['logSummary']);
        $this->assertEquals($this->deObjectify($expect)['logSummary'], $this->deObjectify($unserialized)['logSummary']);
    }

    protected function deObjectify($data)
    {
        /*
        foreach ($data as $i => $v) {
            if ($v instanceof \bdk\Debug\LogEntry) {
                $data[$i] = array(
                    $v['method'],
                    $v['args'],
                    $v['meta'],
                );
            }
        }
        */
        foreach (array('alerts','log') as $what) {
            if (!isset($data[$what])) {
                continue;
            }
            foreach ($data[$what] as $i => $v) {
                $data[$what][$i] = array(
                    $v['method'],
                    $v['args'],
                    $v['meta'],
                );
            }
        }
        if (isset($data['logSummary'])) {
            foreach ($data['logSummary'] as $i => $group) {
                foreach ($group as $i2 => $v) {
                    $data['logSummary'][$i][$i2] = array(
                        $v['method'],
                        $v['args'],
                        $v['meta'],
                    );
                }
            }
        }
        return $data;
    }

    /**
     * Test
     *
     * @return void
     */
    public function testErrorStats()
    {

        parent::$allowError = true;

        1 / 0;    // warning

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

    public function testHasLog()
    {
        $this->assertFalse($this->debug->internal->hasLog());
        $this->debug->log('something');
        $this->assertTrue($this->debug->internal->hasLog());
        $this->debug->clear();
        $this->assertFalse($this->debug->internal->hasLog());
    }
}
