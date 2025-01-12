<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Collector\StatementInfoLogger;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;

/**
 * Log database activity
 */
class LogDb
{
    public $debug;

    protected $app;
    protected $serviceProvider;
    protected $statementInfoLogger;

    /**
     * Constructor
     *
     * @param ServiceProvider $serviceProvider ServiceProvider instance
     * @param Application     $app             Laravel Application
     */
    public function __construct(ServiceProvider $serviceProvider, Application $app)
    {
        $this->serviceProvider = $serviceProvider;
        $this->app = $app;
        $this->debug = $serviceProvider->debug->getChannel('Db', array(
            'channelIcon' => ':database:',
        ));
        $this->statementInfoLogger = new StatementInfoLogger($this->debug);
    }

    /**
     * Subscribe to database events and log them
     *
     * @return void
     */
    public function log()
    {
        if (!$this->serviceProvider->shouldCollect('db', true) || !isset($this->app['db'])) {
            return;
        }

        /** @var \Illuminate\Database\DatabaseManager */
        $dbManager = $this->app['db'];

        try {
            $this->dbListen($dbManager);
        } catch (Exception $e) {
            $this->debug->warn($e->getMessage());
        }

        try {
            $this->dbSubscribe($dbManager);
        } catch (Exception $e) {
            $this->debug->log('exception', $e->getMessage());
        }
    }

    /**
     * Build DatabaseManager event handler
     *
     * @param string|null $msg        Group message
     * @param bool        $isGroupEnd (false) groupEnd ?
     *
     * @return Closure
     */
    private function buildDbEventHandler($msg, $isGroupEnd = false)
    {
        return function () use ($msg, $isGroupEnd) {
            if ($isGroupEnd === false) {
                $this->debug->group($msg);
                return;
            }
            if ($msg) {
                $this->debug->log($msg);
            }
            $this->debug->groupEnd();
        };
    }

    /**
     * Register a database query listener with the connection.
     *
     * @param DatabaseManager $dbManager DatabaseManager instance
     *
     * @return void
     */
    protected function dbListen(DatabaseManager $dbManager)
    {
        // listen found in Illuminate\Database\Connection
        $dbManager->listen(function ($query, $bindings = null, $time = null, $connection = null) use ($dbManager) {
            if (!$this->serviceProvider->shouldCollect('db', true)) {
                // We've turned off collecting after the listener was attached
                return;
            }

            // Laravel 5.2 changed the way some core events worked. We must account for
            // the first argument being an "event object", where arguments are passed
            // via object properties, instead of individual arguments.
            $connection = $query instanceof QueryExecuted
                ? $query->connection
                : $dbManager->connection($connection);
            if ($query instanceof QueryExecuted) {
                $bindings = $query->bindings;
                $time = $query->time;
                $query = $query->sql;
            }
            $statementInfo = new StatementInfo(
                $query,
                $connection->prepareBindings($bindings)
            );
            $statementInfo->setDuration($time / 1000); // time was passed in milliseconds
            $this->statementInfoLogger->log($statementInfo);
        });
    }

    /**
     * Listen to database events
     *
     * @param DatabaseManager $dbManager DatabaseManager instance
     *
     * @return void
     */
    private function dbSubscribe(DatabaseManager $dbManager)
    {
        $eventDispatcher = $dbManager->getEventDispatcher();

        $eventDispatcher->listen(
            \Illuminate\Database\Events\TransactionBeginning::class,
            $this->buildDbEventHandler('Begin Transaction')
        );
        $eventDispatcher->listen(
            \Illuminate\Database\Events\TransactionCommitted::class,
            $this->buildDbEventHandler(null, true)
        );
        $eventDispatcher->listen(
            \Illuminate\Database\Events\TransactionRolledBack::class,
            $this->buildDbEventHandler('rollback', true)
        );

        $eventDispatcher->listen(
            'connection.*.beganTransaction',
            $this->buildDbEventHandler('Begin Transaction')
        );
        $eventDispatcher->listen(
            'connection.*.committed',
            $this->buildDbEventHandler(null, true)
        );
        $eventDispatcher->listen(
            'connection.*.rollingBack',
            $this->buildDbEventHandler('rollback', true)
        );
    }
}
