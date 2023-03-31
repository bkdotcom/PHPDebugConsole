<?php

namespace bdk\Test\Debug\Route;

use bdk\CurlHttpMessage\Handler\Mock;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\Test\Debug\DebugTestFramework;
use Psr\Http\Message\RequestInterface;

/**
 * Test Discord route
 *
 * @covers \bdk\Debug\Route\Discord
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
 */
class DiscordTest extends DebugTestFramework
{
    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown(): void
    {
        $discordRoute = $this->debug->getRoute('discord');
        $clientProp = new \ReflectionProperty($discordRoute, 'client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($discordRoute, null);
        $this->debug->removePlugin($discordRoute);
        parent::tearDown();
    }

    public function testDiscord()
    {
        parent::$allowError = true;

        $this->debug->setCfg('collect', false);

        $requests = array();

        $mock = new Mock([
            static function (RequestInterface $request) use (&$requests) {
                $requests[] = $request;
                return (new Response())
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream(\json_encode(array(
                        'success' => true,
                    ))));
            },
        ]);

        $webhookUrl = 'https://discord.com/api/webhooks/some_id/some_token';

        $this->debug->addPlugin($this->debug->getRoute('discord'));
        $this->debug->getRoute('discord')->setCfg(array(
            'onClientInit' => static function ($curlClient) use ($mock) {
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'webhookUrl' => $webhookUrl,
        ));

        $this->debug->errorHandler->handleError(E_ERROR, 'everything is awesome', __FILE__, __LINE__);
        $line = __LINE__ - 1;

        self::assertCount(1, $requests);
        self::assertSame($webhookUrl, (string) $requests[0]->getUri());
        self::assertSame('application/json; charset=utf-8', $requests[0]->getHeaderLine('Content-Type'));
        $json = \json_decode((string) $requests[0]->getBody(), true);
        self::assertSame(array(
            'content' => ':no_entry: **Fatal Error**' . "\n"
                . 'GET http://test.example.com/noun/id/verb' ."\n"
                . 'everything is awesome' . "\n"
                . __FILE__ . ' (line ' . $line . ')',
        ), $json);
    }

    public function testCli()
    {
        parent::$allowError = true;

        $this->debug->setCfg(array(
            'serviceProvider' => array(
                'serverRequest' => new \bdk\HttpMessage\ServerRequest('GET', '', array(
                    'argv' => array('foo','bar'),
                )),
            ),
            // 'route' => 'stream',
            // 'stream' => 'php://temp',
            'collect' => false,
        ));

        $requests = array();

        $mock = new Mock([
            static function (RequestInterface $request) use (&$requests) {
                $requests[] = $request;
                return (new Response())
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream(\json_encode(array(
                        'success' => true,
                    ))));
            },
        ]);

        $webhookUrl = 'https://discord.com/api/webhooks/some_id/some_token';

        $this->debug->addPlugin($this->debug->getRoute('discord'));
        $this->debug->getRoute('discord')->setCfg(array(
            'onClientInit' => static function ($curlClient) use ($mock) {
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'webhookUrl' => $webhookUrl,
        ));

        $this->debug->errorHandler->handleError(E_WARNING, 'yikes', __FILE__, __LINE__);

        self::assertCount(1, $requests);
    }
}
