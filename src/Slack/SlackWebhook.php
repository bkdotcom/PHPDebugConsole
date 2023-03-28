<?php

declare(strict_types=1);

namespace bdk\Slack;

use bdk\Slack\AbstractSlack;
use bdk\Slack\SlackMessage;

/**
 * Send slack message notifications using Webhooks url
 *
 * @link https://api.slack.com/incoming-webhooks
 */
class SlackWebhook extends AbstractSlack
{
    protected $cfg = array(
        'webhookUrl' => '',
    );

    /**
     * Constructor
     *
     * @param string $webhookUrl Slack webhook url
     */
    public function __construct($webhookUrl = null)
    {
        $this->cfg['webhookUrl'] = $webhookUrl ?: \getenv('SLACK_WEBHOOK_URL');
        parent::__construct();
    }

    /**
     * POST SlackMessage to slack webhookUrl
     *
     * @param SlackMessage $slackMessage SlackMessage instance
     *
     * @return array
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
        return \json_decode($body, true);
    }
}
