<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.1
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\HttpMessage\Utility\Stream as StreamUtility;
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
            Debug::EVENT_CONFIG => ['onConfig', -1],
            Debug::EVENT_OUTPUT => ['logResponse', PHP_INT_MAX],
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
     * Debug::EVENT_CONFIG event listener (low priority)
     *
     * Begin output buffering if we're logging the response
     *
     * @param Event $event Debug::EVENT_CONFIG Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event['debug'];
        if (!$cfg || !$event['isTarget']) {
            return;
        }
        $debug = $event->getSubject();
        $valsTest = array(
            'collect' => true,
            'logResponse' => true,
        );
        if (
            \array_intersect_assoc($cfg, $valsTest)
            && \array_intersect_assoc($valsTest, $debug->getCfg(null, Debug::CONFIG_DEBUG)) === $valsTest
        ) {
            // collect and/or logResponse being updated and both values are now true
            $debug->obStart();
        }
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
                'icon' => ':receive:',
            ))
        );
        $this->logResponseHeaders();
        $this->logResponseContent();
    }

    /**
     * Get response content, & type
     *
     * @return array{content:string,contentLength:int,contentType:string}
     */
    private function getResponseInfo()
    {
        /*
            get the contents of the output buffer we started
            Note that we don't clear, echo, flush, or end the buffer here
        */
        $content = \ob_get_contents();
        $contentLength = \strlen($content);
        if ($this->debug->response) {
            $content = $this->debug->response->getBody();
            $contentLength = $content->getSize() ?: \strlen(StreamUtility::getContents($content));
        }
        return array(
            'content' => $content,
            'contentLength' => $contentLength,
            'contentType' => $this->debug->getResponseHeader('Content-Type'),
        );
    }

    /**
     * log response body/content
     *
     * @return void
     */
    private function logResponseContent()
    {
        $responseInfo = $this->getResponseInfo();
        $contentType = $this->detectContentType($responseInfo['content'], $responseInfo['contentType']);
        $this->assertCorrectContentType($contentType, $responseInfo['contentType']);

        $logContent = $this->testLogResponseContent($contentType, $responseInfo['contentLength']);

        if (\headers_sent($file, $line)) {
            $this->debug->log('Output started at ' . $file . '::' . $line, $this->debug->meta(array(
                'detectFiles' => true,
                'file' => $file,
                'line' => $line,
            )));
        }

        if ($logContent === false) {
            return;
        }

        $this->debug->log(
            'response content (%c%s%c)',
            'font-family: monospace;',
            $contentType,
            '',
            $this->debug->prettify($responseInfo['content'], $contentType),
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
            $this->debug->log('Not logging response body for Content-Type "' . $contentType . '"');
            !$contentLength || $this->debug->log('Content-Length', $contentLength);
            return false;
        }

        if ($contentLength === 0) {
            $this->debug->log('Empty response body.  We may have been unable to capture output.');
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
