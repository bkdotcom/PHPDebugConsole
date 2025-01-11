<?php

namespace bdk\Debug\Collector\Doctrine;

use bdk\Debug;
use bdk\Debug\Collector\DatabaseTrait;
use bdk\Debug\Collector\StatementInfo;
use bdk\HttpMessage\Utility\Uri as UriUtil;
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

    /** @var array */
    private $params = array();

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
            $this->buildDsn(),
            $this->meta(array(
                'argsAsParams' => false,
                'level' => 'info',
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
     * Build DSN url from params
     *
     * @return string
     */
    private function buildDsn()
    {
        $params = \array_merge(array(
            'dbname' => null,
            'driver' => null,
            'host' => 'localhost',
            'memory' => false,
            'password' => null,
            'user' => null,
        ), $this->params);
        $parts = $this->paramsToUrlParts($params);
        $dsn = (string) UriUtil::fromParsed($parts);
        if ($parts['path'] === ':memory:') {
            $dsn = \str_replace('/localhost', '/', $dsn);
        }
        return $dsn;
    }

    /**
     * Convert params to url parts
     *
     * @param array $params Connection params
     *
     * @return array
     */
    private function paramsToUrlParts(array $params)
    {
        $map = array(
            'dbname' => 'path',
            'driver' => 'scheme',
            'password' => 'pass',
        );
        \ksort($params);
        $rename = \array_intersect_key($params, $map);
        $keysNew = \array_values(\array_intersect_key($map, $rename));
        $renamed = \array_combine($keysNew, \array_values($rename));
        $parts = \array_merge($renamed, $params);
        if ($params['memory'] || $params['dbname'] === ':memory:') {
            $params['memory'] = true;
            $parts['path'] = ':memory:';
        }
        $parts['scheme'] = \str_replace('_', '-', $parts['scheme']);
        return $parts;
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
