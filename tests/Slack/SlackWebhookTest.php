<?php

namespace bdk\Test\Slack;

use bdk\CurlHttpMessage\Factory;
use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\Slack\SlackMessage;
use bdk\Slack\SlackWebhook;
use bdk\Test\PolyFill\AssertionTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @covers \bdk\Slack\AbstractSlack
 * @covers \bdk\Slack\SlackWebhook
 */
class SlackWebhookTest extends TestCase
{
    use AssertionTrait;

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

    public function testPost()
    {
        $webhook = $this->buildClient();
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
        $message = new SlackMessage([
            'text' => 'Brad was here',
        ]);
        $responseData = $webhook->post($message);
        $lastRequest = $this->mockHandler->getLastRequest();
        $lastResponse = $webhook->getLastResponse();
        self::assertIsArray($responseData);
        self::assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $lastResponse);
        self::assertStringContainsString('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    private function buildClient()
    {
        $slackWebhook = new SlackWebhook('https://hooks.slack.com/services/blahblah/blahblah/blahblahblah');
        $stack = $slackWebhook->getClient()->getStack();
        $this->mockHandler = new MockHandler();
        $stack->setHandler($this->mockHandler);
        return $slackWebhook;
    }
}
