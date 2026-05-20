<?php

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\MySqli\MySqliStmt;
use bdk\Debug\Collector\StatementInfo;
use bdk\Proxy\ListenerInterface;
use bdk\PubSub\Event;
use Exception;
use mysqli as mysqliBase;

/**
 * Listener for MySqli proxy
 */
class MySqliProxyListener implements ListenerInterface
{
    use DatabaseTrait;

    /** @var bool */
    public $connectionAttempted = false;

    /** @var array arguments passed to method */
    private $arguments = [];

    /** @var Exception|null */
    private $exception = null;

    /** @var array */
    private $initValues = array();

    /** @var mysqliBase */
    private $proxy;

    /** @var mixed method return value */
    private $result = null;

    /** @var list<string> */
    private $savePoints = array();

    /** @var mysqliBase */
    private $subject;

    /**
     * Constructor
     *
     * @param Debug|null $debug (optional) $debug instance
     */
    public function __construct($debug = null)
    {
        $this->traitInit($debug, 'MySqli');
    }

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception = null)
    {
        $listenerMethod = 'afterCall' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $methodName)));
        if (\method_exists($this, $listenerMethod)) {
            $this->arguments = $arguments;
            $this->exception = $exception;
            $this->initValues = $initValues;
            $this->result = $result;
            $this->$listenerMethod();
            $result = $this->result;
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function init($subject, $proxy)
    {
        $this->subject = $subject;
        $this->proxy = $proxy;
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onDebugOutput'], 1);
    }

    /**
     * Convert connection param values to key => value array
     *
     * @param array $arguments Connection params passed to mysqli::construct() or mysqli::real_connect()
     *
     * @return array
     */
    public function connectionParamsKeyValue(array $arguments)
    {
        $paramNames = ['host', 'username', 'password', 'dbname', 'port', 'socket'];
        $paramValues = \array_slice($arguments, 0, \count($paramNames));
        $paramValues = \array_pad($paramValues, \count($paramNames), null);
        return \array_combine($paramNames, $paramValues);
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
        $exception = null;
        $debug->utility->callSuppressed(function () use ($debug) {
            $debug->groupCollapsed(
                $debug->i18n->trans('info.for.x', array('x' => 'MySqli')),
                $this->subject->host_info,
                $this->meta(array(
                    'argsAsParams' => false,
                    'level' => 'info',
                ))
            );
            $this->logRuntime($this->connectionString());
            $debug->groupEnd();
        }, [], $exception);
        if ($exception) {
            $currentGroups = $debug->rootInstance->getPlugin('methodGroup')->getCurrentGroups();
            if (empty($currentGroups)) {
                $debug->group('MySqli ' . $debug->i18n->trans('word.error'), $debug->meta(array('level' => 'error')));
            }
            $debug->error($debug->i18n->trans('db.connection-error'));
            $debug->groupEnd();
        }
        $debug->groupEnd();
    }

    /**
     * Called after mysqli::construct()
     *
     * @return mixed
     */
    protected function afterCallConstruct()
    {
        $this->connectionAttempted = true;
        $this->params = $this->connectionParamsKeyValue($this->arguments);
    }

    /**
     * Called after mysqli::autocommit()
     *
     * @return mixed
     */
    protected function afterCallAutoCommit()
    {
        $this->debug->info('autocommit', $this->arguments[0]);
    }

    /**
     * Called after mysqli::begin_transaction()
     *
     * @return mixed
     */
    protected function afterCallBeginTransaction()
    {
        if ($this->result === false) {
            $this->debug->warn($this->subject->error);
            return $this->result;
        }
        $name = $this->arguments[1];
        $this->savePoints = $name !== null
            ? [$name]
            : [];
        $infoParams = \array_filter(['begin_transaction', $name, $this->meta()]);
        \call_user_func_array([$this->debug, 'info'], $infoParams);
    }

    /**
     * Called after mysqli::commit()
     *
     * @return mixed
     */
    protected function afterCallCommit()
    {
        if ($this->result === false) {
            $this->debug->warn($this->subject->error);
            return $this->result;
        }
        $this->savePoints = array();
        $name = $this->arguments[1];
        if ($name !== null) {
            $this->debug->warn('passing $name param to mysqli::commit() does nothing!');
        }
        $this->debug->info('commit', $this->meta());
    }

    /**
     * Called after mysqli::execute_query()
     *
     * @return mixed
     */
    protected function afterCallExecuteQuery()
    {
        $this->logStatementInfo('execute_query', $this->arguments, $this->result);
    }

    /**
     * Called after mysqli::multi_query()
     *
     * @return mixed
     */
    protected function afterCallMultiQuery()
    {
        $this->logStatementInfo('multi_query', $this->arguments, $this->result);
    }

    /**
     * Called after mysqli::prepare()
     *
     * @return mixed
     */
    protected function afterCallPrepare()
    {
        $this->result = new MySqliStmt($this->subject, $this, $this->arguments[0]);
    }

    /**
     * Called after mysqli::query()
     *
     * @return mixed
     */
    protected function afterCallQuery()
    {
        $this->logStatementInfo('query', $this->arguments, $this->result);
    }

    /**
     * Called after mysqli::real_connect()
     *
     * @return mixed
     */
    protected function afterCallRealConnect()
    {
        $this->connectionAttempted = true;
        $this->params = $this->connectionParamsKeyValue($this->arguments);
    }

    /**
     * Called after mysqli::real_query()
     *
     * @return mixed
     */
    protected function afterCallRealQuery()
    {
        $this->logStatementInfo('real_query', $this->arguments, $this->result);
    }

    /**
     * Called after mysqli::release_savepoint()
     *
     * @return mixed
     */
    protected function afterCallReleaseSavePoint()
    {
        if (PHP_VERSION_ID < 70000) {
            $this->logWithStyling('warn', 'mysqli::release_savepoint on PHP < 7.0 just calls %cSAVEPOINT `Sally`%c');
        }
        if ($this->result === false) {
            $this->debug->warn($this->subject->error);
            return;
        }
        $this->savePoints = $this->debug->arrayUtil->diffStrict($this->savePoints, $this->arguments);
        if (PHP_VERSION_ID < 70000) {
            $this->savePoints[] = $this->arguments[0];
        }
    }

    /**
     * Called after mysqli::rollback()
     *
     * @return mixed
     */
    protected function afterCallRollback()
    {
        if ($this->result === false) {
            $this->debug->warn($this->subject->error);
            return;
        }
        $this->savePoints = [];
        if ($this->arguments[0] !== null) {
            $this->logWithStyling('warn', 'passing $name param to %cmysqli::rollback()%c does not %cROLLBACK TO name%c as you would expect!');
        }
        $this->debug->info('rollBack', $this->meta());
    }

    /**
     * Called after mysqli::savepoint()
     *
     * @return mixed
     */
    protected function afterCallSavePoint()
    {
        if ($this->result === false) {
            $this->debug->warn($this->subject->error);
            return;
        }
        // move name to the end
        $this->savePoints = $this->debug->arrayUtil->diffStrict($this->savePoints, $this->arguments);
        $this->savePoints[] = $this->arguments[0];
        $this->debug->info('savepoint', $this->arguments[0]);
    }

    /**
     * Called after mysqli::stmt_init()
     *
     * @return mixed
     */
    protected function afterCallStmtInit()
    {
        $this->result = new MySqliStmt($this->subject, $this);
    }

    /**
     * Get connection params represented as a connection string / dsn
     *
     * @return string|null
     */
    private function connectionString()
    {
        return $this->params
            ? \bdk\Debug\Utility\Sql::buildDsn(\array_merge(array('scheme' => 'mysql'), $this->params))
            : null;
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
        $result = $this->subject->query('select database() as `database`');
        $row = $result instanceof \mysqli_result
            ? $result->fetch_assoc()
            : null;
        return $row
            ? $row['database']
            : null;
    }

    /**
     * Profiles a call to a mysqli method
     *
     * @param string $method PDO method
     * @param array  $args   method args
     * @param mixed  $result method result
     *
     * @return void
     */
    private function logStatementInfo($method, array $args, $result)
    {
        $params = null;
        if ($method === 'execute_query') {
            $params = $args[1];
        }
        $info = new StatementInfo($args[0], $params, null, $this->initValues);
        if ($this->connectionAttempted === false) {
            $info->end(new Exception('Not connected'), null);
            $this->addStatementInfo($info);
            return;
        }
        $affectedRows = $method !== 'multi_query' && $result
            ? $this->subject->affected_rows
            : null;
        $info->end($this->exception, $affectedRows);
        $this->addStatementInfo($info);
    }

    /**
     * `self::stat()`, but parsed
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod) -> called via DatabaseTrait
     */
    protected function serverInfo()
    {
        $matches = array();
        \preg_match_all('#([^:]+): ([a-zA-Z0-9.]+)\s*#', $this->subject->stat(), $matches);
        $serverInfo = \array_map(static function ($val) {
            /** @psalm-suppress InvalidOperand */
            return $val * 1;
        }, \array_combine($matches[1], $matches[2]));
        $serverInfo['Version'] = $this->subject->server_info;
        \ksort($serverInfo);
        return $serverInfo;
    }
}
