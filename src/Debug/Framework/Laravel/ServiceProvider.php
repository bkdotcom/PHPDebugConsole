<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Framework\Laravel;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Collector\MonologHandler;
use bdk\Debug\Framework\Laravel\CacheEventsSubscriber;
use bdk\Debug\Framework\Laravel\EventsSubscriber;
use bdk\Debug\Framework\Laravel\Middleware;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\ArrayUtil;
use bdk\Debug\Utility\TableRow;
use bdk\ErrorHandler\Error;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * PhpDebugConsole
 */
class ServiceProvider extends BaseServiceProvider
{
    public $debug;
    protected $modelCounts = array();
    private $isLumen = false;
    private $logViews;

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'phpDebugConsole');

        $config = ArrayUtil::mergeDeep($this->app['config']->get('phpDebugConsole'), array(
            'filepathScript' => './js/Debug.jquery.js',
            'logEnvInfo' => array(
                'session' => false,
            ),
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
        $this->logViews = new LogViews($this, $this->app);
        $this->logDb = new LogDb($this, $this->app);
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
        $this->logDb->log();
        $this->logEvents();
        $this->logLaravel();
        $this->logModels();
        $this->logViews->log();
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
            'channelIcon' => ':models:',
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
                'rows' => $tableInfoRows,
            ),
            'totalCols' => [TableRow::SCALAR],
        )));
    }

    /**
     * Config get wrapper
     *
     * @param string $name    option name
     * @param mixed  $default default vale
     *
     * @return bool
     */
    public function shouldCollect($name, $default = false)
    {
        return $this->app['config']->get('phpDebugConsole.laravel.' . $name, $default);
    }

    /**
     * Process the stored model counts for outputting as table
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
                    'attribs' => array(
                        'data-file' => $ref->getFileName(),
                    ),
                    'type' => Type::TYPE_IDENTIFIER,
                    'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
                )),
            );
        }
        return $modelCounts;
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
            'channelIcon' => ':config:',
            'nested' => false,
        ));
        $configChannel->log($config);
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
            [$event];
            foreach (\array_filter($models) as $model) {
                $class = \get_class($model);
                $this->modelCounts[$class] = (int) ($this->modelCounts[$class] ?? 0) + 1;
            }
        });
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, [$this, 'onOutput']);
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
}
