<?php

namespace bdk\Debug\Collector\Pdo;

use SqlFormatter;       // optional library

/**
 * Holds information about a statement
 *
 * @property-read integer   $duration
 * @property-read integer   $errorCode
 * @property-read string    $errorMessage
 * @property-read Exception $exception
 * @property-read string    $id
 * @property-read boolean   $isPrepared
 * @property-read boolean   $isSuccess
 * @property-read integer   $memoryEnd
 * @property-read integer   $memoryStart
 * @property-read integer   $memoryUsage
 * @property-read array     $parameters
 * @property-read integer   $rowCount      number of rows affected by the last DELETE, INSERT, or UPDATE statement
 *                                          or number of rows returned by SELECT statement
 * @property-read string    $sql
 * @property-read string    $sqlWithParams
 * @property-read float     $timeEnd
 * @property-read float     $timeStart
 */
class StatementInfo
{

    protected $duration;
    protected $exception;
    protected $id;
    protected $memoryEnd;
    protected $memoryStart;
    protected $memoryUsage;
    protected $parameters;
    protected $rowCount;
    protected $sql;
    protected $timeEnd;
    protected $timeStart;

    /**
     * @param string $sql    SQL
     * @param array  $params bound params
     * @param string $id     Id of PdoStatement uniqid
     */
    public function __construct($sql, array $params = array(), $id = null)
    {
        $this->sql = $sql;
        $this->parameters = $params;
        $this->id = $id;
        $this->timeStart = \microtime(true);
        $this->memoryStart = \memory_get_usage(false);
        if (\class_exists('\SqlFormatter')) {
            // whitespace only, don't hightlight
            $this->sql = SqlFormatter::format($this->sql, false);
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
            'memoryUsage' => $this->memoryUsage,
            'parameters' => $this->parameters,
            'rowCount' => $this->rowCount,
            'sql' => $this->sql,
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
        $getter = 'get'.\ucfirst($name);
        if (\method_exists($this, $getter)) {
            return $this->$getter();
        } elseif (\preg_match('/^is[A-Z]/', $name) && \method_exists($this, $name)) {
            return $this->$name();
        } elseif (isset($this->$name)) {
            return $this->{$name};
        } else {
            return null;
        }
    }

    /**
     * @param \Exception|null $exception Exception (if statement threw exception)
     * @param integer         $rowCount  Number of rows affected by the last DELETE, INSERT, or UPDATE statement
     * @param float           $timeEnd   microtime statement returned
     * @param integer         $memoryEnd memory usage when statement returned
     *
     * @return void
     */
    public function end(\PDOException $exception = null, $rowCount = 0, $timeEnd = null, $memoryEnd = null)
    {
        $this->exception = $exception;
        $this->rowCount = $rowCount;
        $this->timeEnd = $timeEnd ?: \microtime(true);
        $this->memoryEnd = $memoryEnd ?: \memory_get_usage(false);
        $this->duration = $this->timeEnd - $this->timeStart;
        $this->memoryUsage = $this->memoryEnd - $this->memoryStart;
    }

    /**
     * Returns the exception's code
     *
     * @return string
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

    /**
     * Returns the SQL string with any parameters used embedded
     *
     * @param string $quotationChars Quotation character(s)
     *
     * @return string
     */
    protected function getSqlWithParams($quotationChars = '<>')
    {
        $len = \strlen($quotationChars);
        if ($len > 1) {
            $quoteLeft = \substr($quotationChars, 0, $len / 2);
            $quoteRight = \substr($quotationChars, $len / 2);
        } else {
            $quoteLeft = $quoteRight = $quotationChars;
        }

        $sql = $this->sql;
        foreach ($this->parameters as $k => $v) {
            $v = "$quoteLeft$v$quoteRight";
            if (!\is_numeric($k)) {
                $sql = \preg_replace('/'.$k.'\b/', $v, $sql);
            } else {
                $p = \strpos($sql, '?');
                $sql = \substr($sql, 0, $p) . $v. \substr($sql, $p + 1);
            }
        }
        return $sql;
    }

    /**
     * Checks if this is a prepared statement
     *
     * @return boolean
     */
    protected function isPrepared()
    {
        return $this->id !== null;
    }

    /**
     * Checks if the statement was successful
     *
     * @return boolean
     */
    protected function isSuccess()
    {
        return $this->exception === null;
    }
}
