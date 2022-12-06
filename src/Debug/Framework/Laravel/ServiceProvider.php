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

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Collector\MonologHandler;
use bdk\Debug\Collector\StatementInfo;
use bdk\Debug\Framework\Laravel\CacheEventsSubscriber;
use bdk\Debug\Framework\Laravel\EventsSubscriber;
use bdk\Debug\Framework\Laravel\Middleware;
use bdk\Debug\LogEntry;
use bdk\Debug\Method\TableRow;
use bdk\Debug\Utility\ArrayUtil;
use bdk\ErrorHandler\Error;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\View\View;

/**
 * PhpDebugConsole
 */
class ServiceProvider extends BaseServiceProvider
{
    public $debug;
    protected $modelCounts = array();
    private $isLumen = false;

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'phpDebugConsole');

        $config = ArrayUtil::mergeDeep($this->app['config']->get('phpDebugConsole'), array(
            'logEnvInfo' => array(
                'session' => false,
            ),
            'filepathScript' => './js/Debug.jquery.js',
            'onError' => static function (Error $error) {
                $error['continueToPrevHandler'] = false; // forward error to Laravel Handler?
            },
        ));
        $this->debug = new Debug($config);
        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, static function (LogEntry $logEntry) {
            if ($logEntry->getChannelName() === 'general.local') {
                $logEntry->setMeta('channel', null);
            }
        });
        $this->app->singleton(Debug::class, function () {
            return $this->debug;
        });
    }

    /**
     * Bootstrap
     *
     * @return void
     */
    public function boot()
    {
        $appVersion = $this->app->version();
        $this->isLumen = \strpos($appVersion, 'Lumen') !== false;

        $this->publishes(array(
            __DIR__ . '/config.php' => $this->app->configPath('phpDebugConsole.php'),
        ), 'config');

        // gate
        // mail
        $this->logCacheEvents();
        $this->logConfig();
        $this->logDb();
        $this->logEvents();
        $this->logLaravel();
        $this->logModels();
        $this->logViews();
        $this->registerLogHandler();
        $this->registerMiddleware();
    }

    /**
     * Log model usage
     *
     * @return void
     */
    public function onOutput()
    {
        $debug = $this->debug->getChannel('Models', array(
            'channelIcon' => 'fa fa-cubes',
            'nested' => false,
        ));
        $tableInfoRows = array();
        $modelCounts = $this->buildModelCountTable($tableInfoRows);
        $debug->table('Model Usage', $modelCounts, $debug->meta(array(
            'columnNames' => array(
                TableRow::SCALAR => 'count',
            ),
            'detectFiles' => true,
            'sortable' => true,
            'tableInfo' => array(
                'indexLabel' => 'model',
                'rows' => $tableInfoRows
            ),
            'totalCols' => array(TableRow::SCALAR),
        )));
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
     * Process the stored model counts for outputing as table
     *
     * @param array $tableInfoRows gets updated with tableInfo.rows for table
     *
     * @return array
     */
    private function buildModelCountTable(&$tableInfoRows)
    {
        $modelCounts = array();
        $tableInfoRows = array();
        foreach ($this->modelCounts as $class => $count) {
            $ref = new \ReflectionClass($class);
            $modelCounts[] = $count;
            $tableInfoRows[] = array(
                'key' => $this->debug->abstracter->crateWithVals($class, array(
                    'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
                    'attribs' => array(
                        'data-file' => $ref->getFileName(),
                    )
                )),
            );
        }
        return $modelCounts;
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
            if (!$this->shouldCollect('db', true)) {
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

    /**
     * Log cache events
     *
     * @return void
     */
    protected function logCacheEvents()
    {
        if (!$this->shouldCollect('cacheEvents') || !isset($this->app['events'])) {
            return;
        }
        $options = array(
            'collectValues' => $this->app['config']->get('phpDebugConsole.options.cache.values', true),
        );
        $cacheEventsSub = new CacheEventsSubscriber($options, $this->debug);
        $this->app['events']->subscribe($cacheEventsSub);
    }

    /**
     * Log config information
     *
     * @return void
     */
    protected function logConfig()
    {
        if (!$this->shouldCollect('config', true)) {
            return;
        }
        $config = $this->app['config']->all();
        \ksort($config);
        $configChannel = $this->debug->getChannel('Config', array(
            'channelIcon' => 'fa fa-cogs',
            'nested' => false,
        ));
        $configChannel->log($config);
    }

    /**
     * Subscribe to database events and log them
     *
     * @return void
     */
    protected function logDb()
    {
        if (!$this->shouldCollect('db', true) || !isset($this->app['db'])) {
            return;
        }

        /** @var \Illuminate\Database\DatabaseManager */
        $dbManager = $this->app['db'];

        $dbChannel = $this->debug->getChannel('Db', array(
            'channelIcon' => 'fa fa-database',
        ));

        try {
            $this->dbListen($dbManager, $dbChannel);
        } catch (\Exception $e) {
            $this->debug->warn($e->getMessage());
        }

        try {
            $this->dbSubscribe($dbManager, $dbChannel);
        } catch (\Exception $e) {
            $this->debug->log('exception', $e->getMessage());
        }
    }

    /**
     * Subscribe to all events and log them
     *
     * @return void
     */
    protected function logEvents()
    {
        if (!$this->shouldCollect('events') || !isset($this->app['events'])) {
            return;
        }
        $eventsSubscriber = new EventsSubscriber($this->debug);
        $this->app['events']->subscribe($eventsSubscriber);
    }

    /**
     * Log Laravel version, environment, & locale
     *
     * @return void
     */
    protected function logLaravel()
    {
        if (!$this->shouldCollect('laravel', true)) {
            return;
        }
        $this->debug->groupSummary();
        $this->debug->group('Laravel', $this->debug->meta('level', 'info'));
        $this->debug->log('version', $this->app::VERSION);
        $this->debug->log('environment', $this->app->environment());
        $this->debug->log('locale', $this->app->getLocale());
        $this->debug->groupEnd();
        $this->debug->groupEnd();
    }

    /**
     * Log models used
     *
     * @return void
     */
    protected function logModels()
    {
        if (!$this->shouldCollect('models', true)) {
            return;
        }
        $this->app['events']->listen('eloquent.retrieved:*', function ($event, $models) {
            // "use" our function params so things (ie phpmd) don't complain
            array($event);
            foreach (\array_filter($models) as $model) {
                $class = \get_class($model);
                $this->modelCounts[$class] = (int) ($this->modelCounts[$class] ?? 0) + 1;
            }
        });
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, array($this, 'onOutput'));
    }

    /**
     * Log views
     *
     * @return void
     */
    protected function logViews()
    {
        if (!$this->shouldCollect('laravel', true)) {
            return;
        }
        $this->viewChannel = $this->debug->getChannel('Views', array(
            'channelIcon' => 'fa fa-file-text-o',
        ));
        $this->app['events']->listen(
            'composing:*',
            function ($view, $data = []) {
                if ($data) {
                    $view = $data[0]; // For Laravel >= 5.4
                }
                $this->logView($view);
            }
        );
    }

    /**
     * Log view information
     *
     * @param View $view View instance
     *
     * @return void
     */
    protected function logView(View $view)
    {
        $name = $view->getName();
        $path = $view->getPath();
        $pathStr = \is_object($path)
            ? null
            : \realpath($path);

        $info = \array_filter(array(
            'name' => $name,
            'path' => $pathStr
                ? $this->debug->abstracter->crateWithVals(
                    \ltrim(\str_replace(\base_path(), '', $pathStr), '/'),
                    array(
                        'attribs' => array(
                            'data-file' => $path,
                        ),
                    )
                )
                : null,
            'params' => \call_user_func(array($this, 'logViewParams'), $view),
            'type' => \is_object($path)
                ? \get_class($view)
                : (\substr($path, -10) === '.blade.php'
                    ? 'blade'
                    : \pathinfo($path, PATHINFO_EXTENSION)),
        ));
        $this->viewChannel->log('view', $info, $this->viewChannel->meta('detectFiles'));
    }

    /**
     * Get view params (view data)
     *
     * @param View $view View instance
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function logViewParams(View $view)
    {
        $data = $view->getData();
        /** @var bool|'type' */
        $collectValues = $this->app['config']->get('phpDebugConsole.options.views.data');
        if ($collectValues === true) {
            \ksort($data);
            return $data;
        }
        if ($collectValues !== 'type') {
            $data = \array_keys($data);
            \sort($data);
            return $data;
        }
        foreach ($data as $k => $v) {
            $type = $this->debug->abstracter->getType($v)[0];
            $data[$k] = $type === 'object'
                ? $this->debug->abstracter->crateWithVals(\get_class($v), array(
                    'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
                ))
                : $type;
        }
        \ksort($data);
        return $data;
    }

    /**
     * Register Monolog handler
     *
     * @return void
     */
    protected function registerLogHandler()
    {
        $monologHandler = new MonologHandler($this->debug);
        $this->app['log']->pushHandler($monologHandler);
    }

    /**
     * Register our Middleware
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        $kernel = $this->app[Kernel::class];
        $kernel->prependMiddleware(Middleware::class);
    }

    /**
     * Config get wrapper
     *
     * @param string $name    option name
     * @param mixed  $default default vale
     *
     * @return bool
     */
    protected function shouldCollect($name, $default = false)
    {
        return $this->app['config']->get('phpDebugConsole.laravel.' . $name, $default);
    }
}
