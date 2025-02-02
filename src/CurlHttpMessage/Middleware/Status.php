<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage\Middleware;

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\CurlHttpMessage\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * Check response for 4xx or 5xx error
 */
class Status
{
    /**
     * Invoke
     *
     * @param callable $handler Next request handler in the middleware stack
     *
     * @return Closure
     */
    public function __invoke(callable $handler)
    {
        return static function (CurlReqRes $curlReqRes) use ($handler) {
            return $handler($curlReqRes)
                ->then(static function (ResponseInterface $response) use ($curlReqRes) {
                    $code = $response->getStatusCode();
                    if ($code < 400) {
                        return $response;
                    }
                    throw BadResponseException::create($curlReqRes->getRequest(), $response);
                });
        };
    }
}
