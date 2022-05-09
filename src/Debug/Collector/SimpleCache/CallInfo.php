<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector\SimpleCache;

use bdk\Debug\AbstractComponent;
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
class CallInfo extends AbstractComponent
{
    protected $duration;
    protected $exception;
    protected $keyOrKeys;
    protected $memoryEnd;
    protected $memoryStart;
    protected $memoryUsage;
    protected $method = '';
    protected $timeEnd;
    protected $timeStart;

    protected $readOnly = array(
        'duration',
        'exception',
        'keyOrKeys',
        'memoryEnd',
        'memoryStart',
        'memoryUsage',
        'method',
        'timeEnd',
        'timeStart',
    );

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
