<?php

/**
 * @package   bdk\promise
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\Promise;

/**
 * A task queue that executes tasks in a FIFO order.
 *
 * This task queue class is used to settle promises asynchronously and
 * maintains a constant stack size. You can use the task queue asynchronously
 * by calling the `run()` function of the global task queue in an event loop.
 *
 *     bdk\Promise::queue()->run();
 */
class TaskQueue
{
    /** @var bool */
    private $enableShutdown = true;

    /** @var callable[] */
    private $queue = array();

    /**
     * Constructor
     *
     * @param bool $withShutdown Process the queue on shutdown?
     */
    public function __construct($withShutdown = true)
    {
        if ($withShutdown) {
            \register_shutdown_function([$this, 'onShutdown']);
        }
    }

    /**
     * Is the queue empty?
     *
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->queue;
    }

    /**
     * Adds a task to the queue that will be executed when run() is called.
     *
     * @param callable $task callable to run
     *
     * @return void
     */
    public function add(callable $task)
    {
        $this->queue[] = $task;
    }

    /**
     * Execute all of the pending task in the queue.
     *
     * @return void
     */
    public function run()
    {
        while ($task = \array_shift($this->queue)) {
            /** @var callable $task */
            $task();
        }
    }

    /**
     * The task queue will be run and exhausted by default when the process
     * exits IF the exit is not the result of a PHP E_ERROR error.
     *
     * You can disable running the automatic shutdown of the queue by calling
     * this function. If you disable the task queue shutdown process, then you
     * MUST either run the task queue (as a result of running your event loop
     * or manually using the run() method) or wait on each outstanding promise.
     *
     * Note: This shutdown will occur before any destructors are triggered.
     *
     * @return void
     */
    public function disableShutdown()
    {
        $this->enableShutdown = false;
    }

    /**
     * Shutdown function
     *
     * @return void
     *
     * @internal
     */
    public function onShutdown()
    {
        if ($this->enableShutdown === false) {
            return;
        }
        // Only run the tasks if an E_ERROR didn't occur.
        $err = \error_get_last();
        if (!$err || ($err['type'] ^ E_ERROR)) {
            $this->run();
        }
    }
}
