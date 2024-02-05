<?php

namespace bdk\Teams;

use bdk\CurlHttpMessage\Client;
use bdk\Teams\Cards\CardInterface;
use OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;

/**
 * Send teams message notifications using webhook url
 *
 * @psalm-api
 */
class TeamsWebhook
{
    /** @var array{
     *   webhookUrl:  string,
     * }
     */
    protected $cfg = array(
        'webhookUrl' => '',
    );

    /** @var Client */
    protected $client;

    /** @var ResponseInterface|null */
    protected $lastResponse = null;

    /**
     * Constructor
     *
     * @param string $webhookUrl Slack webhook url
     *
     * @throws OutOfBoundsException
     */
    public function __construct($webhookUrl = null)
    {
        $webhookUrl = $webhookUrl ?: \getenv('TEAMS_WEBHOOK_URL');
        if (\is_string($webhookUrl) === false) {
            throw new OutOfBoundsException('webhookUrl must be defined');
        }
        $this->cfg['webhookUrl'] = $webhookUrl;
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
     * @return ResponseInterface|null
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
     * @return array|false
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
        /** @var array|false */
        return \json_decode($body, true);
    }
}
