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

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\Pdo\CompatTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\PubSub\Event;
use PDO as PdoBase;
use PDOException;

/**
 * A PDO decorator/proxy which traces statements
 */
class Pdo extends PdoBase
{
    use CompatTrait;
    use DatabaseTrait;

    /** @var PdoBase */
    protected $pdo;

    /**
     * Constructor
     *
     * @param PdoBase    $pdo   PDO instance
     * @param Debug|null $debug (optional) Specify PHPDebugConsole instance
     *                            if not passed, will create PDO channel on singleton instance
     *                            if root channel is specified, will create a PDO channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(PdoBase $pdo, $debug = null)
    {
        \bdk\Debug\Utility::assertType($debug, 'bdk\Debug');
        $this->traitInit($debug, 'PDO');
        $this->pdo = $pdo;
        $this->setAttribute(PdoBase::ATTR_STATEMENT_CLASS, ['bdk\Debug\Collector\Pdo\Statement', [$this]]);
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onDebugOutput'], 1);
    }

    /**
     * Initiates a transaction
     *
     * @link   http://php.net/manual/en/pdo.begintransaction.php
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function beginTransaction()
    {
        $this->debug->info('beginTransaction', $this->meta());
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @link   http://php.net/manual/en/pdo.commit.php
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function commit()
    {
        $this->debug->info('commit', $this->meta());
        return $this->pdo->commit();
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the database handle
     *
     * @link   http://php.net/manual/en/pdo.errorinfo.php
     * @return string a five characters alphanumeric identifier defined in the ANSI SQL-92 standard
     */
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
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
     * @return int|false PDO::exec returns the number of rows that were modified or deleted by the
     *    SQL statement you issued. If no rows were affected, PDO::exec returns 0. This function may
     *    return Boolean FALSE, but may also return a non-Boolean value which evaluates to FALSE.
     *    Please read the section on Booleans for more information
     */
    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        return $this->profileCall('exec', $statement, \func_get_args());
    }

    /**
     * Retrieve a database connection attribute
     *
     * @param int $attribute One of the PDO::ATTR_* constants
     *
     * @link   http://php.net/manual/en/pdo.getattribute.php
     * @return mixed A successful call returns the value of the requested PDO attribute.
     *    An unsuccessful call returns null.
     */
    #[\ReturnTypeWillChange]
    public function getAttribute($attribute)
    {
        return $this->pdo->getAttribute($attribute);
    }

    /**
     * Checks if inside a transaction
     *
     * @link   http://php.net/manual/en/pdo.intransaction.php
     * @return bool true if a transaction is currently active, and false if not.
     */
    #[\ReturnTypeWillChange]
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
    #[\ReturnTypeWillChange]
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
     * @return \PDOStatement|false If the database server successfully prepares the statement,
     * @link   http://php.net/manual/en/pdo.prepare.php
     *   PDO::prepare returns a PDOStatement object. If the database server cannot successfully prepare
     *   the statement, PDO::prepare returns FALSE or emits PDOException (depending on error handling).
     */
    #[\ReturnTypeWillChange]
    public function prepare($statement, $driverOptions = array())
    {
        return $this->pdo->prepare($statement, $driverOptions);
    }

    /*
        query() is in CompatTrait
    */

    /**
     * Quotes a string for use in a query.
     *
     * @param string $string        The string to be quoted.
     * @param int    $parameterType (optional) Provides a data type hint for drivers that have
     *                                   alternate quoting styles.
     *
     * @return string|false A quoted string that is theoretically safe to pass into an SQL statement.
     *   Returns `false` if the driver does not support quoting in this way.
     * @link   http://php.net/manual/en/pdo.quote.php
     */
    #[\ReturnTypeWillChange]
    public function quote($string, $parameterType = PdoBase::PARAM_STR)
    {
        return $this->pdo->quote($string, $parameterType);
    }

    /**
     * Rolls back a transaction
     *
     * @return bool
     * @link   http://php.net/manual/en/pdo.rollback.php
     */
    #[\ReturnTypeWillChange]
    public function rollBack()
    {
        $this->debug->info('rollBack', $this->meta());
        return $this->pdo->rollBack();
    }

    /**
     * Set an attribute
     *
     * @param int   $attribute Attribute const
     * @param mixed $value     Attribute value
     *
     * @return bool
     * @link   http://php.net/manual/en/pdo.setattribute.php
     */
    #[\ReturnTypeWillChange]
    public function setAttribute($attribute, $value)
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $this->debug;
        $debug->groupSummary(0);

        $nameParts = \explode('.', $debug->getCfg('channelName', Debug::CONFIG_DEBUG));
        $name = \end($nameParts);
        $driverName = $this->pdo->getAttribute(PdoBase::ATTR_DRIVER_NAME);

        $groupParams = \array_filter([
            ($name !== 'general' ? $name : 'PDO') . ' info',
            $driverName,
            $driverName !== 'sqlite'
                ? $this->pdo->getAttribute(PdoBase::ATTR_CONNECTION_STATUS)
                : null,
            $this->meta(array(
                'argsAsParams' => false,
                'level' => 'info',
            )),
        ]);
        \call_user_func_array([$debug, 'groupCollapsed'], $groupParams);
        $this->logRuntime($debug);
        $debug->groupEnd(); // groupCollapsed
        $debug->groupEnd(); // groupSummary
    }

    /**
     * Get current database / schema
     *
     * @return string|null
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via DatabaseTrait
     */
    private function currentDatabase()
    {
        try {
            // Returns the default (current) database name as a string in the utf8 character set
            $statement = $this->pdo->query('select database()');
            if ($statement) {
                return $statement->fetchColumn();
            }
        } catch (PDOException $e) {
            return null;
        }
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
    protected function profileCall($method, $sql, $args = [])
    {
        $info = new StatementInfo($sql);
        $isExceptionMode = $this->pdo->getAttribute(PdoBase::ATTR_ERRMODE) === PdoBase::ERRMODE_EXCEPTION;

        $result = null;
        $exception = null;
        try {
            $result = \call_user_func_array([$this->pdo, $method], $args);
            if (!$isExceptionMode && $result === false) {
                $error = $this->pdo->errorInfo();
                $exception = new PDOException($error[2], $error[0]);
            }
        } catch (PDOException $e) {
            $exception = $e;
        }

        $info->end($exception);
        $this->addStatementInfo($info);

        if ($isExceptionMode && $exception !== null) {
            throw $exception;
        }
        return $result;
    }

    /**
     * Return server information
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via DatabaseTrait
     */
    protected function serverInfo()
    {
        return $this->pdoServerInfo($this->pdo);
    }
}
