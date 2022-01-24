<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Stream;
use bdk\Test\PolyFill\AssertionTrait;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 *
 */
class StreamTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);

        $this->assertTrue($stream instanceof Stream);

        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isSeekable());

        $this->assertIsInt($stream->getSize());
        $this->assertIsBool($stream->eof());
        $this->assertIsInt($stream->tell());

        // close.
        $this->assertEquals($resource, $stream->detach());
        $this->assertNull($stream->getSize());
        $this->assertNull($stream->detach());

        // Test 2
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'c');
        $stream = new Stream($resource);
        $this->assertTrue($stream->isWritable());
        $this->assertFalse($stream->isReadable());

        // Test 3
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r');
        $stream = new Stream($resource);
        $this->assertFalse($stream->isWritable());
        $this->assertTrue($stream->isReadable());

        // Test 4
        $stream = new Stream('This is a test');
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isReadable());
    }

    public function testToString()
    {
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->write('Foo Bar');
        $this->assertSame('Foo Bar', (string) $stream);
    }

    public function testGetSize()
    {
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);
        $this->assertSame(\filesize(TEST_DIR . '/assets/logo.png'), $stream->getSize());
        $stream->close();
    }

    public function testGetMetadata()
    {
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);
        $expectedMeta = [
            'blocked' => true,
            'eof' => false,
            'mode' => 'r+',
            'seekable' => true,
            // 'stream_type' => 'STDIO',
            'timed_out' => false,
            'unread_bytes' => 0,
            'uri' => TEST_DIR . '/assets/logo.png',
            // 'wrapper_type' => 'plainfile',
        ];

        $meta = $stream->getMetadata();
        // stream_type and wrapper_type may differ due to bdk\Debug\Utility\FileStreamWrapper
        $meta = \array_intersect_key($meta, $expectedMeta);
        \ksort($meta);
        $this->assertEquals($expectedMeta['mode'], $meta['mode']);
        $this->assertEquals('r+', $stream->getMetadata('mode'));
        $this->assertEquals($expectedMeta, $meta);
        $stream->close();
        $this->assertEquals(array(), $stream->getMetadata());
    }

    public function testSeekAndRewind()
    {
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);
        $stream->seek(10);
        $this->assertSame(10, $stream->tell());
        $stream->rewind();
        $this->assertSame(0, $stream->tell());
        $stream->close();
    }

    public function testReadAndWrite()
    {
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->write('Foo Bar');
        $stream->rewind();
        $this->assertSame('Fo', $stream->read(2));
        $stream->close();
    }

    /*
        Exceptions
    */

    public function testExceptionStream()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => Stream should be a resource, but string provided.
        new Stream(new \stdClass());
    }

    public function testExceptionTellStreamDoesNotExist()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->close();
        // Exception => Stream does not exist.
        $stream->tell();
    }

    public function testExceptionSeekStreamDoesNotExist()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->close();
        // Exception => Stream does not exist.
        $stream->seek(10);
    }

    public function testExceptionSeekNotSeekable()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r'));
        $reflection = new ReflectionObject($stream);
        $seekable = $reflection->getProperty('seekable');
        $seekable->setAccessible(true);
        $seekable->setValue($stream, false);
        // Exception => Stream is not seekable.
        $stream->seek(10);
    }

    public function testExceptionSeekStreamDoesNotSeekable()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r'));
        // Exception => Set position equal to offset bytes.. Unable to seek to stream at position 10
        $stream->seek(10);
    }

    public function testExceptionWriteStreamDoesNotExist()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->close();
        // Exception => Stream does not exist.
        $stream->write('Foo Bar');
    }

    public function testExceptionReadStreamDoesNotExist()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->write('Foo Bar');
        $stream->rewind();
        $stream->close();
        // Exception => Stream does not exist.
        $stream->read(2);
    }

    public function testExceptionGetContentsStreamDoesNotExist()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->write('Foo Bar');
        $stream->rewind();
        $stream->close();
        // Exception => Stream does not exist.
        $stream->getContents();
    }

    public function testExceptionGetContentsStreamIsNotReadable()
    {
        $this->expectException('RuntimeException');
        $stream = new Stream(\fopen('php://temp', 'r+'));
        $stream->write('Foo Bar');
        $stream->rewind();
        $reflection = new ReflectionObject($stream);
        $readable = $reflection->getProperty('readable');
        $readable->setAccessible(true);
        $readable->setValue($stream, false);
        // Exception => Unable to read stream contents.
        $stream->getContents();
    }
}
