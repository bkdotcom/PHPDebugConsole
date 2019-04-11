<?php

namespace bdk\Debug\Collector\Pdo;

use PDO as PdoBase;
use PDOException;
use bdk\Debug;

/**
 * A PDO proxy which traces statements
 */
class Pdo extends PdoBase
{
    public $debug;
    protected $pdo;
    protected $executedStatements = array();

    /**
     * Constructor
     *
     * @param \PDO  $pdo   PDO instance
     * @param Debug $debug (optional) Specify PHPDebugConsole instance
     *                        if not passed, will create PDO channnel on singleton instance
     *                        if root channel is specifyed, will create a PDO channel
     */
    public function __construct(\PDO $pdo, Debug $debug = null)
    {
        if (!$debug) {
            $debug = \bdk\Debug::_getChannel('PDO');
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('PDO');
        }
        $this->pdo = $pdo;
        $this->debug = $debug;
        $this->pdo->setAttribute(PdoBase::ATTR_STATEMENT_CLASS, array('bdk\Debug\Collector\Pdo\Statement', array($this)));
    }

    /**
     * Magic method... inaccessible method called.
     *
     * @param string $name method name
     * @param array  $args method arguments
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        return \call_user_func_array(array($this->pdo, $name), $args);
    }

    /**
     * Magic Getter
     *
     * @param string $name property to get
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->pdo->{$name};
    }

    /**
     * Magic setter
     *
     * @param string $name  property to set
     * @param mixed  $value property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $this->pdo->$name = $value;
    }

    /**
     * Initiates a transaction
     *
     * @link   http://php.net/manual/en/pdo.begintransaction.php
     * @return boolean
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @link   http://php.net/manual/en/pdo.commit.php
     * @return boolean
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Fetch extended error information associated with the last operation on the database handle
     *
     * @link   http://php.net/manual/en/pdo.errorinfo.php
     * @return array PDO::errorInfo returns an array of error information
     */
    public function errorCode()
    {
        return $this->pdo->errorCode();
    }

    /**
     * Fetch extended error information associated with the last operation on the database handle
     *
     * @link   http://php.net/manual/en/pdo.errorinfo.php
     * @return array PDO::errorInfo returns an array of error information
     */
    public function errorInfo()
    {
        return $this->pdo->errorInfo();
    }

    /**
     * Execute an SQL statement and return the number of affected rows
     *
     * @param string $statement The SQL statement to prepare and execute.
     *
     * @link   http://php.net/manual/en/pdo.exec.php
     * @return integer|boolean PDO::exec returns the number of rows that were modified or deleted by the
     *    SQL statement you issued. If no rows were affected, PDO::exec returns 0. This function may
     *    return Boolean FALSE, but may also return a non-Boolean value which evaluates to FALSE.
     *    Please read the section on Booleans for more information
     */
    public function exec($statement)
    {
        return $this->profileCall('exec', $statement, \func_get_args());
    }

    /**
     * Retrieve a database connection attribute
     *
     * @param integer $attribute One of the PDO::ATTR_* constants
     *
     * @link   http://php.net/manual/en/pdo.getattribute.php
     * @return mixed A successful call returns the value of the requested PDO attribute.
     *    An unsuccessful call returns null.
     */
    public function getAttribute($attribute)
    {
        return $this->pdo->getAttribute($attribute);
    }

    /**
     * Checks if inside a transaction
     *
     * @link   http://php.net/manual/en/pdo.intransaction.php
     * @return boolean true if a transaction is currently active, and false if not.
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Returns the ID of the last inserted row or sequence value
     *
     * @param string $name (optional)
     *
     * @link   http://php.net/manual/en/pdo.lastinsertid.php
     * @return string If a sequence name was not specified for the name parameter, PDO::lastInsertId
     *   returns a string representing the row ID of the last row that was inserted into the database.
     */
    public function lastInsertId($name = null)
    {
        return $this->pdo->lastInsertId($name);
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param string $statement     This must be a valid SQL statement template for the target DB server.
     * @param array  $driverOptions [optional] This array holds one or more key=&gt;value pairs to
     * set attribute values for the PDOStatement object that this method returns.
     *
     * @return TraceablePDOStatement|boolean If the database server successfully prepares the statement,
     * @link   http://php.net/manual/en/pdo.prepare.php
     *   PDO::prepare returns a PDOStatement object. If the database server cannot successfully prepare
     *   the statement, PDO::prepare returns FALSE or emits PDOException (depending on error handling).
     */
    public function prepare($statement, $driverOptions = array())
    {
        return $this->pdo->prepare($statement, $driverOptions);
    }

    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object
     *
     * @param string $statement The SQL statement to prepare and execute.
     *
     * @return TraceablePDOStatement|boolean PDO::query returns a PDOStatement object, or FALSE on
     * failure.
     * @link   http://php.net/manual/en/pdo.query.php
     */
    public function query($statement)
    {
        return $this->profileCall('query', $statement, \func_get_args());
    }

    /**
     * Quotes a string for use in a query.
     *
     * @param string  $string        The string to be quoted.
     * @param integer $parameterType (optional) Provides a data type hint for drivers that have
     *                                   alternate quoting styles.
     *
     * @return string|boolean A quoted string that is theoretically safe to pass into an SQL statement.
     *   Returns FALSE if the driver does not support quoting in this way.
     * @link   http://php.net/manual/en/pdo.quote.php
     */
    public function quote($string, $parameterType = PdoBase::PARAM_STR)
    {
        return $this->pdo->quote($string, $parameterType);
    }

    /**
     * Rolls back a transaction
     *
     * @return boolean
     * @link   http://php.net/manual/en/pdo.rollback.php
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    /**
     * Set an attribute
     *
     * @param integer $attribute Attribute const
     * @param mixed   $value     Attribute value
     *
     * @return boolean
     * @link   http://php.net/manual/en/pdo.setattribute.php
     */
    public function setAttribute($attribute, $value)
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * Profiles a call to a PDO method
     *
     * @param string $method PDO method
     * @param string $sql    sql statement
     * @param array  $args   method args
     *
     * @return mixed The result of the call
     * @throws PDOException
     */
    protected function profileCall($method, $sql, array $args)
    {
        $trace = new TracedStatement($sql);
        $trace->start();

        $exception = null;
        try {
            $result = \call_user_func_array(array($this->pdo, $method), $args);
        } catch (PDOException $e) {
            $exception = $e;
        }

        if ($this->pdo->getAttribute(PdoBase::ATTR_ERRMODE) !== PdoBase::ERRMODE_EXCEPTION && $result === false) {
            $error = $this->pdo->errorInfo();
            $exception = new PDOException($error[2], $error[0]);
        }

        $trace->end($exception);
        // $this->addExecutedStatement($trace);

        if ($this->pdo->getAttribute(PdoBase::ATTR_ERRMODE) === PdoBase::ERRMODE_EXCEPTION && $exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Adds an executed TracedStatement
     *
     * @param TracedStatement $stmt
     */
    /*
    public function addExecutedStatement(TracedStatement $stmt)
    {
        $this->executedStatements[] = $stmt;
    }
    */

    /**
     * Returns the accumulated execution time of statements
     *
     * @return integer
     */
    /*
    public function getAccumulatedStatementsDuration()
    {
        return \array_reduce($this->executedStatements, function ($v, $s) { return $v + $s->getDuration(); });
    }
    */

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return integer
     */
    /*
    public function getMemoryUsage()
    {
        return \array_reduce($this->executedStatements, function ($v, $s) { return $v + $s->getMemoryUsage(); });
    }
    */

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return integer
     */
    /*
    public function getPeakMemoryUsage()
    {
        return \array_reduce($this->executedStatements, function ($v, $s) { $m = $s->getEndMemory(); return $m > $v ? $m : $v; });
    }
    */

    /**
     * Returns the list of executed statements as TracedStatement objects
     *
     * @return array
     */
    /*
    public function getExecutedStatements()
    {
        return $this->executedStatements;
    }
    */

    /**
     * Returns the list of failed statements
     *
     * @return array
     */
    /*
    public function getFailedExecutedStatements()
    {
        return \array_filter($this->executedStatements, function ($s) { return !$s->isSuccess(); });
    }
    */
}
