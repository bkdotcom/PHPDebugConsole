<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\LogEntry;
use Exception;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * PHPDebugConsole Middleware for Guzzle
 */
class AbstractAsyncMiddleware extends AbstractComponent
{
    protected $cfg = array(
        'asyncResponseWithRequest' => true,
        'icon' => 'fa fa-exchange',
        'iconAsync' => 'fa fa-random',
        'idPrefix' => '',
        'inclRequestBody' => false,
        'inclResponseBody' => false,
        'label' => 'http request',
        'prettyRequestBody' => true,
        'prettyResponseBody' => true,
    );

    /** @var Debug */
    protected $debug;

    /** @var callable|null */
    protected $nextHandler;

    /** @var callable|null */
    protected $onRedirectOrig;

    /**
     * Constructor
     *
     * @param array $cfg   configuration
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                       if not passed, will create Guzzle channel on singleton instance
     *                       if root channel is specified, will create a Guzzle channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($cfg = array(), Debug $debug = null)
    {
        $this->setCfg($cfg);
        if (!$debug) {
            $debug = Debug::getChannel($this->cfg['label'], array('channelIcon' => $this->cfg['icon']));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($this->cfg['label'], array('channelIcon' => $this->cfg['icon']));
        }
        $debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, array($this, 'onOutputLogEntry'));
        $this->debug = $debug;
    }

    /**
     * @param callable $nextHandler next handler in stack
     *
     * @return callable
     */
    public function __invoke(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
        return array($this, 'onRequest');
    }

    /**
     * Subscribe to logEntry output... conditionaly output response group
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onOutputLogEntry(LogEntry $logEntry)
    {
        if ($this->cfg['asyncResponseWithRequest'] !== true) {
            return;
        }
        if ($logEntry->getMeta('asyncResponseGroup') !== true) {
            return;
        }
        if ($logEntry->getMeta('middlewareId') !== \spl_object_hash($this)) {
            // processed by a different middleware instance
            return;
        }
        $logEntry['output'] = $logEntry['route'] instanceof \bdk\Debug\Route\Stream;
    }

    /**
     * Called when redirect encountered
     * but only if this middleware is added to the bottom of the stack (unshift)
     * and only for syncronous request
     *
     * @param RequestInterface  $request  Request
     * @param ResponseInterface $response Response
     * @param UriInterface      $uriNew   The new location
     *
     * @return void
     */
    public function onRedirect(RequestInterface $request, ResponseInterface $response, UriInterface $uriNew)
    {
        $this->debug->info('redirect', $response->getStatusCode(), (string) $uriNew);
        if ($this->onRedirectOrig) {
            \call_user_func($this->onRedirectOrig, $request, $response, $uriNew);
        }
    }

    /**
     * Fulfilled Request handler
     *
     * @param ResponseInterface $response    Response
     * @param array             $requestInfo Request Information
     *
     * @return ResponseInterface
     */
    public function onFulfilled(ResponseInterface $response, array $requestInfo)
    {
        $meta = $this->debug->meta();
        if ($requestInfo['isAsyncronous']) {
            $meta = $this->debug->meta(array(
                'asyncResponseGroup' => true,
                'middlewareId' => \spl_object_hash($this),
            ));
            $this->asyncResponseGroup(
                $requestInfo['request'],
                $response,
                $meta
            );
        }
        $this->logResponse($response, $requestInfo);
        $this->debug->groupEnd($meta);
        return $response;
    }

    /**
     * Start a new group for asyncronous response
     *
     * @param RequestInterface       $request  RequestInterface
     * @param ResponseInterface|null $response ResponseInterface (if available)
     * @param array                  $meta     additional meta info
     * @param bool                   $isError  (false) rejection?
     *
     * @return void
     */
    protected function asyncResponseGroup(RequestInterface $request, $response, array $meta = array(), $isError = false)
    {
        $meta['icon'] = $this->cfg['icon'];
        $this->debug->groupCollapsed(
            $this->cfg['label'] . ' ' . ($isError
                ? 'Error'
                : 'Response'),
            $request->getMethod(),
            (string) $request->getUri(),
            $response
                ? $response->getStatusCode()
                : null,
            $meta
        );
    }

    /**
     * Build request header string
     *
     * @param MessageInterface $message Request or Response
     *
     * @return string
     */
    private function buildHeadersString(MessageInterface $message)
    {
        $result = $message instanceof RequestInterface
            ? \trim($message->getMethod()
                . ' ' . $this->debug->redact($message->getRequestTarget()))
                . ' HTTP/' . $message->getProtocolVersion() . "\r\n"
            : 'HTTP/' . $message->getProtocolVersion()
                . ' ' . $message->getStatusCode()
                . ' ' . $message->getReasonPhrase() . "\r\n";
        $headers = $this->debug->redactHeaders($message->getHeaders());
        foreach ($headers as $name => $values) {
            $result .= $name . ': ' . \implode(', ', $values) . "\r\n";
        }
        return \rtrim($result);
    }

    /**
     * Get the request/response body
     *
     * Will return formatted Abstraction if html/json/xml
     *
     * @param MessageInterface $msg request or response
     *
     * @return \bdk\Debug\Abstraction\Abstraction|string|null
     */
    protected function getBody(MessageInterface $msg)
    {
        $bodyStream = $msg->getBody();
        $contentType = $msg->getHeader('Content-Type');
        $contentType = $contentType
            ? $contentType[0]
            : null;
        $body = $this->debug->utility->getStreamContents($bodyStream);
        if (\strlen($body) === 0) {
            return '';
        }
        $prettify = $msg instanceof RequestInterface
            ? $this->cfg['prettyRequestBody']
            : $this->cfg['prettyResponseBody'];
        return $prettify
            ? $this->debug->prettify($body, $contentType)
            : $body;
    }

    /**
     * Log request headers and request body
     *
     * @param RequestInterface $request     Request
     * @param array            $requestInfo Request information
     *
     * @return void
     */
    protected function logRequest(RequestInterface $request, array $requestInfo)
    {
        $this->debug->groupCollapsed(
            $this->cfg['label'],
            $request->getMethod(),
            (string) $request->getUri(),
            $this->debug->meta(array(
                'icon' => $this->cfg['icon'],
                'id' => $this->cfg['idPrefix'] . $requestInfo['requestId'],
                'redact' => true,
            ))
        );
        if ($requestInfo['isAsyncronous']) {
            $this->debug->info('asyncronous', $this->debug->meta('icon', $this->cfg['iconAsync']));
        }
        $this->debug->log('request headers', $this->buildHeadersString($request));
        $this->logRequestBody($request);
        if ($requestInfo['isAsyncronous']) {
            $this->debug->groupEnd();
        }
    }

    /**
     * Log the request body
     *
     * @param RequestInterface $request Request
     *
     * @return void
     */
    protected function logRequestBody(RequestInterface $request)
    {
        if ($this->cfg['inclRequestBody'] === false) {
            return;
        }
        $body = $this->getBody($request);
        $method = $request->getMethod();
        $methodHasBody = $this->debug->utility->httpMethodHasBody($method);
        if ($methodHasBody === false && $body === '') {
            return;
        }
        $this->debug->log('request body', $body, $this->debug->meta('redact'));
    }

    /**
     * Log request headers and request body
     *
     * @param ResponseInterface|null           $response     Response
     * @param array                            $requestInfo  Request information
     * @param RequestException|GuzzleException $rejectReason Response exception
     *
     * @return void
     */
    protected function logResponse(ResponseInterface $response = null, array $requestInfo = array(), Exception $rejectReason = null)
    {
        $duration = $this->debug->timeEnd($this->cfg['label'] . ':' . $requestInfo['requestId'], false);
        $metaAppend = $requestInfo['isAsyncronous'] && $this->cfg['asyncResponseWithRequest']
            ? $this->debug->meta('appendGroup', $this->cfg['idPrefix'] . $requestInfo['requestId'])
            : $this->debug->meta();
        if ($rejectReason) {
            $message = \preg_replace('/ response:\n.*$/s', '', $rejectReason->getMessage());
            $this->debug->warn(\get_class($rejectReason), $rejectReason->getCode(), $message, $metaAppend);
        }
        $this->debug->time($duration, $metaAppend);
        if (!$response) {
            return;
        }
        $this->debug->log('response headers', $this->buildHeadersString($response), $metaAppend);
        if ($this->cfg['inclResponseBody']) {
            $this->debug->log(
                'response body',
                $this->getBody($response),
                $this->debug->meta('redact'),
                $metaAppend
            );
        }
    }
}
