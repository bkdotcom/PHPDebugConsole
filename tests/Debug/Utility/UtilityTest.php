<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility;
use bdk\HttpMessage\Stream;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Utility class
 */
class UtilityTest extends DebugTestFramework
{
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
        $this->assertSame('1 kB', Utility::getBytes('1kb'));
        $this->assertSame('1 kB', Utility::getBytes('1024'));
        $this->assertSame('1 kB', Utility::getBytes(1024));
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test from cli
     */
    public function testGetEmittedHeader()
    {
        $this->assertSame(array(), Utility::getEmittedHeader());
    }

    public function testGetIncludedFiles()
    {
        $filesA = \get_included_files();
        $filesB = Utility::getIncludedFiles();
        \sort($filesA);
        \sort($filesB);
        $this->assertArraySubset($filesA, $filesB);
    }

    public function testGetStreamContents()
    {
        $stream = new Stream('this is a test');
        $stream->seek(8);
        $this->assertSame('this is a test', Utility::getStreamContents($stream));
        $this->assertSame('a test', $stream->getContents());
    }

    public function testIsFile()
    {
        $this->assertFalse(Utility::isFile(123));
        $this->assertTrue(Utility::isFile(__FILE__));
        // is_file() expects parameter 1 to be a valid path, string given
        $this->assertFalse(Utility::isFile("\0foo.txt"));
        $this->assertFalse(Utility::isFile(__DIR__ . '/' . "\0foo.txt"));
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test
     */
    public function testMemoryLimit()
    {
        $this->assertNotNull(Utility::memoryLimit());
    }

    /**
     * Test
     *
     * @return array of serialized logs
     */
    /*
    public function serializeLogProvider()
    {
        $log = array(
            array('log', 'What rolls down stairs'),
            array('info', 'alone or in pairs'),
            array('warn', 'rolls over your neighbor\'s dog?'),
        );
        $serialized = Utility::serializeLog($log);
        return array(
            array($serialized, $log)
        );
    }
    */

    /**
     * Test
     *
     * @param string $serialized   string provided by serializeLogProvider dataProvider
     * @param array  $unserialized the unserialized array
     *
     * @return void
     *
     * @dataProvider serializeLogProvider
     */
    /*
    public function testUnserializeLog($serialized, $unserialized)
    {
        $log = Utility::unserializeLog($serialized, $this->debug);
        $this->assertSame($unserialized, $log);
    }
    */
}
