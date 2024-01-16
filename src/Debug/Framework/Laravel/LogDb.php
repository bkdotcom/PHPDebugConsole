<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.3
 */

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug;
use bdk\Debug\Collector\StatementInfo;
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
        $this->debug = $serviceProvider->debug;
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

        $dbChannel = $this->debug->getChannel('Db', array(
            'channelIcon' => 'fa fa-database',
        ));

        try {
            $this->dbListen($dbManager, $dbChannel);
        } catch (Exception $e) {
            $this->debug->warn($e->getMessage());
        }

        try {
            $this->dbSubscribe($dbManager, $dbChannel);
        } catch (Exception $e) {
            $this->debug->log('exception', $e->getMessage());
        }
    }

    /**
     * Build DatabaseManager event handler
     *
     * @param Debug       $dbChannel  PHPDebugConsole channel
     * @param string|null $msg        Group message
     * @param bool        $isGroupEnd (false) groupEnd ?
     *
     * @return Closure
     */
    private function buildDbEventHandler(Debug $dbChannel, $msg, $isGroupEnd = false)
    {
        return static function () use ($dbChannel, $msg, $isGroupEnd) {
            if ($isGroupEnd === false) {
                $dbChannel->group($msg);
                return;
            }
            if ($msg) {
                $dbChannel->log($msg);
            }
            $dbChannel->groupEnd();
        };
    }

    /**
     * Register a database query listener with the connection.
     *
     * @param DatabaseManager $dbManager DatabaseManager instance
     * @param Debug           $dbChannel Debug instance
     *
     * @return void
     */
    protected function dbListen(DatabaseManager $dbManager, Debug $dbChannel)
    {
        // listen found in Illuminate\Database\Connection
        $dbManager->listen(function ($query, $bindings = null, $time = null, $connection = null) use ($dbManager, $dbChannel) {
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
            $statementInfo->setDuration($time);
            $statementInfo->appendLog($dbChannel);
        });
    }

    /**
     * Listen to database events
     *
     * @param DatabaseManager $dbManager DatabaseManager instance
     * @param Debug           $dbChannel Debug instance
     *
     * @return void
     */
    private function dbSubscribe(DatabaseManager $dbManager, Debug $dbChannel)
    {
        $eventDispatcher = $dbManager->getEventDispatcher();

        $eventDispatcher->listen(
            \Illuminate\Database\Events\TransactionBeginning::class,
            $this->buildDbEventHandler($dbChannel, 'Begin Transaction')
        );
        $eventDispatcher->listen(
            \Illuminate\Database\Events\TransactionCommitted::class,
            $this->buildDbEventHandler($dbChannel, null, true)
        );
        $eventDispatcher->listen(
            \Illuminate\Database\Events\TransactionRolledBack::class,
            $this->buildDbEventHandler($dbChannel, 'rollback', true)
        );

        $eventDispatcher->listen(
            'connection.*.beganTransaction',
            $this->buildDbEventHandler($dbChannel, 'Begin Transaction')
        );
        $eventDispatcher->listen(
            'connection.*.committed',
            $this->buildDbEventHandler($dbChannel, null, true)
        );
        $eventDispatcher->listen(
            'connection.*.rollingBack',
            $this->buildDbEventHandler($dbChannel, 'rollback', true)
        );
    }
}
