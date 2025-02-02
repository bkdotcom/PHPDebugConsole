<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage\Handler;

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\Promise\FulfilledPromise;
use bdk\Promise\PromiseInterface;
use CurlHandle;

/**
 * Fetch the request with curl, and return a Promise
 */
class Curl
{
    /** @var CurlHandle|resource */
    protected $curlHandle;

    /**
     * Invoke handler
     *
     * @param CurlReqRes $curlReqRes CurlReqRes instance
     *
     * @return PromiseInterface
     */
    public function __invoke(CurlReqRes $curlReqRes)
    {
        $curlHandle = $this->getCurlHandle();
        $curlReqRes->setCurlHandle($curlHandle);
        $response = $curlReqRes->exec();
        return new FulfilledPromise($response);
    }

    /**
     * Reuse existing curl handle or init a new one
     *
     * @return resource|CurlHandle
     */
    private function getCurlHandle()
    {
        if (\is_resource($this->curlHandle) === false && ($this->curlHandle instanceof CurlHandle) === false) {
            $this->curlHandle = \curl_init();
            return $this->curlHandle;
        }
        if (\function_exists('curl_reset') === false) {
            \curl_close($this->curlHandle);
            $this->curlHandle = \curl_init();
            return $this->curlHandle;
        }
        \curl_reset($this->curlHandle);
        return $this->curlHandle;
    }
}
