<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector\SimpleCache;

use Exception;

/**
 * Holds information about a SimpleCache call
 *
 * @property-read int       $duration
 * @property-read Exception $exception
 * @property-read bool      $isSuccess
 * @property-read array     $keyOrKeys
 * @property-read int       $memoryEnd
 * @property-read int       $memoryStart
 * @property-read int       $memoryUsage
 * @property-read string    $method
 * @property-read float     $timeEnd
 * @property-read float     $timeStart
 */
class CallInfo
{

    protected $duration;
    protected $exception;
    protected $memoryEnd;
    protected $memoryStart;
    protected $memoryUsage;
    protected $method = '';
    protected $keyOrKeys;
    protected $timeEnd;
    protected $timeStart;

    /**
     * @param string $method    method called
     * @param mixed  $keyOrKeys aftected key or keys being
     */
    public function __construct($method, $keyOrKeys = null)
    {
        $this->method = $method;
        $this->keyOrKeys = $keyOrKeys;
        $this->timeStart = \microtime(true);
        $this->memoryStart = \memory_get_usage(false);
    }

    /**
     * Magic method
     *
     * @return array
     */
    public function __debugInfo()
    {
        return array(
            'duration' => $this->duration,
            'exception' => $this->exception,
            'memoryUsage' => $this->memoryUsage,
            'method' => $this->method,
            'keyOrKeys' => $this->keyOrKeys,
        );
    }

    /**
     * Magic getter
     *
     * @param string $name property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get' . \ucfirst($name);
        if (\method_exists($this, $getter)) {
            return $this->$getter();
        }
        if (\preg_match('/^is[A-Z]/', $name) && \method_exists($this, $name)) {
            return $this->$name();
        }
        if (isset($this->$name)) {
            return $this->{$name};
        }
        return null;
    }

    /**
     * @param Exception|null $exception Exception (if statement threw exception)
     *
     * @return void
     */
    public function end(Exception $exception = null)
    {
        $this->exception = $exception;
        $this->timeEnd = \microtime(true);
        $this->memoryEnd = \memory_get_usage(false);
        $this->duration = $this->timeEnd - $this->timeStart;
        $this->memoryUsage = $this->memoryEnd - $this->memoryStart;
    }

    /**
     * Checks if the statement was successful
     *
     * @return bool
     */
    protected function isSuccess()
    {
        return $this->exception === null;
    }
}
