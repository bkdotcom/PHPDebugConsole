<?php

namespace bdk\Test\Debug\Route;

use bdk\CurlHttpMessage\Handler\Mock;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Test\Debug\DebugTestFramework;
use Psr\Http\Message\RequestInterface;

/**
 * Test Slack route
 *
 * @covers \bdk\Debug\Route\AbstractErrorRoute
 * @covers \bdk\Debug\Route\Slack
 */
class SlackTest extends DebugTestFramework
{
    use ExpectExceptionTrait;

    protected static $persistErrors = array();

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown(): void
    {
        $slackRoute = $this->debug->getRoute('slack');
        \bdk\Debug\Utility\Reflection::propSet($slackRoute, 'slackClient', null);
        $this->debug->removePlugin($slackRoute);
        $this->debug->errorHandler->eventManager->unsubscribe(ErrorHandler::EVENT_ERROR, array($this, 'onErrorThrow'));
        $this->debug->errorHandler->eventManager->unsubscribe(ErrorHandler::EVENT_ERROR, array($this, 'onError'));
        parent::tearDown();
    }

    public function testAssertCfgInvalidUse()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('bdk\Debug\Route\Slack: Invalid cfg value.  `use` must be "auto"|"api"|"webhook"');
        $slack = $this->debug->getRoute('slack');
        $slack->setCfg('use', 'bogus');
        $error = new Error($this->debug->errorHandler, array(
            'file' => __FILE__,
            'line' => __LINE__,
            'message' => 'Hi error',
            'type' => E_WARNING,
        ));
        $slack->onError($error);
    }

    public function testAssertCfgMissingValues()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('bdk\Debug\Route\Slack: missing config value(s): token,channel,webhookUrl or equivalent env-var(s): SLACK_TOKEN, SLACK_CHANNEL, SLACK_WEBHOOK_URL');
        $slack = $this->debug->getRoute('slack');
        $slack->setCfg('use', 'auto');
        $error = new Error($this->debug->errorHandler, array(
            'file' => __FILE__,
            'line' => __LINE__,
            'message' => 'Hi error',
            'type' => E_WARNING,
        ));
        $slack->onError($error);
    }

    public function testSlackApi()
    {
        parent::$allowError = true;

        $this->debug->setCfg('collect', false);

        $requests = array();
        $microtime = \microtime(true);

        $mock = new Mock([
            static function (RequestInterface $request) use (&$requests, $microtime) {
                $requests[] = $request;
                return (new Response())
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream(\json_encode(array(
                        'success' => true,
                        'ts' => $microtime,
                    ))));
            },
            static function (RequestInterface $request) use (&$requests) {
                $requests[] = $request;
                return (new Response())
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream(\json_encode(array(
                        'success' => true,
                        'ts' => \microtime(true),
                    ))));
            },
        ]);

        $webhookUrl = 'https://hooks.slack.com/services/bogus-webhook-url';

        $this->debug->addPlugin($this->debug->getRoute('slack'));
        $this->debug->getRoute('slack')->setCfg(array(
            'channel' => '#slack-integration-test',
            'onClientInit' => static function ($client) use ($mock) {
                $curlClient = $client->getClient();
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'token' => 'bogus-token',
            'use' => 'auto',
            'webhookUrl' => $webhookUrl,
        ));

        $this->debug->errorHandler->handleError(E_ERROR, 'everything is awesome', __FILE__, __LINE__);
        $line = __LINE__ - 1;

        self::assertCount(2, $requests);
        self::assertSame('https://slack.com/api/chat.postMessage', (string) $requests[0]->getUri());
        self::assertSame('Bearer bogus-token', $requests[0]->getHeaderLine('Authorization'));
        self::assertSame('application/json; charset=utf-8', $requests[0]->getHeaderLine('Content-Type'));
        self::assertSame(array(
            'blocks' => array(
                array(
                    'text' => array(
                        'text' => ':no_entry: Fatal Error',
                        'type' => 'plain_text',
                    ),
                    'type' => 'header',
                ),
                array(
                    'elements' => array(
                        array(
                            'text' => 'GET ' . (string) $this->debug->serverRequest->getUri(),
                            'type' => 'plain_text',
                        ),
                    ),
                    'type' => 'context',
                ),
                array(
                    'text' => array(
                        'text' => 'everything is awesome',
                        'type' => 'plain_text',
                    ),
                    'type' => 'section',
                ),
                array(
                    'elements' => array(
                        array(
                            'text' => __FILE__ . ' (line ' . $line . ')',
                            'type' => 'plain_text',
                        ),
                    ),
                    'type' => 'context',
                ),
            ),
            'channel' => '#slack-integration-test',
            'mrkdwn' => true,
            'reply_broadcast' => false,
            'text' => ":no_entry: Fatal Error\neverything is awesome",
            'unfurl_links' => false,
            'unfurl_media' => true,
        ), \json_decode((string) $requests[0]->getBody(), true));

        self::assertSame('https://slack.com/api/chat.postMessage', (string) $requests[1]->getUri());
        self::assertSame('Bearer bogus-token', $requests[1]->getHeaderLine('Authorization'));
        self::assertSame('application/json; charset=utf-8', $requests[1]->getHeaderLine('Content-Type'));
        $data = \json_decode((string) $requests[1]->getBody(), true);
        $backtrace = $data['blocks'][1]['text']['text'];
        $data['blocks'][1]['text']['text'] = '';
        unset($data['thread_ts']);
        self::assertSame(array(
            'blocks' =>  array(
                array(
                    'text' =>  array(
                        'text' => 'Backtrace',
                        'type' => 'plain_text',
                    ),
                    'type' => 'header',
                ),
                array(
                    'text' =>  array(
                        'text' => '',
                        'type' => 'mrkdwn',
                    ),
                    'type' => 'section',
                ),
            ),
            'channel' => '#slack-integration-test',
            'reply_broadcast' => false,
            // 'thread_ts' => $microtime,
            'unfurl_links' => false,
            'unfurl_media' => true,
        ), $data);
        self::assertStringMatchesFormat(__FILE__ . ':_' . $line . '_' . "\n" . '*' . __CLASS__ . '->' . __FUNCTION__ . '*%A', $backtrace);
    }

    public function testSlackWebhook()
    {
        parent::$allowError = true;

        $this->debug->setCfg('collect', false);

        $requests = array();
        $microtime = \microtime(true);

        $mock = new Mock([
            static function (RequestInterface $request) use (&$requests, $microtime) {
                $requests[] = $request;
                return (new Response())
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream(\json_encode(array(
                        'success' => true,
                        'ts' => $microtime,
                    ))));
            },
        ]);

        $webhookUrl = 'https://hooks.slack.com/services/bogus-webhook-url';

        $this->debug->addPlugin($this->debug->getRoute('slack'));
        $this->debug->getRoute('slack')->setCfg(array(
            'channel' => null,
            'onClientInit' => static function ($client) use ($mock) {
                $curlClient = $client->getClient();
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'token' => null,
            'webhookUrl' => $webhookUrl,
        ));

        $this->debug->errorHandler->handleError(E_WARNING, 'everything is awesome', __FILE__, __LINE__);
        $line = __LINE__ - 1;

        self::assertCount(1, $requests);
        self::assertSame($webhookUrl, (string) $requests[0]->getUri());
        self::assertSame('application/json; charset=utf-8', $requests[0]->getHeaderLine('Content-Type'));
        self::assertSame(array(
            'blocks' => array(
                array(
                    'text' => array(
                        'text' => ':warning: Warning',
                        'type' => 'plain_text',
                    ),
                    'type' => 'header',
                ),
                array(
                    'elements' => array(
                        array(
                            'text' => 'GET ' . (string) $this->debug->serverRequest->getUri(),
                            'type' => 'plain_text',
                        ),
                    ),
                    'type' => 'context',
                ),
                array(
                    'text' => array(
                        'text' => 'everything is awesome',
                        'type' => 'plain_text',
                    ),
                    'type' => 'section',
                ),
                array(
                    'elements' => array(
                        array(
                            'text' => __FILE__ . ' (line ' . $line . ')',
                            'type' => 'plain_text',
                        ),
                    ),
                    'type' => 'context',
                ),
            ),
            'mrkdwn' => true,
            'reply_broadcast' => false,
            'text' => ":warning: Warning\neverything is awesome",
            'unfurl_links' => false,
            'unfurl_media' => true,
        ), \json_decode((string) $requests[0]->getBody(), true));
    }

    /**
     * @dataProvider providerNotSent
     */
    public function testShouldSend($type, $message, $restore, $collect, $sent)
    {
        parent::$allowError = true;
        $this->debug->setCfg('collect', $collect);

        $requests = array();
        $microtime = \microtime(true);

        $mock = new Mock([
            static function (RequestInterface $request) use (&$requests, $microtime) {
                $requests[] = $request;
                return (new Response())
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody(new Stream(\json_encode(array(
                        'success' => true,
                        'ts' => $microtime,
                    ))));
            },
        ]);

        $webhookUrl = 'https://hooks.slack.com/services/bogus-webhook-url';

        $this->debug->addPlugin($this->debug->getRoute('slack'));
        $this->debug->getRoute('slack')->setCfg(array(
            'channel' => null,
            'onClientInit' => static function ($client) use ($mock) {
                $curlClient = $client->getClient();
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'token' => null,
            'webhookUrl' => $webhookUrl,
        ));
        $this->debug->errorHandler->eventManager->subscribe(ErrorHandler::EVENT_ERROR, array($this, 'onErrorThrow'));
        $this->debug->errorHandler->eventManager->subscribe(ErrorHandler::EVENT_ERROR, array($this, 'onError'), -2);

        if ($restore) {
            // debugTestFramework clears errors between tests.. we want to maintain here
            $this->debug->errorHandler->setData('errors', self::$persistErrors);
        }

        try {
            $this->debug->errorHandler->handleError($type, $message, __FILE__, __LINE__);
        } catch (\Exception $e) {
            // meh
        }

        self::$persistErrors = $this->debug->errorHandler->get('errors');

        if (\count($requests) !== ($sent ? 1 : 0)) {
            var_dump(array(
                'sent' => $sent,
                'requests' => \array_map(static function ($request) {
                    return array(
                        'body' => (string) $request->getBody(),
                        'uri' => (string) $request->getUri(),
                    );
                }, $requests),
            ));
        }

        self::assertCount($sent ? 1 : 0, $requests);
    }

    public function onErrorThrow(Error $error)
    {
        if ($error['message'] === 'throw me') {
            $error['throw'] = true;
        }
    }

    public function onError(Error $error)
    {
        if (isset($error['stats']['slack'])) {
            // change last sent to future!
            $error['stats']['slack']['timestamp'] = \time() + 1;
        }
    }

    public static function providerNotSent()
    {
        return array(
            'warning' => array(E_WARNING, 'dog', true, false, true),
            'warning repeat' => array(E_WARNING, 'dog', true, false, false),
            'warning recent' => array(E_WARNING, 'dog', false, false, false),
            'warning in console' => array(E_WARNING, 'microwave in use', true, true, false),
            'notice' => array(E_NOTICE, 'meh', true, false, false),
            'throw' => array(E_WARNING, 'throw me', true, false, false),
        );
    }
}
