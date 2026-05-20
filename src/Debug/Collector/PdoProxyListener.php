<?php

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\Proxy\ListenerInterface;
use bdk\PubSub\Event;
use PDO as PDOBase;
use PDOException;

/**
 * Listener for Pdo proxy
 */
class PdoProxyListener implements ListenerInterface
{
    use DatabaseTrait;

    /** @var PDOBase */
    private $subject;

    /** @var array */
    private $initValues = array();

    /** @var Exception|null */
    private $exception = null;

    /**
     * Constructor
     *
     * @param Debug|null $debug (optional) $debug instance
     */
    public function __construct($debug = null)
    {
        $this->traitInit($debug, 'PDO');
    }

    /**
     * {@inheritDoc}
     */
    public function afterCall($methodName, array $arguments, $result, array $initValues, $exception = null)
    {
        $listenerMethod = 'afterCall' . \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $methodName)));
        if (\method_exists($this, $listenerMethod)) {
            $this->initValues = $initValues;
            $this->exception = $exception;
            $this->$listenerMethod($arguments, $result);
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function init($subject, $proxy)
    {
        $this->subject = $subject;
        $this->subject->setAttribute(PDOBase::ATTR_STATEMENT_CLASS, ['bdk\Debug\Collector\Pdo\Statement', [$proxy, $this]]);
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onDebugOutput'], 1);
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

        $name = $debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $driverName = $this->subject->getAttribute(PDOBase::ATTR_DRIVER_NAME);

        $groupParams = \array_filter([
            $debug->i18n->trans('info.for.x', array('x' => $name)),
            $driverName,
            $driverName !== 'sqlite'
                ? $this->subject->getAttribute(PDOBase::ATTR_CONNECTION_STATUS)
                : null,
            $this->meta(array(
                'argsAsParams' => false,
                'level' => 'info',
            )),
        ]);
        \call_user_func_array([$debug, 'groupCollapsed'], $groupParams);
        $this->logRuntime();
        $debug->groupEnd(); // groupCollapsed
        $debug->groupEnd(); // groupSummary
    }

    /**
     * Log calls to PDO::beginTransaction
     *
     * @return void
     */
    private function afterCallBeginTransaction()
    {
        $this->debug->info('beginTransaction', $this->meta());
    }

    /**
     * Log calls to PDO::commit
     *
     * @return void
     */
    private function afterCallCommit()
    {
        $this->debug->info('commit', $this->meta());
    }

    /**
     * Log calls to PDO::exec
     *
     * @param array $args   The arguments passed to the method
     * @param mixed $result The result of the call
     *
     * @return void
     */
    private function afterCallExec(array $args, $result)
    {
        $this->logStatementInfo($args, $result);
    }

    /**
     * Log calls to PDO::query
     *
     * @param array $args   The arguments passed to the method
     * @param mixed $result The result of the call
     *
     * @return void
     */
    private function afterCallQuery(array $args, $result)
    {
        $this->logStatementInfo($args, $result);
    }

    /**
     * Log calls to PDO::rollback
     *
     * @return void
     */
    private function afterCallRollback()
    {
        $this->debug->info('rollBack', $this->meta());
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
            $statement = $this->subject->query('select database()');
            if ($statement) {
                return $statement->fetchColumn();
            }
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Log statement information
     *
     * @param array $args   The arguments passed to the method
     * @param mixed $result The result of the call
     *
     * @return void
     */
    private function logStatementInfo(array $args, $result)
    {
        $info = new StatementInfo($args[0], null, null, array(
            'memoryStart' => null,
            'timeStart' => null,
        ));

        $exception = $this->exception;
        $isExceptionMode = $this->subject->getAttribute(PDOBase::ATTR_ERRMODE) === PDOBase::ERRMODE_EXCEPTION;
        if (!$isExceptionMode && $result === false) {
            $error = $this->subject->errorInfo();
            $exception = new PDOException($error[2], $error[0]);
        }

        $info->end($exception);
        $this->addStatementInfo($info);
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
        return $this->pdoServerInfo($this->subject);
    }
}
