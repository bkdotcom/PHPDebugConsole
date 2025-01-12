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

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\CurlHttpMessage\Exception\RequestException;
use bdk\Promise;
use Psr\Http\Message\ResponseInterface;

/**
 * PHPDebugConsole Middleware for CurlHttpMEssage
 */
class CurlHttpMessageMiddleware extends AbstractAsyncMiddleware
{
    /**
     * {@inheritDoc}
     */
    public function __construct($cfg = array(), $debug = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

        $this->cfg = \array_merge($this->cfg, array(
            'idPrefix' => 'curl_',
            'label' => 'CurlHttpMessage',
        ));
        parent::__construct($cfg, $debug);
    }

    /**
     * Log Request Begin
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return Promise
     */
    public function onRequest(CurlReqRes $curlReqRes)
    {
        $request = $curlReqRes->getRequest();
        $options = $curlReqRes->getOptions();
        $requestInfo = array(
            'isAsynchronous' => $options['isAsynchronous'],
            'request' => $request,
            'requestId' => \spl_object_hash($request),
        );
        $this->onRedirectOrig = $options['onRedirect'];
        if ($requestInfo['isAsynchronous'] === false) {
            $curlReqRes->setOption('onRedirect', [$this, 'onRedirect']);
        }
        $this->logRequest($request, $requestInfo);
        return $this->doRequest($curlReqRes, $requestInfo);
    }

    /**
     * Rejected Request handler
     *
     * @param RequestException $reason      Reject reason
     * @param array            $requestInfo Request information
     *
     * @return bdk\Promise
     */
    public function onRejected(RequestException $reason, array $requestInfo)
    {
        $meta = $this->debug->meta();
        $response = $reason->getResponse();
        if ($requestInfo['isAsynchronous']) {
            $meta = $this->debug->meta(array(
                'asyncResponseGroup' => true,
                'middlewareId' => \spl_object_hash($this),
            ));
            $this->asyncResponseGroup(
                $requestInfo['request'],
                $response,
                $meta,
                true
            );
        }
        $this->logResponse($response, $requestInfo, $reason);
        $this->debug->groupEnd($meta);
        return Promise::rejectionFor($reason);
    }

    /**
     * call nextHandler and register our fulfill and reject callbacks
     *
     * @param CurlReqRes $curlReqRes  CurlReqRes instance
     * @param array      $requestInfo Request info
     *
     * @return Promise
     */
    protected function doRequest(CurlReqRes $curlReqRes, array $requestInfo)
    {
        // start timer
        $this->debug->time($this->cfg['label'] . ':' . $requestInfo['requestId']);
        $handler = $this->nextHandler;
        return $handler($curlReqRes)->then(
            function (ResponseInterface $response) use ($requestInfo) {
                return $this->onFulfilled($response, $requestInfo);
            },
            function (RequestException $reason) use ($requestInfo) {
                return $this->onRejected($reason, $requestInfo);
            }
        );
    }
}
