<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.4
 */

namespace bdk\Debug\Route;

use bdk\Debug;
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
class Slack extends AbstractErrorRoute
{
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

    protected $statsKey = 'slack';

    /**
     * {@inheritDoc}
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->cfg = \array_merge($this->cfg, array(
            'channel' => \getenv('SLACK_CHANNEL'),
            'token' => \getenv('SLACK_TOKEN'),
            'webhookUrl' => \getenv('SLACK_WEBHOOK_URL'),
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
        if (\in_array($this->cfg['use'], ['auto', 'api', 'webhook'], true) === false) {
            throw new RuntimeException($this->debug->i18n->trans('exception.invalid-config-value', array(
                'class' => __CLASS__,
                'expect' => '"auto"|"api"|"webhook"',
                'key' => 'use',
            )));
        }
        if (\in_array($this->cfg['use'], ['auto', 'api'], true) && $this->cfg['token'] && $this->cfg['channel']) {
            // 'api'
            return;
        }
        if (\in_array($this->cfg['use'], ['auto', 'webhook'], true) && $this->cfg['webhookUrl']) {
            // 'webhook'
            return;
        }
        $missing = array(
            'api' => ['token, channel', 'SLACK_TOKEN, SLACK_CHANNEL'],
            'auto' => ['token,channel,webhookUrl', 'SLACK_TOKEN, SLACK_CHANNEL, SLACK_WEBHOOK_URL'],
            'webhook' => ['webhookUrl', 'SLACK_WEBHOOK_URL'],
        );
        $message = $this->debug->i18n->trans('exception.missing-config-env-var', array(
            'class' => __CLASS__,
            'envVar' => $missing[$this->cfg['use']][1],
            'key' => $missing[$this->cfg['use']][0],
        ));
        throw new RuntimeException($message);
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
        $this->slackClient = $use === 'api' || ($use === 'auto' && $this->cfg['token'] && $this->cfg['channel'])
            ? new SlackApi($this->cfg['token'])
            : new SlackWebhook($this->cfg['webhookUrl']);
        if (\is_callable($this->cfg['onClientInit'])) {
            \call_user_func($this->cfg['onClientInit'], $this->slackClient);
        }
        return $this->slackClient;
    }

    /**
     * {@inheritDoc}
     */
    protected function buildMessages(Error $error)
    {
        $messages = array();
        $icon = $error->isFatal()
            ? ':no_entry:'
            : ':warning:';
        $messages[] = (new SlackMessage())
            ->withHeader($icon . ' ' . $error['typeStr'])
            ->withText($icon . ' ' . $error['typeStr'] . "\n" . $error->getMessageText())
            ->withContext([
                $this->getRequestMethodUri(),
            ])
            ->withSection(array(
                'text' => $error->getMessageText(),
                'type' => 'plain_text',
            ))
            ->withContext([
                $error['fileAndLine'],
            ]);
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
            $frames[$i] = \str_replace("**\n", '', $frame);
        }
        return (new SlackMessage())
            ->withHeader('Backtrace')
            ->withSection(\implode("\n", $frames));
    }

    /**
     * {@inheritDoc}
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
