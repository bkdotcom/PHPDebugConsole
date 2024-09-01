<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\HttpMessage\ServerRequestExtended as ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\HttpMessage\Utility\ContentType;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Plugin\AbstractLogReqRes
 * @covers \bdk\Debug\Plugin\LogRequest
 * @covers \bdk\Debug\Plugin\Redaction
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class LogRequestTest extends DebugTestFramework
{
    protected $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>
<note>
    <to>Jack</to>
    <from>Jill</from>
    <subject>Reminder</subject>
    <message>I\'m thirsty</message>
    <password>foo</password>
</note>';

    /**
     * @doesNotPerformAssertions
     */
    public function testBootstrap()
    {
        $this->debug->removePlugin($this->debug->getPlugin('logRequest'));
        $this->debug->addPlugin(new \bdk\Debug\Plugin\LogRequest(), 'logRequest');
    }

    public function testJsonRequest()
    {
        $requestBody = \json_encode(array('foo' => 'bar=baz'));
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withMethod('POST')
                ->withHeader('Content-Type', ContentType::JSON)
                ->withBody(new Stream($requestBody))
                ->withParsedBody(array()),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    array(
                        'attribs' => array(
                            'class' => array('highlight', 'language-json', 'no-quotes'),
                        ),
                        'brief' => false,
                        'contentType' => ContentType::JSON,
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => true,
                        // 'strlen' => 24,
                        // 'strlenValue' => 24,
                        'type' => Type::TYPE_STRING,
                        'typeMore' => Type::TYPE_STRING_JSON,
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'valueDecoded' => \json_decode($requestBody, true),
                    ),
                ),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/2'))
        );
    }

    public function testJsonRequestWrongType()
    {
        $requestBody = \json_encode(array('foo' => 'bar=baz'));
        \parse_str($requestBody, $parsedBody);
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withMethod('POST')
                ->withHeader('Content-Type', ContentType::FORM)
                ->withBody(new Stream($requestBody))
                ->withParsedBody($parsedBody),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertSame(
            array(
                'method' => 'warn',
                'args' => array('It appears application/json was received with the wrong Content-Type' . "\n"
                    . 'Pay no attention to $_POST and instead use php://input'),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/2'))
        );
        self::assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    array(
                        'attribs' => array(
                            'class' => array('highlight', 'language-json', 'no-quotes'),
                        ),
                        'brief' => false,
                        'contentType' => ContentType::JSON,
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => true,
                        // 'strlen' => 24,
                        // 'strlenValue' => 24,
                        'type' => Type::TYPE_STRING,
                        'typeMore' => Type::TYPE_STRING_JSON,
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'valueDecoded' => \json_decode($requestBody, true),
                    ),
                ),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/3'))
        );
    }

    public function testXmlRequest()
    {
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withHeader('Content-Type', 'application/debug+xml')
                ->withMethod('POST')
                ->withBody(new Stream($this->xml)),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertSame('php://input', $logEntries[2]['args'][0]);
        self::assertSame('application/debug+xml', $logEntries[2]['args'][1]['contentType']);
        self::assertStringMatchesFormatNormalized(\str_replace('foo', '█████████', $this->xml), $logEntries[2]['args'][1]['value']);
    }

    public function testXmlRequestWrongType()
    {
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => $this->debug->serverRequest
                ->withHeader('Content-Type', ContentType::FORM)
                ->withMethod('PUT')
                ->withBody(new Stream($this->xml)),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        $contentTypeDetected = $logEntries[3]['args'][1]['contentType']; // either text/xml or application/xml
        self::assertSame('It appears ' . $contentTypeDetected . ' was received with the wrong Content-Type', $logEntries[2]['args'][0]);
        self::assertSame('php://input', $logEntries[3]['args'][0]);
        self::assertTrue(
            \in_array($contentTypeDetected, array(ContentType::XML, 'application/xml'), true),
            \sprintf('%s not in %s', $contentTypeDetected, \json_encode(array(ContentType::XML, 'application/xml')))
        );
        self::assertStringMatchesFormatNormalized(\str_replace('foo', '█████████', $this->xml), $logEntries[3]['args'][1]['value']);
    }

    public function testPostMeth()
    {
        $post = array('foo' => 'bar');
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => (new ServerRequest('POST'))
                ->withHeader('Content-Type', ContentType::FORM)
                ->withParsedBody($post)
                ->withBody(new Stream(\http_build_query($post))),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertSame(
            array(
                'method' => 'log',
                'args' => array('$_POST', $post),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/2'))
        );
    }

    /**
     * A "from globals" multipart request probably won't contain the raw body as PHP doesn't make it available
     */
    public function testPostMethMultipart()
    {
        $post = array('foo' => 'bar');
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => (new ServerRequest('POST'))
                ->withHeader('Content-Type', ContentType::FORM_MULTIPART . '; boundary=----testme')
                ->withParsedBody($post),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertSame(
            array(
                'method' => 'log',
                'args' => array('$_POST', $post),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/2'))
        );
    }

    /**
     * PHP won't have php://input with multipart, but it's possible
     *   the supplied ServerRequest object may contain a supplied body
     */
    public function testPostMethMultipartWithBody()
    {
        $post = array('foo' => 'bar');
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => (new ServerRequest('POST'))
                ->withHeader('Content-Type', ContentType::FORM_MULTIPART . '; boundary=----testme')
                ->withParsedBody($post)
                ->withBody(new Stream(\http_build_query($post))),
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertSame(
            array(
                'method' => 'log',
                'args' => array('$_POST', $post),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/2'))
        );
    }

    public function testPostOnlyFiles()
    {
        $this->debug->rootInstance->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                $request = new ServerRequest('POST', null, array(
                    'REQUEST_METHOD' => 'POST',
                ));
                return $request
                    ->withUploadedFiles(array(
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
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

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
                                    // 'strlen' => 3,
                                    // 'strlenValue' => 3,
                                    'type' => Type::TYPE_STRING,
                                    'typeMore' => Type::TYPE_STRING_NUMERIC,
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
                            'summary' => '',
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
                                    // 'strlen' => 3,
                                    // 'strlenValue' => 3,
                                    'type' => Type::TYPE_STRING,
                                    'typeMore' => Type::TYPE_STRING_NUMERIC,
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
                            'summary' => '',
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
    }

    public function testPostNoBody()
    {
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                return new ServerRequest('POST');
            },
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertSame(
            array(
                'method' => 'warn',
                'args' => array('POST request with no body'),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/1'))
        );
    }

    public function testPutMethod()
    {
        $requestBody = \json_encode(array('foo' => 'bar=bazy'));
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () use ($requestBody) {
                $request = new ServerRequest('PUT');
                return $request
                    ->withHeader('Content-Type', ContentType::JSON)
                    ->withBody(new Stream($requestBody));
            },
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        self::assertEquals(
            array(
                'method' => 'log',
                'args' => array(
                    'php://input',
                    // 'font-style: italic; opacity: 0.8;',
                    // '(prettified)',
                    array(
                        'attribs' => array(
                            'class' => array('highlight', 'language-json', 'no-quotes'),
                        ),
                        'brief' => false,
                        'contentType' => ContentType::JSON,
                        'debug' => Abstracter::ABSTRACTION,
                        'prettified' => true,
                        'prettifiedTag' => true,
                        // 'strlen' => 25,
                        // 'strlenValue' => 25,
                        'type' => Type::TYPE_STRING,
                        'typeMore' => Type::TYPE_STRING_JSON,
                        'value' => \json_encode(\json_decode($requestBody), JSON_PRETTY_PRINT),
                        'valueDecoded' => \json_decode($requestBody, true),
                    ),
                ),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'redact' => true,
                ),
            ),
            $this->helper->logEntryToArray($this->debug->data->get('log/2'))
        );
    }

    public function testGetMethod()
    {
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                $request = (new ServerRequest('GET'))
                    ->withCookieParams(array('SESSIONID' => '123'))
                    ->withHeader('X-Test', '123');
                return $request;
            },
        ));
        $this->debug->setCfg('logRequestInfo', true);
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertCount(3, $logEntries);
        self::assertSame('request headers', $logEntries[1]['meta']['caption']);
        self::assertSame('$_COOKIE', $logEntries[2]['meta']['caption']);
    }

    public function testGetWithBody()
    {
        $requestBody = \json_encode(array('foo' => 'bar=bazy'));
        $this->debug->setCfg('serviceProvider', array(
            'serverRequest' => static function () use ($requestBody) {
                $request = new ServerRequest('GET');
                return $request
                    ->withHeader('Content-Type', ContentType::JSON)
                    ->withCookieParams(array('SESSIONID' => '123'))
                    ->withHeader('X-Test', '123')
                    ->withBody(new Stream($requestBody));
            },
        ));
        $this->debug->setCfg('logRequestInfo', array(
            'post',
        ));
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();

        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertCount(3, $logEntries);
        self::assertEquals(
            array(
                'method' => 'warn',
                'args' => array('GET request with body'),
                'meta' => array(
                    'channel' => 'Request / Response',
                    'detectFiles' => false,
                    'evalLine' => null,
                    'file' => null,
                    'line' => null,
                    'uncollapse' => true,
                ),
            ),
            $logEntries[1]
        );
        self::assertSame(ContentType::JSON, $logEntries[2]['args'][1]['contentType']);
        self::assertSame(array('foo' => 'bar=bazy'), $logEntries[2]['args'][1]['valueDecoded']);
    }

    public function testDontLogFiles()
    {
        $this->debug->rootInstance->setCfg('serviceProvider', array(
            'serverRequest' => static function () {
                $request = new ServerRequest('POST');
                return $request->withUploadedFiles(array(
                    'foo' => new UploadedFile(
                        TEST_DIR . '/assets/logo.png',
                        10000,
                        UPLOAD_ERR_OK,
                        'logo.png',
                        'image/png'
                    )))
                    ->withCookieParams(array('SESSIONID' => '123'))
                    ->withHeader('X-Test', '123');
            },
        ));
        $this->debug->setCfg('logRequestInfo', array(
            'cookies',
        ));
        $logReqRes = $this->debug->getPlugin('logRequest');
        $logReqRes->logRequest();
        $logEntries = $this->helper->deObjectifyData($this->debug->data->get('log'));
        self::assertCount(2, $logEntries);
        self::assertSame(array('SESSIONID'), \array_keys($logEntries[1]['args'][0]));
        self::assertSame('$_COOKIE', $logEntries[1]['meta']['caption']);
    }
}
