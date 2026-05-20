<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector\SimpleCache;

use bdk\Debug\AbstractComponent;
use Exception;

/**
 * Holds information about a SimpleCache call
 *
 * @property-read int       $duration
 * @property-read Exception $exception
 * @property-read array     $keyOrKeys
 * @property-read int       $memoryEnd
 * @property-read int       $memoryStart
 * @property-read int       $memoryUsage
 * @property-read string    $method
 * @property-read bool|null $success
 * @property-read float     $timeEnd
 * @property-read float     $timeStart
 */
class CallInfo extends AbstractComponent
{
    /** @var int|null */
    protected $duration;

    /** @var Exception|null */
    protected $exception;

    /** @var array|null */
    protected $keyOrKeys;

    /** @var int|null */
    protected $memoryEnd;

    /** @var int */
    protected $memoryStart;

    /** @var int|null */
    protected $memoryUsage;

    /** @var string */
    protected $method = '';

    /** @var bool|null */
    protected $success = null;

    /** @var float|null */
    protected $timeEnd;

    /** @var float */
    protected $timeStart;

    /** @var list<string> */
    protected $readOnly = [
        'duration',
        'exception',
        'keyOrKeys',
        'memoryEnd',
        'memoryStart',
        'memoryUsage',
        'method',
        'success',
        'timeEnd',
        'timeStart',
    ];

    /**
     * @param string $method        method called
     * @param mixed  $keyOrKeys     affected key or keys
     * @param array  $initialValues (optional) initial values for properties
     */
    public function __construct($method, $keyOrKeys = null, array $initialValues = array())
    {
        $values = array(
            ['keyOrKeys', 'array|string|null', $keyOrKeys],
            ['memoryStart', 'int', \memory_get_usage(false)],
            ['method', 'string', $method],
            ['timeStart', 'float|int', \microtime(true)],
        );
        $typeAssertions = \array_column($values, 1, 0);
        $defaultValues = \array_column($values, 2, 0);
        // toss any unknown key/values
        $initialValues = \array_diff_key($initialValues, $defaultValues);
        // merge in arguments and give them priority over initialValues
        $initialValues = \array_merge($initialValues, array(
            'keyOrKeys' => $keyOrKeys,
            'method' => $method,
        ));
        $initialValues = \array_filter($initialValues, static function ($value) {
            return $value !== null;
        });
        $initialValues = \array_merge($defaultValues, $initialValues);
        foreach ($initialValues as $property => $value) {
            \bdk\Debug\Utility\PhpType::assertType($value, $typeAssertions[$property], $property);
            $this->$property = $value;
        }
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
            'keyOrKeys' => $this->keyOrKeys,
            'memoryUsage' => $this->memoryUsage,
            'method' => $this->method,
            'success' => $this->success,
        );
    }

    /**
     * @param bool|null      $success   whether the call was successful
     * @param Exception|null $exception Exception (if statement threw exception)
     *
     * @return void
     */
    public function end($success, $exception = null)
    {
        \bdk\Debug\Utility\PhpType::assertType($success, 'bool|null');
        \bdk\Debug\Utility\PhpType::assertType($exception, 'Exception|null');

        $this->timeEnd = \microtime(true);
        $this->duration = $this->timeEnd - $this->timeStart;
        $this->exception = $exception;
        $this->memoryEnd = \memory_get_usage(false);
        $this->memoryUsage = $this->memoryEnd - $this->memoryStart;
        $this->success = $success !== false && $exception === null;
    }
}
