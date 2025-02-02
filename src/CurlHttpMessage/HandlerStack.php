<?php

/**
 * @package   bdk\curlhttpmessage
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2023-2025 Brad Kent
 */

namespace bdk\CurlHttpMessage;

use bdk\Promise\PromiseInterface;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Manage the middleware stack and ultimate request handler
 *
 * @psalm-type handler = callable(RequestInterface, array): PromiseInterface
 */
class HandlerStack
{
    /** @var handler */
    private $handler;

    /** @var list<list{callable,string|null}> */
    private $stack = [];

    /** @var handler|null */
    private $stackCallable;

    /**
     * @param handler|null $handler Underlying HTTP handler.
     */
    public function __construct($handler = null)
    {
        $this->setHandler($handler);
    }

    /**
     * Call the stack
     *
     * @param mixed $inputVal Input value to pass through the stack
     *
     * @return ResponseInterface|PromiseInterface
     */
    public function __invoke($inputVal)
    {
        $stackCallable = $this->stackCallable();
        return $stackCallable($inputVal);
    }

    /**
     * Add a middleware after existing named middleware
     *
     * @param string                      $findName   Middleware to find
     * @param callable(callable):callable $middleware Middleware function
     * @param string                      $withName   Name to register for this middleware.
     *
     * @return void
     */
    public function after($findName, callable $middleware, $withName = null)
    {
        $this->assertName($withName);
        $this->splice($findName, $withName, $middleware, false);
        $this->stackCallable = null;
    }

    /**
     * Add a middleware before existing named middleware
     *
     * @param string                      $findName   Middleware to find
     * @param callable(callable):callable $middleware Middleware function
     * @param string                      $withName   Name to register for this middleware.
     *
     * @return void
     */
    public function before($findName, callable $middleware, $withName = null)
    {
        $this->assertName($withName);
        $this->splice($findName, $withName, $middleware, true);
        $this->stackCallable = null;
    }

    /**
     * Push a middleware to the stack
     *
     * @param callable(callable):callable $middleware Middleware function
     * @param string                      $name       Name to register for this middleware.
     *
     * @return void
     */
    public function push(callable $middleware, $name = null)
    {
        $this->assertName($name);
        $this->stack[] = [$middleware, $name];
        $this->stackCallable = null;
    }

    /**
     * Unshift a middleware to the bottom of the stack.
     *
     * @param callable(callable):callable $middleware Middleware function
     * @param string                      $name       Name to register for this middleware.
     *
     * @return void
     */
    public function unshift(callable $middleware, $name = null)
    {
        $this->assertName($name);
        \array_unshift($this->stack, [$middleware, $name]);
        $this->stackCallable = null;
    }

    /**
     * Remove a middleware by instance or name from the stack.
     *
     * @param callable|string $remove Middleware to remove by instance or name.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function remove($remove)
    {
        if (\is_string($remove) === false && \is_callable($remove) === false) {
            throw new InvalidArgumentException(\sprintf(
                __METHOD__ . ' requires a string or callable. %s provided',
                self::getDebugType($remove)
            ));
        }
        $index = \is_callable($remove) ? 0 : 1;
        $this->stack = \array_values(\array_filter(
            $this->stack,
            static function ($callableAndName) use ($index, $remove) {
                return $callableAndName[$index] !== $remove;
            }
        ));
        $this->stackCallable = null;
    }

    /**
     * Replace current request handler
     *
     * @param callable|null $handler Request handler callable
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function setHandler($handler)
    {
        if ($handler === null) {
            return;
        }
        if (\is_callable($handler) === false) {
            throw new InvalidArgumentException('handler must be a callable');
        }
        $this->handler = $handler;
        $this->stackCallable = null;
    }

    /**
     * Assert that provided name is string (or null) and unique
     *
     * @param string|null $name Name to test
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function assertName($name)
    {
        if ($name === null || $name === '') {
            return;
        }
        if (\is_string($name) === false) {
            throw new InvalidArgumentException(\sprintf(
                'Name should be a string. %s provided',
                self::getDebugType($name)
            ));
        }
        $found = \array_filter($this->stack, static function ($callableAndName) use ($name) {
            return $callableAndName[1] === $name;
        });
        if (\count($found) > 0) {
            throw new RuntimeException('Middleware already in stack: ' . $name);
        }
    }

    /**
     * Find index of named middleware
     *
     * @param string $name name assigned to middleware
     *
     * @return int
     *
     * @throws RuntimeException
     */
    private function findByName($name)
    {
        foreach ($this->stack as $index => $callableAndName) {
            if ($callableAndName[1] === $name) {
                return $index;
            }
        }
        throw new RuntimeException('Middleware not found: ' . $name);
    }

    /**
     * Gets the type name of a variable in a way that is suitable for debugging
     *
     * @param mixed $value The value being type checked
     *
     * @return string
     */
    protected static function getDebugType($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \strtolower(\gettype($value));
    }

    /**
     * Splices a function into the middleware list at a specific position.
     *
     * @param string   $findName   Name to search
     * @param string   $withName   Name to assign
     * @param callable $middleware Middleware
     * @param bool     $before     insert before name?
     *
     * @return void
     */
    private function splice($findName, $withName, callable $middleware, $before)
    {
        $index = $this->findByName($findName);
        $insert = [$middleware, $withName];
        $replacement = $before
            ? [$insert, $this->stack[$index]]
            : [$this->stack[$index], $insert];
        \array_splice($this->stack, $index, 1, $replacement);
    }

    /**
     * Compose the middleware and handler into a single callable function.
     *
     * @return handler
     */
    private function stackCallable()
    {
        if ($this->stackCallable !== null) {
            return $this->stackCallable;
        }
        $prevHandler = $this->handler;
        foreach (\array_reverse($this->stack) as $middlewareAndName) {
            /** @var callable(RequestInterface, array): PromiseInterface $prev */
            $prevHandler = $middlewareAndName[0]($prevHandler);
        }
        $this->stackCallable = $prevHandler;
        return $this->stackCallable;
    }
}
