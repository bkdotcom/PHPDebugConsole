<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\Client;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Test\CurlHttpMessage\Fixture\JsonSerializable;
use bdk\Test\CurlHttpMessage\AbstractTestCase;

/**
 * @covers \bdk\CurlHttpMessage\AbstractClient
 * @covers \bdk\CurlHttpMessage\Client
 * @covers \bdk\CurlHttpMessage\Factory
 * @covers \bdk\CurlHttpMessage\Handler\Curl
 */
class ClientTest extends AbstractTestCase
{
    use AssertionTrait;
    use ProviderTrait;

    public function testGetStack()
    {
        $client = new Client();
        $stack = $client->getStack();
        self::assertInstanceOf($this->classes['HandlerStack'], $stack);
    }

    public function testUsesDefaultHandler()
    {
        $client = new Client();
        $response = $client->get($this->baseUrl . '/echo');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testCanSendSynchronously()
    {
        $client = new Client();
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');
        $response = $client->handle($request);
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testClientHasOptions()
    {
        $client = new Client([
            'timeout'  => 2,
            'headers'  => ['bar' => 'baz'],
            'handler'  => new MockHandler(),
        ]);
        $options = self::propGet($client, 'options');
        self::assertArrayHasKey('timeout', $options);
        self::assertSame(2, $options['timeout']);
    }

    public function testMergesDefaultOptionsAndDoesNotOverwriteUa()
    {
        $client = new Client([
            'headers' => ['User-agent' => 'default'],
        ]);
        $options = self::propGet($client, 'options');
        self::assertSame(['User-agent' => 'default'], $options['headers']);
        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertStringContainsString('User-Agent: default', $requestInfo['headers']);
    }

    public function testDoesNotOverwriteHeaderWithDefault()
    {
        $client = new Client([
            'headers' => ['User-agent' => 'default'],
        ]);
        $response = $client->get($this->baseUrl . '/echo', [
            'User-Agent' => 'bar',
        ]);
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertStringContainsString('User-Agent: bar', $requestInfo['headers']);
    }

    public function testDoesNotOverwriteHeaderWithDefaultInRequest()
    {
        $client = new Client([
            'headers' => ['User-agent' => 'default'],
        ]);
        $request = $this->factory->request('GET', $this->baseUrl . '/echo', [
            'User-Agent' => 'bar',
        ]);
        $response = $client->handle($request);
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertStringContainsString('User-Agent: bar', $requestInfo['headers']);
    }

    public function testDoesOverwriteHeaderWithSetRequestOption()
    {
        $client = new Client([
            'headers' => ['User-agent' => 'foo'],
        ]);
        $request = $this->factory->request('GET', $this->baseUrl . '/echo', [
            'User-Agent' => 'bar',
        ]);
        $response = $client->handle($request, [
            'headers' => [
                'User-Agent' => 'YO',
            ],
        ]);
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertStringContainsString('User-Agent: YO', $requestInfo['headers']);
    }

    public function testCanUnsetRequestOptionWithNull()
    {
        $mock = new MockHandler([
            $this->factory->response()
        ]);
        $client = new Client([
            'headers' => ['foo' => 'bar'],
            'handler' => $mock,
        ]);
        $client->get('http://example.com', null);
        self::assertFalse($mock->getLastRequest()->hasHeader('foo'));
    }

    public function testThrowsHttpErrorsByDefault()
    {
        $mock = new MockHandler([
            $this->factory->response(404)
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);

        $this->expectException($this->classes['BadResponseException']);
        $client->get('http://foo.com');
    }

    public function testCookiesPersist()
    {
        $client = new Client([
            // 'handler' => $mock,
        ]);
        $response = $client->get($this->baseUrl . '/echo?cookies[foo]=bar');
        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertSame(array('foo' => 'bar'), $requestInfo['cookieParams']);
    }

    public function testCanSetContentDecodingToValue()
    {
        $client = new Client(array(
            'curl' => array(
                CURLOPT_ENCODING => 'gzip',
            ),
        ));
        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertStringContainsString('Accept-Encoding: gzip', $requestInfo['headers']);
    }

    public function testAddsAcceptEncodingbyCurl()
    {
        $client = new Client(array(
            'curl' => [
                CURLOPT_ENCODING => '',
            ],
        ));

        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertMatchesRegularExpression('/Accept-Encoding: .*deflate/m', $requestInfo['headers']);
    }

    public function testValidatesHeaders()
    {
        $client = new Client();

        $this->expectException($this->classes['InvalidArgumentException']);
        $client->get('http://foo.com', 'foo');
    }

    public function testAddsBody()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, ['body' => 'foo']);
        $last = $mock->getLastRequest();
        self::assertSame('foo', (string) $last->getBody());
    }

    public function testFormParamsEncodedProperly()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $client->post(
            'http://foo.com',
            [],
            [
                'foo' => 'bar bam',
                'baz' => ['boo' => 'qux'],
            ]
        );
        $last = $mock->getLastRequest();
        self::assertSame(
            'foo=bar+bam&baz%5Bboo%5D=qux',
            (string) $last->getBody()
        );
    }

    public function testJsonSerializable()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $body = new JsonSerializable(array('foo' => 'bar'));
        $client->post('http://foo.com', array(), $body);
        $lastRequest = $mock->getLastRequest();
        self::assertSame('application/json; charset=utf-8', $lastRequest->getHeaderLine('Content-Type'));
        self::assertSame(array('foo' => 'bar'), \json_decode($lastRequest->getBody(), true));
    }

    public function testJsonString()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $data = array('foo' => 'bar');
        $body = $this->factory->stream(\json_encode($data));
        $client->post('http://foo.com', array(), $body);
        $lastRequest = $mock->getLastRequest();
        self::assertSame('application/json; charset=utf-8', $lastRequest->getHeaderLine('Content-Type'));
        self::assertSame($data, \json_decode($lastRequest->getBody(), true));
    }

    public function testRequestSendsWithSync()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $client->request('GET', 'http://foo.com');
        self::assertFalse($mock->getLastOptions()['isAsynchronous']);
    }

    public function testSendSendsWithSync()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $client->handle($this->factory->request('GET', 'http://foo.com'));
        self::assertFalse($mock->getLastOptions()['isAsynchronous']);
    }

    public function testSendWithInvalidHeader()
    {
        $client = new Client();
        $request = $this->factory->request('GET', $this->baseUrl . '/echo');

        $this->expectException($this->classes['InvalidArgumentException']);
        $client->handle($request, [
            'headers' => ['X-Foo: Bar'],
        ]);
    }

    public function testSendWithInvalidHeaders()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('GET', 'http://foo.com');

        $this->expectException($this->classes['InvalidArgumentException']);
        $client->handle($request, [
            'headers' => ['X-Foo: Bar', 'X-Test: Fail'],
        ]);
    }

    public function testHostHeaderOverridesRequest()
    {
        $mock = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $request = $this->factory->request('GET', 'http://127.0.0.1:8585/test', [
            'Host' => 'foo.com',
        ]);
        $client->handle($request);
        self::assertSame(array('foo.com'), $mock->getLastRequest()->getHeader('Host'));
    }

    public function testHandlerIsCallable()
    {
        $this->expectException($this->classes['InvalidArgumentException']);

        new Client([
            'handler' => 'not_callable',
        ]);
    }

    /**
     * @dataProvider methodProvider
     */
    public function testHelperMethods($method, $uri, $headers = array(), $body = null)
    {
        $handler = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client(array(
            'handler' => $handler,
        ));

        $response = \call_user_func(array($client, $method), $uri, $headers, $body);
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        $lastRequest = $handler->getLastRequest();
        self::assertSame(\strtoupper($method), $lastRequest->getMethod());
        self::assertSame($uri, (string) $lastRequest->getUri());
        if ($body) {
            self::assertSame(
                \json_encode(array('foo' => 'bar'), JSON_PRETTY_PRINT),
                (string) $lastRequest->getBody()
            );
        }
    }

    /**
     * @dataProvider methodProvider
     */
    public function testRequest($method, $uri, $headers = array(), $body = null)
    {
        $handler = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client(array(
            'handler' => $handler,
        ));

        $response = $client->request(\strtoupper($method), $uri, array(
            'headers' => $headers,
            'body' => $body,
        ));
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        $lastRequest = $handler->getLastRequest();
        self::assertSame(\strtoupper($method), $lastRequest->getMethod());
        self::assertSame($uri, (string) $lastRequest->getUri());
        if ($body) {
            self::assertSame(
                \json_encode(array('foo' => 'bar'), JSON_PRETTY_PRINT),
                (string) $lastRequest->getBody()
            );
        }
    }

    /**
     * @dataProvider methodProvider
     */
    public function testHandle($method, $uri, $headers = array(), $body = null)
    {
        $handler = new MockHandler([
            $this->factory->response(),
        ]);
        $client = new Client(array(
            'handler' => $handler,
        ));

        $request = $this->factory->request($method, $uri, $headers, $body);
        $response = $client->handle($request);
        self::assertInstanceOf($this->classes['ResponseInterface'], $response);
        $lastRequest = $handler->getLastRequest();
        self::assertSame(\strtoupper($method), $lastRequest->getMethod());
        self::assertSame($uri, (string) $lastRequest->getUri());
        if ($body) {
            self::assertSame(
                \json_encode(array('foo' => 'bar'), JSON_PRETTY_PRINT),
                (string) $lastRequest->getBody()
            );
        }
    }
}
