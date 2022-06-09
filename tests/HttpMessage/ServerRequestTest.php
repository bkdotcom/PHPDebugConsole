<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Request;
use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\UploadedFile;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionObject;

/**
 * @covers \bdk\HttpMessage\AbstractServerRequest
 * @covers \bdk\HttpMessage\ServerRequest
 */
class ServerRequestTest extends TestCase
{
    use ExpectExceptionTrait;
    use DataProviderTrait;
    use FactoryTrait;

    public function testConstruct()
    {
        $serverRequest = $this->createServerRequest();
        $this->assertTrue($serverRequest instanceof Message);
        $this->assertTrue($serverRequest instanceof Request);
        $this->assertTrue($serverRequest instanceof ServerRequest);
    }

    public function testAuthHeaders()
    {
        $serverRequest = $this->createServerRequest('GET', 'http://www.test.com/', array(
            'REDIRECT_HTTP_AUTHORIZATION' => 'Basic ' . \base64_encode('username:password'),
        ));
        $this->assertSame(array(
            'Host' => array('www.test.com'),
            'Authorization' => array('Basic ' . \base64_encode('username:password')),
        ), $serverRequest->getHeaders());

        $digestVal = 'Digest username="Mufasa", realm="testrealm@host.com", nonce="dcd98b7102dd2f0e8b11d0f600bfb0c093", uri="/dir/index.html", qop=auth, nc=00000001, cnonce="0a4f113b", response="6629fae49393a05397450978507c4ef1", opaque="5ccc069c403ebaf9f0171e9517f40e41';
        $serverRequest = $this->createServerRequest('GET', 'http://www.test.com/', array(
            'PHP_AUTH_DIGEST' => $digestVal,
        ));
        $this->assertSame(array(
            'Host' => array('www.test.com'),
            'Authorization' => array($digestVal),
        ), $serverRequest->getHeaders());
    }

    public function testConstructWithUri()
    {
        $serverRequest = $this->createServerRequest(
            'GET',
            '/some/page?foo=bar&dingle.berry=brown&a%20b=c&d+e=f&g h=i',
            array(
                'SERVER_PROTOCOL' => 'HTTP/1.0',
                'CONTENT_TYPE' => 'text/html',
            )
        );
        $this->assertSame(array(
            'foo' => 'bar',
            'dingle.berry' => 'brown',
            'a b' => 'c',
            'd e' => 'f',
            'g h' => 'i',
        ), $serverRequest->getQueryParams());
        $this->assertSame('1.0', $serverRequest->getProtocolVersion());
        $this->assertSame('text/html', $serverRequest->getHeaderLine('Content-Type'));

        // test options parsing works on constructor
        ServerRequest::parseStrOpts('convSpace', true);
        $serverRequest = $this->createServerRequest(
            'GET',
            '/some/page?foo=bar&dingle.berry=brown&a%20b=c&d+e=f&g h=i'
        );
        $this->assertSame(array(
            'foo' => 'bar',
            'dingle.berry' => 'brown',
            'a_b' => 'c',
            'd_e' => 'f',
            'g_h' => 'i',
        ), $serverRequest->getQueryParams());
        ServerRequest::parseStrOpts('convSpace', false);

        /*
            Test new values replace
        */
        $serverRequest = $serverRequest->withQueryParams(array(
            'new' => 'new',
        ));
        $this->assertSame(array(
            'new' => 'new',
        ), $serverRequest->getQueryParams());
    }

    public function testFromGlobals()
    {
        $serverBackup = $_SERVER;
        $getBackup = $_GET;
        $_SERVER = array(
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_HOST' => 'www.test.com:8080',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/path?ding=dong',
            'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT'],
            'SCRIPT_NAME' => isset($_SERVER['SCRIPT_NAME'])
                ? $_SERVER['SCRIPT_NAME']
                : null,
            'PHP_AUTH_USER' => 'billybob',
            'PHP_AUTH_PW' => '1234',
        );
        $_FILES = array(
            'files1' => [
                'name' => 'test1.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php1234.tmp',
                'error' => UPLOAD_ERR_OK,
                'size' => 100000,
            ],

            // <input type="file" name="files2[a]">
            // <input type="file" name="files2[b]">

            'files2' => [
                'name' => [
                    'a' => 'test2.jpg',
                    'b' => 'test3.jpg',
                ],
                'type' => [
                    'a' => 'image/jpeg',
                    'b' => 'image/jpeg',
                ],
                'tmp_name' => [
                    'a' => '/tmp/php1235.tmp',
                    'b' => '/tmp/php1236.tmp',
                ],
                'error' => [
                    'a' => UPLOAD_ERR_OK,
                    'b' => UPLOAD_ERR_OK,
                ],
                'size' => [
                    'a' => 100001,
                    'b' => 100010,
                ],
            ],
        );
        ServerRequest::$inputStream = __DIR__ . '/input.json';
        $request = ServerRequest::fromGlobals();
        $this->assertSame(array('foo' => 'bar'), $request->getParsedBody());
        $this->assertSame('http://www.test.com:8080/path?ding=dong', (string) $request->getUri());
        $this->assertEquals(array(
            'files1' => $this->createUploadedFile(
                '/tmp/php1234.tmp',
                100000,
                UPLOAD_ERR_OK,
                'test1.jpg',
                'image/jpeg'
            ),
            'files2' => array(
                'a' => $this->createUploadedFile(
                    '/tmp/php1235.tmp',
                    100001,
                    UPLOAD_ERR_OK,
                    'test2.jpg',
                    'image/jpeg'
                ),
                'b' => $this->createUploadedFile(
                    '/tmp/php1236.tmp',
                    100010,
                    UPLOAD_ERR_OK,
                    'test3.jpg',
                    'image/jpeg'
                ),
            ),
        ), $request->getUploadedFiles());
        $_FILES = array();

        $_SERVER['HTTPS'] = 'on';
        $request = ServerRequest::fromGlobals();
        $this->assertSame('https://www.test.com:8080/path?ding=dong', (string) $request->getUri());

        // test parse_url failure
        $_SERVER = array(
            'HTTP_HOST' => '/s?a=12&b=12.3.3.4:1233',
            'SERVER_PORT' => '8080',
            'QUERY_STRING' => 'ding=dong',
        );
        $request = ServerRequest::fromGlobals();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http:/?ding=dong', (string) $request->getUri());

        $_SERVER = array(
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'somedomain',
            'SERVER_PORT' => '8080',
            // 'QUERY_STRING' => 'ding=dong',
        );
        $request = ServerRequest::fromGlobals();
        $this->assertSame('http://somedomain:8080/', (string) $request->getUri());

        $_SERVER = array(
            'REQUEST_METHOD' => 'GET',
            'SERVER_ADDR' => '192.168.100.42',
            'SERVER_PORT' => '8080',
            // 'QUERY_STRING' => 'ding=dong',
        );
        $_GET = array(
            'foo' => 'bar',
        );
        $request = ServerRequest::fromGlobals();
        $this->assertSame('http://192.168.100.42:8080/?foo=bar', (string) $request->getUri());

        $_SERVER = $serverBackup;
        $_GET = $getBackup;
    }

    public function testPostFromInput()
    {
        $serverRequest = $this->createServerRequest();
        $reflectionMethod = new ReflectionMethod($serverRequest, 'postFromInput');
        $reflectionMethod->setAccessible(true);

        $parsed = $reflectionMethod->invokeArgs($serverRequest, array(
            'application/unknown',
        ));
        $this->assertNull($parsed);

        $parsed = $reflectionMethod->invokeArgs($serverRequest, array(
            'application/json',
        ));
        $this->assertNull($parsed);

        $parsed = $reflectionMethod->invokeArgs($serverRequest, array(
            'application/x-www-form-urlencoded',
            __DIR__ . '/input.txt'
        ));
        $this->assertSame(array(
            0 => 'foo',
            1 => 'bar',
            2 => 'baz',
            4 => 'boom',
            'dingle.berry' => 'brown',
            'a b' => 'c',
            'd e' => 'f',
            'g h' => 'i',
        ), $parsed);

        ServerRequest::parseStrOpts(array(
            'convDot' => true,
            'convSpace' => false,
        ));
        $parsed = $reflectionMethod->invokeArgs($serverRequest, array(
            'application/x-www-form-urlencoded',
            __DIR__ . '/input.txt'
        ));
        $this->assertSame(array(
            0 => 'foo',
            1 => 'bar',
            2 => 'baz',
            4 => 'boom',
            'dingle_berry' => 'brown',
            'a b' => 'c',
            'd e' => 'f',
            'g h' => 'i',
        ), $parsed);
        ServerRequest::parseStrOpts(array(
            'convDot' => false,
            'convSpace' => false,
        ));
    }

    public function testProperties()
    {
        $serverRequest = $this->createServerRequest();

        $properties = array(
            'attributes' => array(),
            'cookie' => array(),
            'get' => array(),
            'post' => null,
            'server' => array(
                'REQUEST_METHOD' => 'GET',
            ),
            'files' => array(),
        );

        $reflection = new ReflectionObject($serverRequest);

        foreach ($properties as $k => $vExpect) {
            $prop = $reflection->getProperty($k);
            $prop->setAccessible(true);
            $this->assertSame($vExpect, $prop->getValue($serverRequest), $k);
            unset($prop);
        }
    }

    public function testGetMethods()
    {
        // Test 1
        $serverRequest = $this->createServerRequest();
        $this->assertSame('GET', $serverRequest->getMethod());
        $this->assertSame([
            'REQUEST_METHOD' => 'GET',
        ], $serverRequest->getServerParams());
        $this->assertSame([], $serverRequest->getCookieParams());
        $this->assertSame(null, $serverRequest->getParsedBody());
        $this->assertSame([], $serverRequest->getQueryParams());
        $this->assertSame([], $serverRequest->getUploadedFiles());
        $this->assertSame([], $serverRequest->getAttributes());

        // Test 2
        $serverRequest = $this->createServerRequest('POST', '', array('what' => 'server'))
            ->withCookieParams(array('what' => 'cookie'))
            ->withParsedBody(array('what' => 'post'))
            ->withQueryParams(array('what' => 'get'))
            ->withAttribute('what', 'attribute')
            ->withUploadedFiles(self::mockFiles(1));

        $this->assertSame('POST', $serverRequest->getMethod());
        $this->assertEquals(array(
            'what' => 'server',
            'REQUEST_METHOD' => 'POST',
        ), $serverRequest->getServerParams());
        $this->assertEquals(array('what' => 'cookie'), $serverRequest->getCookieParams());
        $this->assertEquals(array('what' => 'post'), $serverRequest->getParsedBody());
        $this->assertEquals(array('what' => 'get'), $serverRequest->getQueryParams());
        $this->assertEquals(array('what' => 'attribute'), $serverRequest->getAttributes());
        $this->assertEquals(
            array(
                'file1' => $this->createUploadedFile(
                    '/tmp/php1234.tmp',
                    100000,
                    UPLOAD_ERR_OK,
                    'file1.jpg',
                    'image/jpeg'
                ),
            ),
            $serverRequest->getUploadedFiles()
        );
    }

    public function testWithMethods()
    {
        $new = $this->createServerRequest()
            ->withCookieParams(['foo3' => 'bar3'])
            ->withParsedBody(['foo4' => 'bar4', 'foo5' => 'bar5'])
            ->withQueryParams(['foo6' => 'bar6', 'foo7' => 'bar7'])
            ->withAttribute('foo8', 'bar9')
            ->withUploadedFiles(self::mockFiles(2));

        $this->assertSame('GET', $new->getMethod());
        $this->assertEquals([
            'REQUEST_METHOD' => 'GET',
        ], $new->getServerParams());
        $this->assertEquals(['foo3' => 'bar3'], $new->getCookieParams());
        $this->assertEquals(['foo4' => 'bar4', 'foo5' => 'bar5'], $new->getParsedBody());
        $this->assertEquals(['foo6' => 'bar6', 'foo7' => 'bar7'], $new->getQueryParams());
        $this->assertEquals('bar9', $new->getAttribute('foo8'));

        $this->assertEquals(
            array(
                'file2' => $this->createUploadedFile(
                    '/tmp/php1235',
                    123456,
                    UPLOAD_ERR_OK,
                    'file2.png',
                    'image/png'
                ),
            ),
            $new->getUploadedFiles()
        );

        $new2 = $new->withoutAttribute('foo8');

        $this->assertEquals(null, $new2->getAttribute('foo8'));
        $this->assertSame($new2, $new2->withoutAttribute('noSuch'));
        $this->assertSame($new2, $new2->withoutAttribute(false));
    }

    /*
        Exceptions
    */

    public function testExceptionUploadedFilesArray()
    {
        // $value = (object) [];
        $value = 'not array';
        $exceptionClass = \is_array($value)
            ? 'InvalidArgumentException'
            : (PHP_VERSION_ID >= 70000
                ? 'TypeError'
                : 'ErrorException');
        $this->expectException($exceptionClass);
        $this->createServerRequest()
            ->withUploadedFiles($value);
    }

    public function testExceptionUploadedFiles()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid file in uploaded files structure. Expected UploadedFileInterface, but ');
        $this->createServerRequest()
            ->withUploadedFiles([
                [
                    ['files' => ''],
                ],
            ]);
    }

    public function testExceptionWithUploadedFile()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid value in files specification');
        $files = [
            'bogusFiles' => '/tmp/php1234.tmp',
        ];
        $serverRequest = $this->createServerRequest();
        $reflectionMethod = new ReflectionMethod($serverRequest, 'filesFromGlobals');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($serverRequest, array(
            $files,
        ));
    }

    public function testExceptionParsedBody()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Only accepts array, object and null, but string provided.');

        // Exception => Only accepts array, object and null, but string provided.
        $serverRequest = $this->createServerRequest()
            ->withParsedBody('I am a string');
    }

    public function testExceptionParseStrOpts()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('parseStrOpts expects string or array but boolean provided.');
        ServerRequest::parseStrOpts(false);
    }

    public function testParseUploadedFiles()
    {
        $files = [
            'files0' => $this->createUploadedFile(
                '/tmp/php1234.tmp',
                100000,
                UPLOAD_ERR_OK,
                'test1.jpg',
                'image/jpeg'
            ),

            // <input type="file" name="file1">
            'files1' => [
                'name' => 'test1.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php1234.tmp',
                'error' => UPLOAD_ERR_OK,
                'size' => 100000,
                'full_path' => '/sue/bob/test1.jpg',
            ],

            // <input type="file" name="files2[a]">
            // <input type="file" name="files2[b]">
            'files2' => [
                'name' => [
                    'a' => 'test2.jpg',
                    'b' => 'test3.jpg',
                ],
                'type' => [
                    'a' => 'image/jpeg',
                    'b' => 'image/jpeg',
                ],
                'tmp_name' => [
                    'a' => '/tmp/php1235.tmp',
                    'b' => '/tmp/php1236.tmp',
                ],
                'error' => [
                    'a' => UPLOAD_ERR_OK,
                    'b' => UPLOAD_ERR_OK,
                ],
                'size' => [
                    'a' => 100001,
                    'b' => 100010,
                ],
                'full_path' => [
                    'a' => '/sue/bob/test2.jpg',
                    'b' => '/sue/bob/test3.jpg',
                ],
            ],

            // <input type="file" name="files3[]">
            // <input type="file" name="files3[]">
            'files3' => [
                'name' => [
                    0 => 'test4.jpg',
                    1 => 'test5.jpg',
                ],
                'type' => [
                    0 => 'image/jpeg',
                    1 => 'image/jpeg',
                ],
                'tmp_name' => [
                    0 => '/tmp/php1237.tmp',
                    1 => '/tmp/php1238.tmp',
                ],
                'error' => [
                    0 => UPLOAD_ERR_OK,
                    1 => UPLOAD_ERR_OK,
                ],
                'size' => [
                    0 => 100100,
                    1 => 101000,
                ],
            ],

            // <input type="file" name="files4[foo][bar]">
            'files4' => [
                'name' => [
                    'foo' => [
                        'bar' => 'test6.png',
                    ],
                ],
                'type' => [
                    'foo' => [
                        'bar' => 'image/png',
                    ],
                ],
                'tmp_name' => [
                    'foo' => [
                        'bar' => '/tmp/php1239',
                    ],
                ],
                'error' => [
                    'foo' => [
                        'bar' => UPLOAD_ERR_OK,
                    ],
                ],
                'size' => [
                    'foo' => [
                        'bar' => 110000,
                    ],
                ],
            ],
        ];

        $expectedFiles = array(
            'files0' => $this->createUploadedFile(
                '/tmp/php1234.tmp',
                100000,
                UPLOAD_ERR_OK,
                'test1.jpg',
                'image/jpeg'
            ),
            'files1' => $this->createUploadedFile(
                '/tmp/php1234.tmp',
                100000,
                UPLOAD_ERR_OK,
                'test1.jpg',
                'image/jpeg',
                '/sue/bob/test1.jpg'
            ),
            'files2' => array(
                'a' => $this->createUploadedFile(
                    '/tmp/php1235.tmp',
                    100001,
                    UPLOAD_ERR_OK,
                    'test2.jpg',
                    'image/jpeg',
                    '/sue/bob/test2.jpg'
                ),
                'b' => $this->createUploadedFile(
                    '/tmp/php1236.tmp',
                    100010,
                    UPLOAD_ERR_OK,
                    'test3.jpg',
                    'image/jpeg',
                    '/sue/bob/test3.jpg'
                ),
            ),
            'files3' => array(
                0 => $this->createUploadedFile(
                    '/tmp/php1237.tmp',
                    100100,
                    UPLOAD_ERR_OK,
                    'test4.jpg',
                    'image/jpeg'
                ),
                1 => $this->createUploadedFile(
                    '/tmp/php1238.tmp',
                    101000,
                    UPLOAD_ERR_OK,
                    'test5.jpg',
                    'image/jpeg'
                ),
            ),
            'files4' => array(
                'foo' => array(
                    'bar' => $this->createUploadedFile(
                        '/tmp/php1239',
                        110000,
                        UPLOAD_ERR_OK,
                        'test6.png',
                        'image/png'
                    ),
                ),
            ),
        );

        $serverRequest = $this->createServerRequest();
        $reflectionMethod = new ReflectionMethod($serverRequest, 'filesFromGlobals');
        $reflectionMethod->setAccessible(true);

        $uploadedFiles = $reflectionMethod->invokeArgs($serverRequest, array(
            $files,
        ));

        $this->assertEquals($expectedFiles, $uploadedFiles);
    }

    /*
        Methods that help for testing.
    */

    /**
     * Moke a uploadedFiles array
     *
     * @param int $item which array to return
     *
     * @return array
     */
    private static function mockFiles($item = 1)
    {
        if ($item === 1) {
            return array(
                'file1' => self::createUploadedFile(
                    '/tmp/php1234.tmp',
                    100000,
                    UPLOAD_ERR_OK,
                    'file1.jpg',
                    'image/jpeg'
                ),
            );
        }
        if ($item === 2) {
            return array(
                'file2' => self::createUploadedFile(
                    '/tmp/php1235',
                    123456,
                    UPLOAD_ERR_OK,
                    'file2.png',
                    'image/png'
                ),
            );
        }
    }

    /**
     * @param $value
     *
     * @dataProvider validQueryParams
     */
    public function testWithQueryParamsAcceptsValidValues($value)
    {
        $params = null;
        \parse_str($value, $params);
        $request = $this->createServerRequest()
            ->withQueryParams($params);
        $this->assertSame($params, $request->getQueryParams());
    }

    /**
     * @param $value
     *
     * @dataProvider invalidQueryParams
     */
    public function testWithQueryParamsRejectsInvalidValues($value)
    {
        $exceptionClass = \is_array($value)
            ? 'InvalidArgumentException'
            : (PHP_VERSION_ID >= 70000
                ? 'TypeError'
                : 'ErrorException');
        $this->expectException($exceptionClass);
        $this->createServerRequest()
            ->withQueryParams($value);
    }

    /**
     * @param $value
     *
     * @dataProvider validCookieParams
     */
    public function testWithCookieParamsAcceptsValidValues($value)
    {
        $request = $this->createServerRequest()
            ->withCookieParams($value);
        $this->assertSame($value, $request->getCookieParams());
    }

    /**
     * @param $value
     *
     * @dataProvider invalidCookieParams
     */
    public function testWithCookieParamsRejectsInvalidValues($value)
    {
        $exceptionClass = \is_array($value)
            ? 'InvalidArgumentException'
            : (PHP_VERSION_ID >= 70000
                ? 'TypeError'
                : 'ErrorException');
        $this->expectException($exceptionClass);
        $this->createServerRequest()
            ->withCookieParams($value);
    }

    /**
     * @param $name
     * @param $value
     *
     * @dataProvider validAttributeNamesAndValues
     */
    public function testWithAttributeAcceptsValidNamesAndValues($name, $value)
    {
        $request = $this->createServerRequest()
            ->withAttribute($name, $value);
        $this->assertSame($value, $request->getAttribute($name));
    }

    /**
     * @param $name
     * @param $value
     *
     * @dataProvider invalidAttributeNamesAndValues
     */
    public function testWithAttributeAcceptsRejectsInvalidValues($name, $value)
    {
        $this->expectException('InvalidArgumentException');
        $this->createServerRequest()
            ->withAttribute($name, $value);
    }
}
