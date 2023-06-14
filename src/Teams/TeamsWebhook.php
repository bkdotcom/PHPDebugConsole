<?php

namespace bdk\Teams;

use bdk\CurlHttpMessage\Client;
use bdk\Teams\Cards\CardInterface;

/**
 * Send teams message notifications using webhook url
 */
class TeamsWebhook
{
    protected $cfg = array(
        'webhookUrl' => '',
    );

    /** @var Client */
    protected $client;

    /** @var ResponseInterface */
    protected $lastResponse;

    /**
     * Constructor
     *
     * @param string $webhookUrl Slack webhook url
     */
    public function __construct($webhookUrl = null)
    {
        $this->cfg['webhookUrl'] = $webhookUrl ?: \getenv('TEAMS_WEBHOOK_URL');
        $this->client = new Client();
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return ResponseInterface
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * POST message / card to teams via channel webhook
     *
     * @param CardInterface $card Card instance
     *
     * @return array
     */
    public function post(CardInterface $card)
    {
        $this->lastResponse = $this->client->post(
            $this->cfg['webhookUrl'],
            array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            $card
        );
        $body = (string) $this->lastResponse->getBody();
        return \json_decode($body, true);
    }
}
