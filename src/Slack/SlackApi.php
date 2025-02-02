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

/**
 * @method array chatDelete()
 * @method array chatDeleteScheduledMessage()
 * @method array chatGetPermalink()
 * @method array chatMeMessage()
 * @method array chatPostEphemeral()
 * @method array chatPostMessage()
 * @method array chatScheduledMessagesList()
 * @method array chatScheduleMessage()
 * @method array chatUnfurl()
 * @method array chatUpdate()
 *
 * @link https://api.slack.com/
 *
 * @psalm-api
 */
class SlackApi extends AbstractSlack
{
    /** @var string */
    private $baseUrl = 'https://slack.com/api/';

    /** @var array<string,string|array{httpMethod:string,uri:string}> */
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

    /** @var string */
    protected $token = '';  // Slack API token  (defaults to SLACK_TOKEN env var)

    /**
     * Constructor
     *
     * @param string $token Slack API token
     *
     * @throws BadMethodCallException
     */
    public function __construct($token = null)
    {
        $token = $token ?: \getenv('SLACK_TOKEN');
        if (\is_string($token) === false) {
            throw new BadMethodCallException('Slack token must be provided.');
        }
        $this->token = $token;
        $endpoints = array();
        /** @psalm-var string $httpMethod */
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
     * @return array|false
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        $info = $this->getMethodInfo($method);
        $url = $this->baseUrl . $info['uri'];
        $headers = array(
            'Authorization' =>  'Bearer ' . $this->token,
        );
        if ($info['httpMethod'] === 'get') {
            if (\is_array($args[0])) {
                $query = \http_build_query($args[0]);
                $url = $url . '?' . $query;
            }
            $this->lastResponse = $this->client->get($url, $headers);
        } elseif ($info['httpMethod'] === 'post') {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
            $this->lastResponse = $this->client->post($url, $headers, $args[0]);
        }
        if ($this->lastResponse) {
            $responseBody = (string) $this->lastResponse->getBody();
            /** @psalm-var array */
            return \json_decode($responseBody, true);
        }
        return false;
    }

    /**
     * Get slack method info
     *
     * @param string $method Slack method
     *
     * @return array{httpMethod:string,uri:string}
     *
     * @throws BadMethodCallException
     */
    private function getMethodInfo($method)
    {
        $key = \strtolower($method);
        if (isset($this->endpoints[$key]) === false || \is_array($this->endpoints[$key]) === false) {
            throw new BadMethodCallException('Unknown slack method - ' . $method);
        }
        return $this->endpoints[$key];
    }
}
