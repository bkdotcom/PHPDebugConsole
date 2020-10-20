<?php

namespace bdk\DebugTests\Plugin;

use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class LogEnvTest extends DebugTestFramework
{

    public function testLogPhpInfoEr()
    {

        $logEnv = new \bdk\Debug\Plugin\LogEnv();

        /*
        $refDebug = new \ReflectionProperty($onBootstrap, 'debug');
        $refDebug->setAccessible(true);
        $refDebug->setValue($onBootstrap, $this->debug);
        */

        $refMethod = new \ReflectionMethod($logEnv, 'logPhpInfoEr');
        $refMethod->setAccessible(true);

        $this->debug->addPlugin($logEnv);

        $this->debug->setCfg('logEnvInfo.errorReporting', true);

        /*
            Test error_reporting != "all" but debug is "all"
        */
        \error_reporting(E_ALL & ~E_STRICT);
        $refMethod->invoke($logEnv);
        // $logEnv->onPluginInit(new Event($this->debug));
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                        . 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)',
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
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
        ));

        /*
            Test debug != all (= "system")
        */
        $this->debug->setCfg('errorReporting', 'system');
        // $refMethod->invoke($onBootstrap);
        // $logEnv->onPluginInit(new Event($this->debug));
        $refMethod->invoke($logEnv);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                        . 'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                    'font-family:monospace; opacity:0.8;',
                    'font-family:inherit; white-space:pre-wrap;',
                ),
                array(
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
        ));

        /*
            Test debug != all (but has same value as error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT);
        // $refMethod->invoke($onBootstrap);
        // $logEnv->onPluginInit(new Event($this->debug));
        $refMethod->invoke($logEnv);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                        . 'PHPDebugConsole\'s errorHandler is also using a errorReporting value of `%cE_ALL & ~E_STRICT%c`',
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
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
        ));

        /*
            Test debug != all (value different than error_reporting)
        */
        $this->debug->setCfg('errorReporting', E_ALL & ~E_STRICT & ~E_DEPRECATED);
        // $refMethod->invoke($onBootstrap);
        // $logEnv->onPluginInit(new Event($this->debug));
        $refMethod->invoke($logEnv);
        $this->testMethod(null, array(), array(
            'entry' => array(
                'warn',
                array(
                    'PHP\'s %cerror_reporting%c is set to `%cE_ALL & ~E_STRICT%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                        . 'PHPDebugConsole\'s errorHandler is using a errorReporting value of `%cE_ALL & ~E_STRICT & ~E_DEPRECATED%c`',
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
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
        ));

        /*
            Reset
        */
        \error_reporting(E_ALL | E_STRICT);
        $this->debug->setCfg('errorReporting', E_ALL | E_STRICT);
        $this->debug->setCfG('logEnvInfo.errorReporting', false);
    }
}
