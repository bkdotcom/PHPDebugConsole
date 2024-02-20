<?php

namespace bdk\Slack;

use bdk\CurlHttpMessage\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for SlackApi & SlackWebhook
 */
abstract class AbstractSlack
{
    /** @var Client */
    protected $client;

    /** @var ResponseInterface|null */
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
     * @return ResponseInterface|null
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }
}
