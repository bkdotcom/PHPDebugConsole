<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility;
use bdk\HttpMessage\Stream;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\PolyFill\ExpectExceptionTrait;

/**
 * PHPUnit tests for Utility class
 *
 * @covers \bdk\Debug\Utility
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class UtilityTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    public function testEmitHeaders()
    {
        Utility::emitHeaders(array());
        $this->assertSame(array(), $GLOBALS['collectedHeaders']);

        Utility::emitHeaders(array(
            'Location' => 'http://www.test.com/',
            'Content-Security-Policy' => array(
                'foo',
                'bar',
            ),
            array('Content-Length', 1234),
        ));
        $this->assertSame(array(
            array('Location: http://www.test.com/', true),
            array('Content-Security-Policy: foo', true),
            array('Content-Security-Policy: bar', false),
            array('Content-Length: 1234', true),
        ), $GLOBALS['collectedHeaders']);

        $GLOBALS['headersSent'] = array(__FILE__, 42);
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Headers already sent: ' . __FILE__ . ', line 42');
        Utility::emitHeaders(array('foo' => 'bar'));
    }

    public function testFormatDuration()
    {
        $this->assertSame('1:01:01.066000', Utility::formatDuration(3661.066, "%h:%I:%S.%F"));
        $this->assertSame('1h 01m 01s', Utility::formatDuration(3661));
        $this->assertSame('30m 30s', Utility::formatDuration(1830));
        $this->assertSame('33 sec', Utility::formatDuration(33));
        $this->assertSame('123 ms', Utility::formatDuration(0.123));
        $this->assertSame('123 μs', Utility::formatDuration(0.000123));

        $this->assertSame('123 μs', Utility::formatDuration(0.000123, 'us'));
        $this->assertSame('1234 ms', Utility::formatDuration(1.234, 'ms'));
        $this->assertSame('66 sec', Utility::formatDuration(66, 's'));
        $this->assertSame('66 sec', Utility::formatDuration(66, 'sec'));
    }

    public function testGetBytes()
    {
        $this->assertSame('1 PB', Utility::getBytes('1pb'));
        $this->assertSame('1 TB', Utility::getBytes('1tb'));
        $this->assertSame('1 GB', Utility::getBytes('1gb'));
        $this->assertSame('1 MB', Utility::getBytes('1mb'));
        $this->assertSame('1 kB', Utility::getBytes('1 kb'));
        $this->assertSame('1 kB', Utility::getBytes('1024'));
        $this->assertSame('1 kB', Utility::getBytes(1024));
        $this->assertSame('123 B', Utility::getBytes('123 b'));
        $this->assertSame('0 B', Utility::getBytes(0));
        $this->assertSame('0 B', Utility::getBytes(0.5));

        $this->assertSame(\pow(2, 50), Utility::getBytes('1pb', true));
        $this->assertSame(\pow(2, 40), Utility::getBytes('1tb', true));
        $this->assertSame(\pow(2, 30), Utility::getBytes('1gb', true));
        $this->assertSame(\pow(2, 20), Utility::getBytes('1mb', true));
        $this->assertSame(\pow(2, 10), Utility::getBytes('1kb', true));

        $this->assertSame(false, Utility::getBytes('bob'));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetEmittedHeader()
    {
        $GLOBALS['collectedHeaders'] = array();
        $GLOBALS['headersSent'] = array();
        Utility::emitHeaders(array(
            'Content-Type' => 'application/json',
            'Location' => 'http://www.test.com/',
            'Content-Security-Policy' => array(
                'foo',
                'bar',
            ),
            array('Content-Length', 1234),
        ));
        $this->assertSame('application/json', Utility::getEmittedHeader());
        $this->assertSame('foo, bar', Utility::getEmittedHeader('Content-Security-Policy'));
        $this->assertSame(array('foo', 'bar'), Utility::getEmittedHeader('Content-Security-Policy', null));
        $this->assertSame('', Utility::getEmittedHeader('Not-Sent'));
        $this->assertSame(array(), Utility::getEmittedHeader('Not-Sent', null));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetEmittedHeaders()
    {
        $GLOBALS['collectedHeaders'] = array();
        $GLOBALS['headersSent'] = array();
        Utility::emitHeaders(array(
            'Location' => 'http://www.test.com/',
            'Content-Security-Policy' => array(
                'foo',
                'bar',
            ),
            array('Content-Length', 1234),
        ));
        $this->assertSame(array(
            'Location' => array(
                'http://www.test.com/',
            ),
            'Content-Security-Policy' => array(
                'foo',
                'bar',
            ),
            'Content-Length' => array(
                '1234',
            ),
        ), Utility::getEmittedHeaders());
    }

    public function testGetStreamContents()
    {
        $stream = new Stream('this is a test');
        $stream->seek(8);
        $this->assertSame('this is a test', Utility::getStreamContents($stream));
        $this->assertSame('a test', $stream->getContents());
    }

    public function testGitBranch()
    {
        $branch = Utility::gitBranch();
        $this->assertTrue(\is_string($branch) || $branch === null);
    }

    /**
     * @dataProvider httpMethodHasBodyProvider
     */
    public function testHttpMethodHasBody($method, $hasBodyExpect)
    {
        $hasBody = Utility::httpMethodHasBody($method);
        $this->assertSame($hasBodyExpect, $hasBody);
    }

    public function testIsFile()
    {
        $this->assertFalse(Utility::isFile(123));
        $this->assertTrue(Utility::isFile(__FILE__));
        // is_file() expects parameter 1 to be a valid path, string given
        $this->assertFalse(Utility::isFile("\0foo.txt"));
        $this->assertFalse(Utility::isFile(__DIR__ . '/' . "\0foo.txt"));
    }

    public function httpMethodHasBodyProvider()
    {
        return array(
            'CUSTOM' => array('CUSTOM', true),
            'POST' => array('POST', true),
            'PUT' => array('PUT', true),
            'CONNECT' => array('CONNECT', false),
            'DELETE' => array('DELETE', false),
            'GET' => array('GET', false),
            'HEAD' => array('HEAD', false),
            'OPTIONS' => array('OPTIONS', false),
            'TRACE' => array('TRACE', false),
        );
    }
}
