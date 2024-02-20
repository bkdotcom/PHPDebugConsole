<?php

namespace bdk\Test\Slack;

use bdk\CurlHttpMessage\Factory;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Slack\SlackApi;
use bdk\Slack\SlackMessage;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @covers \bdk\Slack\SlackApi
 */
class SlackApiTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    private $mockHandler;
    private $factory;

    /**
     * {@inheritDoc}
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->factory = new Factory();
        parent::__construct($name, $data, $dataName);
    }

    public function testConstrutorThrowsException()
    {
        $this->expectException('BadMethodCallException');
        $this->expectExceptionMessage('Slack token must be provided.');
        $slackApi = new SlackApi();
    }

    /**
     * @param string $method
     * @param array  $data
     * @param array  $info
     *
     * @return void
     *
     * @dataProvider methodProvider
     */
    public function testMethods($method, $data, $info)
    {
        $api = $this->buildClient();
        $this->mockHandler->append([
            function (RequestInterface $request) {
                return $this->factory->response(200, '', [
                    'Content-Type' => 'application/json; charset=utf-8',
                ], [
                    'requestHeaders' => $request->getHeaders(),
                    'requestBody' => \json_decode($request->getBody()),
                ]);
            },
        ]);
        if ($method !== 'chatGetPermalink') {
            $data = new SlackMessage($data);
        }
        $responseData = \call_user_func([$api, $method], $data);
        $lastRequest = $this->mockHandler->getLastRequest();
        $lastResponse = $api->getLastResponse();
        self::assertIsArray($responseData);
        self::assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $lastResponse);
        self::assertSame($info['httpMethod'], $lastRequest->getMethod());
        if ($info['httpMethod'] === 'POST') {
            self::assertStringContainsString('application/json', $lastRequest->getHeaderLine('Content-Type'));
        }
    }

    public function testUnknownMethod()
    {
        $api = $this->buildClient();
        $this->expectException('BadMethodCallException');
        $message = new SlackMessage([]);
        $api->chatSomething();
    }

    public static function methodProvider()
    {
        $methods = [
            'chatDelete' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatDeleteScheduledMessage' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatGetPermalink' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'GET',
                ],
            ],
            'chatMeMessage' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatPostEphemeral' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatPostEphemeral' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatPostMessage' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatScheduledMessagesList' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatScheduleMessage' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatUnfurl' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
            'chatUpdate' => [
                [
                    'text' => 'foo',
                ],
                [
                    'httpMethod' => 'POST',
                ],
            ],
        ];
        foreach ($methods as $method => $args) {
            $methods[$method] = \array_merge([$method], $args);
        }
        return $methods;
    }

    private function buildClient()
    {
        $slackApi = new SlackApi('testToken');
        $stack = $slackApi->getClient()->getStack();
        $this->mockHandler = new MockHandler();
        $stack->setHandler($this->mockHandler);
        return $slackApi;
    }
}
