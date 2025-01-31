<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3.1
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\HttpMessage\Utility\ContentType;
use bdk\HttpMessage\Utility\Stream as StreamUtility;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log Request
 * Display in dedicated tab
 */
class LogRequest extends AbstractLogReqRes implements SubscriberInterface
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
        $collectWas = $this->debug->setCfg('collect', true);
        $this->logRequest();
        $this->debug->setCfg('collect', $collectWas, Debug::CONFIG_NO_RETURN);
    }

    /**
     * Log request headers, cookie, post, & files data
     *
     * @return void
     */
    public function logRequest()
    {
        if ($this->testLogRequest() === false) {
            return;
        }
        $this->debug->log(
            'Request',
            $this->debug->meta(array(
                'attribs' => array(
                    'style' => $this->headerStyle,
                ),
                'icon' => ':send:',
            ))
        );
        $this->debug->alert(
            '%c%s%c %s',
            'font-weight:bold;',
            $this->debug->serverRequest->getMethod(),
            '',
            $this->debug->serverRequest->getRequestTarget(),
            $this->debug->meta('level', 'info')
        );
        $this->logRequestHeaders();
        $this->logRequestCookies();
        $this->logPostOrInput();
        $this->logFiles();
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
        $files = $this->debug->serverRequest->getUploadedFiles();
        if (!$files) {
            return;
        }
        $files = $this->debug->arrayUtil->mapRecursive(static function ($uploadedFile) {
            return array(
                'error' => $uploadedFile->getError(),
                'name' => $uploadedFile->getClientFilename(),
                'size' => $uploadedFile->getSize(),
                'tmp_name' => $uploadedFile->getError() === UPLOAD_ERR_OK
                    ? $uploadedFile->getStream()->getMetadata('uri')
                    : '',
                'type' => $uploadedFile->getClientMediaType(),
            );
        }, $files);
        $this->debug->log('$_FILES', $files);
    }

    /**
     * Log php://input
     *
     * @param string $method      Http method
     * @param string $contentType Content-Type value
     *
     * @return void
     */
    private function logInput($method, $contentType)
    {
        $request = $this->debug->serverRequest;
        $input = StreamUtility::getContents($request->getBody());
        $methodExpectBody = $this->debug->utility->httpMethodHasBody($method);
        $logInput = $input
            || $methodExpectBody
            || $request->getHeaderLine('Content-Length')
            || $request->getHeaderLine('Transfer-Encoding');
        if ($logInput === false) {
            return;
        }
        $meta = $this->debug->meta(array(
            'detectFiles' => false,
            'file' => null,
            'line' => null,
        ));
        if ($input) {
            if ($methodExpectBody === false) {
                $this->debug->warn($method . ' request with body', $meta);
            }
            $input = $this->debug->prettify($input, $contentType);
            $this->debug->log('php://input', $input, $this->debug->meta('redact'));
        } elseif (!$request->getUploadedFiles()) {
            $this->debug->warn($method . ' request with no body', $meta);
        }
    }

    /**
     * Log $_POST or php://input
     *
     * @return void
     */
    private function logPostOrInput()
    {
        if (!$this->debug->getCfg('logRequestInfo.post', Debug::CONFIG_DEBUG)) {
            return;
        }
        $request = $this->debug->serverRequest;
        $method = $request->getMethod();
        $mediaTypeUser = (string) $request->getMediaType();
        $mediaType = $this->detectContentType(
            StreamUtility::getContents($request->getBody()),
            $mediaTypeUser
        );
        $parsedBody = $request->getParsedBody();
        $this->assertCorrectContentType($mediaType, $mediaTypeUser, $method);
        if (
            $method === 'POST'
            && \in_array($mediaType, [ContentType::FORM, ContentType::FORM_MULTIPART], true)
            && $parsedBody
        ) {
            $this->debug->log('$_POST', $parsedBody, $this->debug->meta('redact'));
            return;
        }
        $this->logInput($method, $mediaType);
    }

    /**
     * Log Request Cookies
     *
     * @return void
     */
    private function logRequestCookies()
    {
        if ($this->debug->getCfg('logRequestInfo.cookies', Debug::CONFIG_DEBUG) === false) {
            return;
        }
        $cookieVals = \array_map(function ($val) {
            if (\is_numeric($val)) {
                $val = $this->debug->abstracter->crateWithVals($val, array(
                    'attribs' => array(
                        'class' => 'text-left',
                    ),
                ));
            }
            return $val;
        }, $this->debug->serverRequest->getCookieParams());
        \ksort($cookieVals, SORT_NATURAL);
        if ($cookieVals) {
            $this->debug->table('$_COOKIE', $cookieVals, $this->debug->meta('redact'));
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
        $headers = $this->debug->serverRequest->getHeaders();
        $headers = $this->debug->redactHeaders($headers);
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
        }, $headers);
        if ($headers) {
            \ksort($headers, SORT_NATURAL);
            $this->debug->table('request headers', $headers);
        }
    }

    /**
     * Check if we should log request
     *
     * @return bool
     */
    private function testLogRequest()
    {
        $isHttp = \strpos($this->debug->getInterface(), 'http') === 0;
        $logRequest = \count(\array_filter($this->debug->getCfg('logRequestInfo', Debug::CONFIG_DEBUG))) > 0;
        return $isHttp && $logRequest;
    }
}
