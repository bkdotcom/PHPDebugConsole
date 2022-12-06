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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\MySqli\ExecuteQueryTrait;
use bdk\Debug\Collector\MySqli\MySqliStmt;
use bdk\Debug\Collector\StatementInfo;
use bdk\PubSub\Event;
use Exception;
use mysqli as mysqliBase;
use RuntimeException;

/**
 * mysqli extended with debugging
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class MySqli extends mysqliBase
{
    use DatabaseTrait;
    use ExecuteQueryTrait;

    public $connectionAttempted = false;
    protected $icon = 'fa fa-database';
    protected $loggedStatements = array();
    protected $autocommit = true;
    protected $savepoints = array();
    private $debug;

    /**
     * Constructor
     *
     * @param string $host     host name or IP
     * @param string $username MySQL user name
     * @param string $passwd   password
     * @param string $dbname   default database used wiehn performing queries
     * @param int    $port     port number
     * @param string $socket   socket or named pipe that should be used
     * @param Debug  $debug    (optional) Specify PHPDebugConsole instance
     *                           if not passed, will create MySqli channel on singleton instance
     *                           if root channel is specified, will create a MySqli channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null, Debug $debug = null)
    {
        $params = \func_num_args()
            ? array(
                'host' => $host,
                'username' => $username,
                'passwd' => $passwd,
                'dbname' => $dbname,
                'port' => $port,
                'socket' => $socket,
            )
            : array();
        $this->doConstruct($params);
        if (!$debug) {
            $debug = Debug::_getChannel('MySqli', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('MySqli', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $debug->eventManager->subscribe(Debug::EVENT_OUTPUT, array($this, 'onDebugOutput'), 1);
        $debug->addPlugin($debug->pluginHighlight);
    }

    /**
     * Turns on or off auto-committing database modification (begin transaction)
     *
     * @param bool $mode Whether to turn on auto-commit or not.
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function autocommit($mode)
    {
        $this->autocommit = $mode;
        $this->debug->info('autocommit', $mode);
        return parent::autocommit($mode);
    }

    /**
     * Initiates a transaction
     *
     * @param int    $flags A bitmask of MYSQLI_TRANS_START_* constants
     * @param string $name  Savepoint name for the transaction
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function begin_transaction($flags = 0, $name = null)
    {
        $return = $name === null
            ? parent::begin_transaction($flags)
            : parent::begin_transaction($flags, $name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $this->savepoints = $name
            ? array($name)
            : array();
        $groupArgs = \array_filter(array(
            'transaction',
            $name,
            $this->debug->meta(array(
                'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            )),
        ));
        \call_user_func_array(array($this->debug, 'group'), $groupArgs);
        return $return;
    }

    /**
     * Commits the current transaction
     *
     * @param int    $flags A bitmask of MYSQLI_TRANS_COR_* constants
     * @param string $name  If provided then COMMIT/name/ is executed.
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function commit($flags = 0, $name = null)
    {
        $return = $name === null
            ? parent::commit($flags)
            : parent::commit($flags, $name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $this->savepoints = array();
        if ($name !== null) {
            $this->debug->warn('passing $name param to mysqli::commit() does nothing!');
        }
        $this->debug->groupEnd($return);
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function multi_query($query)
    {
        return $this->profileCall('multi_query', $query, \func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function prepare($query)
    {
        return new MySqliStmt($this, $query);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function query($query, $resultmode = MYSQLI_STORE_RESULT)
    {
        return $this->profileCall('query', $query, array($query, $resultmode));
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function real_connect($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null, $flags = null)
    {
        $this->connectionAttempted = true;
        return parent::real_connect($host, $username, $passwd, $dbname, $port, $socket, (int) $flags);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function real_query($query)
    {
        return $this->profileCall('real_query', $query, \func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function release_savepoint($name)
    {
        $return = parent::release_savepoint($name);
        $index = \array_search($name, $this->savepoints, true);
        if (PHP_VERSION_ID < 70000) {
            $this->debug->warn(
                'mysqli::release_savepoint on PHP < 7.0 just calls %cSAVEPOINT `Sally`%c',
                'font-family: monospace;',
                ''
            );
        }
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        if ($index !== false) {
            unset($this->savepoints[$index]);
            $this->savepoints = \array_values($this->savepoints);
        }
        if (PHP_VERSION_ID < 70000) {
            $this->savepoints[] = $name;
        }
        return $return;
    }

    /**
     * Rolls back current transaction
     *
     * @param int    $flags A bitmask of MYSQLI_TRANS_COR_* constants.
     * @param string $name  If provided then ROLLBACK /name/ is executed.
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function rollBack($flags = 0, $name = null)
    {
        $return = $name === null
            ? parent::rollback($flags)
            : parent::rollback($flags, $name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $this->savepoints = array();
        if ($name !== null) {
            $this->debug->warn(
                'passing $name param to %cmysqli::rollback()%c does not %cROLLBACK TO name%c as you would expect!',
                'font-family: monospace;',
                '',
                'font-family: monospace;',
                ''
            );
        }
        $this->debug->groupEnd('rolled back');
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function savepoint($name)
    {
        $return = parent::savepoint($name);
        if (!$return) {
            $this->debug->warn($this->error);
            return $return;
        }
        $index = \array_search($name, $this->savepoints, true);
        if ($index !== false) {
            unset($this->savepoints[$index]);
            $this->savepoints = \array_values($this->savepoints);
        }
        $this->savepoints[] = $name;
        $this->debug->info('savepoint', $name);
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function stmt_init()
    {
        return new MySqliStmt($this, null);
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
        $debug->groupSummary(0);
        \set_error_handler(static function ($errno, $errstr) {
            throw new RuntimeException($errstr, $errno);
        }, E_ALL);
        try {
            $debug->groupCollapsed(
                'MySqli info',
                $this->host_info,
                $debug->meta(array(
                    'argsAsParams' => false,
                    'icon' => $this->icon,
                    'level' => 'info',
                ))
            );
            $this->logRuntime($debug);
            $debug->groupEnd(); // groupCollapsed
        } catch (RuntimeException $e) {
            $debug->group('MySqli Error', $debug->meta(array('level' => 'error')));
            $debug->log('Connection Error');
            $debug->groupEnd(); // MySqli Error
            $debug->groupEnd(); // groupCollapsed (opened in try)
        }
        \restore_error_handler();
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
        $result = parent::query('select database() as `database`');
        if ($result instanceof \mysqli_result) {
            $row = $result->fetch_assoc();
            if ($row) {
                return $row['database'];
            }
        }
        return null;
    }

    /**
     * Call mysqli constructor with appropriate params
     *
     * @param array $params host, username, etc
     *
     * @return void
     */
    private function doConstruct($params)
    {
        if (!$params) {
            /*
                Calling the constructor with no parameters is the same as calling mysqli_init().
            */
            parent::__construct();
            return;
        }
        $paramsDefault = array(
            'host' => \ini_get('mysqli.default_host'),
            'username' => \ini_get('mysqli.default_user'),
            'passwd' => \ini_get('mysqli.default_pw'),
            'dbname' => '',
            'port' => \ini_get('mysqli.default_port'),
            'socket' => \ini_get('mysqli.default_socket'),
        );
        $params = \array_filter($params);
        $params = \array_merge($paramsDefault, $params);
        $this->connectionAttempted = true;
        parent::__construct(
            $params['host'],
            $params['username'],
            $params['passwd'],
            $params['dbname'],
            $params['port'],
            $params['socket']
        );
    }

    /**
     * Profiles a call to a mysqli method
     *
     * @param string $method PDO method
     * @param string $sql    sql statement
     * @param array  $args   method args
     *
     * @return mixed The result of the call
     * @throws Exception
     */
    private function profileCall($method, $sql, array $args)
    {
        $info = new StatementInfo($sql);
        if ($this->connectionAttempted === false) {
            $info->end(new Exception('Not connected'), null);
            $this->addStatementInfo($info);
            return false;
        }
        if ($method === 'execute_query') {
            $info->setParams($args[1]);
        }
        $return = \call_user_func_array(array('mysqli', $method), $args);
        $exception = !$return
            ? new Exception($this->error, $this->errno)
            : null;
        $affectedRows = $method !== 'multi_query' && $return
            ? $this->affected_rows
            : null;
        $info->end($exception, $affectedRows);
        $this->addStatementInfo($info);
        return $return;
    }

    /**
     * `self::stat()`, but parsed
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via DatabaseTrait
     */
    private function serverInfo()
    {
        $matches = array();
        \preg_match_all('#([^:]+): ([a-zA-Z0-9.]+)\s*#', $this->stat(), $matches);
        $serverInfo = \array_map(static function ($val) {
            /** @psalm-suppress InvalidOperand */
            return $val * 1;
        }, \array_combine($matches[1], $matches[2]));
        $serverInfo['Version'] = $this->server_info;
        \ksort($serverInfo);
        return $serverInfo;
    }
}
