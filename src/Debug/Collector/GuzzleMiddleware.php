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

use GuzzleHttp;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PHPDebugConsole Middleware for Guzzle
 */
class GuzzleMiddleware extends AbstractAsyncMiddleware
{
    /**
     * {@inheritDoc}
     */
    public function __construct($cfg = array(), $debug = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');

        $this->cfg = \array_merge($this->cfg, array(
            'idPrefix' => 'guzzle_',
            'label' => 'Guzzle',
        ));
        parent::__construct($cfg, $debug);

        $this->debug->backtrace->addInternalClass('GuzzleHttp\\', 1);
    }

    /**
     * Log Request Begin
     *
     * @param RequestInterface $request Request
     * @param array            $options opts
     *
     * @return GuzzleHttp\Promise\PromiseInterface
     */
    public function onRequest(RequestInterface $request, array $options)
    {
        $requestInfo = array(
            'isAsynchronous' => empty($options[RequestOptions::SYNCHRONOUS]),
            'request' => $request,
            'requestId' => \spl_object_hash($request),
        );
        if ($options['allow_redirects'] === true) {
            $options['allow_redirects'] = GuzzleHttp\RedirectMiddleware::$defaultSettings;
        }
        if ($options['allow_redirects']) {
            $this->onRedirectOrig = isset($options['allow_redirects']['on_redirect'])
                ? $options['allow_redirects']['on_redirect']
                : null;
            if ($requestInfo['isAsynchronous'] === false) {
                $options['allow_redirects']['on_redirect'] = [$this, 'onRedirect'];
            }
        }
        $this->logRequest($request, $requestInfo);
        return $this->doRequest($request, $options, $requestInfo);
    }

    /**
     * Rejected Request handler
     *
     * @param GuzzleException $reason      Reject reason
     * @param array           $requestInfo Request information
     *
     * @return GuzzleHttp\Promise\PromiseInterface
     */
    public function onRejected(GuzzleException $reason, array $requestInfo)
    {
        $meta = $this->debug->meta();
        $response = $reason instanceof RequestException
            ? $reason->getResponse()
            : null;
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
        return \class_exists('GuzzleHttp\\Promise\\Create')
            ? Promise\Create::rejectionFor($reason)
            : Promise\rejection_for($reason);
    }

    /**
     * call nextHandler and register our fulfill and reject callbacks
     *
     * @param RequestInterface $request     Psr7 RequestInterface
     * @param array            $options     Guzzle request options
     * @param array            $requestInfo Request info
     *
     * @return GuzzleHttp\Promise\PromiseInterface
     */
    protected function doRequest(RequestInterface $request, array $options, array $requestInfo)
    {
        // start timer
        $this->debug->time($this->cfg['label'] . ':' . $requestInfo['requestId']);
        $handler = $this->nextHandler;
        return $handler($request, $options)->then(
            function (ResponseInterface $response) use ($requestInfo) {
                return $this->onFulfilled($response, $requestInfo);
            },
            function (GuzzleException $reason) use ($requestInfo) {
                return $this->onRejected($reason, $requestInfo);
            }
        );
    }
}
