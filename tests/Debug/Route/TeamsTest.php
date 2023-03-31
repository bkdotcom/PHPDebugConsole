<?php

namespace bdk\Test\Debug\Route;

use bdk\CurlHttpMessage\Handler\Mock;
use bdk\HttpMessage\Response;
use bdk\HttpMessage\Stream;
use bdk\Test\Debug\DebugTestFramework;
use Psr\Http\Message\RequestInterface;

/**
 * Test Teams route
 *
 * @covers \bdk\Debug\Route\Teams
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedKeys.IncorrectKeyOrder
 */
class TeamsTest extends DebugTestFramework
{
    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown(): void
    {
        $teamsRoute = $this->debug->getRoute('teams');
        $teamsClientProp = new \ReflectionProperty($teamsRoute, 'teamsClient');
        $teamsClientProp->setAccessible(true);
        $teamsClientProp->setValue($teamsRoute, null);
        $this->debug->removePlugin($teamsRoute);
        parent::tearDown();
    }

    public function testTeams()
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

        $webhookUrl = 'https://qwerty.webhook.office.com/webhookb2/blah/blah/blah';

        $this->debug->addPlugin($this->debug->getRoute('teams'));
        $this->debug->getRoute('teams')->setCfg(array(
            'onClientInit' => static function ($client) use ($mock) {
                $curlClient = $client->getClient();
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'webhookUrl' => $webhookUrl,
        ));

        $this->debug->errorHandler->handleError(E_ERROR, 'everything is awesome', __FILE__, __LINE__);
        $line = __LINE__ - 1;

        self::assertCount(1, $requests);
        self::assertSame('https://qwerty.webhook.office.com/webhookb2/blah/blah/blah', (string) $requests[0]->getUri());
        self::assertSame('application/json; charset=utf-8', $requests[0]->getHeaderLine('Content-Type'));
        $json = \json_decode((string) $requests[0]->getBody(), true);
        $backtrace = $json['attachments'][0]['content']['body'][4]['rows'];
        $json['attachments'][0]['content']['body'][4]['rows'] = \array_slice($backtrace, 0, 1);

        self::assertSame(array(
            'type' => 'message',
            'attachments' => array(
                array(
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => array(
                        'type' => 'AdaptiveCard',
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'body' => array(
                            array(
                                'type' => 'TextBlock',
                                'style' => 'heading',
                                'text' => '🚫 Fatal Error',
                            ),
                            array(
                                'type' => 'TextBlock',
                                'isSubtle' => true,
                                'text' => 'GET http://test.example.com/noun/id/verb',
                            ),
                            array(
                                'type' => 'TextBlock',
                                'text' => 'everything is awesome',
                                'wrap' => true,
                            ),
                            array(
                                'type' => 'FactSet',
                                'facts' => array(
                                    array(
                                        'title' => 'file',
                                        'value' => __FILE__,
                                    ),
                                    array(
                                        'title' => 'line',
                                        'value' => (string) $line,
                                    ),
                                ),
                            ),
                            array(
                                'type' => 'Table',
                                'columns' => array(
                                    array(
                                        'width' => 2,
                                    ),
                                    array(
                                        'horizontalCellContentAlignment' => 'right',
                                        'width' => 0.5,
                                    ),
                                    array(
                                        'width' => 1,
                                    ),
                                ),
                                'firstRowAsHeader' => true,
                                'gridStyle' => 'attention',
                                'rows' => array(
                                    array(
                                        'type' => 'TableRow',
                                        'cells' => array(
                                            array(
                                                'type' => 'TableCell',
                                                'items' => array(
                                                    array(
                                                        'type' => 'TextBlock',
                                                        'text' => 'file',
                                                        'wrap' => true,
                                                    ),
                                                ),
                                            ),
                                            array(
                                                'type' => 'TableCell',
                                                'items' => array(
                                                    array(
                                                        'type' => 'TextBlock',
                                                        'text' => 'line',
                                                        'wrap' => true,
                                                    ),
                                                ),
                                            ),
                                            array(
                                                'type' => 'TableCell',
                                                'items' => array(
                                                    array(
                                                        'type' => 'TextBlock',
                                                        'text' => 'function',
                                                        'wrap' => true,
                                                    ),
                                                ),
                                            ),
                                        ),
                                        'style' => 'attention',
                                    ),
                                ),
                                'showGridLines' => true,
                            ),
                        ),
                        'version' => 1.5,
                    ),
                ),
            ),
        ), $json);
        self::assertGreaterThan(1, \count($backtrace));
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

        $webhookUrl = 'https://qwerty.webhook.office.com/webhookb2/blah/blah/blah';

        $this->debug->addPlugin($this->debug->getRoute('teams'));
        $this->debug->getRoute('teams')->setCfg(array(
            'onClientInit' => static function ($client) use ($mock) {
                $curlClient = $client->getClient();
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'webhookUrl' => $webhookUrl,
        ));

        $this->debug->errorHandler->handleError(E_WARNING, 'yikes', __FILE__, __LINE__);

        self::assertCount(1, $requests);
    }

    public function testNoSend()
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

        $this->debug->addPlugin($this->debug->getRoute('teams'));
        $this->debug->getRoute('teams')->setCfg(array(
            'onClientInit' => static function ($client) use ($mock) {
                $curlClient = $client->getClient();
                $stack = $curlClient->getStack();
                $stack->setHandler($mock);
            },
            'throttleMin' => 0,
            'webhookUrl' => 'https://qwerty.webhook.office.com/webhookb2/blah/blah/blah',
        ));

        $this->debug->errorHandler->handleError(E_NOTICE, 'meh', __FILE__, __LINE__);

        self::assertCount(0, $requests);
    }
}
