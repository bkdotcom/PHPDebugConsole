<?php

namespace bdk\Promise;

use BadMethodCallException;
use bdk\Promise;
use bdk\Promise\PromiseInterface;
use Exception;
use LogicException;
use Throwable;

/**
 * Underlying helper methods for Promise implementation
 */
abstract class AbstractPromise implements PromiseInterface
{
     /** @var mixed */
    protected $result;

    /** @var string */
    protected $state = self::PENDING;

    /** @var callable|null */
    protected $waitFn;

    /** @var callable|null */
    protected $cancelFn;

    /** @var list<list{Promise,callable|null,callable|null}> */
    protected $handlers = [];

    /** @var list<self> Promise chain */
    protected $waitList = [];

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $method Inaccessible method name
     * @param array  $args   Arguments passed to method
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call($method, array $args)
    {
        if (\preg_match('/^is([A-Z][a-z]+)$/', $method) && empty($args)) {
            $args = [$this];
        }
        return $this->__callStatic($method, $args);
    }

    /**
     * Magic method... inaccessible static method called.
     *
     * @param string $method Inaccessible method name
     * @param array  $args   Arguments passed to method
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public static function __callStatic($method, array $args)
    {
        $isClass = 'bdk\\Promise\\Is';
        $utilClasses = [
            'bdk\\Promise\\Utils',
            'bdk\\Promise\\Create',
        ];
        if (\preg_match('/^is([A-Z][a-z]+)$/', $method, $matches) && \method_exists($isClass, \strtolower($matches[1]))) {
            return \call_user_func([$isClass, \strtolower($matches[1])], $args[0]);
        }
        foreach ($utilClasses as $class) {
            if (\method_exists($class, $method)) {
                return \call_user_func_array([$class, $method], $args);
            }
        }
        throw new BadMethodCallException(\sprintf('Undefined method: %s::%s()', \get_called_class(), $method));
    }

    /**
     * Helper method for FulfilledPromise and RejectedPromise
     *
     * @param callable $callback onFulfilled or onRejected callback
     *
     * @return Promise
     */
    protected function addQueuedCallback(callable $callback)
    {
        $queue = self::queue();
        $result = $this->result;
        $promise = new Promise([$queue, 'run']);
        $queue->add(static function () use ($promise, $result, $callback) {
            self::tryReject($promise, static function () use ($promise, $result, $callback) {
                $promise->resolve($callback($result));
            });
        });
        return $promise;
    }

    /**
     * Resolve/reject the promise
     *
     * @param string $state self::FULFILLED OR self::REJECTED
     * @param mixed  $value Resolve value or reject reason
     *
     * @return void
     *
     * @throws LogicException
     */
    protected function settle($state, $value)
    {
        if ($state === $this->state && $value === $this->result) {
            // Ignore calls with the same resolution.
            return;
        }

        $this->settleAssert($state, $value);

        // Clear out the state of the promise but stash the handlers.
        $handlers = $this->handlers;
        $this->state = $state;
        $this->result = $value;
        $this->handlers = [];
        $this->waitList = [];
        $this->waitFn = null;
        $this->cancelFn = null;

        $this->settleInvokeHandlers($state, $value, $handlers);
    }

    /**
     * Assert valid state and value
     *
     * @param string $state self::FULFILLED OR self::REJECTED
     * @param mixed  $value Resolve value or reject reason
     *
     * @return void
     *
     * @throws LogicException
     */
    private function settleAssert($state, $value)
    {
        if ($this->state !== self::PENDING) {
            throw $this->state === $state
                ? new LogicException('The promise is already ' . $state . '.')
                : new LogicException('Cannot change a ' . $this->state . ' promise to ' . $state);
        }
        if ($value === $this) {
            throw new LogicException('Cannot fulfill or reject a promise with itself');
        }
    }

    /**
     * Invoke or merge our handlers
     *
     * @param string $state    self::FULFILLED OR self::REJECTED
     * @param mixed  $value    resolve value or reject reason
     * @param array  $handlers list of Promise, onFulfilled, onRejected
     *
     * @return void
     */
    private function settleInvokeHandlers($state, $value, array $handlers)
    {
        /*
            could optimize..
            + if no handlers, return early
            + if $value instance of self (not extended from)
               then We can just merge our handlers onto the next promise.
               $value->handlers = \array_merge($value->handlers, $handlers);
        */
        if (self::isThenable($value)) {
            // value is some other thenable implementation
            //   invoke our handlers when value is resolved.
            $value->then(
                static function ($value) use ($handlers) {
                    self::invokeHandlers($handlers, 1, $value);
                },
                static function ($reason) use ($handlers) {
                    self::invokeHandlers($handlers, 2, $reason);
                }
            );
            return;
        }

        // not thenable.. resolve the handlers in the task queue.
        $index = $state === self::FULFILLED ? 1 : 2;
        self::queue()->add(static function () use ($handlers, $index, $value) {
            self::invokeHandlers($handlers, $index, $value);
        });
    }

    /**
     * Invoke promise's onFulfilled or onReject callbacks with value
     *
     * @param array $handlers promise, onFulfilled, onRejected
     * @param int   $index    1 (resolve) or 2 (reject).
     * @param mixed $value    Value to pass to the callback.
     *
     * @return void
     */
    private static function invokeHandlers(array $handlers, $index, $value)
    {
        foreach ($handlers as $handler) {
            self::invokeHandler($handler, $index, $value);
        }
    }

    /**
     * Invoke promise's onFulfilled or onReject callback with value
     *
     * @param array $handler promise, onFulfilled, onRejected
     * @param int   $index   1 (resolve) or 2 (reject).
     * @param mixed $value   Value to pass to the callback.
     *
     * @return void
     */
    private static function invokeHandler(array $handler, $index, $value)
    {
        /** @var PromiseInterface */
        $promise = $handler[0];

        self::tryReject($promise, static function () use ($promise, $handler, $index, $value) {
            if (isset($handler[$index])) {
                /*
                    If $callable throws an exception, $handler will be in the exception stack trace.
                    Since $handler contains a reference to the callable itself we get a circular reference.
                    We clear the $handler here to avoid that memory leak.
                */
                $callable = $handler[$index];
                unset($handler);
                $promise->resolve($callable($value));
                return;
            }
            if ($index === 1) {
                // Forward resolution values as-is.
                $promise->resolve($value);
                return;
            }
            // Forward rejections down the chain.
            $promise->reject($value);
        });
    }

    /**
     * Call the given callable if promise is not settled
     * Reject the promise if exception occurs
     *
     * @param Promise  $promise  Promise to operate on
     * @param callable $callable Function to call
     *
     * @return void
     */
    protected static function tryReject(Promise $promise, callable $callable)
    {
        if ($promise->isSettled()) {
            return;
        }
        try {
            $callable();
        } catch (Throwable $reason) {
            $promise->reject($reason);
        } catch (Exception $reason) {
            $promise->reject($reason);
        }
    }

    /**
     * Invoke the wait function
     *
     * @return void
     */
    protected function waitIfPending()
    {
        if ($this->state !== self::PENDING) {
            return;
        }

        $this->waitInvoke();
        self::queue()->run();

        /** @psalm-suppress RedundantCondition */
        if ($this->state === self::PENDING) {
            $this->reject('The wait callback did not resolve the promise');
        }
    }

    /**
     * Invoke waitFn or waitList
     *
     * @return void
     */
    private function waitInvoke()
    {
        if ($this->waitFn) {
            $this->waitInvokeFn();
            return;
        }
        if ($this->waitList) {
            $this->waitInvokeList();
            return;
        }
        $this->reject('Cannot wait on a promise that has no wait function.'
            . ' You must provide a wait function when constructing the promise'
            . ' to be able to wait on it.');
    }

    /**
     * Invoke the wait function
     *
     * @return void
     *
     * @throws Exception
     */
    private function waitInvokeFn()
    {
        $waitFn = $this->waitFn;
        $this->waitFn = null;
        try {
            $waitFn();
        } catch (Exception $e) {
            if ($this->state !== self::PENDING) {
                // The promise was already resolved,
                //   there's a problem in the application.
                throw $e;
            }
            // The promise has not been resolved yet,
            //   reject the promise with the exception.
            $this->reject($e);
        }
    }

    /**
     * Invoke the wait list
     *
     * @return void
     */
    private function waitInvokeList()
    {
        $waitList = $this->waitList;
        $this->waitList = [];
        \array_walk($waitList, static function ($result) {
            do {
                $result->waitIfPending();
                $result = $result->result;
            } while ($result instanceof Promise);

            if ($result instanceof PromiseInterface) {
                $result->wait(false);
            }
        });
    }
}
