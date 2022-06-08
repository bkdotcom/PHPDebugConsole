<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Request;
use bdk\HttpMessage\Uri;
use bdk\Test\PolyFill\AssertionTrait;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\HttpMessage\Request
 */
class RequestTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;
    use DataProviderTrait;
    use FactoryTrait;

    public function testConstruct()
    {
        $request = $this->factory()->createRequest('GET', '', '', [], '1.1');

        $this->assertTrue($request instanceof Request);
        $this->assertTrue($request instanceof Message);

        $uri = $this->factory()->createUri('https://www.example.com');
        $request = $this->factory()->createRequest('GET', $uri, '', [], '1.1');

        $this->assertSame('www.example.com', $request->getUri()->getHost());
    }

    public function testGetMethods()
    {
        // Test 1

        $request = $this->factory()->createRequest('POST', 'http://www.bradkent.com/php/debug/?test=test');

        $this->assertSame('/php/debug/?test=test', $request->getRequestTarget());
        $this->assertSame('POST', $request->getMethod());

        // Let's double check the Uri instance again.
        $this->assertTrue($request->getUri() instanceof Uri);
        $this->assertSame('http', $request->getUri()->getScheme());
        $this->assertSame('www.bradkent.com', $request->getUri()->getHost());
        $this->assertSame('', $request->getUri()->getUserInfo());
        $this->assertSame('/php/debug/', $request->getUri()->getPath());
        $this->assertSame(null, $request->getUri()->getPort());
        $this->assertSame('test=test', $request->getUri()->getQuery());
        $this->assertSame('', $request->getUri()->getFragment());

        // Test 2

        $request = $this->factory()->createRequest('GET', 'http://www.bradkent.com/');

        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testWithMethods()
    {
        $request = $this->factory()->createRequest('GET', 'http://www.bradkent.com/');

        $uriGoogle = $this->factory()->createUri('http://google.com');

        $newRequest = $request
            ->withMethod('POST')
            ->withUri($uriGoogle);

        $this->assertSame('POST', $newRequest->getMethod());
        $this->assertSame('/', $newRequest->getRequestTarget());
        $this->assertSame('google.com', $newRequest->getUri()->getHost());

        $newRequestNoChange = $newRequest->withUri($uriGoogle);
        $this->assertSame($newRequest, $newRequestNoChange);

        $new2Request = $newRequest->withRequestTarget('/newTarget/test/?q=1234');
        $this->assertSame('/newTarget/test/?q=1234', $new2Request->getRequestTarget());

        /*
        $new3Request = $new2Request->withUri(new Uri('https://www.test.com'), true);

        // Preserve Host Header
        $this->assertSame('google.com', $new3Request->getHeaderLine('host'));
        $this->assertSame('www.test.com', $new3Request->getUri()->getHost());

        $uri = new Uri('/somePath');
        $request = $request->withUri($uri);
        $this->assertSame('www.bradkent.com', $request->getHeaderLine('Host'));

        $uri = new Uri('http://www.test.com:8080/somePath');
        $request = $request->withUri($uri);
        $this->assertSame('www.test.com:8080', $request->getHeaderLine('host'));
        */
    }

    public function testHostHeaderFromUri()
    {
        $request = $this->factory()->createRequest('GET', 'http://example.com');
        $this->assertArrayHasKey('Host', $request->getHeaders());
        $this->assertSame(['example.com'], $request->getHeader('host'));
        $this->assertSame('example.com', $request->getHeaderLine('host'));
    }

    public function testHostHeaderWithPortFromUri()
    {
        $request = $this->factory()->createRequest('GET', 'http://foo.com:8124/bar');
        $this->assertArrayHasKey('Host', $request->getHeaders());
        $this->assertEquals('foo.com:8124', $request->getHeaderLine('host'));
    }

    public function testHostHeaderWithoutStandardPortFromUri()
    {
        $request = $this->factory()->createRequest('GET', 'http://example.com:80');
        $this->assertArrayHasKey('Host', $request->getHeaders());
        $this->assertContains('example.com', $request->getHeader('Host'));
    }

    public function testWithUriUpdatesHostHeader()
    {
        $request1 = $this->factory()->createRequest('GET', 'http://foo.com/baz?bar=bam');
        $this->assertEquals('foo.com', $request1->getHeaderLine('host'));

        $request2 = $request1->withUri($this->factory()->createUri('http://www.baz.com/bar'));
        $this->assertEquals('www.baz.com', $request2->getHeaderLine('host'));
    }

    public function testWithUriUpdatesHostHeaderWithPort()
    {
        $request1 = $this->factory()->createRequest('GET', 'http://foo.com:8124/bar');
        $this->assertEquals('foo.com:8124', $request1->getHeaderLine('host'));

        $request2 = $request1->withUri($this->factory()->createUri('http://foo.com:8125/bar'));
        $this->assertEquals('foo.com:8125', $request2->getHeaderLine('host'));
    }

    public function testWithUriNotUpdateHostIfHostless()
    {
        $request = $this->factory()->createRequest('GET', 'http://www.bradkent.com/')
            ->withUri($this->factory()->createUri('/somePath'));
        $this->assertSame('www.bradkent.com', $request->getHeaderLine('Host'));
    }

    public function testWithUriPreserveHost()
    {
        $host = \md5($this->randomBytes(12)) . '.com';
        $request = $this->factory()->createRequest('GET', '')
            ->withHeader('Host', $host);
        $this->assertEquals($host, $request->getHeaderLine('host'));

        $request = $request->withUri($this->factory()->createUri('http://www.foo.com/bar'), true);
        $this->assertEquals($host, $request->getHeaderLine('host'));
    }

    public function testWithoutHeaderUpdatesHostFromUri()
    {
        $request1 = $this->factory()->createRequest('GET', 'http://www.example.com');
        $this->assertEquals('www.example.com', $request1->getHeaderLine('host'));

        $request2 = $request1->withoutHeader('host');
        $this->assertArrayHasKey('Host', $request2->getHeaders());
        $this->assertStringContainsString('www.example.com', $request2->getHeaderLine('Host'));
    }

    public function testNoHostHeader()
    {
        $request = $this->factory()->createRequest('GET', '');
        $this->assertFalse($request->hasHeader('host'));
    }

    public function testNoHostHeaderIfHostlessUri()
    {
        $request = $this->factory()->createRequest('GET', '/test?a');
        $this->assertFalse($request->hasHeader('host'));
    }

    public function testWithHeaderUpdatesHost()
    {
        $request = $this->factory()->createRequest('GET', 'http://www.example.com')
            ->withHeader('Host', 'www.test.com');
        $this->assertSame('www.test.com', $request->getHeaderLine('host'));
    }

    /*
        Exceptions
    */

    public function testExceptionConstructor()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => URI should be a string or an instance of UriInterface, but array provided.
        new Request('GET', array());
    }

    /*
    public function testExceptionMethod1()
    {
        $this->expectException('InvalidArgumentException');
        $request = $this->factory()->createRequest('GET', 'http://www.bradkent.com/');
        // Exception => HTTP method must be a string.
        $request->withMethod(['POST']);
    }

    public function testExceptionMethod2()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => Unsupported HTTP method.
        //    It must be compatible with RFC-7231 request method
        new Request('GETX', 'http://www.bradkent.com/');
    }

    public function testExceptionMethod3()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => Unsupported HTTP method.
        //    It must be compatible with RFC-7231 request method
        new Request('', 'http://www.bradkent.com/');
    }
    */

    public function testExceptionRequestTargetContainSpaceCharacter()
    {
        $this->expectException('InvalidArgumentException');
        $request = new Request('GET', 'http://www.bradkent.com/');
        // Exception => A request target cannot contain any whitespace.
        $request->withRequestTarget('/newTarget/te st/?q=1234');
    }

    public function testExceptionRequestTargetInvalidType()
    {
        $this->expectException('InvalidArgumentException');
        $request = new Request('GET', 'http://www.bradkent.com/');
        // Exception => A request target must be a string.
        $request->withRequestTarget(['foo' => 'bar']);
    }

    /**
     * @param mixed $version
     *
     * @dataProvider invalidRequestMethods
     */
    public function testWithMethodInvalidThrowsException($method)
    {
        $this->expectException('InvalidArgumentException');
        $this->factory()->createRequest('GET', '')
            ->withMethod($method);
    }
}
