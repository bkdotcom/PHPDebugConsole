<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Uri;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 * @covers \bdk\HttpMessage\AbstractUri
 * @covers \bdk\HttpMessage\Uri
 */
class UriTest extends TestCase
{
    use ExpectExceptionTrait;
    use DataProviderTrait;
    use FactoryTrait;

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
        $newUri = $newUri->withPort(80);
        $this->assertSame(null, $newUri->getPort());

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

    /*
    public function testNonParsableUri()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Unable to parse URI: http:///example.com');
        // Exception => Uri must be a string, but integer provided.
        new Uri('http:///example.com');
    }

    public function testExceptionAssertString()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Uri must be a string, but stdClass provided.');
        // Exception => Uri must be a string, but integer provided.
        new Uri((object) [1234]);
    }

    public function testExceptionHost()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('"example_test.com" is not a valid host');
        $uri = new Uri();
        // Exception => "example_test.com" is not a valid host
        $uri->withHost('example_test.com');
    }

    public function testExceptionWithPortInvalidVariableType()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Port must be a int, but string provided.');
        $uri = new Uri();
        // Exception => Port must be an integer or a null value, but string provided.
        $uri->withPort('foo');
    }

    public function testExceptionWithPortInvalidRangeNumer()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid port: 70000. Must be between 0 and 65535');
        $uri = new Uri();
        // Exception => Port number should be in a range of 0-65535, but 70000 provided.
        $uri->withPort(70000);
    }
    */

    /**
     * @param string $input
     *
     * @dataProvider validUris
     */
    public function testValidUrisFormsArePreserved($input)
    {
        $uri = $this->factory()->createUri($input);
        $this->assertSame($input, (string) $uri);
    }

    /**
     * @param string $scheme
     *
     * @dataProvider validUriSchemes
     */
    public function testValidSchemesAreAccepted($scheme)
    {
        $uri = $this->factory()->createUri()->withScheme($scheme);
        $this->assertSame($scheme, $uri->getScheme());
    }

    /**
     * @param string $input
     * @param string $path
     * @param string $query
     * @param string $fragment
     * @param string $output
     *
     * @dataProvider uriComponents
     */
    public function testUriComponentsEncoding($input, $path, $query, $fragment, $output)
    {
        $uri = $this->factory()->createUri($input);
        $this->assertSame($path, $uri->getPath());
        $this->assertSame($query, $uri->getQuery());
        $this->assertSame($fragment, $uri->getFragment());
        $this->assertSame($output, (string) $uri);
    }

    /**
     * @param mixed $uri
     *
     * @dataProvider invalidUris
     */
    public function testInvalidUrisAreRejected($uri)
    {
        $this->expectException('InvalidArgumentException');
        $this->factory()->createUri($uri);
    }

    /**
     * @param mixed $scheme
     *
     * @dataProvider invalidUriSchemes
     */
    public function testWithSchemeRejectsInvalid($scheme)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withScheme($scheme);
        $uri->getScheme();
    }

    /**
     * @param mixed $user
     * @param mixed $password
     *
     * @dataProvider invalidUriUserInfos
     */
    public function testWithUserInfoRejectsInvalid($user, $password)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withUserInfo($user, $password);
        $uri->getUserInfo();
    }

    /**
     * @param mixed $host
     *
     * @dataProvider invalidUriHosts
     */
    public function testWithHostRejectsInvalid($host)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withHost($host);
        $uri->getHost();
    }

    /**
     * @param mixed $port
     *
     * @dataProvider invalidUriPorts
     */
    public function testWithPortRejectsInvalid($port)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withPort($port);
        $uri->getPort();
    }

    /**
     * @param mixed $path
     *
     * @dataProvider invalidUriPaths
     */
    public function testWithPathRejectsInvalid($path)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withPath($path);
        $uri->getPath();
    }

    /**
     * @param mixed $query
     *
     * @dataProvider invalidUriQueries
     */
    public function testWithQueryRejectsInvalidValues($query)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withQuery($query);
        $uri->getQuery();
    }

    /**
     * @param mixed $fragment
     *
     * @dataProvider invalidUriFragments
     */
    public function testWithFragmentRejectsInvalidValues($fragment)
    {
        $this->expectException('InvalidArgumentException');
        $uri = $this->factory()->createUri()->withFragment($fragment);
        $uri->getFragment();
    }
}
