<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Uri;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 *
 */
class UriTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testConstruct()
    {
        $uri = new Uri(null);
        $this->assertTrue($uri instanceof Uri);
        $this->assertSame('', (string) $uri);

        $uri = new Uri('http://jack:1234@example.com/demo/?test=5678&test2=90#section-1');
        $this->assertTrue($uri instanceof Uri);
        $this->assertSame('http://jack:1234@example.com/demo/?test=5678&test2=90#section-1', (string) $uri);
    }

    public function testToString()
    {
        // Test 1
        $uri = new Uri('http://jack:1234@example.com:8888/demo/?test=5678&test2=90#section-1');
        $this->assertSame((string) $uri, 'http://jack:1234@example.com:8888/demo/?test=5678&test2=90#section-1');

        // Test 2
        $uri = new Uri('http://example.com:8888/demo/#section-1');
        $this->assertSame((string) $uri, 'http://example.com:8888/demo/#section-1');

        // Test 3  (leading / added to path)
        $uri = new Uri('http://example.com');
        $uri = $uri->withPath('test');
        $this->assertSame((string) $uri, 'http://example.com/test');

        // Test 3  (leading slashes trimmed)
        $uri = new Uri();
        $uri = $uri->withPath('///trimMe');
        $this->assertSame((string) $uri, '/trimMe');
    }

    public function testProperties()
    {
        $uri = new Uri('http://jack:1234@example.com:8080/demo/?test=5678&test2=90#section-1');

        $properties = array(
            'fragment' => 'section-1',
            'host' => 'example.com',
            'path' => '/demo/',
            'port' => 8080,
            'query' => 'test=5678&test2=90',
            'scheme' => 'http',
            'userInfo' => 'jack:1234',
        );

        $reflection = new ReflectionObject($uri);

        foreach ($properties as $k => $v) {
            $prop = $reflection->getProperty($k);
            $prop->setAccessible(true);
            $this->assertSame($v, $prop->getValue($uri));
            unset($prop);
        }
    }

    public function testGetMethods()
    {
        // Test 1

        $uri = new Uri('http://jack:1234@example.com:8080/demo/?test=5678&test2=90#section-1');

        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('jack:1234', $uri->getUserInfo());
        $this->assertSame('/demo/', $uri->getPath());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('test=5678&test2=90', $uri->getQuery());
        $this->assertSame('section-1', $uri->getFragment());

        // Test 2

        $uri = new Uri('https://www.example.com');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('www.example.com', $uri->getHost());
        $this->assertSame('', $uri->getUserInfo()); // string
        $this->assertSame('', $uri->getPath());     // string
        $this->assertSame(null, $uri->getPort());   // int|null
        $this->assertSame('', $uri->getQuery());    // string
        $this->assertSame('', $uri->getFragment()); // string
    }

    public function testWithMethods()
    {
        $uri = new Uri('https://www.example.com');

        // Test 1

        $newUri = $uri->withScheme('http')
            ->withHost('example.com')
            ->withPort(8080)
            ->withUserInfo('jack', '4321')
            ->withPath('/en')
            ->withQuery('test=123')
            ->withFragment('1234');

        $this->assertSame('http', $newUri->getScheme());
        $this->assertSame('example.com', $newUri->getHost());
        $this->assertSame('jack:4321', $newUri->getUserInfo());
        $this->assertSame('/en', $newUri->getPath());
        $this->assertSame(8080, $newUri->getPort());
        $this->assertSame('test=123', $newUri->getQuery());
        $this->assertSame('1234', $newUri->getFragment());

        // Test 2

        $newUri = $uri->withScheme('http')
            ->withHost('127.0.0.1')
            ->withPort('80')
            ->withUserInfo('people')
            ->withPath('/天安門')
            ->withQuery('chineseChars=六四')
            ->withFragment('19890604');

        $this->assertSame('http', $newUri->getScheme());
        $this->assertSame('127.0.0.1', $newUri->getHost());
        $this->assertSame('people', $newUri->getUserInfo());
        $this->assertSame('/%E5%A4%A9%E5%AE%89%E9%96%80', $newUri->getPath());
        $this->assertSame(null, $newUri->getPort());
        $this->assertSame('chineseChars=%E5%85%AD%E5%9B%9B', $newUri->getQuery());
        $this->assertSame('19890604', $newUri->getFragment());

        // Test 3 - assert that 'localhost' is a valid host

        $newUri = $uri->withHost('localhost');
        $this->assertSame('localhost', $newUri->getHost());

        // Test 4 - should return existing instance if no update)
        $newUri = $uri->withScheme('https');
        $this->assertSame($uri, $newUri, 'not updating scheme should return existing uri obj');

        // Test 5 - should return existing instance if no update)
        $newUri = $newUri->withUserInfo('jack', '4321');
        $newUri2 = $newUri->withUserInfo('jack', '4321');
        $this->assertSame($newUri, $newUri2, 'not updating userInfo should return existing uri obj');

        // Test 5 - should return existing instance if no update)
        $newUri = $uri->withHost('www.example.com');
        $this->assertSame($uri, $newUri, 'not updating host should return existing uri obj');

        // Test 6 - should return existing instance if no update)
        $newUri = $uri->withPath('');
        $this->assertSame($uri, $newUri, 'not updating path should return existing uri obj');

        // Test 7 - should return existing instance if no update)
        $newUri = $uri->withQuery('test=123');
        $newUri2 = $newUri->withQuery('test=123');
        $this->assertSame($newUri, $newUri2, 'not updating query should return existing uri obj');

        // Test 8 - should return existing instance if no update)
        $newUri = $uri->withFragment('hash');
        $newUri2 = $newUri->withFragment('hash');
        $this->assertSame($newUri, $newUri2, 'not updating fragment should return existing uri obj');
    }

    public function testFilterPort()
    {
        $uri = new Uri('http://example.com:80');
        $this->assertSame(null, $uri->getPort());

        $uri = new Uri('//example.com:80');
        $this->assertSame(80, $uri->getPort());
    }

    /*
        Exceptions
    */

    public function testNonParsableUri()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => Uri must be a string, but integer provided.
        new Uri('http:///example.com');
    }

    public function testExceptionAssertString()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => Uri must be a string, but integer provided.
        new Uri(1234);
    }

    public function testExceptionHost()
    {
        $this->expectException('InvalidArgumentException');
        $uri = new Uri();
        // Exception => "example_test.com" is not a valid host
        $uri->withHost($uri, 'example_test.com');
    }

    public function testExceptionWithPortInvalidVariableType()
    {
        $this->expectException('InvalidArgumentException');
        $uri = new Uri();
        // Exception => Port must be an integer or a null value, but string provided.
        $uri->withPort('foo');
    }

    public function testExceptionWithPortInvalidRangeNumer()
    {
        $this->expectException('InvalidArgumentException');
        $uri = new Uri();
        // Exception => Port number should be in a range of 0-65535, but 70000 provided.
        $uri->withPort(70000);
    }
}
