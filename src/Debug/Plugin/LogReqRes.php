<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug\Plugin;

use bdk\Debug\Abstraction\Abstraction;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use Exception;

/**
 * Log Request/Response
 * Display in dedicated tab
 */
class LogReqRes implements SubscriberInterface
{

    private $debug;

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.pluginInit' => 'onPluginInit',
            'php.shutdown' => array('logResponse', PHP_INT_MAX),
        );
    }

    /**
     * debug.pluginInit subscriber
     *
     * @param Event $event debug.bootstrap event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $this->debug = $event->getSubject()->getChannel('Request / Response', array('nested' => false));
        $this->logRequest();    // headers, cookies, post
    }

    /**
     * log response headers & body/content
     *
     * @return void
     */
    public function logResponse()
    {
        if (!$this->debug->getCfg('logResponse')) {
            return;
        }
        $this->debug->log('response headers', $this->debug->getResponseHeaders(true));
        $contentType = $this->debug->getResponseHeader('Content-Type', ', ');
        if (!\preg_match('#\b(json|xml)\b#', $contentType)) {
            // we're not interested in logging response
            if (\ob_get_level()) {
                \ob_end_flush();
            }
            return;
        }
        $this->logResponseContent();
    }

    /**
     * Get request body contents without affecting stream pointer
     *
     * @return string
     */
    private function getRequestBodyContents()
    {
        try {
            $stream = $this->debug->request->getBody();
            $pos = $stream->tell();
            $body = (string) $stream; // __toString() is like getContents(), but without throwing exceptions
            $stream->seek($pos);
            return $body;
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Log $_POST or php://input & $_FILES
     *
     * @return void
     */
    private function logPost()
    {
        $request = $this->debug->request;
        $method = $request->getMethod();
        $contentType = $request->getHeaderLine('Content-Type');
        if ($method === 'GET') {
            return;
        }
        $havePostVals = false;
        if ($method === 'POST') {
            $isCorrectContentType = $this->testPostContentType($contentType);
            $post = $request->getParsedBody();
            if (!$isCorrectContentType) {
                $this->debug->warn(
                    'It appears ' . $contentType . ' was posted with the wrong Content-Type' . "\n"
                        . 'Pay no attention to $_POST and instead use php://input',
                    $this->debug->meta(array(
                        'detectFiles' => false,
                        'file' => null,
                        'line' => null,
                    ))
                );
            } elseif ($post) {
                $havePostVals = true;
                $this->debug->log('$_POST', $post, $this->debug->meta('redact'));
            }
        }
        if (!$havePostVals) {
            // Not POST, empty $_POST, or not application/x-www-form-urlencoded or multipart/form-data
            $input = $this->getRequestBodyContents();
            if ($input) {
                $this->logRequestBody($contentType);
            } elseif (!$request->getUploadedFiles()) {
                $this->debug->warn(
                    $method . ' request with no body',
                    $this->debug->meta(array(
                        'detectFiles' => false,
                        'file' => null,
                        'line' => null,
                    ))
                );
            }
        }
        if ($request->getUploadedFiles()) {
            $this->debug->log('$_FILES', $request->getUploadedFiles());
        }
    }

    /**
     * Log request headers, Cookie, Post, & Files data
     *
     * @return void
     */
    private function logRequest()
    {
        $logInfo = $this->debug->getCfg('logRequestInfo');
        $this->logRequestHeaders();
        if ($logInfo['cookies']) {
            $cookieVals = $this->debug->request->getCookieParams();
            \ksort($cookieVals, SORT_NATURAL);
            $this->debug->table('$_COOKIE', $cookieVals, $this->debug->meta('redact'));
        }
        // don't expect a request body for these methods
        $noBodyMethods = array('CONNECT','GET','HEAD','OPTIONS','TRACE');
        $expectBody = !\in_array($this->debug->request->getMethod(), $noBodyMethods);
        if ($logInfo['post'] && $expectBody) {
            $this->logPost();
        }
    }

    /**
     * log php://input
     *
     * @param string $contentType Content-Type
     *
     * @return void
     */
    private function logRequestBody($contentType = null)
    {
        $event = $this->debug->rootInstance->eventManager->publish('debug.prettify', $this->debug, array(
            'value' => $this->getRequestBodyContents(),
            'contentType' => $contentType,
        ));
        $input = $event['value'];
        $this->debug->log(
            'php://input %c%s',
            'font-style: italic; opacity: 0.8;',
            $input instanceof Abstraction
                ? '(prettified)'
                : '',
            $input,
            $this->debug->meta('redact')
        );
    }

    /**
     * Log Request Headers
     *
     * @return void
     */
    private function logRequestHeaders()
    {
        if ($this->debug->getCfg('logRequestInfo.headers') === false) {
            return;
        }
        $headers = \array_map(function ($vals) {
            return \join(', ', $vals);
        }, $this->debug->request->getHeaders());
        if ($headers) {
            \ksort($headers, SORT_NATURAL);
            $this->debug->table('request headers', $headers, $this->debug->meta('redact'));
        }
    }

    /**
     * log response body/content
     *
     * @return void
     */
    private function logResponseContent()
    {
        $maxLen = $this->debug->getCfg('logResponseMaxLen');
        $maxLen = $this->debug->utilities->getBytes($maxLen, true);
        // get the contents of the output buffer we started to collect response
        $response = \ob_get_clean();
        echo $response;
        $contentLength = \strlen($response);
        if ($this->debug->response) {
            $response = '';
            $contentLength = 0;
            try {
                $stream = $this->debug->response->getBody();
                $contentLength = $stream->getSize(); // likely returns null (unknown)
                if ($contentLength <= $maxLen) {
                    $response = $this->debug->utilities->getStreamContents($stream);
                    $contentLength = \strlen($response);
                }
            } catch (Exception $e) {
                $this->debug->warn('Exception', array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ));
                return;
            }
        }
        if (!$maxLen || $contentLength < $maxLen) {
            $event = $this->debug->rootInstance->eventManager->publish('debug.prettify', $this->debug, array(
                'value' => $response,
                'contentType' => $contentType,
            ));
            $this->debug->log(
                'response content (%c%s) %c%s',
                'font-family: monospace;',
                $contentType,
                'font-style: italic; opacity: 0.8;',
                $event['value'] instanceof Abstraction
                    ? '(prettified)'
                    : '',
                $event['value'],
                $this->debug->meta('redact')
            );
        } else {
            $this->debug->log('response too large to output (' . $contentLength . ')');
        }
    }

    /**
     * Test if $_POST is properly populated or not
     *
     * If JSON or XML is posted using the default application/x-www-form-urlencoded Content-Type
     * $_POST will be improperly populated
     *
     * @param string $contentType Will get populated with detected content type
     *
     * @return bool
     */
    private function testPostContentType(&$contentType)
    {
        $contentTypeRaw = $this->debug->request->getHeaderLine('Content-Type');
        if ($contentTypeRaw) {
            // remove encoding if pressent
            \preg_match('#^([^;]+)#', $contentTypeRaw, $matches);
            $contentType = $matches[1];
        }
        if (!$this->debug->request->getParsedBody()) {
            // nothing in $_POST means it can't be wrong
            return true;
        }
        /*
        $_POST is populated...
            which means Content-Type was application/x-www-form-urlencoded or multipart/form-data
            if we detect php://input is json or XML, then must have been
            posted with wrong Content-Type
        */
        $input = $this->getRequestBodyContents();
        $json = \json_decode($input, true);
        $isJson = \json_last_error() === JSON_ERROR_NONE && \is_array($json);
        if ($isJson) {
            $contentType = 'application/json';
            return false;
        }
        if ($this->debug->utilities->isXml($input)) {
            $contentType = 'text/xml';
            return false;
        }
        return true;
    }
}
