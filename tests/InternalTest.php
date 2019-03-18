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
        // $notice[bar] = 'undefined constant';    // this is a warning in PHP 7.2
        $notice = $undefinedVar;
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
        $unserialized = $this->debug->utilities->unserializeLog($this->debug, $body);
        $expect = array(
            'alerts' => $this->debug->getData('alerts'),
            'log' => $this->debug->getData('log'),
            'logSummary' => $this->debug->getData('logSummary'),
            'requestId' => $this->debug->getData('requestId'),
            'runtime' => $this->debug->getData('runtime'),
        );
        $this->assertSame($this->deObjectify($expect), $this->deObjectify($unserialized));
    }

    protected function deObjectify($data)
    {
        foreach ($data['alerts'] as $i => $v) {
            $data['alerts'][$i] = array(
                $v['method'],
                $v['args'],
                $v['meta'],
            );
        }
        foreach ($data['log'] as $i => $v) {
            $data['log'][$i] = array(
                $v['method'],
                $v['args'],
                $v['meta'],
            );
        }
        foreach ($data['logSummary'] as $i => $group) {
            foreach ($group as $i2 => $v) {
                $data['logSummary'][$i][$i2] = array(
                    $v['method'],
                    $v['args'],
                    $v['meta'],
                );
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

        1/0;    // warning

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
        ), $this->debug->internal->errorStats());
    }

    public function testHasLog()
    {
        $this->assertFalse($this->debug->internal->hasLog());
        $this->debug->log('something');
        $this->assertTrue($this->debug->internal->hasLog());
        $this->debug->clear();
        $this->assertFalse($this->debug->internal->hasLog());
    }

    public function testLogPhpInfoEr()
    {
        $refMethod = new \ReflectionMethod($this->debug->internal, 'logPhpInfoEr');
        $refMethod->setAccessible(true);
        /*
            Test error_reporting != "all" but debug is "all"
        */
        error_reporting(E_ALL & ~E_STRICT);
        $refMethod->invoke($this->debug->internal);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`'."\n"
                        .'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                array(
                    'file' => null,
                    'line' => null,
                    'channel' => 'general',
                ),
            ),
        ));

        /*
            Test debug != all (= "system")
        */
        $this->debug->setCfg('errorReporting', 'system');
        $refMethod->invoke($this->debug->internal);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`'."\n"
                        .'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                array(
                    'file' => null,
                    'line' => null,
                    'channel' => 'general',
                ),
            ),
        ));

        /*
            Test debug != all (but has same value as error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT);
        $refMethod->invoke($this->debug->internal);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`'."\n"
                        .'PHPDebugConsole\'s errorHandler is also using a errorReporting value of `%cE_ALL & ~E_STRICT%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                array(
                    'file' => null,
                    'line' => null,
                    'channel' => 'general',
                ),
            ),
        ));

        /*
            Test debug != all (value different than error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT & ~E_DEPRECATED);
        $refMethod->invoke($this->debug->internal);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`'."\n"
                        .'PHPDebugConsole\'s errorHandler is using a errorReporting value of `%cE_ALL & ~E_STRICT & ~E_DEPRECATED%c`',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                array(
                    'file' => null,
                    'line' => null,
                    'channel' => 'general',
                ),
            ),
        ));

        /*
            Reset
        */
        error_reporting(E_ALL | E_STRICT);
        $this->debug->setCfg('errorReporting', E_ALL | E_STRICT);
    }
}
