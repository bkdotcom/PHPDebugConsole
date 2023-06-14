<?php

namespace bdk\Test\Slack;

use bdk\HttpMessage\ServerRequest;
use bdk\HttpMessage\Stream;
use bdk\Slack\SlackCommand;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Slack\SlackCommand
 */
class SlackCommandTest extends TestCase
{
    use ExpectExceptionTrait;

    const SIGNING_SECRET = 'twinkieweinersandwich';

    public function testHandled()
    {
        $handled = false;
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ], [
            'myCommand' => static function (ServerRequest $request) use (&$handled) {
                $handled = true;
                return $request->getParsedBody()['command'];
            },
        ]);
        $request = $this->buildRequest();
        $return = $slackCommand->handle($request);
        self::assertTrue($handled);
        self::assertSame('myCommand', $return);
    }

    public function testRegisterHandler()
    {
        $handled = false;
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ]);
        $slackCommand->registerHandler('myCommand', static function (ServerRequest $request) use (&$handled) {
            $handled = true;
            return $request->getParsedBody()['command'];
        });
        $request = $this->buildRequest();
        $return = $slackCommand->handle($request);
        self::assertTrue($handled);
        self::assertSame('myCommand', $return);
    }

    public function testHandledDefault()
    {
        $handled = false;
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ], [
            'default' => static function (ServerRequest $request) use (&$handled) {
                $handled = true;
                return $request->getParsedBody()['command'];
            },
        ]);
        $request = $this->buildRequest();
        $return = $slackCommand->handle($request);
        self::assertTrue($handled);
        self::assertSame('myCommand', $return);
    }

    public function testNotHandledException()
    {
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ]);
        $request = $this->buildRequest();
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Unable to handle command: myCommand');
        $slackCommand->handle($request);
    }

    public function testHandleUnsigned()
    {
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ]);
        $request = $this->buildRequest()->withoutHeader('X-Slack-Signature');
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Unsigned request');
        $slackCommand->handle($request);
    }

    public function testHandleTimestampOutOfBounds()
    {
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ]);
        $request = $this->buildRequest()->withHeader('X-Slack-Request-Timestamp', time() + 61);
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Request timestamp out of bounds');
        $slackCommand->handle($request);
    }

    public function testHandleUnrecognizedVersion()
    {
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ]);
        $request = $this->buildRequest();
        $signature = $request->getHeaderLine('X-Slack-Signature');
        $signatureParts = \explode('=', $signature, 2);
        $signature = 'v1=' . $signatureParts[1];
        $request = $request->withHeader('X-Slack-Signature', $signature);
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Unrecognized signature version');
        $slackCommand->handle($request);
    }

    public function testHandleInvalidSignature()
    {
        $slackCommand = new SlackCommand([
            'signingSecret' => self::SIGNING_SECRET,
        ]);
        $request = $this->buildRequest()
            ->withHeader('X-Slack-Signature', 'v0=invalid');
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Invalid signature');
        $slackCommand->handle($request);
    }

    private function buildRequest()
    {
        $requestParams = array(
            'api_app_id' => '1234',
            'channel_id' => '1234',
            'command' => 'myCommand',
            'enterprise_id' => '1234',
            'response_url' => 'http://127.0.0.1:8080/echo',
            'team_id' => '1234',
            'text' => 'this is a test',
            'trigger_id' => '1234',
        );
        $secret = self::SIGNING_SECRET;
        $timestamp = \time();

        $body = \http_build_query($requestParams);

        $signature = 'v0=' . \hash_hmac('sha256', \implode(':', array(
            'v0',
            $timestamp,
            $body,
        )), $secret);

        return (new ServerRequest('POST'))
            ->withBody(new Stream($body))
            ->withParsedBody($requestParams)
            ->withHeader('X-Slack-Signature', $signature)
            ->withHeader('X-Slack-Request-Timestamp', $timestamp);
    }
}
