<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use PDO as PdoBase;
use PDOException;
use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\Debug\Collector\Pdo\StatementInfo;

/**
 * A PDO proxy which traces statements
 */
class Pdo extends PdoBase
{
    public $debug;
    protected $pdo;
    protected $loggedStatements = array();

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
        $this->debug->eventManager->subscribe('debug.output', array($this, 'onDebugOutput'));
        // $this->debug->eventManager->subscribe('debug.outputLogEntry', array($this, 'onOutputLogEntry'));
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
     * debug.output subscriber
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $event->getSubject();
        // parse server info
        \preg_match_all(
            '/([^:]+): ([a-zA-Z0-9.]+)\s*/',
            $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO),
            $matches
        );
        $serverInfo = \array_map(function ($val) {
            return $val * 1;
        }, \array_combine($matches[1], $matches[2]));
        $serverInfo['Version'] = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        \ksort($serverInfo);
        $debug->groupSummary(0);
        $debug->groupCollapsed(
            'PDO info',
            $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS),
            $debug->meta(array(
                'level' => 'info',
                'argsAsParams' => false,
            ))
        );
        $debug->log('logged operations: ', \count($this->loggedStatements));
        $debug->log('total time: ', $this->getTimeSpent());
        $debug->log('max memory usage', $debug->utilities->getBytes($this->getPeakMemoryUsage()));
        $debug->log('server info', $serverInfo);
        $debug->groupEnd();
        $debug->groupEnd();
    }

    /**
     * Custom output for StatementInfo object
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    /*
    public function onOutputLogEntry(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $isHtmlOrWamp = $logEntry['outputAs'] instanceof \bdk\Debug\Output\Html || $logEntry['outputAs'] instanceof \bdk\Debug\Output\Wamp;
        if ($isHtmlOrWamp && $logEntry['method'] == 'log' && $this->debug->abstracter->isAbstraction($args[0])) {
            $info = $args[0];
            $vals = \array_map(function ($info) {
                return $info['value'];
            }, $info['properties']);
            $params = $vals['parameters'];
            if ($params) {
                $params = $this->debug->output->html->dump($params);
                $params = \preg_match('#<span class="array-inner">\s+(.*?)\s</span>\s*<span class="t_punct">#s', $params, $matches);
                $params = $matches[1];
            }
            $logEntry['args'] = array('
                <pre><code class="language-sql">'.\htmlspecialchars($vals['sql']).'</code></pre>
                <dl class="list-unstyled pull-left">'
                    .($params
                        ? '<dt>Parameters</dt>'
                            .'<dd class="no-indent">'.$params.'</dd>'
                        : '').'
                </dl>
            ');
            $logEntry->setMeta(array(
                'format' => 'html',
                'class' => 'no-indent',
            ));
        }
    }
    */

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
     * Adds an executed TracedStatement
     *
     * @param StatementInfo $info statement info instance
     *
     * @return void
     */
    public function addStatementInfo(StatementInfo $info)
    {
        $this->loggedStatements[] = $info;
        \preg_match('/^((?:DROP|SHOW).+$|SELECT\s*(?P<select>.*?)\s*FROM\s+\S+|UPDATE\s+\S+|DELETE.*?FROM\s+\S+)(?P<more>.*)/mis', $info->sql, $matches);
        $isMore = !empty($matches['more']);
        $label = $matches[1].($isMore ? '…' : '');
        if (\strlen($matches['select']) > 100) {
            $label = \str_replace($matches['select'], '(…)', $label);
        }
        $this->debug->groupCollapsed($label, $this->debug->meta(array(
            'icon' => 'fa fa-database',
            'boldLabel' => false,
        )));
        if ($isMore) {
            $this->debug->log(
                '<pre><code class="language-sql">'.\htmlspecialchars($info->sql).'</code></pre>',
                $this->debug->meta('class', 'no-indent')
            );
        }
        if ($info->parameters) {
            $this->debug->log('parameters', $info->parameters);
        }
        $this->debug->time('duration', $info->duration);
        $this->debug->log('memory usage', $this->debug->utilities->getBytes($info->memoryUsage));
        $this->debug->log('rowCount', $info->rowCount);
        $this->debug->groupEnd();
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return integer
     */
    public function getTimeSpent()
    {
        $time = \array_reduce($this->loggedStatements, function ($val, StatementInfo $info) {
            return $val + $info->duration;
        });
        return \round($time, 6);
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return integer
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
     * Returns the list of executed statements as TracedStatement objects
     *
     * @return array
     */
    public function getLoggedStatements()
    {
        return $this->loggedStatements;
    }
}
