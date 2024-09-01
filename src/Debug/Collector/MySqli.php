<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @since     2.3
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

    /** @var bool */
    public $connectionAttempted = false;

    /** @var bool */
    protected $autocommit = true;

    /** @var list<string> */
    protected $savePoints = array();

    /** @var Debug */
    private $debug;

    /**
     * Constructor
     *
     * @param string     $host     host name or IP
     * @param string     $username MySQL user name
     * @param string     $passwd   password
     * @param string     $dbname   default database used when performing queries
     * @param int        $port     port number
     * @param string     $socket   socket or named pipe that should be used
     * @param Debug|null $debug    (optional) Specify PHPDebugConsole instance
     *                               if not passed, will create MySqli channel on singleton instance
     *                               if root channel is specified, will create a MySqli channel
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($host = null, $username = null, $passwd = null, $dbname = null, $port = null, $socket = null, $debug = null) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        \bdk\Debug\Utility\Php::assertType($debug, 'bdk\Debug');

        $this->doConstruct(\func_num_args()
            ? \array_slice(\func_get_args(), 0, 6)
            : array());
        if (!$debug) {
            $debug = Debug::getChannel('MySqli', array('channelIcon' => $this->icon));
        } elseif ($debug === $debug->rootInstance) {
            $debug = $debug->getChannel('MySqli', array('channelIcon' => $this->icon));
        }
        $this->debug = $debug;
        $debug->eventManager->subscribe(Debug::EVENT_OUTPUT, array($this, 'onDebugOutput'), 1);
        $debug->addPlugin($debug->pluginHighlight);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function autocommit($mode)
    {
        $this->autocommit = $mode;
        $this->debug->info('autocommit', $mode);
        return parent::autocommit($mode);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function begin_transaction($flags = 0, $name = null)
    {
        // name became nullable as of PHP 8
        $return = $name === null
            ? parent::begin_transaction($flags)
            : parent::begin_transaction($flags, $name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $this->savePoints = $name !== null
            ? array($name)
            : array();
        $infoParams = \array_filter(array('begin_transaction', $name, $this->meta()));
        \call_user_func_array(array($this->debug, 'info'), $infoParams);
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function commit($flags = 0, $name = null)
    {
        // name became nullable as of PHP 8
        $return = $name === null
            ? parent::commit($flags)
            : parent::commit($flags, $name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $this->savePoints = array();
        if ($name !== null) {
            $this->debug->warn('passing $name param to mysqli::commit() does nothing!');
        }
        $this->debug->info('commit', $this->meta());
        return $return;
    }

    // execute_query defined in ExecuteQueryTrait

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
    public function query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        return $this->profileCall('query', $query, array($query, $resultMode));
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
        if (PHP_VERSION_ID < 70000) {
            $this->logWithStyling('warn', 'mysqli::release_savepoint on PHP < 7.0 just calls %cSAVEPOINT `Sally`%c');
        }
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $index = \array_search($name, $this->savePoints, true);
        if ($index !== false) {
            unset($this->savePoints[$index]);
            $this->savePoints = \array_values($this->savePoints);
        }
        if (PHP_VERSION_ID < 70000) {
            $this->savePoints[] = $name;
        }
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function rollBack($flags = 0, $name = null)
    {
        // name became nullable as of PHP 8
        $return = $name === null
            ? parent::rollback($flags)
            : parent::rollback($flags, $name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $this->savePoints = array();
        if ($name !== null) {
            $this->logWithStyling('warn', 'passing $name param to %cmysqli::rollback()%c does not %cROLLBACK TO name%c as you would expect!');
        }
        $this->debug->info('rollBack', $this->meta());
        return $return;
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function savepoint($name)
    {
        $return = parent::savepoint($name);
        if ($return === false) {
            $this->debug->warn($this->error);
            return $return;
        }
        $index = \array_search($name, $this->savePoints, true);
        if ($index !== false) {
            \array_splice($this->savePoints, $index, 1);
        }
        $this->savePoints[] = $name;
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
        $debug->groupCollapsed(
            'MySqli info',
            $this->host_info,
            $this->meta(array(
                'argsAsParams' => false,
                'level' => 'info',
            ))
        );
        \set_error_handler(static function ($errno, $errstr) {
            throw new RuntimeException($errstr, $errno);
        }, E_ALL);
        try {
            $this->logRuntime($debug);
        } catch (RuntimeException $e) {
            $debug->group('MySqli Error', $debug->meta(array('level' => 'error')));
            $debug->log('Connection Error');
            $debug->groupEnd();
        }
        \restore_error_handler();
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
        $result = parent::query('select database() as `database`');
        $row = $result instanceof \mysqli_result
            ? $result->fetch_assoc()
            : null;
        return $row
            ? $row['database']
            : null;
    }

    /**
     * Call mysqli constructor with appropriate params
     *
     * Default values will be used for all empty values
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
        // @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
        $paramsDefault = array(
            'host' => \ini_get('mysqli.default_host'),
            'username' => \ini_get('mysqli.default_user'),
            'passwd' => \ini_get('mysqli.default_pw'),
            'dbname' => '',
            'port' => \ini_get('mysqli.default_port'),
            'socket' => \ini_get('mysqli.default_socket'),
        );
        $params = \array_replace(\array_fill(0, \count($paramsDefault), null), $params);
        $params = \array_combine(\array_keys($paramsDefault), $params);
        $p = \array_merge($paramsDefault, \array_filter($params));
        $this->connectionAttempted = true;
        parent::__construct($p['host'], $p['username'], $p['passwd'], $p['dbname'], $p['port'], $p['socket']);
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
    private function profileCall($method, $sql, array $args = array())
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
