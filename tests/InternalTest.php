<?php
/**
 * Run with --process-isolation option
 */

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

        /*
            Test that not emailed if nothing logged
        */
        $this->debug->internal->onShutdown();
        $this->assertFalse($this->emailCalled);

        $this->debug->log('this is a test');

        /*
            Test that emailed if something logged
        */
        $this->expectedSubject = 'Debug Log';
        $this->debug->internal->onShutdown();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        $this->debug->setCfg('emailLog', true);

        /*
            Test that not emailed if no error
        */
        $this->debug->internal->onShutdown();
        $this->assertFalse($this->emailCalled);

        /*
            Test that not emailed for notice
        */
        $notice[bar] = 'undefined constant';
        $this->debug->internal->onShutdown();
        $this->assertFalse($this->emailCalled);

        /*
            Test that emailed if there's an error
        */
        $warning = 1/0; // warning
        $this->expectedSubject = 'Debug Log: Error';
        $this->debug->internal->onShutdown();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        /*
            Test that not emailed if disabled
        */
        $this->debug->setCfg('emailLog', false);
        $this->debug->internal->onShutdown();
        $this->assertFalse($this->emailCalled);
    }

    public function emailMock($toAddr, $subject, $body)
    {
        $this->emailCalled = true;
        $this->assertSame($this->debug->getCfg('emailTo'), $toAddr);
        $this->assertSame($this->expectedSubject, $subject);
        $unserialized = $this->debug->utilities->unserializeLog($body);
        $this->assertSame($this->debug->getData('log'), $unserialized);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testErrorStats()
    {
    }
}
