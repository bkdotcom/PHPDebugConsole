<?php

namespace bdk\Test\Teams;

use bdk\CurlHttpMessage\Handler\Mock as MockHandler;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\Teams\Cards\MessageCard;
use bdk\Teams\TeamsWebhook;
use bdk\Test\PolyFill\AssertionTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @covers \bdk\Teams\TeamsWebhook
 */
class TeamsWebhookTest extends TestCase
{
    use AssertionTrait;

    private $mockHandler;

    /**
     * {@inheritDoc}
     */
    /*
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        // $this->factory = new Factory();
        parent::__construct($name, $data, $dataName);
    }
    */

    public function testPost()
    {
        $webhook = $this->buildClient();
        $this->mockHandler->append([
            static function (RequestInterface $request) {
                $response = new Response();
                $stream = new Stream(\json_encode(array(
                    'requestHeaders' => $request->getHeaders(),
                    'requestBody' => \json_decode($request->getBody()),
                )));
                return $response
                    ->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withBody($stream);
            },
        ]);
        $message = new MessageCard('Title', 'Message');
        $responseData = $webhook->post($message);
        $lastRequest = $this->mockHandler->getLastRequest();
        $lastResponse = $webhook->getLastResponse();
        self::assertIsArray($responseData);
        self::assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $lastResponse);
        self::assertStringContainsString('application/json', $lastRequest->getHeaderLine('Content-Type'));
    }

    private function buildClient()
    {
        $teamsWebhook = new TeamsWebhook('https://abcde.webhook.office.com/webhookb2/blahblahlbah/IncomingWebhook/blah/blah');
        $stack = $teamsWebhook->getClient()->getStack();
        $this->mockHandler = new MockHandler();
        $stack->setHandler($this->mockHandler);
        return $teamsWebhook;
    }
}
