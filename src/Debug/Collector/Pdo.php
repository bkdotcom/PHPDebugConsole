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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\Pdo\MethodSignatureCompatTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Plugin\Highlight;
use bdk\PubSub\Event;
use PDO as PdoBase;
use PDOException;

/**
 * A PDO decorator/proxy which traces statements
 */
class Pdo extends PdoBase
{
    use MethodSignatureCompatTrait;

    private $debug;
    protected $pdo;
    protected $loggedStatements = array();
    protected $icon = 'fa fa-database';

    /**
     * Constructor
     *
     * @param PdoBase $pdo   PDO instance
     * @param Debug   $debug (optional) Specify PHPDebugConsole instance
     *                         if not passed, will create PDO channnel on singleton instance
     *                         if root channel is specified, will create a PDO channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(PdoBase $pdo, Debug $debug = null)
    {
        if (!$debug) {
            $debug = Debug::_getChannel('PDO', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('PDO', array('channelIcon' => $this->icon));
        }
        $this->pdo = $pdo;
        $this->debug = $debug;
        $this->pdo->setAttribute(PdoBase::ATTR_STATEMENT_CLASS, array('bdk\Debug\Collector\Pdo\Statement', array($this)));
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, array($this, 'onDebugOutput'), 1);
        $this->debug->addPlugin(new Highlight());
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
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $event->getSubject();
        $driverName = $this->pdo->getAttribute(PdoBase::ATTR_DRIVER_NAME);

        // parse server info
        $serverInfo = $driverName !== 'sqlite'
            ? $this->pdo->getAttribute(PdoBase::ATTR_SERVER_INFO)
            : '';
        $matches = array();
        \preg_match_all('/([^:]+): ([a-zA-Z0-9.]+)\s*/', $serverInfo, $matches);
        $serverInfo = \array_map(function ($val) {
            /** @psalm-suppress InvalidOperand */
            return $val * 1;
        }, \array_combine($matches[1], $matches[2]));
        $serverInfo['Version'] = $this->pdo->getAttribute(PdoBase::ATTR_SERVER_VERSION);
        \ksort($serverInfo);

        $status = $driverName !== 'sqlite'
            ? $this->pdo->getAttribute(PdoBase::ATTR_CONNECTION_STATUS)
            : null;

        $debug->groupSummary(0);
        $nameParts = \explode('.', $debug->getCfg('channelName', Debug::CONFIG_DEBUG));
        $name = \end($nameParts);
        $groupParams = array(
            $name . ' info',
            $driverName,
        );
        if ($status) {
            $groupParams[] = $status;
        }
        $groupParams[] = $debug->meta(array(
            'argsAsParams' => false,
            'icon' => $this->icon,
            'level' => 'info',
        ));
        \call_user_func_array(array($debug, 'groupCollapsed'), $groupParams);
        try {
            // Returns the default (current) database name as a string in the utf8 character set
            $statement = $this->pdo->query('select database()');
            if ($statement) {
                $database = $statement->fetchColumn();
                if ($database) {
                    $debug->log('database', $database);
                }
            }
        } catch (PDOException $e) {
            // no such method
        }
        $debug->log('logged operations: ', \count($this->loggedStatements));
        $debug->time('total time', $this->getTimeSpent());
        $debug->log('max memory usage', $debug->utility->getBytes($this->getPeakMemoryUsage()));
        $debug->log('server info', $serverInfo);
        if ($this->prettified() === false) {
            $debug->info('install jdorn/sql-formatter to prettify logged sql statemeents');
        }
        $debug->groupEnd(); // groupCollapsed
        $debug->groupEnd(); // groupSummary
    }

    /**
     * Initiates a transaction
     *
     * @link   http://php.net/manual/en/pdo.begintransaction.php
     * @return bool
     */
    public function beginTransaction()
    {
        $this->debug->group('transaction', $this->debug->meta(array(
            'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
        )));
        return $this->pdo->beginTransaction();
    }

    /**
     * Commits a transaction
     *
     * @link   http://php.net/manual/en/pdo.commit.php
     * @return bool
     */
    public function commit()
    {
        $return = $this->pdo->commit();
        $this->debug->groupEnd($return);
        return $return;
    }

    /**
     * Fetch the SQLSTATE associated with the last operation on the database handle
     *
     * @link   http://php.net/manual/en/pdo.errorinfo.php
     * @return string a five characters alphanumeric identifier defined in the ANSI SQL-92 standard
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
     * @return int|false PDO::exec returns the number of rows that were modified or deleted by the
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
     * @param int $attribute One of the PDO::ATTR_* constants
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
     * @return bool true if a transaction is currently active, and false if not.
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
     * @return \PDOStatement|false If the database server successfully prepares the statement,
     * @link   http://php.net/manual/en/pdo.prepare.php
     *   PDO::prepare returns a PDOStatement object. If the database server cannot successfully prepare
     *   the statement, PDO::prepare returns FALSE or emits PDOException (depending on error handling).
     */
    public function prepare($statement, $driverOptions = array())
    {
        return $this->pdo->prepare($statement, $driverOptions);
    }

    /*
        query() is in MethodSignatureCompatTrait
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
    public function rollBack()
    {
        $return = $this->pdo->rollBack();
        $this->debug->log('rollback', $this->debug->meta(array(
            'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
        )));
        $this->debug->groupEnd($return);
        return $return;
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
    public function setAttribute($attribute, $value)
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    /**
     * Logs StatementInfo
     *
     * @param StatementInfo $info statement info instance
     *
     * @return void
     */
    public function addStatementInfo(StatementInfo $info)
    {
        $this->loggedStatements[] = $info;
        $info->appendLog($this->debug);
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return float
     */
    public function getTimeSpent()
    {
        return \array_reduce($this->loggedStatements, function ($val, StatementInfo $info) {
            return $val + $info->duration;
        });
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return int
     */
    public function getPeakMemoryUsage()
    {
        return \array_reduce($this->loggedStatements, function ($carry, StatementInfo $info) {
            $mem = $info->memoryUsage;
            return $mem > $carry
                ? $mem
                : $carry;
        });
    }

    /**
     * Returns the list of executed statements as StatementInfo objects
     *
     * @return StatementInfo[]
     */
    public function getLoggedStatements()
    {
        return $this->loggedStatements;
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
    protected function profileCall($method, $sql, $args = array())
    {
        $info = new StatementInfo($sql);
        $isExceptionMode = $this->pdo->getAttribute(PdoBase::ATTR_ERRMODE) === PdoBase::ERRMODE_EXCEPTION;

        $result = null;
        $exception = null;
        try {
            $result = \call_user_func_array(array($this->pdo, $method), $args);
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
     * Were attempts to prettify successful?
     *
     * @return bool
     */
    private function prettified()
    {
        $falseCount = 0;
        foreach ($this->loggedStatements as $info) {
            $prettified = $info->prettified;
            if ($prettified === true) {
                return true;
            }
            if ($prettified === false) {
                $falseCount++;
            }
        }
        return $falseCount === 0;
    }
}
