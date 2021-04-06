<?php

namespace bdk\DebugTests\Utility;

use bdk\Debug\Utility;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class UtilityTest extends DebugTestFramework
{

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

    /**
     * Test
     *
     * @return void
     */
    public function testIsBase64Encoded()
    {
        $base64Str = \base64_encode(\chunk_split(\str_repeat('zippity do dah', 50)));
        $this->assertTrue(Utility::isBase64Encoded($base64Str));

        $this->assertFalse(Utility::isBase64Encoded('I\'m just a bill.'));
        $this->assertFalse(Utility::isBase64Encoded('onRenderComplete'));
        $this->assertFalse(Utility::isBase64Encoded('/Users/jblow/not/base64/'));
    }

    public function testIsFile()
    {
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
