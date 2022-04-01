<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Stream;
use bdk\Test\PolyFill\AssertionTrait;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use stdClass;

/**
 * @covers \bdk\HttpMessage\Message
 */
class MessageTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $message = new Message();
        $this->assertTrue($message instanceof Message);
    }

    public function testGetMethods()
    {
        $message = $this->testSetHeaders();
        $this->assertSame('1.1', $message->getProtocolVersion());
        $this->assertEquals(['Mozilla/5.0 (Windows NT 10.0; Win64; x64)'], $message->getHeader('user-agent'));
        $this->assertEquals([], $message->getHeader('header-not-exists'));
        $this->assertEquals('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', $message->getHeaderLine('user-agent'));
        // Test - has
        $this->assertTrue($message->hasHeader('user-agent'));
    }

    public function testWithMethods()
    {
        $message = $this->testSetHeaders();
        $newMessage = $message->withProtocolVersion('2.0')
            ->withHeader('hello-world', 'old')
            ->withHeader('hello-world', 'ok')
            ->withHeader('host', 'test.com');
        $this->assertSame('2.0', $newMessage->getProtocolVersion());
        $this->assertEquals(['ok'], $newMessage->getHeader('hello-world'));
        $new2Message = $newMessage
            ->withAddedHeader('hello-world', 'not-ok')
            ->withAddedHeader('foo-bar', 'okok')
            ->withAddedHeader('others', 2)
            ->withAddedHeader('others', 6.4);
        $this->assertEquals(['ok', 'not-ok'], $new2Message->getHeader('hello-world'));
        $this->assertEquals(['okok'], $new2Message->getHeader('foo-bar'));
        $this->assertEquals(['2', '6.4'], $new2Message->getHeader('others'));
        $this->assertSame('host', \array_keys($new2Message->getHeaders())[0]);
        // Test - without
        $new3Message = $new2Message->withoutHeader('hello-world');
        $this->assertFalse($new3Message->hasHeader('hello-world'));

        $message = new Message();
        $messageNew = $message->withProtocolVersion(1.1);
        $this->assertSame($message, $messageNew);

        $messageNew = $message->withoutHeader('hello-world');
        $this->assertSame($message, $messageNew);
    }

    public function testBodyMethods()
    {
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);
        $message = new Message();

        $this->assertInstanceOf('bdk\\HttpMessage\\Stream', $message->getBody());

        $messageNew = $message->withBody($stream);
        $this->assertSame($stream, $messageNew->getBody());

        $messageNew2 = $messageNew->withBody($stream);
        $this->assertSame($messageNew, $messageNew2);
    }

    public function testSetHeaders()
    {
        $message = new Message();
        $testArray = [
            123 => ['value'],
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'Custom-Value' => '1234',
        ];
        $expectedArray = [
            'User-Agent' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)'],
            'Custom-Value' => ['1234'],
            123 => ['value'],
        ];
        $reflection = new ReflectionObject($message);
        $setHeaders = $reflection->getMethod('setHeaders');
        $setHeaders->setAccessible(true);
        $setHeaders->invokeArgs($message, [$testArray]);
        $this->assertEquals($expectedArray, $message->getHeaders());
        $this->assertTrue($message instanceof Message);
        return $message;
    }

    public function testWithAddedHeaderArrayValueAndKeys()
    {
        $message = new Message();
        $message = $message->withAddedHeader('content-type', [
            'foo' => 'text/html',
        ]);
        $message = $message->withAddedHeader('content-type', [
            'foo' => 'text/plain',
            'bar' => 'application/json',
        ]);

        $headerLine = $message->getHeaderLine('content-type');
        $this->assertStringContainsString('text/html', $headerLine);
        $this->assertStringContainsString('text/plain', $headerLine);
        $this->assertStringContainsString('application/json', $headerLine);

        $message = $message->withAddedHeader('foo', '');
        $headerLine = $message->getHeaderLine('foo');
        $this->assertSame('', $headerLine);
    }

    /*
        Exceptions
    */

    public function testExceptionHeaderName()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => "hello-wo)rld" is not valid header name, it must be an RFC 7230 compatible string.
        $message->withHeader('hello-wo)rld', 'ok');
    }

    public function testExceptionHeaderNameEmpty()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => empty string is not a valid header name
        $message->withHeader('', 'ok');
    }

    public function testExceptionHeaderName2()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => "hello-wo)rld" is not valid header name, it must be an RFC 7230 compatible string.
        $message->withHeader(['test'], 'ok');
    }

    public function testExceptionHeaderValueBoolean()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => The header field value only accepts string and array, but "boolean" provided.
        $message->withHeader('hello-world', false);
    }

    public function testExceptionHeaderValueNull()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => The header field value only accepts string and array, but "NULL" provided.
        $message->withHeader('hello-world', null);
    }

    public function testExceptionHeaderValueEmptyArray()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => The header field value only accepts string and non-array
        $message->withHeader('hello-world', []);
    }

    public function testExceptionHeaderValueObject()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        $mockObject = new stdClass();
        $mockObject->test = 1;
        // Exception => The header field value only accepts string and array, but "object" provided.
        $message->withHeader('hello-world', $mockObject);
    }

    public function testExceptionHeaderValueArray()
    {
        $this->expectException('InvalidArgumentException');
        // An invalid type is inside the array.
        $testArr = array(
            'test',
            true
        );
        $message = new Message();
        // Exception => The header values only accept string and number, but "boolean" provided.
        $message->withHeader('hello-world', $testArr);
    }

    public function testExceptionHeaderValueInvalidString()
    {
        $this->expectException('InvalidArgumentException');
        $message = new Message();
        // Exception => "This string contains many invisible spaces." is not valid header
        //    value, it must contains visible ASCII characters only.
        $message->withHeader('hello-world', 'This string contains many invisible spaces.');
    }

    public function testExceptionProtocolVersion()
    {
        $this->expectException('InvalidArgumentException');
        $request = new Message();
        // Exception => Unsupported HTTP protocol version number.
        $request->withProtocolVersion('1.5');
    }
}
