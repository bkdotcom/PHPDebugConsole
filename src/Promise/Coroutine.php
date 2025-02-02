<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use bdk\Promise;
use bdk\Promise\Create;
use bdk\Promise\PromiseInterface;
use Closure;
use Exception;
use Throwable;

/**
 * Creates a promise that is resolved using a generator that yields values or
 * promises (somewhat similar to C#'s async keyword).
 *
 * When called, the Coroutine::of method will start an instance of the generator
 * and returns a promise that is fulfilled with its final yielded value.
 *
 * Control is returned back to the generator when the yielded promise settles.
 * This can lead to less verbose code when doing lots of sequential async calls
 * with minimal processing in between.
 *
 *     use GuzzleHttp\Promise;
 *
 *     function createPromise($value) {
 *         return new Promise\FulfilledPromise($value);
 *     }
 *
 *     $promise = Promise\Coroutine::of(function () {
 *         $value = (yield createPromise('a'));
 *         try {
 *             $value = (yield createPromise($value . 'b'));
 *         } catch (\Exception $e) {
 *             // The promise was rejected.
 *         }
 *         yield $value . 'c';
 *     });
 *
 *     // Outputs "abc"
 *     $promise->then(function ($v) { echo $v; });
 *
 * @link http://bluebirdjs.com/docs/api/promise.coroutine.html
 */
final class Coroutine implements PromiseInterface
{
    /** @var PromiseInterface|null */
    private $currentPromise;

    /** @var \Generator */
    private $generator;

    /** @var Promise */
    private $promise;

    /**
     * Constructor
     *
     * @param callable $generatorFn Generator function to wrap into a promise.
     */
    public function __construct(callable $generatorFn)
    {
        $this->generator = $generatorFn();
        $this->promise = new Promise(function () {
            while ($this->currentPromise) {
                $this->currentPromise->wait();
            }
        });
        $this->tryCatch(function () {
            $this->nextCoroutine($this->generator->current());
        });
    }

    /**
     * Create a new coroutine.
     *
     * @param callable $generatorFn Generator function to wrap into a promise.
     *
     * @return self
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public static function of(callable $generatorFn)
    {
        return new self($generatorFn);
    }

    /**
     * {@inheritDoc}
     */
    public function then($onFulfilled = null, $onRejected = null)
    {
        \bdk\Promise\Utils::assertType($onFulfilled, 'callable');
        \bdk\Promise\Utils::assertType($onRejected, 'callable');

        return $this->promise->then($onFulfilled, $onRejected);
    }

    /**
     * {@inheritDoc}
     */
    public function otherwise(callable $onRejected)
    {
        return $this->promise->otherwise($onRejected);
    }

    /**
     * {@inheritDoc}
     */
    public function wait($unwrap = true)
    {
        return $this->promise->wait($unwrap);
    }

    /**
     * {@inheritDoc}
     */
    public function getState()
    {
        return $this->promise->getState();
    }

    /**
     * {@inheritDoc}
     */
    public function resolve($value)
    {
        $this->promise->resolve($value);
    }

    /**
     * {@inheritDoc}
     */
    public function reject($reason)
    {
        $this->promise->reject($reason);
    }

    /**
     * {@inheritDoc}
     */
    public function cancel()
    {
        $this->currentPromise->cancel();
        $this->promise->cancel();
    }

    /**
     * @param mixed $value Resolved value
     *
     * @return void
     *
     * @internal
     */
    public function handleSuccess($value)
    {
        $this->currentPromise = null;
        $this->tryCatch(function () use ($value) {
            $nextYield = $this->generator->send($value);
            if ($this->generator->valid()) {
                $this->nextCoroutine($nextYield);
                return;
            }
            $this->promise->resolve($value);
        });
    }

    /**
     * @param mixed $reason Failure reason
     *
     * @return void
     *
     * @internal
     */
    public function handleFailure($reason)
    {
        $this->currentPromise = null;
        $this->tryCatch(function () use ($reason) {
            $nextYield = $this->generator->throw(Create::exceptionFor($reason));
            // The throw was caught, so keep iterating on the coroutine
            $this->nextCoroutine($nextYield);
        });
    }

    /**
     * @param mixed $yielded Promise or value
     *
     * @return void
     */
    private function nextCoroutine($yielded)
    {
        $this->currentPromise = Create::promiseFor($yielded)
            ->then(
                [$this, 'handleSuccess'],
                [$this, 'handleFailure']
            );
    }

    /**
     * Wrap passed Closure in try/catch and invoke it
     *
     * @param Closure $func Closure
     *
     * @return void
     */
    private function tryCatch(Closure $func)
    {
        try {
            $func();
        } catch (Exception $exception) {
            $this->promise->reject($exception);
        } catch (Throwable $throwable) {
            $this->promise->reject($throwable);
        }
    }
}
