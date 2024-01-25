<?php

namespace bdk\Test\HttpMessage\Utility;

use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Utility\ParseStr;
use bdk\HttpMessage\Utility\ServerRequest as ServerRequestUtil;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\HttpMessage\FactoryTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers bdk\HttpMessage\ServerRequest
 * @covers bdk\HttpMessage\Utility\ParseStr
 * @covers bdk\HttpMessage\Utility\ServerRequest
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class ServerRequestTest extends TestCase
{
	use ExpectExceptionTrait;
	use FactoryTrait;

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
        ServerRequestUtil::$inputStream = __DIR__ . '/input.json';
        $request = ServerRequest::fromGlobals();
        self::assertSame(array('foo' => 'bar'), $request->getParsedBody());
        self::assertSame('http://www.test.com:8080/path?ding=dong', (string) $request->getUri());
        self::assertEquals(array(
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

        // test parse_url failure
        $_SERVER = array(
            'HTTP_HOST' => '/s?a=12&b=12.3.3.4:1233',
            'SERVER_PORT' => '8080',
            'QUERY_STRING' => 'ding=dong',
        );
        $request = ServerRequest::fromGlobals();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('http:/?ding=dong', (string) $request->getUri());

        $_SERVER = $serverBackup;
        $_GET = $getBackup;
    }

    public function testPostFromInput()
    {
        $serverRequestUtil = new ServerRequestUtil();
        $reflectionMethod = new ReflectionMethod($serverRequestUtil, 'postFromInput');
        $reflectionMethod->setAccessible(true);

        $parsed = $reflectionMethod->invokeArgs($serverRequestUtil, array(
            'application/unknown',
        ));
        self::assertNull($parsed);

        $parsed = $reflectionMethod->invokeArgs($serverRequestUtil, array(
            'application/json',
        ));
        self::assertNull($parsed);

        $parsed = $reflectionMethod->invokeArgs($serverRequestUtil, array(
            'application/x-www-form-urlencoded',
            __DIR__ . '/input.txt'
        ));
        self::assertSame(array(
            0 => 'foo',
            1 => 'bar',
            2 => 'baz',
            4 => 'boom',
            'dingle.berry' => 'brown',
            'a b' => 'c',
            'd e' => 'f',
            'g h' => 'i',
        ), $parsed);

        ParseStr::setOpts(array(
            'convDot' => true,
            'convSpace' => false,
        ));
        $parsed = $reflectionMethod->invokeArgs($serverRequestUtil, array(
            'application/x-www-form-urlencoded',
            __DIR__ . '/input.txt'
        ));
        self::assertSame(array(
            0 => 'foo',
            1 => 'bar',
            2 => 'baz',
            4 => 'boom',
            'dingle_berry' => 'brown',
            'a b' => 'c',
            'd e' => 'f',
            'g h' => 'i',
        ), $parsed);
        ParseStr::setOpts(array(
            'convDot' => false,
            'convSpace' => false,
        ));
    }

    public function testExceptionWithUploadedFile()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid value in files specification at bogusFiles.  Array expected.  string provided.');
        $files = [
            'bogusFiles' => '/tmp/php1234.tmp',
        ];
        $serverRequestUtil = new ServerRequestUtil();
        $reflectionMethod = new ReflectionMethod($serverRequestUtil, 'filesFromGlobals');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($serverRequestUtil, array(
            $files,
        ));
    }

    public function testExceptionWithUploadedFile2()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid value in files specification at bogusFile.error.  Array expected.  integer provided.');
        $files = [
            'bogusFile' => array(
                'error' => UPLOAD_ERR_OK,
                'name' => 'image.png',
                'tmp_name' => '/tmp/php1234.tmp',
                'type' => 'image/png',
                // missing size
            ),
        ];
        $serverRequestUtil = new ServerRequestUtil();
        $reflectionMethod = new ReflectionMethod($serverRequestUtil, 'filesFromGlobals');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($serverRequestUtil, array(
            $files,
        ));
    }

    public function testParseUploadedFiles()
    {
        $files = [
            'files0' => array(
                'error' => UPLOAD_ERR_OK,
                'name' => 'test1.jpg',
                'size' => 100000,
                'tmp_name' => '/tmp/php1234.tmp',
                'type' => 'image/jpeg',
            ),

            // <input type="file" name="file1">
            'files1' => array(
                'name' => 'test1.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php1234.tmp',
                'error' => UPLOAD_ERR_OK,
                'size' => 100000,
                'full_path' => '/sue/bob/test1.jpg',
            ),

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

        $serverRequestUtil = new ServerRequestUtil();
        $reflectionMethod = new ReflectionMethod($serverRequestUtil, 'filesFromGlobals');
        $reflectionMethod->setAccessible(true);

        $uploadedFiles = $reflectionMethod->invokeArgs($serverRequestUtil, array(
            $files,
        ));

        self::assertEquals($expectedFiles, $uploadedFiles);
    }


}
