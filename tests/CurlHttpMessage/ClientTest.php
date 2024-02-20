<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\Client;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Test\CurlHttpMessage\Fixture\JsonSerializable;
use bdk\Test\CurlHttpMessage\TestCase;

/**
 * @covers \bdk\CurlHttpMessage\AbstractClient
 * @covers \bdk\CurlHttpMessage\Client
 * @covers \bdk\CurlHttpMessage\Factory
 * @covers \bdk\CurlHttpMessage\Handler\Curl
 */
class ClientTest extends TestCase
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

    /*
    public function testValidatesArgsForMagicMethods()
    {
        $client = new Client();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Magic request methods require a URI and optional options array');
        $client->options();
    }
    */

    /*
    public function testCanSendAsyncGetRequests()
    {
        $client = new Client(array(
            'isAsyncronous' => true,
        ));
        $promise = $client->get($this->baseUrl . '/echo');
        self::assertInstanceOf($this->classes['PromiseInterface'], $promise);
        $response = $promise->wait();
        self::assertSame(200, $response->getStatusCode());
    }
    */

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
            // 'base_uri' => 'http://foo.com',
            'timeout'  => 2,
            'headers'  => ['bar' => 'baz'],
            'handler'  => new MockHandler(),
        ]);
        $options = \bdk\Debug\Utility\Reflection::propGet($client, 'options');
        // self::assertArrayHasKey('base_uri', $options);
        // self::assertInstanceOf(Uri::class, $options['base_uri']);
        // self::assertSame('http://foo.com', (string) $options['base_uri']);
        // self::assertArrayHasKey('handler', $options);
        // self::assertNotNull($options['handler']);
        self::assertArrayHasKey('timeout', $options);
        self::assertSame(2, $options['timeout']);
    }

    /*
    public function testCanMergeOnBaseUri()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'base_uri' => 'http://foo.com/bar/',
            'handler'  => $mock
        ]);
        $client->get('baz');
        self::assertSame(
            'http://foo.com/bar/baz',
            (string)$mock->getLastRequest()->getUri()
        );
    }
    */

    /*
    public function testCanMergeOnBaseUriWithRequest()
    {
        $mock = new MockHandler([$this->factory->response(), $this->factory->response()]);
        $client = new Client([
            // 'base_uri' => 'http://foo.com/bar/'
            'handler'  => $mock,
        ]);
        $client->request('GET', new Uri('baz'));
        self::assertSame(
            'http://foo.com/bar/baz',
            (string) $mock->getLastRequest()->getUri()
        );

        $client->get(new Uri('baz'), [
            'base_uri' => 'http://example.com/foo/',
        ]);
        self::assertSame(
            'http://example.com/foo/baz',
            (string) $mock->getLastRequest()->getUri(),
            'Can overwrite the base_uri through the request options'
        );
    }
    */

    /*
    public function testCanUseRelativeUriWithSend()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'handler'  => $mock,
            'base_uri' => 'http://bar.com'
        ]);
        $config = Helpers::readObjectAttribute($client, 'config');
        self::assertSame('http://bar.com', (string) $config['base_uri']);
        $request = $this->factory->request('GET', '/baz');
        $client->handle($request);
        self::assertSame(
            'http://bar.com/baz',
            (string) $mock->getLastRequest()->getUri()
        );
    }
    */

    public function testMergesDefaultOptionsAndDoesNotOverwriteUa()
    {
        $client = new Client([
            'headers' => ['User-agent' => 'default'],
        ]);
        $options = \bdk\Debug\Utility\Reflection::propGet($client, 'options');
        self::assertSame(['User-agent' => 'default'], $options['headers']);
        // self::assertFalse($options['isAsyncronous']);
        // self::assertIsArray($options['allow_redirects']);
        // self::assertTrue($options['http_errors']);
        // self::assertTrue($options['decode_content']);
        // self::assertTrue($options['verify']);
        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertStringContainsString('User-Agent: default', $requestInfo['headers']);
    }

    public function testDoesNotOverwriteHeaderWithDefault()
    {
        $client = new Client([
            'headers' => ['User-agent' => 'default'],
            // 'handler' => $mock,
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

    /*
    public function testAllowRedirectsCanBeTrue()
    {
        $mock = new MockHandler([$this->factory->response(200, [], 'foo')]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);
        $client->get('http://foo.com', ['allow_redirects' => true]);
        self::assertIsArray($mock->getLastOptions()['allow_redirects']);
    }
    */

    /*
    public function testValidatesAllowRedirects()
    {
        $mock = new MockHandler([$this->factory->response(200, [], 'foo')]);
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('allow_redirects must be true, false, or array');
        $client->get('http://foo.com', ['allow_redirects' => 'foo']);
    }
    */

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

    /*
    public function testValidatesCookies()
    {
        $mock = new MockHandler([
            $this->factory->response(200, [], 'foo')],
        );
        // $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cookies must be an instance of GuzzleHttp\\Cookie\\CookieJarInterface');
        $client->get('http://foo.com', ['cookies' => 'foo']);
    }
    */

    /*
    public function testSetCookieToTrueUsesSharedJar()
    {
        $mock = new MockHandler([
            $this->factory->response(200, ['Set-Cookie' => 'foo=bar']),
            $this->factory->response(),
        ]);
        // $handler = HandlerStack::create($mock);
        $client = new Client([
            'handler' => $handler,
            'cookies' => true
        ]);
        $client->get('http://foo.com');
        $client->get('http://foo.com');
        self::assertSame('foo=bar', $mock->getLastRequest()->getHeaderLine('Cookie'));
    }
    */

    public function testCookiesPersist()
    {
        /*
        $mock = new MockHandler([
            $this->factory->response(200, null, ['Set-Cookie' => 'foo=bar']),
            $this->factory->response(),
        ]);
        */
        // $handler = HandlerStack::create($mock);
        $client = new Client([
            // 'handler' => $mock,
        ]);
        // $jar = new CookieJar();
        $response = $client->get($this->baseUrl . '/echo?cookies[foo]=bar');
        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        self::assertSame(array('foo' => 'bar'), $requestInfo['cookieParams']);
    }

    /*
    public function testCanDisableContentDecoding()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['decode_content' => false]);
        $last = $mock->getLastRequest();
        self::assertFalse($last->hasHeader('Accept-Encoding'));
        self::assertFalse($mock->getLastOptions()['decode_content']);
    }
    */

    public function testCanSetContentDecodingToValue()
    {
        /*
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['decode_content' => 'gzip']);
        $last = $mock->getLastRequest();
        self::assertSame('gzip', $last->getHeaderLine('Accept-Encoding'));
        self::assertSame('gzip', $mock->getLastOptions()['decode_content']);
        */
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

        // Server::flush();
        // Server::enqueue([$this->factory->response()]);
        $response = $client->get($this->baseUrl . '/echo');
        $requestInfo = \json_decode($response->getBody(), true);
        // \bdk\Test\Debug\Helper::stderr('requestInfo', $requestInfo);
        self::assertMatchesRegularExpression('/Accept-Encoding: .*deflate/m', $requestInfo['headers']);
        // $sent = Server::received()[0];
        // self::assertTrue($sent->hasHeader('Accept-Encoding'));

        // $mock = new MockHandler([$this->factory->response()]);
        // $client->get('http://foo.com', ['handler' => $mock]);
        // self::assertSame([\CURLOPT_ENCODING => ''], $mock->getLastOptions()['curl']);
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

    /*
    public function testValidatesQuery()
    {
        $mock = new MockHandler();
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('PUT', 'http://foo.com');

        $this->expectException(\InvalidArgumentException::class);
        $client->handle($request, ['query' => false]);
    }
    */

    /*
    public function testQueryCanBeString()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, ['query' => 'foo']);
        self::assertSame('foo', $mock->getLastRequest()->getUri()->getQuery());
    }
    */

    /*
    public function testQueryCanBeArray()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, ['query' => ['foo' => 'bar baz']]);
        self::assertSame('foo=bar%20baz', $mock->getLastRequest()->getUri()->getQuery());
    }
    */

    /*
    public function testCanAddJsonData()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, ['json' => ['foo' => 'bar']]);
        $last = $mock->getLastRequest();
        self::assertSame('{"foo":"bar"}', (string) $mock->getLastRequest()->getBody());
        self::assertSame('application/json', $last->getHeaderLine('Content-Type'));
    }
    */

    /*
    public function testCanAddJsonDataWithoutOverwritingContentType()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, [
            'headers' => ['content-type' => 'foo'],
            'json'    => 'a',
        ]);
        $last = $mock->getLastRequest();
        self::assertSame('"a"', (string) $mock->getLastRequest()->getBody());
        self::assertSame('foo', $last->getHeaderLine('Content-Type'));
    }
    */

    /*
    public function testCanAddJsonDataWithNullHeader()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, [
            'headers' => null,
            'json'    => 'a',
        ]);
        $last = $mock->getLastRequest();
        self::assertSame('"a"', (string) $mock->getLastRequest()->getBody());
        self::assertSame('application/json', $last->getHeaderLine('Content-Type'));
    }
    */

    /*
    public function testAuthCanBeTrue()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['auth' => false]);
        $last = $mock->getLastRequest();
        self::assertFalse($last->hasHeader('Authorization'));
    }
    */

    /*
    public function testAuthCanBeArrayForBasicAuth()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['auth' => ['a', 'b']]);
        $last = $mock->getLastRequest();
        self::assertSame('Basic YTpi', $last->getHeaderLine('Authorization'));
    }
    */

    /*
    public function testAuthCanBeArrayForDigestAuth()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['auth' => ['a', 'b', 'digest']]);
        $last = $mock->getLastOptions();
        self::assertSame([
            \CURLOPT_HTTPAUTH => 2,
            \CURLOPT_USERPWD  => 'a:b'
        ], $last['curl']);
    }
    */

    /*
    public function testAuthCanBeArrayForNtlmAuth()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['auth' => ['a', 'b', 'ntlm']]);
        $last = $mock->getLastOptions();
        self::assertSame([
            \CURLOPT_HTTPAUTH => 8,
            \CURLOPT_USERPWD  => 'a:b'
        ], $last['curl']);
    }
    */

    /*
    public function testAuthCanBeCustomType()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->get('http://foo.com', ['auth' => 'foo']);
        $last = $mock->getLastOptions();
        self::assertSame('foo', $last['auth']);
    }
    */

    /*
    public function testCanAddFormParams()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->post('http://foo.com', [
            'form_params' => [
                'foo' => 'bar bam',
                'baz' => ['boo' => 'qux']
            ]
        ]);
        $last = $mock->getLastRequest();
        self::assertSame(
            'application/x-www-form-urlencoded',
            $last->getHeaderLine('Content-Type')
        );
        self::assertSame(
            'foo=bar+bam&baz%5Bboo%5D=qux',
            (string) $last->getBody()
        );
    }
    */

    public function testFormParamsEncodedProperly()
    {
        // $separator = \ini_get('arg_separator.output');
        // \ini_set('arg_separator.output', '&amp;');
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

        // \ini_set('arg_separator.output', $separator);
    }

    /*
    public function testEnsuresThatFormParamsAndMultipartAreExclusive()
    {
        $client = new Client(['handler' => static function () {
        }]);

        $this->expectException(\InvalidArgumentException::class);
        $client->post('http://foo.com', [
            'form_params' => ['foo' => 'bar bam'],
            'multipart' => []
        ]);
    }
    */

    /*
    public function testCanSendMultipart()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->post('http://foo.com', [
            'multipart' => [
                [
                    'name'     => 'foo',
                    'contents' => 'bar'
                ],
                [
                    'name'     => 'test',
                    'contents' => \fopen(__FILE__, 'r')
                ]
            ]
        ]);

        $last = $mock->getLastRequest();
        self::assertStringContainsString(
            'multipart/form-data; boundary=',
            $last->getHeaderLine('Content-Type')
        );

        self::assertStringContainsString(
            'Content-Disposition: form-data; name="foo"',
            (string) $last->getBody()
        );

        self::assertStringContainsString('bar', (string) $last->getBody());
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="foo"' . "\r\n",
            (string) $last->getBody()
        );
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="test"; filename="ClientTest.php"',
            (string) $last->getBody()
        );
    }
    */

    /*
    public function testCanSendMultipartWithExplicitBody()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $client->handle(
            $this->factory->request(
                'POST',
                'http://foo.com',
                [],
                new Psr7\MultipartStream(
                    [
                        [
                            'name' => 'foo',
                            'contents' => 'bar',
                        ],
                        [
                            'name' => 'test',
                            'contents' => \fopen(__FILE__, 'r'),
                        ],
                    ]
                )
            )
        );

        $last = $mock->getLastRequest();
        self::assertStringContainsString(
            'multipart/form-data; boundary=',
            $last->getHeaderLine('Content-Type')
        );

        self::assertStringContainsString(
            'Content-Disposition: form-data; name="foo"',
            (string) $last->getBody()
        );

        self::assertStringContainsString('bar', (string) $last->getBody());
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="foo"' . "\r\n",
            (string) $last->getBody()
        );
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="test"; filename="ClientTest.php"',
            (string) $last->getBody()
        );
    }
    */

    /*
    public function testUsesProxyEnvironmentVariables()
    {
        unset($_SERVER['HTTP_PROXY'], $_SERVER['HTTPS_PROXY'], $_SERVER['NO_PROXY']);
        \putenv('HTTP_PROXY=');
        \putenv('HTTPS_PROXY=');
        \putenv('NO_PROXY=');

        try {
            $client = new Client();
            $options = \bdk\Debug\Utility\Reflection::propGet($client, 'options');
            self::assertArrayNotHasKey('proxy', $options);

            \putenv('HTTP_PROXY=127.0.0.1');
            $client = new Client();
            $options = \bdk\Debug\Utility\Reflection::propGet($client, 'options');
            self::assertArrayHasKey('proxy', $options);
            self::assertSame(['http' => '127.0.0.1'], $options['proxy']);

            \putenv('HTTPS_PROXY=127.0.0.2');
            \putenv('NO_PROXY=127.0.0.3, 127.0.0.4');
            $client = new Client();
            $options = \bdk\Debug\Utility\Reflection::propGet($client, 'options');
            self::assertArrayHasKey('proxy', $options);
            self::assertSame(
                [
                    'http' => '127.0.0.1',
                    'https' => '127.0.0.2',
                    'no' => ['127.0.0.3', '127.0.0.4'],
                ],
                $options['proxy']
            );
        } finally {
            \putenv('HTTP_PROXY=');
            \putenv('HTTPS_PROXY=');
            \putenv('NO_PROXY=');
        }
    }
    */

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
        self::assertFalse($mock->getLastOptions()['isAsyncronous']);
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
        self::assertFalse($mock->getLastOptions()['isAsyncronous']);
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

    /*
    public function testCanSetCustomHandler()
    {
        $mock = new MockHandler([$this->factory->response(500)]);
        $client = new Client([
            'handler' => $mock,
        ]);
        $mock2 = new MockHandler([$this->factory->response(200)]);
        self::assertSame(
            200,
            $client->handle($this->factory->request('GET', 'http://foo.com'), [
                'handler' => $mock2,
            ])->getStatusCode()
        );
    }
    */

    /*
    public function testProperlyBuildsQuery()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('PUT', 'http://foo.com');
        $client->handle($request, ['query' => ['foo' => 'bar', 'john' => 'doe']]);
        self::assertSame('foo=bar&john=doe', $mock->getLastRequest()->getUri()->getQuery());
    }
    */

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

    /*
    public function testSendSendsWithDomainAndHostHeaderInRequestTheHostShouldBePreserved()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['base_uri' => 'http://foo2.com', 'handler' => $mockHandler]);
        $request = $this->factory->request('GET', '/test', ['Host' => 'foo.com']);

        $client->handle($request);

        self::assertSame('foo.com', $mockHandler->getLastRequest()->getHeader('Host')[0]);
    }
    */

    /*
    public function testValidatesSink()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $this->expectException(\InvalidArgumentException::class);
        $client->get('http://test.com', ['sink' => true]);
    }
    */

    /*
    public function testHttpDefaultSchemeIfUriHasNone()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $client->request('GET', '//example.org/test');

        self::assertSame('http://example.org/test', (string) $mockHandler->getLastRequest()->getUri());
    }
    */

    /*
    public function testOnlyAddSchemeWhenHostIsPresent()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $client->request('GET', 'baz');
        self::assertSame(
            'baz',
            (string) $mockHandler->getLastRequest()->getUri()
        );
    }
    */

    public function testHandlerIsCallable()
    {
        $this->expectException($this->classes['InvalidArgumentException']);

        new Client([
            'handler' => 'not_callable',
        ]);
    }

    /*
    public function testResponseBodyAsString()
    {
        $responseBody = '{ "package": "CurlHttpMessage" }';
        $mock = new MockHandler([
            $this->factory->response(
                200,
                null,
                ['Content-Type' => 'application/json'],
                $responseBody
            ),
        ]);
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('GET', 'http://foo.com');
        $response = $client->handle($request);

        self::assertSame($responseBody, (string) $response->getBody());
    }
    */

    /*
    public function testResponseContent()
    {
        $responseBody = '{ "package": "CurlHttpMessage" }';
        $mock = new MockHandler([
            $this->factory->response(200, null, ['Content-Type' => 'application/json'], $responseBody),
        ]);
        $client = new Client(['handler' => $mock]);
        $request = $this->factory->request('POST', 'http://foo.com');
        $response = $client->handle($request);

        self::assertSame($responseBody, $response->getBody()->getContents());
    }
    */

    /*
    public function testIdnSupportDefaultValue()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $config = Helpers::readObjectAttribute($client, 'config');

        self::assertFalse($config['idn_conversion']);
    }
    */

    /**
     * @requires extension idn
     */
    /*
    public function testIdnIsTranslatedToAsciiWhenConversionIsEnabled()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $client->get('https://яндекс.рф/images', [
            'idn_conversion' => true,
        ]);

        $request = $mockHandler->getLastRequest();

        self::assertSame('https://xn--d1acpjx3f.xn--p1ai/images', (string) $request->getUri());
        self::assertSame('xn--d1acpjx3f.xn--p1ai', (string) $request->getHeaderLine('Host'));
    }
    */

    /*
    public function testIdnStaysTheSameWhenConversionIsDisabled()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $client->get('https://яндекс.рф/images', [
            'idn_conversion' => false,
        ]);

        $request = $mockHandler->getLastRequest();

        self::assertSame('https://яндекс.рф/images', (string) $request->getUri());
        self::assertSame('яндекс.рф', (string) $request->getHeaderLine('Host'));
    }
    */

    /**
     * @requires extension idn
     */
    /*
    public function testExceptionOnInvalidIdn()
    {
        $mockHandler = new MockHandler([$this->factory->response()]);
        $client = new Client(['handler' => $mockHandler]);

        $this->expectException(\GuzzleHttp\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('IDN conversion failed');
        $client->get('https://-яндекс.рф/images', [
            'idn_conversion' => true,
        ]);
    }
    */

    /**
     * @depends testCanUseRelativeUriWithSend
     * @requires extension idn
     */
    /*
    public function testIdnBaseUri()
    {
        $mock = new MockHandler([$this->factory->response()]);
        $client = new Client([
            'handler'  => $mock,
            'base_uri' => 'http://яндекс.рф',
            'idn_conversion' => true,
        ]);
        $config = Helpers::readObjectAttribute($client, 'config');
        self::assertSame('http://яндекс.рф', (string) $config['base_uri']);
        $request = $this->factory->request('GET', '/baz');
        $client->handle($request);
        self::assertSame('http://xn--d1acpjx3f.xn--p1ai/baz', (string) $mock->getLastRequest()->getUri());
        self::assertSame('xn--d1acpjx3f.xn--p1ai', (string) $mock->getLastRequest()->getHeaderLine('Host'));
    }
    */

    /**
     * @requires extension idn
     */
    /*
    public function testIdnWithRedirect()
    {
        $mockHandler = new MockHandler([
            $this->factory->response(302, ['Location' => 'http://www.tést.com/whatever']),
            $this->factory->response()
        ]);
        $handler = HandlerStack::create($mockHandler);
        $requests = [];
        $handler->push(Middleware::history($requests));
        $client = new Client(['handler' => $handler]);

        $client->request('GET', 'https://яндекс.рф/images', [
            RequestOptions::ALLOW_REDIRECTS => [
                'referer' => true,
                'track_redirects' => true
            ],
            'idn_conversion' => true
        ]);

        $request = $mockHandler->getLastRequest();

        self::assertSame('http://www.xn--tst-bma.com/whatever', (string) $request->getUri());
        self::assertSame('www.xn--tst-bma.com', (string) $request->getHeaderLine('Host'));

        $request = $requests[0]['request'];
        self::assertSame('https://xn--d1acpjx3f.xn--p1ai/images', (string) $request->getUri());
        self::assertSame('xn--d1acpjx3f.xn--p1ai', (string) $request->getHeaderLine('Host'));
    }
    */

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
