<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Route;

use bdk\CurlHttpMessage\Client as CurlHttpMessageClient;
use bdk\Debug;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;

/**
 * Send critical errors to Discord
 *
 * Not so much a route as a plugin (we only listen for errors)
 *
 * @see https://discord.com/developers/docs/resources/webhook#execute-webhook
 */
class Discord extends AbstractRoute
{
    use ErrorThrottleTrait;

    protected $cfg = array(
        'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR,
        'onClientInit' => null,
        'throttleMin' => 60, // 0 = no throttle
        'webhookUrl' => null, // default pulled from DISCORD_WEBHOOK_URL env var
    );

    /** @var CurlHttpMessageClient */
    protected $client;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'webhookUrl' => \getenv('DISCORD_WEBHOOK_URL'),
        ));
        $debug->errorHandler->setCfg('enableStats', true);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            ErrorHandler::EVENT_ERROR => array('onError', -1),
        );
    }

    /**
     * ErrorHandler::EVENT_ERROR event subscriber
     *
     * @param Error $error error/event object
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($this->shouldSend($error, 'discord') === false) {
            return;
        }
        $message = $this->buildMessage($error);
        $this->sendMessage($message);
    }

    /**
     * Send message to Discord
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
        $this->client = new CurlHttpMessageClient();
        if (\is_callable($this->cfg['onClientInit'])) {
            \call_user_func($this->cfg['onClientInit'], $this->client);
        }
        return $this->client;
    }

    /**
     * Build Discord error message(s)
     *
     * @param Error $error Error instance
     *
     * @return array
     */
    private function buildMessage(Error $error)
    {
        $emoji = $error->isFatal()
            ? ':no_entry:'
            : ':warning:';
        return array(
            'content' => $emoji . ' **' . $error['typeStr'] . '**' . "\n"
                . ($this->debug->isCli()
                    ? '$: ' . \implode(' ', $this->debug->getServerParam('argv', array()))
                    : $this->debug->serverRequest->getMethod()
                        . ' ' . $this->debug->redact((string) $this->debug->serverRequest->getUri())
                ) . "\n"
                . $error->getMessageText() . "\n"
                . $error['file'] . ' (line ' . $error['line'] . ')',
        );
    }
}