<?php

declare(strict_types=1);

namespace bdk\Slack;

use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Handle incoming command from Slack
 *
 * @see https://api.slack.com/interactivity/slash-commands
 * @see https://api.slack.com/authentication/verifying-requests-from-slack
 */
class SlackCommand
{
    const SIGNING_SIGNATURE_VERSION = 'v0';

    /** @var array */
    protected $cfg = array(
        'signingSecret' => null,
    );

    protected $commandHandlers = array();

    /**
     * Constructor
     *
     * @param array<string, mixed>    $cfg      Configuration
     * @param array<string, callable> $handlers command handlers
     */
    public function __construct(array $cfg = array(), array $handlers = array())
    {
        $this->cfg = \array_merge(array(
            'signingSecret' => \getenv('SLACK_SIGNING_SECRET'),
        ), $cfg);
        foreach ($handlers as $name => $handler) {
            $this->registerHandler($name, $handler);
        }
    }

    /**
     * Handle slack command server request
     *
     * @param ServerRequestInterface $request Server request
     *
     * @return mixed
     *
     * @throws RuntimeException
     */
    public function handle(ServerRequestInterface $request)
    {
        $this->assertSlackRequest($request);
        $command = $request->getParsedBody()['command'];
        if (isset($this->commandHandlers[$command])) {
            $function = $this->commandHandlers[$command];
            return $function($request);
        }
        if (isset($this->commandHandlers['default'])) {
            $function = $this->commandHandlers['default'];
            return $function($request);
        }
        throw new RuntimeException('Unable to handle command: ' . $command);
    }

    /**
     * Register to handle a specific command
     * May also register a 'default' handler
     *
     * @param string   $command Name of command
     * @param callable $handler Callable that can handle command/request
     *
     * @return void
     */
    public function registerHandler($command, callable $handler)
    {
        $this->commandHandlers[$command] = $handler;
    }

    /**
     * Assert that the request is a valid signed request from Slack
     *
     * @param ServerRequestInterface $request [description]
     *
     * @return void
     *
     * @throws RuntimeException
     */
    protected function assertSlackRequest(ServerRequestInterface $request)
    {
        $signature = $request->getHeaderLine('X-Slack-Signature');
        $timestamp = $request->getHeaderLine('X-Slack-Request-Timestamp');
        if (!$signature) {
            throw new RuntimeException('Unsigned request');
        }
        if (\abs(\time() - (int) $timestamp) > 60) {
            throw new RuntimeException('Request timestamp out of bounds');
        }
        $version = \array_replace(array(null, null), \explode('=', $signature, 2))[0];
        if ($version !== self::SIGNING_SIGNATURE_VERSION) {
            throw new RuntimeException('Unrecognized signature version');
        }

        $baseString = \implode(':', array(
            $version,
            $timestamp,
            (string) $request->getBody(),
        ));
        $computedSignature = $version . '=' . \hash_hmac('sha256', $baseString, $this->cfg['signingSecret']);

        if (\hash_equals($computedSignature, $signature) === false) {
            throw new RuntimeException('Invalid signature');
        }
    }
}
