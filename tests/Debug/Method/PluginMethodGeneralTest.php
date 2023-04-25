<?php

namespace bdk\Test\Debug\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\CustomMethod\General
 * @covers \bdk\Debug\Plugin\CustomMethodTrait
 */
class PluginMethodGeneralTest extends DebugTestFramework
{
    public function testEmail()
    {
        /*
        $this->debug->setCfg(array(
            // 'emailLog' => 'always',
            // 'emailTo' => 'test@email.com', // need an email address to email to!
            // 'output' => false,  // email only sent if not outputing
            'emailFrom' => 'testAdmin@test.com',
            // 'emailFunc' => array($this, 'emailMock'),
        ));
        */
        $toAddr = 'fred@test.com';
        $subject = 'that thing you requested';
        $body = 'Here it is';
        $this->debug->email($toAddr, $subject, $body);
        $this->assertSame(array(
            'to' => $toAddr,
            'subject' => $subject,
            'body' => $body,
            'addHeadersStr' => 'From: testFrom@test.com',
        ), $this->emailInfo);
    }

    /**
     * Plugin/CustomMethod/General
     *
     * @return void
     */
    public function testErrorStats()
    {
        parent::$allowError = true;

        $this->debug->errorHandler->handleError(E_WARNING, 'you have been warned', __FILE__, __LINE__);
        $this->debug->setCfg('collect', false);
        $this->debug->errorHandler->handleError(E_NOTICE, 'we tried to warn you', __FILE__, __LINE__);

        $this->assertSame(array(
            'counts' => array(
                'deprecated' => array('inConsole' => 0, 'notInConsole' => 0, 'suppressed' => 0, ),
                'error'      => array('inConsole' => 0, 'notInConsole' => 0, 'suppressed' => 0, ),
                'fatal'      => array('inConsole' => 0, 'notInConsole' => 0, 'suppressed' => 0, ),
                'notice'     => array('inConsole' => 0, 'notInConsole' => 1, 'suppressed' => 0, ),
                'strict'     => array('inConsole' => 0, 'notInConsole' => 0, 'suppressed' => 0, ),
                'warning'    => array('inConsole' => 1, 'notInConsole' => 0, 'suppressed' => 0, ),
            ),
            'inConsole' => 1,
            'inConsoleCategories' => array(
                'warning',
            ),
            'notInConsole' => 1,
        ), $this->debug->errorStats());
    }

    public function testGetDump()
    {
        $this->assertTrue($this->debug->getDump('html', true));
        $this->assertFalse($this->debug->getDump('bogus', true));

        $this->assertInstanceOf('bdk\\Debug\\Dump\\Text', (new Debug(array(
            'logResponse' => false,
        )))->getDump('text'));

        self::$allowError = true;
        // $this->debug->setCfg('onEUserError', null);
        $this->debug->getDump('bogus');
        $line = __LINE__ - 1;
        $logEntry = $this->debug->data->get('log/__end__');
        $logEntry = $this->helper->logEntryToArray($logEntry);
        // $this->helper->stderr($logEntry);
        $this->assertSame(array(
            'method' => 'warn',
            'args' => array(
                'User Notice:',
                '"dumpBogus" is not accessible',
                __FILE__ . ' (line ' . $line . ')',
            ),
            'meta' => array(
                'channel' => 'general.phpError',
                'context' => null,
                'detectFiles' => true,
                'errorCat' => 'notice',
                'errorHash' => $logEntry['meta']['errorHash'],
                'errorType' => E_USER_NOTICE,
                'file' => __FILE__,
                'isSuppressed' => false,
                'line' => $line,
                'sanitize' => true,
                'trace' => null,
                'uncollapse' => true,
            )
        ), $logEntry);
    }

    /*
    public function testGetSubscriptions()
    {
        $this->assertSame(array(
            Debug::EVENT_CUSTOM_METHOD,
        ), \array_keys($this->debug->customMethodGeneral->getSubscriptions()));
    }
    */

    /**
     * Plugin/CustomMethod/General
     */
    public function testHasLog()
    {
        $this->assertFalse($this->debug->hasLog());
        $this->debug->log('something');
        $this->assertTrue($this->debug->hasLog());
        $this->debug->clear();
        $this->assertFalse($this->debug->hasLog());
    }

    public function testObEnd()
    {
        $levelStart = \ob_get_level();
        $isObCache = $this->debug->data->get('isObCache');
        $needRestart = $isObCache;
        if (!$isObCache) {
            $this->debug->obStart();
            $this->assertTrue($this->debug->data->get('isObCache'));
        }
        $level = \ob_get_level();

        $this->debug->obEnd();
        $this->assertFalse($this->debug->data->get('isObCache'));
        $this->assertSame($level - 1, \ob_get_level());

        $this->debug->obEnd();
        $this->assertFalse($this->debug->data->get('isObCache'));
        $this->assertSame($level - 1, \ob_get_level());

        if ($needRestart) {
            $this->debug->obStart();
        }
    }

    public function testObStart()
    {
        $levelStart = \ob_get_level();
        $isObCache = $this->debug->data->get('isObCache');
        $needReclose = !$isObCache;
        if ($isObCache) {
            $this->debug->obEnd();
            $this->assertFalse($this->debug->data->get('isObCache'));
        }
        $level = \ob_get_level();

        $this->debug->setCfg('collect', false);
        $this->debug->obStart();
        $this->assertFalse($this->debug->data->get('isObCache'));
        $this->assertSame($level, \ob_get_level());

        $this->debug->setCfg('collect', true);
        $this->debug->obStart();
        $this->assertTrue($this->debug->data->get('isObCache'));
        $this->assertSame($level + 1, \ob_get_level());

        $this->debug->obStart();
        $this->assertTrue($this->debug->data->get('isObCache'));
        $this->assertSame($level + 1, \ob_get_level());

        if ($needReclose) {
            $this->debug->obEnd();
        }
    }

    public function testPrettify()
    {
        $data = array('foo','bar');
        $json = $this->debug->prettify(\json_encode($data), 'application/json');
        $this->assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => 'json',
                'value' => \json_encode($data, JSON_PRETTY_PRINT),
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-json',
                    ),
                ),
                'addQuotes' => false,
                'brief' => false,
                'contentType' => 'application/json',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
                'valueDecoded' => $data,
            )),
            $json
        );
    }

    public function testSetErrorCaller()
    {
        $this->setErrorCallerHelper();
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __line__ - 3,
            'groupDepth' => 0,
        ), $this->debug->errorHandler->get('errorCaller'));

        $errorCaller = array(
            'file' => '/path/to/file.php',
            'line' => 128,
        );
        $this->debug->setErrorCaller($errorCaller);
        $this->assertSame(\array_merge(
            $errorCaller,
            array('groupDepth' => 0)
        ), $this->debug->errorHandler->get('errorCaller'));
    }

    protected function setErrorCallerHelper()
    {
        $this->debug->setErrorCaller();
    }
}
