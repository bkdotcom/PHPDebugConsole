<?php

namespace bdk\DebugTests\Plugin;

use bdk\Debug;
use bdk\Debug\Plugin\LogReqRes;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Psr7lite\ServerRequest;
use bdk\Debug\Psr7lite\Stream;
use bdk\Debug\Psr7lite\UploadedFile;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class LogReqResTest extends DebugTestFramework
{

    public function testLogPost()
    {
        $logReqRes = new LogReqRes();
        $this->debug->addPlugin($logReqRes);
        $this->debug->setData('log', array());
        $this->debug->setData('logSummary', array());
        $this->debug->setCfg('logRequestInfo', true);

        $debugSingle = Debug::getInstance();

        $reflect = new \ReflectionObject($logReqRes);

        $logPostMeth = $reflect->getMethod('logPost');
        $logPostMeth->setAccessible(true);
        $logRequestMeth = $reflect->getMethod('logRequest');
        $logRequestMeth->setAccessible(true);

        // Utility caches serverParams (statically)...  use serverParamsRef to clear it
        $utilityRef = new \ReflectionClass('bdk\\Debug\\Utility');
        $serverParamsRef = $utilityRef->getProperty('serverParams');
        $serverParamsRef->setAccessible(true);

        /*
            valid form post
        */
        $post = array('foo' => 'bar');
        $debugSingle->setCfg('services', array(
            'request' => $debugSingle->request
                ->withMethod('POST')
                ->withParsedBody($post)
                ->withBody(new Stream(\http_build_query($post))),
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertSame(
            array(
                'log',
                array('$_POST', $post),
                array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        /*
            json properly posted
        */
        $requestBody = \json_encode(array('foo' => 'bar=baz'));
        $debugSingle->setCfg('services', array(
            'request' => $debugSingle->request
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/json')
                ->withBody(new Stream($requestBody))
                ->withParsedBody(array()),
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertEquals(
            array(
                'log',
                array(
                    'php://input %c%s',
                    'font-style: italic; opacity: 0.8;',
                    '(prettified)',
                    new Abstraction(Abstracter::TYPE_STRING, array(
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'attribs' => array(
                            'class' => 'highlight language-json',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                    ))
                ),
                array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                )
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        /*
            json improperly posted
        */
        $requestBody = \json_encode(array('foo' => 'bar=baz'));
        \parse_str($requestBody, $parsedBody);
        $debugSingle->setCfg('services', array(
            'request' => $debugSingle->request
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(new Stream($requestBody))
                ->withParsedBody($parsedBody),
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertSame(
            array('warn',
                array('It appears application/json was posted with the wrong Content-Type' . "\n"
                . 'Pay no attention to $_POST and instead use php://input'),
                array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                ),
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
                    new Abstraction(Abstracter::TYPE_STRING, array(
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'attribs' => array(
                            'class' => 'highlight language-json',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                    ))
                ),
                array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/1'))
        );
        $this->debug->setData('log', array());

        /*
            Post with just uploadedFiles
        */
        $this->debug->setData('log', array());
        $serverParamsRef->setValue(array());
        $files = array(
            'foo' => new UploadedFile(
                TEST_DIR . '/assets/logo.png',
                10000,
                UPLOAD_ERR_OK,
                'logo.png',
                'image/png'
            ),
        );
        $debugSingle->setCfg('services', array(
            'request' => function () use ($files) {
                $request = new ServerRequest('POST', null, array(
                    'REQUEST_METHOD' => 'POST',
                ));
                return $request->withUploadedFiles($files);
            },
        ));
        $this->clearServerParamCache();
        $logRequestMeth->invoke($logReqRes);
        $this->assertSame(
            array(
                'log',
                array('$_FILES', array(
                    'foo' => array(
                        'error' => UPLOAD_ERR_OK,
                        'name' => 'logo.png',
                        'size' => 8138,
                        'tmp_name' => TEST_DIR . '/assets/logo.png',
                        'type' => 'image/png',
                    ),
                )),
                array(
                    'channel' => 'Request / Response',
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        /*
            Post with no body
        */
        $debugSingle->setCfg('services', array(
            'request' => function () {
                return new ServerRequest('POST');
            },
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertSame(
            array(
                'warn',
                array('POST request with no body'),
                array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        /*
            Put method
        */
        $requestBody = \json_encode(array('foo' => 'bar=bazy'));
        $debugSingle->setCfg('services', array(
            'request' => function () use ($requestBody) {
                $request = new ServerRequest('PUT');
                return $request
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream($requestBody));
            },
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertEquals(
            array(
                'log',
                array(
                    'php://input %c%s',
                    'font-style: italic; opacity: 0.8;',
                    '(prettified)',
                    new Abstraction(Abstracter::TYPE_STRING, array(
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'attribs' => array(
                            'class' => 'highlight language-json',
                        ),
                        'addQuotes' => false,
                        'visualWhiteSpace' => false,
                    ))
                ),
                array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());


        /*
            Reset request
        */
        $serverParamsRef->setValue(array());
        $debugSingle->setCfg('services', array(
            'request' => function () {
                return ServerRequest::fromGlobals();
            },
        ));
    }
}
