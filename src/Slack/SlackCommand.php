<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use BadMethodCallException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Handle incoming command from Slack
 *
 * @see https://api.slack.com/interactivity/slash-commands
 * @see https://api.slack.com/authentication/verifying-requests-from-slack
 *
 * @psalm-api
 */
class SlackCommand
{
    const SIGNING_SIGNATURE_VERSION = 'v0';

    /** @var array{signingSecret:string,...<string,mixed>} */
    protected $cfg = array(
        'signingSecret' => '',
    );

    /** @var array<non-empty-string,callable> */
    protected $commandHandlers = array();

    /**
     * Constructor
     *
     * @param array<string,mixed>              $cfg      Configuration
     * @param array<non-empty-string,callable> $handlers command handlers
     *
     * @throws BadMethodCallException
     */
    public function __construct(array $cfg = array(), array $handlers = array())
    {
        $cfg = \array_merge(array(
            'signingSecret' => \getenv('SLACK_SIGNING_SECRET'),
        ), $cfg);
        if (\is_string($cfg['signingSecret']) === false) {
            throw new BadMethodCallException('signingSecret must be provided.');
        }
        $this->cfg = \array_merge($this->cfg, $cfg);
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
        $params = $request->getParsedBody() ?: array();
        /** @psalm-var mixed */
        $command = isset($params['command'])
            ? $params['command']
            : null;
        if (\is_string($command) === false) {
            throw new RuntimeException('Command not provided (or not-string)');
        }
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
     * @param non-empty-string $command Name of command
     * @param callable         $handler Callable that can handle command/request
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
     * @param ServerRequestInterface $request Server request
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

        if ($this->hashEquals($computedSignature, $signature) === false) {
            throw new RuntimeException('Invalid signature');
        }
    }

    /**
     * Polyfill for hash_equals
     *
     * @param string $str1 The string of known length to compare against
     * @param string $str2 The user-supplied string
     *
     * @return bool
     */
    private function hashEquals($str1, $str2)
    {
        if (\function_exists('hash_equals')) {
            return \hash_equals($str1, $str2);
        }
        if (\strlen($str1) !== \strlen($str2)) {
            return false;
        }
        $res = $str1 ^ $str2;
        $ret = 0;
        for ($i = \strlen($res) - 1; $i >= 0; $i--) {
            $ret |= \ord($res[$i]);
        }
        return !$ret;
    }
}
