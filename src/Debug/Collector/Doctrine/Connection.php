<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2024-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector\Doctrine;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\PubSub\Event;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use ReflectionClass;

/**
 * Connection middleware
 */
class Connection extends AbstractConnectionMiddleware
{
    use DatabaseTrait;

    /**
     * Constructor
     *
     * @param ConnectionInterface $connection Connection instance
     * @param array               $params     Connection params
     * @param Debug               $debug      Debug instance
     */
    public function __construct(
        ConnectionInterface $connection,
        #[\SensitiveParameter]
        array $params,
        Debug $debug
    )
    {
        parent::__construct($connection);
        $this->params = $params;
        $this->traitInit($debug);
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
        $debug = $event->getSubject();
        $debug->groupSummary(0);
        $groupParams = \array_filter([
            'Doctrine',
            \bdk\Debug\Utility\Sql::buildDsn($this->params),
            $this->meta(array(
                'argsAsParams' => false,
                'level' => 'info',
                'redact' => true,
            )),
        ]);
        \call_user_func_array([$debug, 'groupCollapsed'], $groupParams);
        $this->logRuntime($debug);
        $debug->groupEnd();  // groupCollapsed
        $debug->groupEnd();  // groupSummary
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql): StatementInterface
    {
        return new Statement(
            parent::prepare($sql),
            $this,
            $sql
        );
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql): ResultInterface
    {
        return $this->profileCall('query', $sql, \func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function exec(string $sql): int
    {
        return $this->profileCall('exec', $sql, \func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(): void
    {
        $this->debug->info('beginTransaction', $this->meta());
        parent::beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): void
    {
        $this->debug->info('commit', $this->meta());
        parent::commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack(): void
    {
        $this->debug->info('rollBack', $this->meta());
        parent::rollBack();
    }

    /**
     * Profiles a call to a PDO method
     *
     * @param string $method PDO method
     * @param string $sql    sql statement
     * @param array  $args   method args
     *
     * @return mixed The result of the call
     * @throws \Exception
     */
    protected function profileCall($method, $sql, array $args = array())
    {
        $info = new StatementInfo($sql);

        $result = null;
        $exception = null;
        try {
            $reflector = new ReflectionClass(__CLASS__);
            $parent = $reflector->getParentClass();
            $method = $parent->getMethod($method);
            $result = $method->invokeArgs($this, $args);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $info->end($exception);
        $this->addStatementInfo($info);

        if ($exception !== null) {
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
        $pdo = $this->getNativeConnection();
        return $this->pdoServerInfo($pdo);
    }
}
