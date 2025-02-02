<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

use bdk\Promise;
use bdk\Promise\Exception\AggregateException;
use bdk\Promise\Exception\RejectionException;
use bdk\Promise\PromiseInterface;
use Exception;
use InvalidArgumentException;
use Throwable;

/**
 * Static methods
 */
final class Utils
{
    /** @var TaskQueue|null */
    private static $queue;

    /**
     * Assert that a value is of a certain type
     *
     * Support extreme range of PHP versions : 5.4 - 8.4 (and beyond)
     * `MyObj $obj = null` has been deprecated in PHP 8.4
     * must now be `?MyObj $obj = null` (which is a php 7.1 feature)
     * Workaround - remove type-hint when we allow null (not ideal) and call assertType
     * When we drop support for php < 7.1, we can remove this method and do proper type-hinting
     *
     * @param mixed  $value     Value to test
     * @param string $type      "array", "callable", "object", or className
     * @param bool   $allowNull (true) allow null?
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public static function assertType($value, $type, $allowNull = true)
    {
        if ($allowNull && $value === null) {
            return;
        }
        if (self::assertTypeCheck($value, $type)) {
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            'Expected %s%s, got %s',
            $type,
            $allowNull ? ' (or null)' : '',
            self::getDebugType($value)
        ));
    }

    /**
     * Get the global task queue used for promise resolution.
     *
     * This task queue MUST be run in an event loop in order for promises to be
     * settled asynchronously. It will be automatically run when synchronously
     * waiting on a promise.
     *
     * <code>
     * while ($eventLoop->isRunning()) {
     *     bdk\Promise::queue()->run();
     * }
     * </code>
     *
     * @return TaskQueueInterface
     */
    public static function queue()
    {
        if (!self::$queue) {
            self::$queue = new TaskQueue();
        }
        return self::$queue;
    }

    /**
     * Adds a function to run in the task queue when it is next `run()` and
     * returns a promise that is fulfilled or rejected with the result.
     *
     * @param callable $task Task function to run.
     *
     * @return PromiseInterface
     */
    public static function task(callable $task)
    {
        $queue = self::queue();
        $promise = new Promise([$queue, 'run']);
        $queue->add(static function () use ($task, $promise) {
            try {
                if ($promise->isPending()) {
                    $promise->resolve($task());
                }
            } catch (Throwable $e) {
                $promise->reject($e);
            } catch (Exception $e) {
                $promise->reject($e);
            }
        });

        return $promise;
    }

    /**
     * Synchronously waits on a promise to resolve and returns an inspection
     * state array.
     *
     * Returns a state associative array containing a "state" key mapping to a
     * valid promise state. If the state of the promise is "fulfilled", the
     * array will contain a "value" key mapping to the fulfilled value of the
     * promise. If the promise is rejected, the array will contain a "reason"
     * key mapping to the rejection reason of the promise.
     *
     * @param PromiseInterface $promise Promise or value.
     *
     * @return array
     */
    public static function inspect(PromiseInterface $promise)
    {
        try {
            return array(
                'state' => Promise::FULFILLED,
                'value' => $promise->wait(),
            );
        } catch (RejectionException $e) {
            return array('state' => PromiseInterface::REJECTED, 'reason' => $e->getReason());
        } catch (Throwable $e) {
            return array('state' => PromiseInterface::REJECTED, 'reason' => $e);
        } catch (Exception $e) {
            return array('state' => PromiseInterface::REJECTED, 'reason' => $e);
        }
    }

    /**
     * Waits on all of the provided promises, but does not unwrap rejected
     * promises as thrown exception.
     *
     * Returns an array of inspection state arrays.
     *
     * @param PromiseInterface[] $promises Traversable of promises to wait upon.
     *
     * @return array
     *
     * @see inspect for the inspection state array format.
     */
    public static function inspectAll($promises)
    {
        $results = array();
        foreach ($promises as $key => $promise) {
            $results[$key] = self::inspect($promise);
        }
        return $results;
    }

    /**
     * Waits on all of the provided promises and returns the fulfilled values.
     *
     * Returns an array that contains the value of each promise (in the same
     * order the promises were provided). An exception is thrown if any of the
     * promises are rejected.
     *
     * @param iterable<PromiseInterface> $promises Iterable of PromiseInterface objects to wait on.
     *
     * @return array
     *
     * @throws \Exception on error
     * @throws \Throwable on error in PHP >=7
     */
    public static function unwrap($promises)
    {
        $results = array();
        foreach ($promises as $key => $promise) {
            $results[$key] = $promise->wait();
        }
        return $results;
    }

    /**
     * Given an array of promises, return a promise that is fulfilled when all
     * the items in the array are fulfilled.
     *
     * The promise's fulfillment value is an array with fulfillment values at
     * respective positions to the original array. If any promise in the array
     * rejects, the returned promise is rejected with the rejection reason.
     *
     * @param mixed $promises  Promises or values.
     * @param bool  $recursive If true, resolves new promises that might have been added to the stack during its own resolution.
     *
     * @return PromiseInterface
     */
    public static function all($promises, $recursive = false)
    {
        $results = array();
        $promise = Each::of(
            $promises,
            static function ($value, $index) use (&$results) {
                $results[$index] = $value;
            },
            // @phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
            static function ($reason, $index, PromiseInterface $aggregate) {
                $aggregate->reject($reason);
            }
        )->then(static function () use (&$results) {
            \ksort($results);
            return $results;
        });

        if ($recursive === true) {
            $promise = $promise->then(static function ($results) use ($recursive, &$promises) {
                foreach ($promises as $promise) {
                    if (Promise::isPending($promise)) {
                        return self::all($promises, $recursive);
                    }
                }
                return $results;
            });
        }

        return $promise;
    }

    /**
     * Initiate a competitive race between multiple promises or values (values
     * will become immediately fulfilled promises).
     *
     * When count amount of promises have been fulfilled, the returned promise
     * is fulfilled with an array that contains the fulfillment values of the
     * winners in order of resolution.
     *
     * This promise is rejected with a {@see AggregateException} if the number
     * of fulfilled promises is less than the desired $count.
     *
     * @param int   $count    Total number of promises.
     * @param mixed $promises Promises or values.
     *
     * @return PromiseInterface
     */
    public static function some($count, $promises)
    {
        $results = array();
        $rejections = array();

        return Each::of(
            $promises,
            static function ($value, $index, PromiseInterface $p) use (&$results, $count) {
                if (Promise::isSettled($p)) {
                    return;
                }
                $results[$index] = $value;
                if (\count($results) >= $count) {
                    $p->resolve(null);
                }
            },
            static function ($reason) use (&$rejections) {
                $rejections[] = $reason;
            }
        )->then(
            static function () use (&$results, &$rejections, $count) {
                if (\count($results) !== $count) {
                    throw new AggregateException('Not enough promises to fulfill count', $rejections);
                }
                \ksort($results);
                return \array_values($results);
            }
        );
    }

    /**
     * Like some(), with 1 as count. However, if the promise fulfills, the
     * fulfillment value is not an array of 1 but the value directly.
     *
     * @param mixed $promises Promises or values.
     *
     * @return PromiseInterface
     */
    public static function any($promises)
    {
        return self::some(1, $promises)->then(static function ($values) {
            return $values[0];
        });
    }

    /**
     * Returns a promise that is fulfilled when all of the provided promises have
     * been fulfilled or rejected.
     *
     * The returned promise is fulfilled with an array of inspection state arrays.
     *
     * @param mixed $promises Promises or values.
     *
     * @return PromiseInterface
     *
     * @see inspect for the inspection state array format.
     */
    public static function settle($promises)
    {
        $results = array();
        return Each::of(
            $promises,
            static function ($value, $index) use (&$results) {
                $results[$index] = array(
                    'state' => PromiseInterface::FULFILLED,
                    'value' => $value,
                );
            },
            static function ($reason, $index) use (&$results) {
                $results[$index] = array(
                    'reason' => $reason,
                    'state' => PromiseInterface::REJECTED,
                );
            }
        )->then(static function () use (&$results) {
            \ksort($results);
            return $results;
        });
    }

    /**
     * Test if value is of a certain type
     *
     * @param mixed  $value Value to test
     * @param string $type  "array", "callable", "object", or className
     *
     * @return bool
     */
    private static function assertTypeCheck($value, $type)
    {
        switch ($type) {
            case 'array':
                return \is_array($value);
            case 'callable':
                return \is_callable($value);
            case 'object':
                return \is_object($value);
            default:
                return \is_a($value, $type);
        }
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value Value to inspect
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \strtolower(\gettype($value));
    }
}
