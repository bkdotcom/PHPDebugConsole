<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
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
        $debug = $event->getSubject();
        $this->debug = $debug->getChannel('Request / Response', array('nested' => false));

        $collectWas = $debug->setCfg('collect', true);
        $debug->groupSummary();

        $this->logRequest();    // headers, cookies, post

        $debug->groupEnd();
        $debug->setCfg('collect', $collectWas);
    }

    /**
     * Log request headers, Cookie, Post, & Files data
     *
     * @return void
     */
    public function logRequest()
    {
        if (\strpos($this->debug->utility->getInterface(), 'http') !== 0) {
            return;
        }
        $this->logRequestHeaders();
        if ($this->debug->getCfg('logRequestInfo.cookies', Debug::CONFIG_DEBUG)) {
            $cookieVals = $this->debug->request->getCookieParams();
            \ksort($cookieVals, SORT_NATURAL);
            if ($cookieVals) {
                $this->debug->table('$_COOKIE', $cookieVals, $this->debug->meta('redact'));
            }
        }
        $this->logPost();
        $this->logFiles();
    }

    /**
     * log response headers & body/content
     *
     * @return void
     */
    public function logResponse()
    {
        if (!$this->debug->getCfg('logResponse', Debug::CONFIG_DEBUG)) {
            return;
        }
        if (\strpos($this->debug->utility->getInterface(), 'http') !== 0) {
            return;
        }
        $this->debug->log('response headers', $this->debug->getResponseHeaders(true));
        $contentType = \implode(', ', $this->debug->getResponseHeader('Content-Type'));
        if (!\preg_match('#\b(json|xml)\b#', $contentType)) {
            // we're not interested in logging response
            if (\ob_get_level()) {
                \ob_end_flush();
            }
            return;
        }
        $this->logResponseContent($contentType);
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
     * Log $_FILES
     *
     * If using ServerRequestInterface, will log result of `getUploadedFiles()`
     *
     * @return void
     */
    private function logFiles()
    {
        if (!$this->debug->getCfg('logRequestInfo.files', Debug::CONFIG_DEBUG)) {
            return;
        }
        if ($this->debug->request->getUploadedFiles()) {
            $this->debug->log('$_FILES', $this->debug->request->getUploadedFiles());
        }
    }

    /**
     * Log $_POST or php://input
     *
     * @return void
     */
    private function logPost()
    {
        if (!$this->debug->getCfg('logRequestInfo.post', Debug::CONFIG_DEBUG)) {
            return;
        }
        $request = $this->debug->request;
        $method = $request->getMethod();

        // don't expect a request body for these methods
        $noBodyMethods = array('CONNECT','GET','HEAD','OPTIONS','TRACE');
        $expectBody = !\in_array($request->getMethod(), $noBodyMethods);
        if ($expectBody === false) {
            return;
        }
        $contentType = $request->getHeaderLine('Content-Type');
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
        if ($this->debug->getCfg('logRequestInfo.headers', Debug::CONFIG_DEBUG) === false) {
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
     * @param string $contentType Content-Type
     *
     * @return void
     */
    private function logResponseContent($contentType)
    {
        $maxLen = $this->debug->getCfg('logResponseMaxLen', Debug::CONFIG_DEBUG);
        $maxLen = $this->debug->utility->getBytes($maxLen, true);
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
                    $response = $this->debug->utility->getStreamContents($stream);
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
        if ($maxLen && $contentLength > $maxLen) {
            $this->debug->log('response too large to output (' . $contentLength . ')');
            return;
        }
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
            $matches = array();
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
        if ($this->debug->utility->isXml($input)) {
            $contentType = 'text/xml';
            return false;
        }
        return true;
    }
}
