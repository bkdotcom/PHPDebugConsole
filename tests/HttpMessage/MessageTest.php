<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Stream;
use bdk\Test\PolyFill\AssertionTrait;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * @covers \bdk\HttpMessage\AssertionTrait
 * @covers \bdk\HttpMessage\Message
 */
class MessageTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;
    use DataProviderTrait;
    use FactoryTrait;

    public function testConstruct()
    {
        $message = $this->createMessage();
        $this->assertTrue($message instanceof Message);
    }

    /*
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
    */

    /*
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
        $this->assertSame('Host', \array_keys($new2Message->getHeaders())[0]);
        // Test - without
        $new3Message = $new2Message->withoutHeader('hello-world');
        $this->assertFalse($new3Message->hasHeader('hello-world'));

        $message = new Message();
        $messageNew = $message->withProtocolVersion(1.1);
        $this->assertSame($message, $messageNew);

        $messageNew = $message->withoutHeader('hello-world');
        $this->assertSame($message, $messageNew);
    }
    */

    public function testBodyMethods()
    {
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = $this->createStream($resource);
        $message = $this->createMessage();

        $this->assertInstanceOf('bdk\\HttpMessage\\Stream', $message->getBody());

        $messageNew = $message->withBody($stream);
        $this->assertSame($stream, $messageNew->getBody());

        $messageNew2 = $messageNew->withBody($stream);
        $this->assertSame($messageNew, $messageNew2);
    }

    public function testSetHeaders()
    {
        $message = $this->createMessage();
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
        $message = $this->createMessage()
            ->withAddedHeader('content-type', [
                'foo' => 'text/html',
            ])
            ->withAddedHeader('content-type', [
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

    public function testExceptionHeaderValueInvalidString()
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withHeader('hello-world', 'This string contains many invisible spaces.');
    }

    public function testWithHeaderRejectsMultipleHostValues()
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withHeader('Host', ['a.com', 'b.com']);
    }

    public function testWithAddedHeaderRejectsAdditionalHost()
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withHeader('Host', ['a.com'])
            ->withAddedHeader('host', 'b.com');
    }

    public function testWithAddedHeaderRejectsMultipleHostValues()
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withAddedHeader('Host', ['a.com', 'b.com']);
    }

    /**
     * @param $version
     *
     * @dataProvider validProtocolVersions
     */
    public function testAcceptsValidProtocolVersion($version)
    {
        $message = $this->createMessage()
            ->withProtocolVersion($version);
        $this->assertEquals($version, $message->getProtocolVersion());
    }

    /**
     * @param mixed $version
     *
     * @dataProvider invalidProtocolVersions
     */
    public function testWithInvalidProtocolVersionThrowsException($version)
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withProtocolVersion($version)->getProtocolVersion();
    }

    /**
     * @param $name
     *
     * @dataProvider validHeaderNames
     */
    public function testWithoutHeader($name)
    {
        $value = \base64_encode($this->randomBytes(12));
        $message = $this->createMessage()
            ->withHeader($name, $value)
            ->withoutHeader($name);

        $this->assertFalse($message->hasHeader(\strtolower($name)));
        $this->assertEquals([], $message->getHeader($name));

        // test removing non-existant
        $message = $message->withoutHeader($name);
        $this->assertFalse($message->hasHeader(\strtolower($name)));
        $this->assertEquals([], $message->getHeader($name));
    }

    /**
     * @param $name
     *
     * @dataProvider validHeaderNames
     */
    public function testWithHeaderAcceptsValidHeaderNames($name)
    {
        $value = \base64_encode($this->randomBytes(12));
        $message = $this->createMessage()
            ->withHeader($name, $value);
        $this->assertTrue($message->hasHeader(\strtolower($name)));
        $this->assertEquals($value, $message->getHeaderLine($name));
    }

    /**
     * @param $name
     *
     * @dataProvider validHeaderNames
     */
    public function testWithAddedHeaderAcceptsValidHeaderNames($name)
    {
        $value = \base64_encode($this->randomBytes(12));
        $message = $this->createMessage()
            ->withAddedHeader($name, $value);
        $this->assertTrue($message->hasHeader(\strtolower($name)));
        $this->assertEquals($value, $message->getHeaderLine($name));
    }

    /**
     * @param $name
     *
     * @dataProvider invalidHeaderNames
     */
    public function testInvalidHeaderNameThrowsException($name)
    {
        $this->expectException('InvalidArgumentException');
        $value = \base64_encode($this->randomBytes(12));
        $this->createMessage()
            ->withHeader($name, $value);
    }

    /**
     * @param $name
     *
     * @dataProvider invalidHeaderNames
     */
    public function testInvalidAddedHeaderNameThrowException($name)
    {
        $this->expectException('InvalidArgumentException');
        $value = \base64_encode($this->randomBytes(12));
        $this->createMessage()
            ->withAddedHeader($name, $value);
    }

    /**
     * @param $value
     *
     * @dataProvider validHeaderValues
     */
    public function testWithHeaderAcceptValidValues($value)
    {
        $message = $this->createMessage()
            ->withHeader('header', 'oldValue')
            ->withHeader('header', $value);
        $this->assertEquals($value, $message->getHeaderLine('header'));
    }

    /**
     * @param $value
     *
     * @dataProvider validHeaderValues
     */
    public function testWithAddedHeaderAcceptsValidValues($value)
    {
        $message = $this->createMessage()
            ->withAddedHeader('header', $value);
        $this->assertEquals($value, $message->getHeaderLine('header'));
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidHeaderValues
     */
    public function testWithHeaderRejectsInvalidValues($value)
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withHeader('header', $value);
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidHeaderValues
     */
    public function testWithHeaderRejectsInvalidArrayValues($value)
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withHeader('header', [$value]);
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidHeaderValues
     */
    public function testWithAddedHeaderRejectsInvalidValues($value)
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withAddedHeader('header', $value);
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidHeaderValues
     */
    public function testWithAddedHeaderRejectsInvalidArrayValues($value)
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withAddedHeader('header', [$value]);
    }

    /**
     * @param string $name
     *
     * @dataProvider hostHeaderVariations
     */
    public function testHostHeaderNameGetsNormalized($name)
    {
        $value = \md5($this->randomBytes(12)) . '.com';
        $headers = $this->createMessage()
            ->withHeader($name, $value)
            ->getHeaders();

        $this->assertArrayHasKey('Host', $headers);
        $this->assertSame([$value], $headers['Host']);

        if ($name !== 'Host') {
            $this->assertArrayNotHasKey($name, $headers);
        }
    }

    /**
     * @param $name
     * @param $value
     *
     * @dataProvider headersWithInjectionVectors
     */
    /*
    public function testWithHeaderRejectsHeadersWithCrlfVectors($name, $value)
    {
        $this->expectException('InvalidArgumentException');
        $this->createMessage()
            ->withHeader($name, $value);
    }
    */

    /**
     * @param $name
     * @param $value
     *
     * @dataProvider headersWithInjectionVectors
     */
    /*
    public function testWithAddedHeaderRejectsHeadersWithCrlfVectors($name, $value)
    {
        $this->expectException('InvalidArgumentException');
        $message = $this->createMessage()
            ->withAddedHeader($name, $value);
    }
    */
}
