<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage;

use bdk\Promise;
use bdk\Promise\EachPromise;
use bdk\Promise\PromiseInterface;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

/**
 * Lightweight PSR-7 (HttpMessage) based cURL asynchronous client
 */
class ClientAsync extends AbstractClient
{
    /**
     * {@inheritDoc}
     */
    public function handle(RequestInterface $request, array $options = array())
    {
        $options['isAsynchronous'] = true;
        return parent::handle($request, $options);
    }

    /**
     * Iterate over supplied requests
     *
     * @param array|\Iterator $requests Requests
     *                                  and/or functions that return promises
     * @param array           $config   Associative array of options
     *                                  - concurrency: (int) Maximum number of requests to send concurrently
     *                                  - options: Array of request options to apply to each request.
     *                                  - fulfilled: (callable) Function to invoke when a request completes.
     *                                  - rejected: (callable) Function to invoke when a request is rejected.
     *
     * @return PromiseInterface
     *
     * @throws InvalidArgumentException if the event format is incorrect.
     */
    public function each($requests, array $config = array())
    {
        if (!isset($config['concurrency'])) {
            $config['concurrency'] = 25;
        }

        $opts = array();
        if (isset($config['options'])) {
            $opts = $config['options'];
            unset($config['options']);
        }

        $iterable = Promise::iteratorFor($requests);
        $requests = function () use ($iterable, $opts) {
            foreach ($iterable as $key => $request) {
                if ($request instanceof RequestInterface) {
                    yield $key => $this->handle($request, $opts);
                    continue;
                }
                if (\is_callable($request)) {
                    yield $key => $request($opts);
                    continue;
                }
                throw new InvalidArgumentException('Each request must be a Psr7\Http\Message\RequestInterface'
                    . ' or a callable that returns a promise that fulfills with a Psr7\Message\Http\ResponseInterface object.');
            }
        };

        $each = new EachPromise($requests(), $config);
        return $each->promise();
    }

    /**
     * Sends multiple requests concurrently and returns an array of responses
     * and exceptions that uses the same ordering as the provided requests.
     *
     * IMPORTANT: This method keeps every request and response in memory,
     * as such, is NOT recommended when sending a large number or an
     * indeterminate number of requests concurrently.
     *
     * @param array|\Iterator $requests Requests to send concurrently.
     * @param array           $config   Passes through the config opts available in
     *                                  {@see \bdk\CurlHttpMessage\ClientAsync::iterator}
     *
     * @return array Returns an array containing the response or an exception
     *               in the same order that the requests were sent.
     *
     * @throws InvalidArgumentException if the event format is incorrect.
     */
    public function batch($requests, array $config = array())
    {
        $responses = array();
        self::buildCallback('fulfilled', $config, $responses);
        self::buildCallback('rejected', $config, $responses);
        $this->each($requests, $config)->wait();
        \ksort($responses);
        return $responses;
    }

    /**
     * build fulfilled / rejected callback
     *
     * @param string $name    'fulfilled' or 'rejected'
     * @param array  $options options
     * @param array  $results results
     *
     * @return void
     */
    private static function buildCallback($name, array &$options, array &$results)
    {
        if (!isset($options[$name])) {
            $options[$name] = static function ($val, $key) use (&$results) {
                $results[$key] = $val;
            };
            return;
        }
        $currentFn = $options[$name];
        $options[$name] = static function ($val, $key) use (&$results, $currentFn) {
            $currentFn($val, $key);
            $results[$key] = $val;
        };
    }
}
