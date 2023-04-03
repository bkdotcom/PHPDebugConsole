<?php

declare(strict_types=1);

namespace bdk\CurlHttpMessage\Handler;

use bdk\CurlHttpMessage\CurlReqRes;
use bdk\Promise;
use bdk\Promise\PromiseInterface;
use Countable;
use Exception;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Handler that returns responses or throw exceptions from a queue.
 */
class Mock implements Countable
{
    /** @var array */
    private $queue = array();

    /** @var CyrlReqRes */
    private $lastCurlReqRes;

    /** @var callable|null */
    private $onFulfilled;

    /** @var callable|null */
    private $onRejected;

    /**
     * The passed in value must be an array of
     * {@see \Psr\Http\Message\ResponseInterface} objects, Exceptions,
     * callables, or Promises.
     *
     * @param array<int, mixed>|null $queue       The parameters to be passed to the append function, as an indexed array.
     * @param callable|null          $onFulfilled Callback to invoke when the return value is fulfilled.
     * @param callable|null          $onRejected  Callback to invoke when the return value is rejected.
     */
    public function __construct(array $queue = null, callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->onFulfilled = $onFulfilled;
        $this->onRejected = $onRejected;

        if ($queue) {
            $this->append($queue);
        }
    }

    /**
     * Handle CurlReqRes request
     *
     * @param CurlReqRes $curlReqRes CurlReqRes request/response
     *
     * @return PromiseInterface
     *
     * @throws OutOfBoundsException
     */
    public function __invoke(CurlReqRes $curlReqRes)
    {
        if (!$this->queue) {
            throw new OutOfBoundsException('Mock queue is empty');
        }

        $options = $curlReqRes->getOptions();
        if (isset($options['delay']) && \is_numeric($options['delay'])) {
            // delay in millisecond
            \usleep((int) $options['delay'] * 1000);
        }

        $this->lastCurlReqRes = $curlReqRes;

        $response = \array_shift($this->queue);

        if (\is_callable($response)) {
            $response = $response($curlReqRes->getRequest(), $curlReqRes->getOptions());
        }

        $response = $response instanceof Exception
            ? Promise::rejectionFor($response)
            : Promise::promiseFor($response);

        return $response->then(
            function (ResponseInterface $value) use ($curlReqRes) {
                $curlReqRes->setResponse($value);

                if ($this->onFulfilled) {
                    $callable = $this->onFulfilled;
                    $callable($value);
                }

                return $value;
            },
            function ($reason) {
                if ($this->onRejected) {
                    $callable = $this->onRejected;
                    $callable($reason);
                }
                return Promise::rejectionFor($reason);
            }
        );
    }

    /**
     * Adds one or more variadic requests, exceptions, callables, or promises
     * to the queue.
     *
     * @param mixed ...$values Value(s) to add to queue
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function append($values)
    {
        $values = \func_num_args() === 1 && \is_array($values) && \is_callable($values) === false
            ? $values
            : \func_get_args();
        foreach ($values as $value) {
            $isValid = $value instanceof ResponseInterface
                || $value instanceof PromiseInterface
                || $value instanceof Exception
                || \is_callable($value);
            if ($isValid === false) {
                $type = \is_object($value) ? \get_class($value) : \gettype($value);
                throw new InvalidArgumentException('Expected a Response, Promise, Throwable or callable. ' . $type . ' provided');
            }
            $this->queue[] = $value;
        }
    }

    /**
     * Get the last received request.
     *
     * @return RequestInterface
     */
    public function getLastRequest()
    {
        return $this->lastCurlReqRes->getRequest();
    }

    /**
     * Get the last received request options.
     *
     * @return array
     */
    public function getLastOptions()
    {
        return $this->lastCurlReqRes->getOptions();
    }

    /**
     * Returns the number of remaining items in the queue.
     *
     * Implements Countable
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->queue);
    }

    /**
     * Reset the queue
     *
     * @return void
     */
    public function reset()
    {
        $this->queue = array();
    }
}