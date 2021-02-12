<?php

namespace bdk\DebugTests\Plugin;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Plugin\LogReqRes;
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

        $reflect = new \ReflectionObject($logReqRes);
        $logPostMeth = $reflect->getMethod('logPost');
        $logPostMeth->setAccessible(true);
        $logRequestMeth = $reflect->getMethod('logRequest');
        $logRequestMeth->setAccessible(true);

        // Internal caches serverParams (statically)...  use serverParamsRef to clear it
        $debugRef = new \ReflectionObject($this->debug);
        $internalProp = $debugRef->getProperty('internal');
        $internalProp->setAccessible(true);
        $internal = $internalProp->getValue($this->debug);

        $internalRef = new \ReflectionObject($internal);
        $serverParams = $internalRef->getProperty('serverParams');
        $serverParams->setAccessible(true);

        /*
            valid form post
        */
        $post = array('foo' => 'bar');
        $this->debug->setCfg('services', array(
            'request' => $this->debug->request
                ->withMethod('POST')
                ->withParsedBody($post)
                ->withBody(new Stream(\http_build_query($post))),
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertSame(
            array(
                'method' => 'log',
                'args' => array('$_POST', $post),
                'meta' => array(
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
        $this->debug->setCfg('services', array(
            'request' => $this->debug->request
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/json')
                ->withBody(new Stream($requestBody))
                ->withParsedBody(array()),
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    // 'font-style: italic; opacity: 0.8;',
                    // '(prettified)',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-json'),
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'strlen' => null,
                        'type' => Abstracter::TYPE_STRING,
                        'typeMore' => Abstracter::TYPE_STRING_JSON,
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'valueDecoded' => \json_decode($requestBody, true),
                        'visualWhiteSpace' => false,
                    ),
                ),
                'meta' => array(
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
        $this->debug->setCfg('services', array(
            'request' => $this->debug->request
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(new Stream($requestBody))
                ->withParsedBody($parsedBody),
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertSame(
            array(
                'method' => 'warn',
                'args' => array('It appears application/json was posted with the wrong Content-Type' . "\n"
                    . 'Pay no attention to $_POST and instead use php://input'),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    // 'font-style: italic; opacity: 0.8;',
                    // '(prettified)',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-json'),
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'strlen' => null,
                        'type' => Abstracter::TYPE_STRING,
                        'typeMore' => Abstracter::TYPE_STRING_JSON,
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'valueDecoded' => \json_decode($requestBody, true),
                        'visualWhiteSpace' => false,
                    ),
                ),
                'meta' => array(
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
        $serverParams->setValue($internal, array());
        $files = array(
            'foo' => new UploadedFile(
                TEST_DIR . '/assets/logo.png',
                10000,
                UPLOAD_ERR_OK,
                'logo.png',
                'image/png'
            ),
        );
        $this->debug->rootInstance->setCfg('services', array(
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
                'method' => 'log',
                'args' => array('$_FILES', array(
                    'foo' => array(
                        'error' => UPLOAD_ERR_OK,
                        'name' => 'logo.png',
                        'size' => \filesize(TEST_DIR . '/assets/logo.png'),
                        'tmp_name' => TEST_DIR . '/assets/logo.png',
                        'type' => 'image/png',
                    ),
                )),
                'meta' => array(
                    'channel' => 'Request / Response',
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        /*
            Post with no body
        */
        $this->debug->setCfg('services', array(
            'request' => function () {
                return new ServerRequest('POST');
            },
        ));
        $logPostMeth->invoke($logReqRes);
        $this->assertSame(
            array(
                'method' => 'warn',
                'args' => array('POST request with no body'),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
            $this->logEntryToArray($this->debug->getData('log/0'))
        );
        $this->debug->setData('log', array());

        /*
            Put method
        */
        $requestBody = \json_encode(array('foo' => 'bar=bazy'));
        $this->debug->setCfg('services', array(
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
                'method' => 'log',
                'args' => array(
                    'php://input',
                    // 'font-style: italic; opacity: 0.8;',
                    // '(prettified)',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-json'),
                        ),
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'strlen' => null,
                        'type' => Abstracter::TYPE_STRING,
                        'typeMore' => Abstracter::TYPE_STRING_JSON,
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'valueDecoded' => \json_decode($requestBody, true),
                        'visualWhiteSpace' => false,
                    ),
                ),
                'meta' => array(
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
        $serverParams->setValue($internal, array());
        $this->debug->setCfg('services', array(
            'request' => function () {
                return ServerRequest::fromGlobals();
            },
        ));
    }
}
