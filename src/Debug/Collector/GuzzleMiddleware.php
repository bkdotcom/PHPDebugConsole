<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\LogEntry;
use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * PHPDebugConsole Middleware for Guzzle
 */
class GuzzleMiddleware extends AbstractComponent
{
    private $debug;
    private $nextHandler;
    private $onRedirectOrig;

    protected $cfg = array(
        'asyncResponseWithRequest' => true,
        'icon' => 'fa fa-exchange',
        'iconAsync' => 'fa fa-random',
        'idPrefix' => 'guzzle_',
        'inclRequestBody' => false,
        'inclResponseBody' => false,
        'label' => 'Guzzle',
        'prettyRequestBody' => true,
        'prettyResponseBody' => true,
    );

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
            $debug = Debug::_getChannel($this->cfg['label'], array('channelIcon' => $this->cfg['icon']));
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
     * Log Request Begin
     *
     * @param RequestInterface $request Request
     * @param array            $options opts
     *
     * @return GuzzleHttp\Promise\PromiseInterface;
     */
    public function onRequest(RequestInterface $request, array $options)
    {
        $requestInfo = array(
            'isAsyncronous' => empty($options[RequestOptions::SYNCHRONOUS]),
            'requestId' => \spl_object_hash($request),
            'request' => $request,
        );
        if ($options['allow_redirects'] === true) {
            $options['allow_redirects'] = GuzzleHttp\RedirectMiddleware::$defaultSettings;
        }
        if ($options['allow_redirects']) {
            $this->onRedirectOrig = isset($options['allow_redirects']['on_redirect'])
                ? $options['allow_redirects']['on_redirect']
                : null;
            if ($requestInfo['isAsyncronous'] === false) {
                $options['allow_redirects']['on_redirect'] = array($this, 'onRedirect');
            }
        }
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
        $this->logRequest($request, $requestInfo);
        if ($requestInfo['isAsyncronous']) {
            $this->debug->groupEnd();
        }
        return $this->doRequest($request, $options, $requestInfo);
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
            $this->asyncResponseGroup($requestInfo['request'], $response, $meta);
        }
        $this->logResponse($response, $requestInfo);
        $this->debug->groupEnd($meta);
        return $response;
    }

    /**
     * Rejected Request handler
     *
     * @param GuzzleException $reason      Reject reason
     * @param array           $requestInfo Request information
     *
     * @return GuzzleHttp\Promise\PromiseInterface;
     */
    public function onRejected(GuzzleException $reason, array $requestInfo)
    {
        $meta = $this->debug->meta();
        $response = $reason instanceof RequestException
            ? $reason->getResponse()
            : null;
        if ($requestInfo['isAsyncronous']) {
            $meta = $this->debug->meta(array(
                'asyncResponseGroup' => true,
                'middlewareId' => \spl_object_hash($this),
            ));
            $this->asyncResponseGroup($requestInfo['request'], $response, $meta, true);
        }
        $this->logResponse($response, $requestInfo, $reason);
        $this->debug->groupEnd($meta);
        return Promise\Create::rejectionFor($reason);
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
    private function asyncResponseGroup(RequestInterface $request, $response, $meta, $isError = false)
    {
        $this->debug->groupCollapsed(
            $this->cfg['label'] . ' ' . ($isError
                ? 'Error'
                : 'Response'),
            $request->getMethod(),
            (string) $request->getUri(),
            $response
                ? $response->getStatusCode()
                : null,
            $this->debug->meta('icon', $this->cfg['icon']),
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
                . ' ' . $message->getRequestTarget())
                . ' HTTP/' . $message->getProtocolVersion() . "\r\n"
            : 'HTTP/' . $message->getProtocolVersion()
                . ' ' . $message->getStatusCode()
                . ' ' . $message->getReasonPhrase()
                . "\r\n";
        foreach ($message->getHeaders() as $name => $values) {
            $result .= $name . ': ' . \implode(', ', $values) . "\r\n";
        }
        return \rtrim($result);
    }

    /**
     * call nexthandler and register our fullfill and reject callbacks
     *
     * @param RequestInterface $request     Psr7 RequestInterface
     * @param array            $options     Guzzle request options
     * @param array            $requestInfo Request info
     *
     * @return GuzzleHttp\Promise\PromiseInterface;
     */
    protected function doRequest(RequestInterface $request, array $options, array $requestInfo)
    {
        // start timer
        $this->debug->time($this->cfg['label'] . ':' . $requestInfo['requestId']);
        $func = $this->nextHandler;
        return $func($request, $options)->then(
            function (ResponseInterface $response) use ($requestInfo) {
                return $this->onFulfilled($response, $requestInfo);
            },
            function (GuzzleException $reason) use ($requestInfo) {
                return $this->onRejected($reason, $requestInfo);
            }
        );
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
    private function getBody(MessageInterface $msg)
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
        $method = $request->getMethod();
        if ($requestInfo['isAsyncronous']) {
            $this->debug->info('asyncronous', $this->debug->meta('icon', $this->cfg['iconAsync']));
        }
        $this->debug->log('request headers', $this->buildHeadersString($request), $this->debug->meta('redact'));
        if ($this->cfg['inclRequestBody'] === false) {
            return;
        }
        $body = $this->getBody($request);
        $methodHasBody = $this->debug->utility->httpMethodHasBody($method);
        if ($methodHasBody === false && $body === '') {
            return;
        }
        $this->debug->log(
            'request body',
            $body,
            $this->debug->meta('redact')
        );
    }

    /**
     * Log request headers and request body
     *
     * @param ResponseInterface|null $response     Response
     * @param array                  $requestInfo  Request information
     * @param GuzzleException        $rejectReason Response exception
     *
     * @return void
     */
    protected function logResponse(ResponseInterface $response = null, array $requestInfo = array(), GuzzleException $rejectReason = null)
    {
        $duration = $this->debug->timeEnd($this->cfg['label'] . ':' . $requestInfo['requestId'], false);
        $metaAppend = $requestInfo['isAsyncronous'] && $this->cfg['asyncResponseWithRequest']
            ? $this->debug->meta('appendGroup', $this->cfg['idPrefix'] . $requestInfo['requestId'])
            : $this->debug->meta();
        if ($rejectReason instanceof GuzzleException) {
            $message = \preg_replace('/ response:\n.*$/s', '', $rejectReason->getMessage());
            $this->debug->warn(\get_class($rejectReason), $rejectReason->getCode(), $message, $metaAppend);
        }
        $this->debug->time($duration, $metaAppend);
        if (!$response) {
            return;
        }
        $this->debug->log('response headers', $this->buildHeadersString($response), $this->debug->meta('redact'), $metaAppend);
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
