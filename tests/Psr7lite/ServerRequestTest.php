<?php

namespace bdk\DebugTests\Psr7lite;

use bdk\Debug\Psr7lite\Message;
use bdk\Debug\Psr7lite\Request;
use bdk\Debug\Psr7lite\ServerRequest;
use bdk\Debug\Psr7lite\UploadedFile;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

class ServerRequestTest extends TestCase
{
    use \bdk\DebugTests\PhpUnitPolyfillTrait;

    public function testConstruct()
    {
        $serverRequest = new ServerRequest();
        $this->assertTrue($serverRequest instanceof Message);
        $this->assertTrue($serverRequest instanceof Request);
        $this->assertTrue($serverRequest instanceof ServerRequest);
    }

    public function testProperties()
    {
        $serverRequest = new ServerRequest();

        $properties = array(
            'attributes' => array(),
            'cookie' => array(),
            'get' => array(),
            'post' => null,
            'server' => array(),
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

        $serverRequest = new ServerRequest();

        $this->assertSame([], $serverRequest->getServerParams());
        $this->assertSame([], $serverRequest->getCookieParams());
        $this->assertSame(null, $serverRequest->getParsedBody());
        $this->assertSame([], $serverRequest->getQueryParams());
        $this->assertSame([], $serverRequest->getUploadedFiles());
        $this->assertSame([], $serverRequest->getAttributes());

        // Test 2
        $serverRequest = (new ServerRequest('POST', '', array('what' => 'server')))
            ->withCookieParams(array('what' => 'cookie'))
            ->withParsedBody(array('what' => 'post'))
            ->withQueryParams(array('what' => 'get'))
            ->withAttribute('what', 'attribute')
            ->withUploadedFiles(self::mockFiles(1));

        $this->assertEquals(array(
            'what' => 'server',
        ), $serverRequest->getServerParams());
        $this->assertEquals(array('what' => 'cookie'), $serverRequest->getCookieParams());
        $this->assertEquals(array('what' => 'post'), $serverRequest->getParsedBody());
        $this->assertEquals(array('what' => 'get'), $serverRequest->getQueryParams());
        $this->assertEquals(array('what' => 'attribute'), $serverRequest->getAttributes());
        $this->assertEquals(
            array(
                'file1' => new UploadedFile(
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
        $serverRequest = new ServerRequest();

        $new = $serverRequest
            ->withCookieParams(['foo3' => 'bar3'])
            ->withParsedBody(['foo4' => 'bar4', 'foo5' => 'bar5'])
            ->withQueryParams(['foo6' => 'bar6', 'foo7' => 'bar7'])
            ->withAttribute('foo8', 'bar9')
            ->withUploadedFiles(self::mockFiles(2));

        $this->assertEquals([], $new->getServerParams());
        $this->assertEquals(['foo3' => 'bar3'], $new->getCookieParams());
        $this->assertEquals(['foo4' => 'bar4', 'foo5' => 'bar5'], $new->getParsedBody());
        $this->assertEquals(['foo6' => 'bar6', 'foo7' => 'bar7'], $new->getQueryParams());
        $this->assertEquals('bar9', $new->getAttribute('foo8'));

        $this->assertEquals(
            array(
                'file2' => new UploadedFile(
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
    }

    /*
        Exceptions
    */

    public function testExceptionUploadedFiles()
    {
        $this->expectException('InvalidArgumentException');

        $serverRequest = new ServerRequest('GET', 'https://example.com');

        /*
        $reflection = new ReflectionObject($serverRequest);
        $assertUploadedFiles = $reflection->getMethod('assertUploadedFiles');
        $assertUploadedFiles->setAccessible(true);
        */

        // Exception => Invalid PSR-7 array structure for handling UploadedFile.
        $serverRequest->withUploadedFiles([
            [
                ['files' => ''],
            ],
        ]);
    }

    public function testExceptionParsedBody()
    {
        $this->expectException('InvalidArgumentException');
        $serverRequest = new ServerRequest('GET', 'https://example.com');

        // Exception => Only accepts array, object and null, but string provided.
        $serverRequest->withParsedBody('I am a string');
    }

    public function testParseUploadedFiles()
    {
        $files = [

            // <input type="file" name="file1">

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
            'files1' => new UploadedFile(
                '/tmp/php1234.tmp',
                100000,
                UPLOAD_ERR_OK,
                'test1.jpg',
                'image/jpeg'
            ),
            'files2' => array(
                'a' => new UploadedFile(
                    '/tmp/php1235.tmp',
                    100001,
                    UPLOAD_ERR_OK,
                    'test2.jpg',
                    'image/jpeg'
                ),
                'b' => new UploadedFile(
                    '/tmp/php1236.tmp',
                    100010,
                    UPLOAD_ERR_OK,
                    'test3.jpg',
                    'image/jpeg'
                ),
            ),
            'files3' => array(
                0 => new UploadedFile(
                    '/tmp/php1237.tmp',
                    100100,
                    UPLOAD_ERR_OK,
                    'test4.jpg',
                    'image/jpeg'
                ),
                1 => new UploadedFile(
                    '/tmp/php1238.tmp',
                    101000,
                    UPLOAD_ERR_OK,
                    'test5.jpg',
                    'image/jpeg'
                ),
            ),
            'files4' => array(
                'foo' => array(
                    'bar' => new UploadedFile(
                        '/tmp/php1239',
                        110000,
                        UPLOAD_ERR_OK,
                        'test6.png',
                        'image/png'
                    ),
                ),
            ),
        );

        $serverRequest = new ServerRequest();
        $reflection = new ReflectionObject($serverRequest);

        $filesFromGlobals = $reflection->getMethod('filesFromGlobals');
        $filesFromGlobals->setAccessible(true);

        $uploadedFiles = $filesFromGlobals->invokeArgs($serverRequest, array(
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
                'file1' => new UploadedFile(
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
                'file2' => new UploadedFile(
                    '/tmp/php1235',
                    123456,
                    UPLOAD_ERR_OK,
                    'file2.png',
                    'image/png'
                ),
            );
        }
    }
}
