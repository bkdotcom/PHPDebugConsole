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
use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PHPDebugConsole Middleware for Guzzle
 */
class GuzzleMiddleware extends AbstractComponent
{
    private $debug;
    private $icon = 'fa fa-exchange';
    private $iconAsync = 'fa fa-random';
    private $nextHandler;
    private $onRedirectOrig;

    protected $cfg = array(
        'asyncResponseWithRequest' => true,
        'inclRequestBody' => false,
        'inclResponseBody' => false,
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
            $debug = Debug::_getChannel('Guzzle', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('Guzzle', array('channelIcon' => $this->icon));
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
        $logEntry['output'] = $logEntry['route'] instanceof \bdk\Debug\Route\Stream;
    }

    /**
     * Called when redirect encountered
     * but only if this middleware is added to the bottom of the stack (unshift)
     * and only for syncronous request
     *
     * @param RequestInterface  $request  Request
     * @param ResponseInterface $response Response
     * @param string            $uriNew   The new location
     *
     * @return void
     */
    public function onRedirect(RequestInterface $request, ResponseInterface $response, $uriNew)
    {
        $this->debug->info('redirect', $response->getStatusCode(), (string) $uriNew);
        if ($this->onRedirectOrig) {
            \call_user_func($this->onRedirectOrig, $request, $response, $uriNew);
        }
    }

    /**
     * Log Request Begin
     *
     * @param RequestInterface $request Guzzle request
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
        $this->onRedirectOrig = isset($options['allow_redirects']['on_redirect'])
            ? $options['allow_redirects']['on_redirect']
            : null;
        if ($requestInfo['isAsyncronous'] === false) {
            $options['allow_redirects']['on_redirect'] = array($this, 'onRedirect');
        }
        $this->debug->groupCollapsed(
            'Guzzle',
            $request->getMethod(),
            (string) $request->getUri(),
            $this->debug->meta(array(
                'icon' => $this->icon,
                'id' => 'guzzle_' . $requestInfo['requestId'],
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
     * @param ResponseInterface $response    Guzzle response
     * @param array             $requestInfo Request Information
     *
     * @return ResponseInterface
     */
    public function onFulfilled(ResponseInterface $response, array $requestInfo)
    {
        $metaGroup = $this->debug->meta();
        if ($requestInfo['isAsyncronous']) {
            $metaGroup = $this->debug->meta('asyncResponseGroup');
            $this->asyncResponseGroup($requestInfo['request'], $response, $metaGroup);
        }
        $this->logResponse($response, $requestInfo);
        $this->debug->groupEnd($metaGroup);
        return $response;
    }

    /**
     * Rejected Request handler
     *
     * @param RequestException $reason      Reject reason
     * @param array            $requestInfo Request information
     *
     * @return GuzzleHttp\Promise\PromiseInterface;
     */
    public function onRejected(RequestException $reason, array $requestInfo)
    {
        $metaGroup = $this->debug->meta();
        $response = $reason->getResponse();
        if ($requestInfo['isAsyncronous']) {
            $metaGroup = $this->debug->meta('asyncResponseGroup');
            $this->asyncResponseGroup($requestInfo['request'], $response, $metaGroup, true);
        }
        $this->logResponse($response, $requestInfo, $reason);
        $this->debug->groupEnd($metaGroup);
        return Promise\rejection_for($reason);
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
            $isError
                ? 'Guzzle Error'
                : 'Guzzle Response',
            $request->getMethod(),
            (string) $request->getUri(),
            $response
                ? $response->getStatusCode()
                : null,
            $this->debug->meta('icon', $this->icon),
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
     * @param array            $options     Guzzle request options]
     * @param array            $requestInfo Request info
     *
     * @return GuzzleHttp\Promise\PromiseInterface;
     */
    protected function doRequest(RequestInterface $request, array $options, array $requestInfo)
    {
        // start timer
        $this->debug->time('guzzle:' . $requestInfo['requestId']);
        $func = $this->nextHandler;
        return $func($request, $options)->then(
            function (ResponseInterface $response) use ($requestInfo) {
                return $this->onFulfilled($response, $requestInfo);
            },
            function (RequestException $reason) use ($requestInfo) {
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
     * Log reqeust headers and reqeust body
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
            $this->debug->info('asyncronous', $this->debug->meta('icon', $this->iconAsync));
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
     * Log reqeust headers and reqeust body
     *
     * @param ResponseInterface $response     Response
     * @param array             $requestInfo  Request information
     * @param Exception         $rejectReason Response exception
     *
     * @return void
     */
    protected function logResponse(ResponseInterface $response = null, array $requestInfo = array(), Exception $rejectReason = null)
    {
        $duration = $this->debug->timeEnd('guzzle:' . $requestInfo['requestId'], false);
        $metaAppend = $requestInfo['isAsyncronous'] && $this->cfg['asyncResponseWithRequest']
            ? $this->debug->meta('appendGroup', 'guzzle_' . $requestInfo['requestId'])
            : $this->debug->meta();
        if ($rejectReason instanceof Exception) {
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
