<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log Response
 * Display in dedicated tab
 *
 * Response will only avail if one of the following
 *   Debug::response obj avail (set via Debug::writeToResponse or setting 'response' service)
 *   output triggered via shutdown
 */
class LogResponse extends AbstractLogReqRes implements SubscriberInterface
{
    /**
     * Constructor
     *
     * @codeCoverageIgnore
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
            Debug::EVENT_OUTPUT => array('logResponse', PHP_INT_MAX),
        );
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP Event instance
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $this->debug = $event->getSubject()->getChannel($this->cfg['channelName'], $this->cfg['channelOpts']);
    }

    /**
     * log response headers & body/content
     *
     * @return void
     */
    public function logResponse()
    {
        if ($this->testLogResponse() === false) {
            return;
        }
        $this->debug->log(
            'Response',
            $this->debug->meta(array(
                'attribs' => array(
                    'style' => $this->headerStyle,
                ),
                'icon' => 'fa fa-arrow-left',
            ))
        );
        $this->logResponseHeaders();
        $this->logResponseContent();
    }

    /**
     * log response body/content
     *
     * @return void
     */
    private function logResponseContent()
    {
        /*
            get the contents of the output buffer we started to collect response
            Note that we don't clear, echo, flush, or end the buffer here
        */
        $content = \ob_get_contents();
        $contentLength = \strlen($content);
        $contentTypeUser = $this->debug->getResponseHeader('Content-Type');
        if ($this->debug->response) {
            $content = $this->debug->response->getBody();
            $contentLength = $content->getSize() ?: \strlen($this->debug->utility->getStreamContents($content));
        }

        $contentType = $this->detectContentType($content, $contentTypeUser);
        $this->assertCorrectContentType($contentType, $contentTypeUser);

        $logContent = $this->testLogResponseContent($contentType, $contentLength);

        if ($logContent === false) {
            return;
        }

        $this->debug->log(
            'response content (%c%s%c)',
            'font-family: monospace;',
            $contentType,
            '',
            $this->debug->prettify($content, $contentType),
            $this->debug->meta('redact')
        );
    }

    /**
     * log response headers
     *
     * @return void
     */
    private function logResponseHeaders()
    {
        $headers = \array_map(static function ($vals) {
            return \implode("\n", $vals);
        }, $this->debug->getResponseHeaders());
        $this->debug->table('response headers', $headers);
    }

    /**
     * Check if we should log response
     *
     * @return bool
     */
    private function testLogResponse()
    {
        $isHttp = \strpos($this->debug->getInterface(), 'http') === 0;
        $logResponse = $this->debug->rootInstance->getCfg('logResponse', Debug::CONFIG_DEBUG);
        return $isHttp && $logResponse;
    }

    /**
     * Should we log the response body?
     *
     * @param string $contentType   Content-Type
     * @param string $contentLength Content-Length (in bytes)
     *
     * @return bool
     */
    private function testLogResponseContent($contentType, $contentLength)
    {
        // only log response for json and xml
        if (\preg_match('#\b(json|xml)\b#', $contentType) !== 1) {
            $this->debug->log(
                'Not logging response body for Content-Type "' . $contentType . '"'
            );
            return false;
        }

        $maxLen = $this->debug->getCfg('logResponseMaxLen', Debug::CONFIG_DEBUG);
        $maxLen = $this->debug->utility->getBytes($maxLen, true);

        if ($maxLen && $contentLength > $maxLen) {
            $this->debug->log('response too large (' . $this->debug->utility->getBytes($contentLength) . ') to output');
            return false;
        }

        return true;
    }
}
