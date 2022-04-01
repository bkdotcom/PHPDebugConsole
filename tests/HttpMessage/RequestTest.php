<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Message;
use bdk\HttpMessage\Request;
use bdk\HttpMessage\Uri;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\HttpMessage\Request
 */
class RequestTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $request = new Request('GET', '', '', [], '1.1');

        $this->assertTrue($request instanceof Request);
        $this->assertTrue($request instanceof Message);

        $uri = new Uri('https://www.example.com');
        $request = new Request('GET', $uri, '', [], '1.1');

        $this->assertSame('www.example.com', $request->getUri()->getHost());
    }

    public function testGetMethods()
    {
        // Test 1

        $request = new Request('POST', 'http://www.bradkent.com/php/debug/?test=test');

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

        $request = new Request('GET', 'http://www.bradkent.com/');

        $this->assertSame('/', $request->getRequestTarget());
    }

    public function testWithMethods()
    {
        $request = new Request('GET', 'http://www.bradkent.com/');

        $uriGoogle = new Uri('http://google.com');

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

    public function testExceptionMethod1()
    {
        $this->expectException('InvalidArgumentException');
        $request = new Request('GET', 'http://www.bradkent.com/');
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
}
