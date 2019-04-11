<?php

namespace bdk\Debug\Collector\Pdo;

/**
 * Holds information about a statement
 *
 * @property-read integer   $duration
 * @property-read integer   $errorCode
 * @property-read string    $errorMessage
 * @property-read Exception $exception
 * @property-read string    $id
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
     * Check parameters for illegal (non UTF-8) strings, like Binary data.
     *
     * @param array $params
     *
     * @return mixed
     */
    /*
    public function checkParameters($params)
    {
        foreach ($params as &$param) {
            if (!\mb_check_encoding($param, 'UTF-8')) {
                $param = '[BINARY DATA]';
            }
        }
        return $params;
    }
    */

    /**
     * Returns the SQL string used for the query
     *
     * @return string
     */
    /*
    public function getSql()
    {
        return $this->sql;
    }
    */

    /**
     * Returns the number of rows affected/returned
     *
     * @return integer
     */
    /*
    public function getRowCount()
    {
        return $this->rowCount;
    }
    */

    /**
     * Returns an array of parameters used with the query
     *
     * @return array
     */
    /*
    public function getParameters()
    {
        // $params = array();
        // foreach ($this->parameters as $name => $param) {
        //     $params[$name] = \htmlentities($param, ENT_QUOTES, 'UTF-8', false);
        // }
        // return $params;
        return $this->parameters;
    }
    */

    /**
     * Returns the prepared statement id
     *
     * @return string
     */
    /*
    public function getPreparedId()
    {
        return $this->preparedId;
    }
    */

    /**
     * @return mixed
     */
    /*
    public function getStartTime()
    {
        return $this->startTime;
    }
    */

    /**
     * @return mixed
     */
    /*
    public function getEndTime()
    {
        return $this->endTime;
    }
    */

    /**
     * Returns the duration in seconds of the execution
     *
     * @return integer
     */
    /*
    public function getDuration()
    {
        return $this->duration;
    }
    */

    /**
     * @return mixed
     */
    /*
    public function getStartMemory()
    {
        return $this->startMemory;
    }
    */

    /**
     * @return mixed
     */
    /*
    public function getEndMemory()
    {
        return $this->endMemory;
    }
    */

    /**
     * Returns the exception triggered
     *
     * @return \Exception
     */
    /*
    public function getException()
    {
        return $this->exception;
    }
    */

    /**
     * Checks if this is a prepared statement
     *
     * @return boolean
     */
    public function isPrepared()
    {
        return $this->preparedId !== null;
    }

    /**
     * Checks if the statement was successful
     *
     * @return boolean
     */
    public function isSuccess()
    {
        return $this->exception === null;
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
}
