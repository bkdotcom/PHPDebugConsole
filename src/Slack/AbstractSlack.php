<?php

namespace bdk\Slack;

use bdk\CurlHttpMessage\Client;

/**
 * Base class for SlackApi & SlackWebhook
 */
abstract class AbstractSlack
{
    /** @var Client */
    protected $client;

    /** @var ResponseInterface */
    protected $lastResponse;

    /**
     * Constructor
     */
    public function __construct()
    {
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
}
