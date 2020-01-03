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

        /*
            Test that not emailed if nothing logged
        */
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        /*
            Test that emailed if something logged
        */
        $this->debug->log('this is a test');
        $this->debug->log(new \DateTime());
        $this->expectedSubject = 'Debug Log';
        $this->debug->internal->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        $this->debug->setCfg('emailLog', 'onError');

        /*
            Test that not emailed if no error
        */
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        /*
            Test that not emailed for notice
        */
        $notice = $undefinedVar;
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);

        /*
            Test that emailed if there's an error
        */
        $warning = 1/0; // warning
        $this->expectedSubject = 'Debug Log: Error';
        $this->debug->internal->onShutdownLow();
        $this->assertTrue($this->emailCalled);
        $this->emailCalled = false;

        /*
            Test that not emailed if disabled
        */
        $this->debug->setCfg('emailLog', false);
        $this->debug->internal->onShutdownLow();
        $this->assertFalse($this->emailCalled);
    }

    public function testLogPost()
    {
        $onBootstrap = new \bdk\Debug\OnBootstrap();
        $reflect = new ReflectionObject($onBootstrap);
        $debugProp = $reflect->getProperty('debug');
        $debugProp->setAccessible(true);
        $debugProp->setValue($onBootstrap, $this->debug);
        $inputProp = $reflect->getProperty('input');
        $inputProp->setAccessible(true);
        $logPostMeth = $reflect->getMethod('logPost');
        $logPostMeth->setAccessible(true);

        $_SERVER['REQUEST_METHOD'] = 'POST';

        // valid form post
        $_POST = array('foo'=>'bar');
        $inputProp->setValue($onBootstrap, http_build_query($_POST));
        $logPostMeth->invoke($onBootstrap);
        $this->assertSame(
            array(
                'log',
                array('$_POST', $_POST),
                array('redact' => true)
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        // json properly posted
        $_POST = array();
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $input = json_encode(array('foo'=>'bar=baz'));
        // parse_str($input, $_POST);
        $inputProp->setValue($onBootstrap, $input);
        $logPostMeth->invoke($onBootstrap);
        $this->assertEquals(
            array(
                'log',
                array(
                    'php://input %c%s',
                    'font-style: italic; opacity: 0.8;',
                    '(prettified)',
                    new Abstraction(array(
                        'type' => 'string',
                        'attribs' => array(
                            'class' => 'language-json prism',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                        'value' => json_encode(json_decode($input), JSON_PRETTY_PRINT),
                    ))
                ),
                array(
                    'redact' => true,
                )
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        // json improperly posted
        $input = json_encode(array('foo'=>'bar=baz'));
        parse_str($input, $_POST);
        $inputProp->setValue($onBootstrap, $input);
        $logPostMeth->invoke($onBootstrap);
        $this->assertSame(
            array('warn',
                array('It appears application/json was posted with the wrong Content-Type'."\n"
                .'Pay no attention to $_POST and instead use php://input'),
                array('detectFiles'=>false,'file'=>null,'line'=>null),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->assertEquals(
            array(
                'log',
                array(
                    'php://input %c%s',
                    'font-style: italic; opacity: 0.8;',
                    '(prettified)',
                    new Abstraction(array(
                        'type' => 'string',
                        'attribs' => array(
                            'class' => 'language-json prism',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                        'value' => json_encode(json_decode($input), JSON_PRETTY_PRINT),
                    ))
                ),
                array(
                    'redact' => true,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/1'))
        );
        $this->debug->setData('log', array());

        // post with just $_FILES
        $input = '';
        $_POST = array();
        $_FILES = array(array('foo'=>'bar'));
        $inputProp->setValue($onBootstrap, $input);
        $logPostMeth->invoke($onBootstrap);
        $this->assertSame(
            array(
                'log',
                array('$_FILES', $_FILES),
                array(),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        // post with no body
        $input = '';
        $_POST = array();
        $_FILES = array();
        $inputProp->setValue($onBootstrap, $input);
        $logPostMeth->invoke($onBootstrap);
        $this->assertSame(
            array(
                'warn',
                array('POST request with no body'),
                array('detectFiles'=>false,'file'=>null,'line'=>null),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        // put method
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $input = json_encode(array('foo'=>'bar=baz'));
        $_POST = array();
        // parse_str($input, $_POST);
        $inputProp->setValue($onBootstrap, $input);
        $logPostMeth->invoke($onBootstrap);
        $this->assertEquals(
            array(
                'log',
                array(
                    'php://input %c%s',
                    'font-style: italic; opacity: 0.8;',
                    '(prettified)',
                    new Abstraction(array(
                        'type' => 'string',
                        'attribs' => array(
                            'class' => 'language-json prism',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                        'value' => json_encode(json_decode($input), JSON_PRETTY_PRINT),
                    ))
                ),
                array('redact' => true)
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());
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
        $this->assertEquals($this->deObjectify($expect), $this->deObjectify($unserialized));
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
