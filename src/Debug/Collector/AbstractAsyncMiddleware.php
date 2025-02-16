<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility;
use bdk\HttpMessage\Utility\ContentType;
use bdk\HttpMessage\Utility\Stream as StreamUtility;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * PHPDebugConsole Middleware for Guzzle & CurlHttpMessage
 */
class AbstractAsyncMiddleware extends AbstractComponent
{
    protected $cfg = array(
        'asyncResponseWithRequest' => true,
        'icon' => ':send-receive:',
        'iconAsync' => ':asynchronous:',
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
     * @param array      $cfg   configuration
     * @param Debug|null $debug (optional) Specify PHPDebugConsole instance
     *                            if not passed, will create Guzzle channel on singleton instance
     *                            if root channel is specified, will create a Guzzle channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($cfg = array(), $debug = null)
    {
        Utility::assertType($debug, 'bdk\Debug');
        $this->setCfg($cfg);
        if (!$debug) {
            $debug = Debug::getChannel($this->cfg['label'], array('channelIcon' => $this->cfg['icon']));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel($this->cfg['label'], array('channelIcon' => $this->cfg['icon']));
        }
        $debug->eventManager->subscribe(Debug::EVENT_OUTPUT_LOG_ENTRY, [$this, 'onOutputLogEntry']);
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
        return [$this, 'onRequest'];
    }

    /**
     * Subscribe to logEntry output... conditionally output response group
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
     * and only for synchronous request
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
        if ($requestInfo['isAsynchronous']) {
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
     * Start a new group for asynchronous response
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
     * Returned string is redacted
     *
     * @param RequestInterface|ResponseInterface $message Request or Response
     *
     * @return string
     */
    private function buildHeadersString($message)
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
        $contentType = $msg->getHeaderLine('Content-Type');
        $body = StreamUtility::getContents($msg->getBody());
        $prettify = $msg instanceof RequestInterface
            ? $this->cfg['prettyRequestBody']
            : $this->cfg['prettyResponseBody'];
        if ($contentType === ContentType::FORM) {
            return $this->debug->abstracter->getAbstraction($body, null, [
                \bdk\Debug\Abstraction\Type::TYPE_STRING,
                \bdk\Debug\Abstraction\Type::TYPE_STRING_FORM,
            ]);
        }
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
        if ($requestInfo['isAsynchronous']) {
            $this->debug->info('asynchronous', $this->debug->meta('icon', $this->cfg['iconAsync']));
        }
        $this->debug->log('request headers', $this->buildHeadersString($request));
        $this->logRequestBody($request);
        if ($requestInfo['isAsynchronous']) {
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
     * @param ResponseInterface|null                $response     Response
     * @param array                                 $requestInfo  Request information
     * @param RequestException|GuzzleException|null $rejectReason Response exception
     *
     * @return void
     */
    protected function logResponse($response = null, array $requestInfo = array(), $rejectReason = null)
    {
        Utility::assertType($response, 'Psr\Http\Message\ResponseInterface');
        Utility::assertType($rejectReason, 'Exception');

        $duration = $this->debug->timeEnd($this->cfg['label'] . ':' . $requestInfo['requestId'], false);
        $metaAppend = $requestInfo['isAsynchronous'] && $this->cfg['asyncResponseWithRequest']
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
