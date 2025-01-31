<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector;

use bdk\Debug\AbstractComponent;
use Exception;

/**
 * Holds information about a SQL statement
 *
 * @property-read integer   $duration      duration (in seconds)
 * @property-read integer   $errorCode
 * @property-read string    $errorMessage
 * @property-read Exception $exception
 * @property-read boolean   $isSuccess
 * @property-read integer   $memoryEnd
 * @property-read integer   $memoryStart
 * @property-read integer   $memoryUsage
 * @property-read array     $params
 * @property-read integer   $rowCount      number of rows affected by the last DELETE, INSERT, or UPDATE statement
 *                                          or number of rows returned by SELECT statement
 * @property-read string    $sql
 * @property-read string    $sqlWithParams
 * @property-read float     $timeEnd
 * @property-read float     $timeStart
 * @property-read array     $types
 */
class StatementInfo extends AbstractComponent
{
    /** @var float|null query duration (in seconds) */
    protected $duration;
    /** @var Exception|null */
    protected $exception;
    /** @var bool */
    protected $isSuccess = false;
    /** @var float|null */
    protected $memoryEnd;
    /** @var float */
    protected $memoryStart;
    /** @var int|null */
    protected $memoryUsage;
    /** @var array */
    protected $params = array();
    /** @var int|null */
    protected $rowCount;
    /** @var string */
    protected $sql;
    /** @var float|null */
    protected $timeEnd;
    /** @var float */
    protected $timeStart;
    /** @var array */
    protected $types;

    /** @var list<string> */
    protected $readOnly = [
        'duration',
        'exception',
        'isSuccess',
        'memoryEnd',
        'memoryStart',
        'memoryUsage',
        'params',
        'rowCount',
        'sql',
        'timeEnd',
        'timeStart',
        'types',
    ];

    /**
     * @param string     $sql    SQL
     * @param array|null $params (optional) bound params
     * @param array|null $types  (optional) bound types
     */
    public function __construct($sql, $params = array(), $types = array())
    {
        \bdk\Debug\Utility::assertType($params, 'array');
        \bdk\Debug\Utility::assertType($types, 'array');

        $this->memoryStart = \memory_get_usage(false);
        $this->params = $params ?: array();
        $this->sql = \trim($sql);
        $this->timeStart = \microtime(true);
        $this->types = $types ?: array();
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
            'isSuccess' => $this->isSuccess,
            'memoryUsage' => $this->memoryUsage,
            'params' => $this->params,
            'rowCount' => $this->rowCount,
            'sql' => $this->sql,
            'types' => $this->types,
        );
    }

    /**
     * End the statement
     *
     * @param Exception|null $exception (optional) Exception (if statement threw exception)
     * @param int            $rowCount  (optional) Number of rows affected by the last DELETE, INSERT, or UPDATE statement
     *
     * @return void
     */
    public function end($exception = null, $rowCount = null)
    {
        \bdk\Debug\Utility::assertType($exception, 'Exception');

        $this->exception = $exception;
        $this->rowCount = $rowCount;
        $this->timeEnd = \microtime(true);
        $this->memoryEnd = \memory_get_usage(false);
        $this->duration = $this->timeEnd - $this->timeStart;
        $this->memoryUsage = \max($this->memoryEnd - $this->memoryStart, 0);
        $this->isSuccess = $exception === null;
    }

    /**
     * Set query's duration
     *
     * @param float $duration duration (in sec)
     *
     * @return void
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    /**
     * Set query's memory usage
     *
     * @param int $memory memory (in bytes)
     *
     * @return void
     */
    public function setMemoryUsage($memory)
    {
        $this->memoryUsage = $memory;
    }

    /**
     * Set bound params
     *
     * @param array|null $params parameter values
     *
     * @return void
     */
    public function setParams($params = array())
    {
        $this->params = $params ?: array();
    }

    /**
     * Returns the exception's code
     *
     * @return int|string
     */
    protected function getErrorCode()
    {
        return $this->exception
            ? $this->exception->getCode()
            : 0;
    }

    /**
     * Returns the exception's message
     *
     * @return string
     */
    protected function getErrorMessage()
    {
        return $this->exception
            ? $this->exception->getMessage()
            : '';
    }
}
