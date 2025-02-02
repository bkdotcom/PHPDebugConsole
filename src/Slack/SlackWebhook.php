<?php

/**
 * @package   bdk\slack
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Slack;

use BadMethodCallException;
use bdk\Slack\AbstractSlack;
use bdk\Slack\SlackMessage;

/**
 * Send slack message notifications using Webhooks url
 *
 * @link https://api.slack.com/incoming-webhooks
 *
 * @psalm-api
 */
class SlackWebhook extends AbstractSlack
{
    /**
     * @var array{
     *   webhookUrl:  string,
     * }
     */
    protected $cfg = array(
        'webhookUrl' => '',
    );

    /**
     * Constructor
     *
     * @param string|null $webhookUrl Slack webhook url
     *
     * @throws BadMethodCallException
     */
    public function __construct($webhookUrl = null)
    {
        $webhookUrl = $webhookUrl ?: \getenv('SLACK_WEBHOOK_URL');
        if (\is_string($webhookUrl) === false) {
            throw new BadMethodCallException('webhookUrl must be provided.');
        }
        $this->cfg['webhookUrl'] = $webhookUrl;
        parent::__construct();
    }

    /**
     * POST SlackMessage to slack webhookUrl
     *
     * @param SlackMessage $slackMessage SlackMessage instance
     *
     * @return array|false
     */
    public function post(SlackMessage $slackMessage)
    {
        $this->lastResponse = $this->client->post(
            $this->cfg['webhookUrl'],
            array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            $slackMessage
        );
        $body = (string) $this->lastResponse->getBody();
        /** @psalm-var array|false */
        return \json_decode($body, true);
    }
}
