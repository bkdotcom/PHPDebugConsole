<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Route;

use bdk\CurlHttpMessage\Client as CurlHttpMessageClient;
use bdk\Debug;
use bdk\ErrorHandler\Error;
use RuntimeException;

/**
 * Send critical errors to Discord
 *
 * Not so much a route as a plugin (we only listen for errors)
 *
 * @see https://discord.com/developers/docs/resources/webhook#execute-webhook
 */
class Discord extends AbstractErrorRoute
{
    protected $cfg = array(
        'errorMask' => 0,
        'onClientInit' => null,
        'throttleMin' => 60, // 0 = no throttle
        'webhookUrl' => null, // default pulled from DISCORD_WEBHOOK_URL env var
    );

    /** @var CurlHttpMessageClient */
    protected $client;

    protected $statsKey = 'discord';

    /**
     * {@inheritDoc}
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'webhookUrl' => \getenv('DISCORD_WEBHOOK_URL'),
        ));
    }

    /**
     * Validate configuration values
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function assertCfg()
    {
        if ($this->cfg['webhookUrl']) {
            return;
        }
        throw new RuntimeException(\sprintf(
            '%s: missing config value: %s.  Also tried env-var: %s',
            __CLASS__,
            'webhookUrl',
            'DISCORD_WEBHOOK_URL'
        ));
    }

    /**
     * {@inheritDoc}
     */
    protected function buildMessages(Error $error)
    {
        $emoji = $error->isFatal()
            ? ':no_entry:'
            : ':warning:';
        $message = array(
            'content' => $emoji . ' **' . $error['typeStr'] . '**' . "\n"
                . $this->getRequestMethodUri() . "\n"
                . $error->getMessageText() . "\n"
                . $error['fileAndLine'],
        );
        return [$message];
    }

    /**
     * Return CurlHttpMessage
     *
     * @return CurlHttpMessageClient
     */
    protected function getClient()
    {
        if ($this->client) {
            return $this->client;
        }
        $this->assertCfg();
        $this->client = new CurlHttpMessageClient();
        if (\is_callable($this->cfg['onClientInit'])) {
            \call_user_func($this->cfg['onClientInit'], $this->client);
        }
        return $this->client;
    }

    /**
     * {@inheritDoc}
     */
    protected function sendMessages(array $messages)
    {
        foreach ($messages as $message) {
            $this->sendMessage($message);
        }
    }

    /**
     * Send message
     *
     * @param array $message Discord message
     *
     * @return void
     */
    protected function sendMessage(array $message)
    {
        $client = $this->getClient();
        $client->post(
            $this->cfg['webhookUrl'],
            array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            $message
        );
    }
}
