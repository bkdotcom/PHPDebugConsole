<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Plugin\LogReqRes;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\LogReqRes
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class LogReqResTest extends DebugTestFramework
{
    public function testLogRes()
    {
        $logReqRes = $this->debug->getPlugin('logReqRes');
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertSame(array(), $logEntries);

        $this->debug->data->set('log', array());
        $json = \json_encode(array('foo' => 'bar'));
        $response = (new Response())
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Stream($json));
        $this->debug->setCfg(array(
            'logResponse' => 'auto',
            'serviceProvider' => array(
                'response' => $response,
            ),
        ));
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertSame('Response', $logEntries[0]['args'][0]);
        self::assertSame('Request / Response', $logEntries[0]['meta']['channel']);
        self::assertSame('table', $logEntries[1]['method']);
        self::assertSame(array(
            'Content-Type' => array('value' => 'application/json'),
        ), $logEntries[1]['args'][0]);
        self::assertSame('response headers', $logEntries[1]['meta']['caption']);
        self::assertSame('{' . "\n"
            . '    "foo": "bar"' . "\n"
            . '}', $logEntries[2]['args'][4]['value']);
        self::assertSame(array(
            'foo' => 'bar',
        ), $logEntries[2]['args'][4]['valueDecoded']);

        $this->debug->data->set('log', array());
        $html = '<!DOCTYPE html><html><head><title>WebCo WebPage</title></head><body>Clever Response</body></html>';
        $response = (new Response())
            ->withHeader('Content-Type', 'text/html')
            ->withBody(new Stream($html));
        $this->debug->setCfg(array(
            // 'logEnvInfo' => true,
            'logResponse' => 'auto',
            'serviceProvider' => array(
                'response' => $response,
            ),
        ));
        $logReqRes->logResponse();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertSame('Not logging response body for Content-Type "text/html"', \end($logEntries)['args'][0]);

        $this->debug->obEnd();
    }

    public function testLogPostOrInput()
    {
        $logReqRes = new LogReqRes();
        $this->helper->setProp($logReqRes, 'debug', $this->debug->getChannel(
            'Request / Response',
            array('nested' => false)
        ));

        $this->debug->addPlugin($logReqRes);
        $this->debug->data->set('log', array());
        $this->debug->data->set('logSummary', array());
        $this->debug->setCfg('logRequestInfo', true);

        $reflect = new \ReflectionObject($logReqRes);
        $logPostMeth = $reflect->getMethod('logPostOrInput');
        $logPostMeth->setAccessible(true);
        $logRequestMeth = $reflect->getMethod('logRequest');
        $logRequestMeth->setAccessible(true);

        /*
            valid form post
        */
        $post = array('foo' => 'bar');
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withMethod('POST')
                ->withParsedBody($post)
                ->withBody(new Stream(\http_build_query($post))),
        ));
        $logPostMeth->invoke($logReqRes);
        self::assertSame(
            array(
                'method' => 'log',
                'args' => array('$_POST', $post),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/0'))
        );
        $this->debug->data->set('log', array());

        /*
            json properly posted
        */
        $requestBody = \json_encode(array('foo' => 'bar=baz'));
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/json')
                ->withBody(new Stream($requestBody))
                ->withParsedBody(array()),
        ));
        $logPostMeth->invoke($logReqRes);
        self::assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-json'),
                        ),
                        'brief' => false,
                        'contentType' => 'application/json',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => true,
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
            $this->helper->logEntryToArray($this->debug->data->get('log/0'))
        );
        $this->debug->data->set('log', array());

        /*
            json improperly posted
        */
        $requestBody = \json_encode(array('foo' => 'bar=baz'));
        \parse_str($requestBody, $parsedBody);
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withMethod('POST')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(new Stream($requestBody))
                ->withParsedBody($parsedBody),
        ));
        $logPostMeth->invoke($logReqRes);
        self::assertSame(
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
            $this->helper->logEntryToArray($this->debug->data->get('log/0'))
        );
        self::assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    array(
                        'addQuotes' => false,
                        'attribs' => array(
                            'class' => array('highlight', 'language-json'),
                        ),
                        'brief' => false,
                        'contentType' => 'application/json',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => true,
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
            $this->helper->logEntryToArray($this->debug->data->get('log/1'))
        );
        $this->debug->data->set('log', array());

        /*
            Post with just uploadedFiles
        */
        $this->helper->setProp($this->debug->pluginMethodReqRes, 'serverParams', array());
        $this->debug->rootInstance->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                $request = new ServerRequest('POST', null, array(
                    'REQUEST_METHOD' => 'POST',
                ));
                return $request->withUploadedFiles(array(
                    'foo' => new UploadedFile(
                        TEST_DIR . '/assets/logo.png',
                        10000,
                        UPLOAD_ERR_OK,
                        'logo.png',
                        'image/png'
                    )))
                    ->withCookieParams(array('SESSIONID' => '123'))
                    ->withHeader('X-Test', '123')
                    ->withHeader('Authorization', 'Basic ' . \base64_encode('fred:1234'));
            },
        ));
        $logRequestMeth->invoke($logReqRes);
        self::assertLogEntries(
            array(
                array(
                    'method' => 'table',
                    'args' => array(
                        array(
                            'Authorization' => array(
                                'value' => 'Basic █████████ (base64\'d fred:█████)',
                            ),
                            'X-Test' => array(
                                'value' => array(
                                    'attribs' => array(
                                        'class' => array('text-left'),
                                    ),
                                    'brief' => false,
                                    'debug' => Abstracter::ABSTRACTION,
                                    'strlen' => null,
                                    'type' => Abstracter::TYPE_STRING,
                                    'typeMore' => Abstracter::TYPE_STRING_NUMERIC,
                                    'value' => '123',
                                ),
                            ),
                        ),
                    ),
                    'meta' => array(
                        'caption' => 'request headers',
                        'channel' => 'Request / Response',
                        'sortable' => true,
                        'tableInfo' => array(
                            'class' => null,
                            'columns' => array(
                                array(
                                    'key' => 'value',
                                ),
                            ),
                            'haveObjRow' => false,
                            'indexLabel' => null,
                            'rows' => array(
                                'Authorization' => array(
                                    'isScalar' => true,
                                ),
                                'X-Test' => array(
                                    'isScalar' => true,
                                ),
                            ),
                            'summary' => null,
                        ),

                    ),
                ),
                array(
                    'method' => 'table',
                    'args' => array(
                        array(
                            'SESSIONID' => array(
                                'value' => array(
                                    'attribs' => array(
                                        'class' => array('text-left'),
                                    ),
                                    'brief' => false,
                                    'debug' => Abstracter::ABSTRACTION,
                                    'strlen' => null,
                                    'type' => Abstracter::TYPE_STRING,
                                    'typeMore' => Abstracter::TYPE_STRING_NUMERIC,
                                    'value' => '123',
                                ),
                            ),
                        ),
                    ),
                    'meta' => array(
                        'caption' => '$_COOKIE',
                        'channel' => 'Request / Response',
                        'redact' => true,
                        'sortable' => true,
                        'tableInfo' => array(
                            'class' => null,
                            'columns' => array(
                                array(
                                    'key' => 'value',
                                ),
                            ),
                            'haveObjRow' => false,
                            'indexLabel' => null,
                            'rows' => array(
                                'SESSIONID' => array(
                                    'isScalar' => true,
                                ),
                            ),
                            'summary' => null,
                        ),
                    ),
                ),
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
            ),
            $this->helper->deObjectifyData(\array_slice($this->debug->data->get('log'), 1))
        );
        $this->debug->data->set('log', array());

        /*
            Post with no body
        */
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                return new ServerRequest('POST');
            },
        ));
        $logPostMeth->invoke($logReqRes);
        self::assertSame(
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
            $this->helper->logEntryToArray($this->debug->data->get('log/0'))
        );
        $this->debug->data->set('log', array());

        /*
            Put method
        */
        $requestBody = \json_encode(array('foo' => 'bar=bazy'));
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () use ($requestBody) {
                $request = new ServerRequest('PUT');
                return $request
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream($requestBody));
            },
        ));
        $logPostMeth->invoke($logReqRes);
        self::assertEquals(
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
                        'brief' => false,
                        'contentType' => 'application/json',
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => true,
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
            $this->helper->logEntryToArray($this->debug->data->get('log/0'))
        );
        $this->debug->data->set('log', array());

        /*
            Get with body
        */
        $requestBody = \json_encode(array('foo' => 'bar=bazy'));
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () use ($requestBody) {
                $request = new ServerRequest('GET');
                return $request
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream($requestBody));
            },
        ));
        $logPostMeth->invoke($logReqRes);
        self::assertEquals(
            array(
                'method' => 'warn',
                'args' => array('GET request with body'),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/0'))
        );
        $this->debug->data->set('log', array());

        /*
            Reset request
        */
        $this->helper->setProp($this->debug->pluginMethodReqRes, 'serverParams', array());
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                return ServerRequest::fromGlobals();
            },
        ));
    }
}
