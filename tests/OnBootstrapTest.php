<?php
/**
 * Run with --process-isolation option
 */

/**
 * PHPUnit tests for Debug class
 */
class OnBootstrapTest extends DebugTestFramework
{

    public function testLogPhpInfoEr()
    {
        $onBootstrap = new \bdk\Debug\OnBootstrap();

        $refDebug = new \ReflectionProperty($onBootstrap, 'debug');
        $refDebug->setAccessible(true);
        $refDebug->setValue($onBootstrap, $this->debug);

        $refMethod = new \ReflectionMethod($onBootstrap, 'logPhpInfoEr');
        $refMethod->setAccessible(true);
        /*
            Test error_reporting != "all" but debug is "all"
        */
        error_reporting(E_ALL & ~E_STRICT);
        $refMethod->invoke($onBootstrap);
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
                ),
            ),
        ));

        /*
            Test debug != all (= "system")
        */
        $this->debug->setCfg('errorReporting', 'system');
        $refMethod->invoke($onBootstrap);
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
                ),
            ),
        ));

        /*
            Test debug != all (but has same value as error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT);
        $refMethod->invoke($onBootstrap);
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
                ),
            ),
        ));

        /*
            Test debug != all (value different than error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT & ~E_DEPRECATED);
        $refMethod->invoke($onBootstrap);
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
