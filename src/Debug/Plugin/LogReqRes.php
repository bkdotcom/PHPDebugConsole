<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use Exception;

/**
 * Log Request/Response
 * Display in dedicated tab
 *
 * Response will only avail if one of the following
 *   Debug::response obj avail (set via Debug::writeToResponse or setting 'response' service)
 *   output triggered via shutdown
 */
class LogReqRes implements SubscriberInterface
{

    private $debug;
    private $headerStyle = 'display:block; font-size:110%; font-weight:bold; padding:0.25em 0.5em; text-indent:0; border-bottom:#31708f 1px solid; background: linear-gradient(0deg, rgba(0,0,0,0.1) 0%, rgba(255,255,255,0.1) 100%);';

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
            Debug::EVENT_OUTPUT => array('logResponse', PHP_INT_MAX),
        );
    }

    /**
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @param Event $event Debug::EVENT_PLUGIN_INIT Event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $debug = $event->getSubject();
        $this->debug = $debug->getChannel('Request / Response', array(
            'channelIcon' => 'fa fa-exchange',
            'channelSort' => 10,
            'nested' => false,
        ));

        $collectWas = $debug->setCfg('collect', true);
        $this->logRequest();    // headers, cookies, post
        $debug->setCfg('collect', $collectWas);
    }

    /**
     * Log request headers, Cookie, Post, & Files data
     *
     * @return void
     */
    public function logRequest()
    {
        if (\strpos($this->debug->getInterface(), 'http') !== 0) {
            return;
        }
        if (!\array_filter($this->debug->getCfg('logRequestInfo', Debug::CONFIG_DEBUG))) {
            return;
        }
        $this->debug->log(
            'Request',
            $this->debug->meta(array(
                'attribs' => array(
                    'style' => $this->headerStyle,
                ),
                'icon' => 'fa fa-arrow-right',
            ))
        );
        $this->logRequestHeaders();
        if ($this->debug->getCfg('logRequestInfo.cookies', Debug::CONFIG_DEBUG)) {
            $cookieVals = \array_map(function ($val) {
                if (\is_numeric($val)) {
                    $val = $this->debug->abstracter->crateWithVals($val, array(
                        'attribs' => array(
                            'class' => 'text-left',
                        ),
                    ));
                }
                return $val;
            }, $this->debug->request->getCookieParams());
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
        if (\strpos($this->debug->getInterface(), 'http') !== 0) {
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
        $headers = \array_map(function ($vals) {
            return \implode("\n", $vals);
        }, $this->debug->getResponseHeaders());
        $this->debug->table('response headers', $headers);
        $contentType = \implode(', ', $this->debug->getResponseHeader('Content-Type'));
        if (!\preg_match('#\b(json|xml)\b#', $contentType)) {
            // we're not interested in logging response
            $this->debug->log(
                $contentType
                    ? 'Not logging response body for Content-Type "' . $contentType . '"'
                    : 'Content-Type unknown:  Not logging response body.'
            );
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
        $files = $this->debug->request->getUploadedFiles();
        if (!$files) {
            return;
        }
        $files = $this->debug->utility->arrayMapRecursive(function ($uploadedFile) {
            return array(
                'error' => $uploadedFile->getError(),
                'name' => $uploadedFile->getClientFilename(),
                'size' => $uploadedFile->getSize(),
                'tmp_name' => $uploadedFile->getStream()->getMetadata('uri'),
                'type' => $uploadedFile->getClientMediaType(),
            );
        }, $files);
        $this->debug->log('$_FILES', $files);
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
                $this->debug->log(
                    'php://input',
                    $this->debug->prettify($input, $contentType),
                    $this->debug->meta('redact')
                );
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
     * Log Request Headers
     *
     * @return void
     */
    private function logRequestHeaders()
    {
        if ($this->debug->getCfg('logRequestInfo.headers', Debug::CONFIG_DEBUG) === false) {
            return;
        }
        $this->debug->alert(
            '%c%s%c %s',
            'font-weight:bold;',
            $this->debug->request->getMethod(),
            '',
            $this->debug->request->getRequestTarget(),
            $this->debug->meta('level', 'info')
        );
        $headers = \array_map(function ($vals) {
            $val = \join(', ', $vals);
            if (\is_numeric($val)) {
                $val = $this->debug->abstracter->crateWithVals($val, array(
                    'attribs' => array(
                        'class' => 'text-left',
                    ),
                ));
            }
            return $val;
        }, $this->debug->request->getHeaders());
        if (isset($headers['Authorization']) && \strpos($headers['Authorization'], 'Basic') === 0) {
            $auth = \base64_decode(\str_replace('Basic ', '', $headers['Authorization']));
            $userpass = \explode(':', $auth);
            $headers['Authorization'] = 'Basic █████████ (base64\'d ' . $userpass[0] . ':password)';
        }
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
        /*
            get the contents of the output buffer we started to collect response
            Note that we don't clear, echo, flush, or end the buffer here
        */
        $response = \ob_get_contents();
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
        $this->debug->log(
            'response content (%c%s%c)',
            'font-family: monospace;',
            $contentType,
            '',
            $this->debug->prettify($response, $contentType),
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
            // remove charset/encoding if pressent
            $contentType = \preg_replace('/\s*[;,].*$/', '', $contentTypeRaw);
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
