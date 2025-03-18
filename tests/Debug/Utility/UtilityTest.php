<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\DebugTestFramework;

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
        self::assertSame(array(), $GLOBALS['collectedHeaders']);

        Utility::emitHeaders(array(
            'Location' => 'http://www.test.com/',
            'Content-Security-Policy' => array(
                'foo',
                'bar',
            ),
            array('Content-Length', 1234),
        ));
        self::assertSame(array(
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
        self::assertSame('1:01:01.066000', Utility::formatDuration(3661.066, "%h:%I:%S.%F"));
        self::assertSame('1h 01m 01s', Utility::formatDuration(3661));
        self::assertSame('30m 30s', Utility::formatDuration(1830));
        self::assertSame('33 sec', Utility::formatDuration(33));
        self::assertSame('123 ms', Utility::formatDuration(0.123));
        self::assertSame('123 μs', Utility::formatDuration(0.000123));

        self::assertSame('123 μs', Utility::formatDuration(0.000123, 'us'));
        self::assertSame('1234 ms', Utility::formatDuration(1.234, 'ms'));
        self::assertSame('66 sec', Utility::formatDuration(66, 's'));
        self::assertSame('66 sec', Utility::formatDuration(66, 'sec'));
    }

    public function testGetBytes()
    {
        self::assertSame('1 PB', Utility::getBytes('1pb'));
        self::assertSame('1 TB', Utility::getBytes('1tb'));
        self::assertSame('1 GB', Utility::getBytes('1gb'));
        self::assertSame('1 MB', Utility::getBytes('1mb'));
        self::assertSame('1 kB', Utility::getBytes('1 kb'));
        self::assertSame('1 kB', Utility::getBytes('1024'));
        self::assertSame('1 kB', Utility::getBytes(1024));
        self::assertSame('123 B', Utility::getBytes('123 b'));
        self::assertSame('0 B', Utility::getBytes(0));
        self::assertSame('0 B', Utility::getBytes(0.5));

        self::assertSame(\pow(2, 50), Utility::getBytes('1pb', true));
        self::assertSame(\pow(2, 40), Utility::getBytes('1tb', true));
        self::assertSame(\pow(2, 30), Utility::getBytes('1gb', true));
        self::assertSame(\pow(2, 20), Utility::getBytes('1mb', true));
        self::assertSame(\pow(2, 10), Utility::getBytes('1kb', true));

        self::assertSame(false, Utility::getBytes('bob'));
    }

    public function testGitBranch()
    {
        $branch = Utility::gitBranch();
        self::assertTrue(\is_string($branch) || $branch === null);
    }

    /**
     * @dataProvider providerHttpMethodHasBody
     */
    public function testHttpMethodHasBody($method, $hasBodyExpect)
    {
        $hasBody = Utility::httpMethodHasBody($method);
        self::assertSame($hasBodyExpect, $hasBody);
    }

    public function testIsFile()
    {
        self::assertFalse(Utility::isFile(123));
        self::assertTrue(Utility::isFile(__FILE__));
        // is_file() expects parameter 1 to be a valid path, string given
        self::assertFalse(Utility::isFile("\0foo.txt"));
        self::assertFalse(Utility::isFile(__DIR__ . '/' . "\0foo.txt"));
    }

    public static function providerHttpMethodHasBody()
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
