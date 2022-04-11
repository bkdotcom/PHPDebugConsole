<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\Test\PolyFill\ExpectExceptionTrait;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\InternalEvents
 * @covers \bdk\Debug\Route\Email
 */
class InternalEventsTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    public function testDumpCustom()
    {
        $val = new Abstraction('someCustomValueType', array('foo' => '<b>bar&baz</b>'));
        $expect = '<span class="t_someCustomValueType" data-type-more="t_string"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
<ul class="array-inner list-unstyled">
\t<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">&lt;b&gt;bar&amp;baz&lt;/b&gt;</span></li>
\t<li><span class="t_key">type</span><span class="t_operator">=&gt;</span><span class="t_string">someCustomValueType</span></li>
</ul><span class="t_punct">)</span></span></span>';
        $expect = \str_replace('\\t', "\t", $expect);
        $dumped = $this->debug->getDump('html')->valDumper->dump($val);
        $this->assertSame($expect, $dumped);

        $callable = function (Event $event) {
            $event['return'] = '<span>woo</span>';
        };
        $this->debug->eventManager->subscribe(Debug::EVENT_DUMP_CUSTOM, $callable);
        $dumped = $this->debug->getDump('html')->valDumper->dump($val);
        $this->assertSame('<span class="t_someCustomValueType"><span>woo</span></span>', $dumped);
        $this->debug->eventManager->unSubscribe(Debug::EVENT_DUMP_CUSTOM, $callable);
    }

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
        ));

        $container = $this->helper->getPrivateProp($this->debug, 'container');
        $internalEvents = $container['internalEvents'];

        //
        // Test that not emailed if nothing logged
        //
        $internalEvents->onShutdownLow();
        $this->assertEmpty($this->emailInfo);

        //
        // Test that emailed if something logged
        //
        $this->debug->log('this is a test');
        $this->debug->log(new \DateTime());
        $internalEvents->onShutdownLow();
        $this->assertNotEmpty($this->emailInfo);
        $this->assertSame($this->debug->getCfg('emailTo'), $this->emailInfo['to']);
        $this->assertSame('Debug Log', $this->emailInfo['subject']);
        $this->assertContainsSerializedLog($this->emailInfo['body']);
        $this->emailInfo = array();

        $this->debug->setCfg('emailLog', 'onError');

        //
        // Test that not emailed if no error
        //
        $internalEvents->onShutdownLow();
        $this->assertEmpty($this->emailInfo);

        //
        // Test that not emailed for notice
        //
        $undefinedVar;  // notice
        $internalEvents->onShutdownLow();
        $this->assertEmpty($this->emailInfo);

        //
        // Test that emailed if there's an error
        //
        // 1 / 0; // warning
        $this->debug->errorHandler->handleError(E_WARNING, 'you have been warned', __FILE__, __LINE__);
        $internalEvents->onShutdownLow();
        $this->assertNotEmpty($this->emailInfo);
        $this->assertSame('Debug Log: Error', $this->emailInfo['subject']);
        $this->assertContainsSerializedLog($this->emailInfo['body']);
        $this->emailInfo = array();

        //
        // Test that not emailed if disabled
        //
        $this->debug->setCfg('emailLog', false);
        $internalEvents->onShutdownLow();
        $this->assertEmpty($this->emailInfo);
    }

    /*
    public function testOnError()
    {
        $error = new Error($this->debug->errorHandler, E_WARNING, 'test error', __FILE__, __LINE__);
        $error['throw'] = true;
        $errorValues = $error->getValues();
        $logCount = $this->debug->data->get('log/__count__');
        $this->debug->internalEvents->onError($error);
        $this->assertSame($errorValues, $error->getValues());
        $this->assertSame($logCount, $this->debug->data->get('log/__count__'));
    }
    */

    public function testErrorAsException()
    {
        /*
        $callable = function (\bdk\ErrorHandler\Error $error) {
            $error['throw'] = true;
        };
        $this->debug->errorHandler->eventManager->subscribe(\bdk\ErrorHandler::EVENT_ERROR, $callable, 1);
        */
        $e = null;
        try {
            $line = __LINE__ + 1;
            \trigger_error('some error');
        } catch (\Exception $e) {
        }
        // $this->debug->errorHandler->eventManager->unsubscribe(\bdk\ErrorHandler::EVENT_ERROR, $callable);
        $this->assertSame(0, $this->debug->data->get('log/__count__'));
        $this->assertInstanceOf('Exception', $e);
        $this->assertSame(
            array(
                'message' => 'some error',
                'file' => __FILE__,
                'line' => $line,
            ),
            array(
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            )
        );
    }

    public function testErrorForceErrorOutput()
    {
        $error = new Error($this->debug->errorHandler, E_ERROR, 'fatality', __FILE__, __LINE__);
        $line = __LINE__ - 1;
        // cli
        // stream
        $this->debug->setCfg(array(
            'serviceProvider' => array(
                'request' => new \bdk\HttpMessage\ServerRequest('GET', '', array(
                    'argv' => array('foo','bar'),
                )),
            ),
            'route' => 'stream',
            // 'stream' => 'php://temp',
            'collect' => false,
        ));
        $this->debug->getRoute('stream')->setCfg('stream', 'php://temp');
        $this->debug->internalEvents->onError($error);
        $logEntry = $this->helper->logEntryToArray($this->debug->data->get('log/__end__'));
        $logEntry['meta']['errorHash'] = '';
        $logEntry['meta']['trace'] = array();
        $this->assertSame(array(
            'method' => 'error',
            'args' => array(
                'Fatal Error:',
                'fatality',
                __FILE__ . ' (line ' . $line . ')',
            ),
            'meta' => array(
                'channel' => 'general.phpError',
                'context' => null,
                'detectFiles' => true,
                'errorCat' => 'fatal',
                'errorHash' => '',
                'errorType' => E_ERROR,
                'file' => __FILE__,
                'isSuppressed' => false,
                'line' => $line,
                'sanitize' => true,
                'trace' => array(),
                'uncollapse' => true,
            ),
        ), $logEntry);
    }

    public function testErrorCollectNoOutputYes()
    {
        $this->debug->setCfg(array(
            'collect' => false,
            'output' => true,
        ));
        $error = new Error($this->debug->errorHandler, E_NOTICE, 'Hi error', __FILE__, __LINE__);
        $this->debug->internalEvents->onError($error);
        $this->assertSame(0, $this->debug->data->get('log/__count__'));
        $this->assertFalse($error['email']);
        $this->assertFalse($error['inConsole']);
    }

    public function testErrorCollectNoOutputNo()
    {
        $this->debug->setCfg(array(
            'collect' => false,
            'output' => false,
        ));
        $error = new Error($this->debug->errorHandler, E_NOTICE, 'Hi error', __FILE__, __LINE__);
        $this->debug->internalEvents->onError($error);
        $this->assertSame(0, $this->debug->data->get('log/__count__'));
        $this->assertArrayNotHasKey('error', $error);
        $this->assertFalse($error['inConsole']);
    }


    public function testPrettify()
    {
        $reflector = new \ReflectionProperty($this->debug->internalEvents, 'highlightAdded');
        $reflector->setAccessible(true);
        $reflector->setValue($this->debug->internalEvents, false);

        $foo = $this->debug->prettify('foo', 'unknown');
        $this->assertSame('foo', $foo);

        $html = $this->debug->prettify('<html><title>test</title></html>', 'text/html');
        $this->assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => null,
                'value' => '<html><title>test</title></html>',
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-markup',
                    ),
                ),
                'addQuotes' => false,
                'contentType' => 'text/html',
                'prettified' => false,
                'prettifiedTag' => false,
                'visualWhiteSpace' => false,
            )),
            $html
        );

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
                'contentType' => 'application/json',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
                'valueDecoded' => $data,
            )),
            $json
        );

        $sql = $this->debug->prettify('SELECT * FROM table WHERE col = "val"', 'application/sql');
        $this->assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => null,
                'value' => \str_replace('·', ' ', 'SELECT·
  *·
FROM·
  table·
WHERE·
  col = "val"'),
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-sql',
                    ),
                ),
                'addQuotes' => false,
                'contentType' => 'application/sql',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
            )),
            $sql
        );

        $xmlExpect = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tns="http://www.SoapClient.com/xml/SQLDataSoap.wsdl" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/">
  <SOAP-ENV:Body>
    <mns:ProcessSRLResponse xmlns:mns="http://www.SoapClient.com/xml/SQLDataSoap.xsd" SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
      <return xsi:type="xsd:string"/>
    </mns:ProcessSRLResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
';
        $xml = $this->debug->prettify(\str_replace("\n", '', $xmlExpect), 'application/xml');
        $this->assertEquals(
            new Abstraction(Abstracter::TYPE_STRING, array(
                'strlen' => null,
                'typeMore' => null,
                'value' => $xmlExpect,
                'attribs' => array(
                    'class' => array(
                        'highlight',
                        'language-xml',
                    ),
                ),
                'addQuotes' => false,
                'contentType' => 'application/xml',
                'prettified' => true,
                'prettifiedTag' => true,
                'visualWhiteSpace' => false,
            )),
            $xml
        );

        $this->assertTrue($reflector->getValue($this->debug->internalEvents));
    }

    public function testShutdown()
    {
        if (false) {
            // test find exit
            exit;
        }
        $exitLine = __LINE__ - 2;

        $this->debug->setCfg(array(
            'output' => true,
            'exitCheck' => true,
        ));
        $closure = function () {
            $this->debug->log('in shutdown');
        };

        \ob_start();
        $this->debug->eventManager->subscribe(EventManager::EVENT_PHP_SHUTDOWN, $closure);
        $this->debug->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
        $this->debug->eventManager->unSubscribe(EventManager::EVENT_PHP_SHUTDOWN, $closure);
        $output = \ob_get_clean();

        $logEntriesExpect = array(
            array(
                'method' => 'info',
                'args' => array(
                    'php.shutdown',
                ),
                'meta' => array(
                    'attribs' => array(
                        'class' => [
                            'php-shutdown',
                        ]
                    ),
                    'icon' => 'fa fa-power-off',
                ),
            ),
            array(
                'method' => 'log',
                'args' => array(
                    'in shutdown',
                ),
                'meta' => array(),
            ),
        );

        if (\bdk\Backtrace::isXdebugFuncStackAvail()) {
            $this->markTestSkipped('requires xdebug_get_function_stack()');
            \array_unshift($logEntriesExpect, array(
                'method' => 'warn',
                'args' => array(
                    'Potentialy shutdown via exit: ',
                    '/Users/bkent/Dropbox/htdocs/common/vendor/bdk/PHPDebugConsole/tests/Debug/InternalEventsTest.php (line ' . $exitLine . ')',
                ),
                'meta' => array(
                    'detectFiles' => true,
                    'file' => '/Users/bkent/Dropbox/htdocs/common/vendor/bdk/PHPDebugConsole/tests/Debug/InternalEventsTest.php',
                    'line' => $exitLine,
                    'uncollapse' => true,
                ),
            ));
            $this->assertStringContainsString('Potentialy shutdown via exit', $output);
        }

        $logEntries = $this->debug->data->get('log');
        $logEntries = $this->helper->deObjectifyData($logEntries);
        $this->assertSame($logEntriesExpect, $logEntries);
    }

    public function assertContainsSerializedLog($string)
    {
        $unserialized = \bdk\Debug\Utility\SerializeLog::unserialize($string);
        $rootInstance = $this->debug->rootInstance;
        $channelNameRoot = $rootInstance->getCfg('channelName', Debug::CONFIG_DEBUG);
        $expect = array(
            'alerts' => $this->helper->deObjectifyData($this->debug->data->get('alerts'), false),
            'log' => $this->helper->deObjectifyData($this->debug->data->get('log'), false),
            'logSummary' => $this->helper->deObjectifyData($this->debug->data->get('logSummary'), false),
            'requestId' => $this->debug->data->get('requestId'),
            'runtime' => $this->debug->data->get('runtime'),
            'config' => array(
                'channelIcon' => $rootInstance->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                'channelName' => $channelNameRoot,
                'channels' => \array_map(function (Debug $channel) use ($channelNameRoot) {
                    $channelName = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
                    return array(
                        'channelIcon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                        'channelShow' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                        'channelSort' => $channel->getCfg('channelSort', Debug::CONFIG_DEBUG),
                        'nested' => \strpos($channelName, $channelNameRoot . '.') === 0,
                    );
                }, $rootInstance->getChannels(true, true)),
                'logRuntime' => $this->debug->getCfg('logRuntime'),
            ),
            'version' => Debug::VERSION,
        );
        foreach ($expect['log'] as $i => $logEntryArray) {
            if (empty($logEntryArray[2])) {
                unset($expect['log'][$i][2]);
            }
        }
        /*
        foreach ($expect['logSummary'][0] as $i => $logEntryArray) {
            if (empty($logEntryArray[2])) {
                unset($expect['logSummary'][0][$i][2]);
            }
        }
        */
        $this->assertEquals(
            $expect,
            $this->helper->deObjectifyData($unserialized)
        );
    }
}
