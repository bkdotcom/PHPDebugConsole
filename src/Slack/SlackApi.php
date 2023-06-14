<?php

namespace bdk\Slack;

use BadMethodCallException;
use bdk\Slack\AbstractSlack;

/**
 * @method array chatDelete
 * @method array chatDeleteScheduledMessage
 * @method array chatGetPermalink
 * @method array chatMeMessage
 * @method array chatPostEphemeral
 * @method array chatPostMessage
 * @method array chatScheduledMessagesList
 * @method array chatScheduleMessage
 * @method array chatUnfurl
 * @method array chatUpdate
 *
 * @link https://api.slack.com/
 */
class SlackApi extends AbstractSlack
{
    protected $cfg = array(
        'token' => '',  // Slack API token  (defaults to SLACK_TOKEN env var)
    );

    private $baseUrl = 'https://slack.com/api/';

    private $endpoints = array(
        'chat.delete' => 'POST',
        'chat.deleteScheduledMessage' => 'POST',
        'chat.getPermalink' => 'GET',
        'chat.meMessage' => 'POST',
        'chat.postEphemeral' => 'POST',
        'chat.postMessage' => 'POST',
        'chat.scheduledMessages.list' => 'POST',
        'chat.scheduleMessage' => 'POST',
        'chat.unfurl' => 'POST',
        'chat.update' => 'POST',
    );

    /**
     * Constructor
     *
     * @param string $token Slack API token
     */
    public function __construct($token = null)
    {
        $this->cfg['token'] = $token ?: \getenv('SLACK_TOKEN');

        $endpoints = array();
        foreach ($this->endpoints as $method => $httpMethod) {
            $key = \strtolower(\str_replace('.', '', $method));
            $endpoints[$key] = array(
                'httpMethod' => \strtolower($httpMethod),
                'uri' => $method,
            );
        }
        $this->endpoints = $endpoints;

        parent::__construct();
    }

    /**
     * Call magic method
     *
     * @param string $method Method name
     * @param array  $args   Method arguments
     *
     * @return array
     *
     * @throws BadMethodCallException
     */
    public function __call($method, $args)
    {
        $key = \strtolower($method);
        if (isset($this->endpoints[$key]) === false) {
            throw new BadMethodCallException('Unknown slack method - ' . $method);
        }
        $info = $this->endpoints[$key];
        $url = $this->baseUrl . $info['uri'];
        $headers = array(
            'Authorization' =>  'Bearer ' . $this->cfg['token'],
        );
        if ($info['httpMethod'] === 'get') {
            $query = \http_build_query($args[0]);
            $url = $url . '?' . $query;
            $this->lastResponse = $this->client->get($url, $headers);
        } elseif ($info['httpMethod'] === 'post') {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
            $this->lastResponse = $this->client->post($url, $headers, $args[0]);
        }
        $responseBody = (string) $this->lastResponse->getBody();
        return \json_decode($responseBody, true);
    }
}
