<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Route;

use bdk\Debug;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\Slack\SlackApi;
use bdk\Slack\SlackMessage;
use bdk\Slack\SlackWebhook;
use RuntimeException;

/**
 * Send critical errors to Slack
 *
 * Not so much a route as a plugin (we only listen for errors)
 */
class Slack extends AbstractRoute
{
    use ErrorThrottleTrait;

    protected $cfg = array(
        'channel' => null, // default pulled from SLACK_CHANNEL env var
        'errorMask' => 0,
        'onClientInit' => null,
        'throttleMin' => 60, // 0 = no throttle
        'token' => null, // default pulled from SLACK_TOKEN env var
        'use' => 'auto', // auto|api|webhook
        'webhookUrl' => null, // default pulled from SLACK_WEBHOOK_URL env var
    );

    /** @var SlackApi|SlackWebhook */
    protected $slackClient;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'channel' => \getenv('SLACK_CHANNEL'),
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR,
            'token' => \getenv('SLACK_TOKEN'),
            'webhookUrl' => \getenv('SLACK_WEBHOOK_URL'),
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
        if ($this->shouldSend($error, 'slack') === false) {
            return;
        }
        $messages = $this->buildMessages($error);
        $this->sendMessages($messages);
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
        if (\in_array($this->cfg['use'], array('auto', 'api', 'webhook'), true) === false) {
            throw new RuntimeException(\sprintf(
                '%s: Invalid cfg value.  `use` must be one of "auto", "api", or "webhook"',
                __CLASS__
            ));
        }
        if ($this->cfg['token'] && $this->cfg['channel']) {
            return;
        }
        if ($this->cfg['webhookUrl']) {
            return;
        }
        throw new RuntimeException(\sprintf(
            '%s: missing config value(s).  Must configure %s.  Or define equivalent environment variable(s) (%s)',
            __CLASS__,
            'token+channel or webhookUrl',
            'SLACK_TOKEN, SLACK_CHANNEL, SLACK_WEBHOOK_URL'
        ));
    }

    /**
     * Return SlackApi or SlackWebhook client depending on what config provided
     *
     * @return SlackApi|SlackWebhook
     */
    protected function getClient()
    {
        if ($this->slackClient) {
            return $this->slackClient;
        }
        $this->assertCfg();
        $use = $this->cfg['use'];
        if ($use === 'auto') {
            $use = $this->cfg['token'] && $this->cfg['channel']
                ? 'api'
                : 'webhook';
        }
        $this->slackClient = $use === 'api'
            ? new SlackApi($this->cfg['token'])
            : new SlackWebhook($this->cfg['webhookUrl']);
        if (\is_callable($this->cfg['onClientInit'])) {
            \call_user_func($this->cfg['onClientInit'], $this->slackClient);
        }
        return $this->slackClient;
    }

    /**
     * Build Slack error message(s)
     *
     * @param Error $error Error instance
     *
     * @return SlackMessage[]
     */
    private function buildMessages(Error $error)
    {
        $messages = array();
        $icon = $error->isFatal()
            ? ':no_entry:'
            : ':warning:';
        $messages[] = (new SlackMessage())
            ->withHeader($icon . ' ' . $error['typeStr'])
            ->withText($icon . ' ' . $error['typeStr'] . "\n" . $error->getMessageText())
            ->withContext(array(
                $this->getRequestMethodUri(),
            ))
            ->withSection(array(
                'text' => $error->getMessageText(),
                'type' => 'plain_text',
            ))
            ->withContext(array(
                $error['fileAndLine'],
            ));
        if ($error->isFatal() && $error['backtrace']) {
            $messages[] = $this->buildMessageBacktrace($error);
        }
        return $messages;
    }

    /**
     * Add trace info to message
     *
     * @param Error $error Error instance
     *
     * @return SlackMessage
     */
    private function buildMessageBacktrace(Error $error)
    {
        $frames = array();
        $frameDefault = array('file' => null, 'line' => null, 'function' => null);
        foreach ($error['backtrace'] as $i => $frame) {
            $frame = \array_merge($frameDefault, $frame);
            $frame = \array_intersect_key($frame, $frameDefault);
            $frame = $this->debug->stringUtil->interpolate(
                "*{function}*\n{file}:_{line}_",
                $frame
            );
            $frame = \preg_replace('/\*\*\n/', '', $frame);
            $frames[$i] = $frame;
        }
        return (new SlackMessage())
            ->withHeader('Backtrace')
            ->withSection(\implode("\n", $frames));
    }

    /**
     * Send message(s) to Slack
     *
     * @param SlackMessage[] $messages Slack messages
     *
     * @return void
     */
    protected function sendMessages(array $messages)
    {
        $ts = null;
        foreach ($messages as $message) {
            $message = $message->withValue('thread_ts', $ts);
            $response = $this->sendMessage($message);
            $ts = $ts ?: $response['ts'];
        }
    }

    /**
     * Send message to Slack
     *
     * @param SlackMessage $message Slack messages
     *
     * @return array
     */
    protected function sendMessage(SlackMessage $message)
    {
        $slackClient = $this->getClient();
        if ($slackClient instanceof SlackApi) {
            $message = $message->withChannel($this->cfg['channel']);
            return $slackClient->chatPostMessage($message);
        }
        return $slackClient->post($message);
    }
}
