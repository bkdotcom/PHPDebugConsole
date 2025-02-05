<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     1.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\Debug\AbstractDebug;
use bdk\Debug\Abstraction\Abstracter;

/**
 * Web-browser/javascript like console class for PHP
 *
 * @method static $this alert(string $message, string $level = error, bool $dismissible = false)
 * @method static $this assert(bool $assertion, mixed ...$msg = null)
 * @method static $this clear(int $bitmask = bdk\Debug::CLEAR_LOG)
 * @method static $this count($label = null, int $flags = null)
 * @method static $this countReset(mixed $label = null, int $flags = null)
 * @method static $this error(mixed ...$arg)
 * @method static $this group(mixed ...$arg)
 * @method static $this groupCollapsed(mixed ...$arg)
 * @method static $this groupEnd(mixed $value = bdk\Debug\Abstraction\Abstracter::UNDEFINED)
 * @method static $this groupSummary(int $priority = 0)
 * @method static $this groupUncollapse()
 * @method static $this info(mixed ...$arg)
 * @method static $this log(mixed ...$arg)
 * @method static $this profile(string $name = null)
 * @method static $this profileEnd(string $name = null)
 * @method static $this table(mixed ...$arg)
 * @method static $this time(string $label = null, float $duration = null)
 * @method static $this timeEnd(string $label = null, bool $log = true, bool $return = auto)
 * @method static $this|float|false timeGet(string $label = null, bool $log = true, bool $return = auto)
 * @method static $this timeLog(string $label = null, mixed ...$args = null)
 * @method static $this trace(bool $inclContext = false, string $caption = trace)
 * @method static $this warn(mixed ...$arg)
 * @method static string output()
 *
 * @method static $this addPlugin(AssetProviderInterface|SubscriberInterface $plugin, string $name = null)
 * @method static $this addPlugins(mixed[] $plugins)
 * @method static bool email(string $toAddr, string $subject, string $body)
 * @method static array errorStats()
 * @method static self getChannel(string $name, $config = array())
 * @method static self[] getChannels($allDescendants = false, $inclTop = false)
 * @method static string getInterface()
 * @method static int|bool string getResponseCode()
 * @method static array|string getResponseHeader(string $header = 'Content-Type', string|null $delimiter = ', ')
 * @method static array|string getResponseHeaders($asString = false)
 * @method static object|bool getRoute(string $name, bool $checkOnly)
 * @method static mixed getServerParam($name, $default = null)
 * @method static bool hasLog()
 * @method static void obEnd()
 * @method static void obStart()
 * @method static Abstraction|string prettify(string $string, string $contentType)
 * @method static mixed redact($val, $key = null)
 * @method static $this removePlugin(string|SubscriberInterface $plugin)
 * @method static string requestId()
 * @method static void setErrorCaller(array $callerInfo)
 * @method static void varDump(mixed ...$arg)
 *
 * @property-read Abstracter           $abstracter
 * @property-read Debug\Utility\ArrayUtil $arrayUtil
 * @property-read Backtrace            $backtrace
 * @property-read Debug\Data           $data
 * @property-read ErrorHandler         $errorHandler
 * @property-read PubSub\Manager       $eventManager
 * @property-read Debug\Utility\Html   $html
 * @property-read Debug\Psr3\Logger    $logger
 * @property-read Debug|null           $parentInstance parent "channel"
 * @property-read Debug\Utility\Php    $php
 * @property-read Debug\Utility\PhpDoc $phpDoc
 * @property-read \Psr\Http\Message\ResponseInterface $response lazy-loaded ResponseInterface (set via writeToResponse)
 * @property-read \bdk\HttpMessage\ServerRequestExtendedInterface $serverRequest
 * @property-read Debug                $rootInstance  root "channel"
 * @property-read Debug\Utility\StringUtil $stringUtil
 * @property-read Debug\Utility\StopWatch $stopWatch
 * @property-read Debug\Utility\Utf8   $utf8
 * @property-read Debug\Utility        $utility
 *
 * @psalm-consistent-constructor
 */
class Debug extends AbstractDebug
{
    const CLEAR_ALERTS = 1;
    const CLEAR_LOG = 2;
    const CLEAR_LOG_ERRORS = 4;
    const CLEAR_SUMMARY = 8;
    const CLEAR_SUMMARY_ERRORS = 16;
    const CLEAR_ALL = 31;
    const CLEAR_SILENT = 32;
    const CONFIG_DEBUG = 'configDebug';
    const CONFIG_INIT = 'configInit';
    const CONFIG_NO_PUBLISH = 1;
    const CONFIG_NO_RETURN = 2;
    const COUNT_NO_INC = 1;
    const COUNT_NO_OUT = 2;

    const EVENT_BOOTSTRAP = 'debug.bootstrap';
    const EVENT_CONFIG = 'debug.config';
    const EVENT_CUSTOM_METHOD = 'debug.customMethod';
    const EVENT_DUMP_CUSTOM = 'debug.dumpCustom';
    const EVENT_LOG = 'debug.log';
    const EVENT_MIDDLEWARE = 'debug.middleware';
    const EVENT_OBJ_ABSTRACT_END = 'debug.objAbstractEnd';
    const EVENT_OBJ_ABSTRACT_START = 'debug.objAbstractStart';
    const EVENT_OUTPUT = 'debug.output';
    const EVENT_OUTPUT_LOG_ENTRY = 'debug.outputLogEntry';
    const EVENT_PLUGIN_INIT = 'debug.pluginInit';
    const EVENT_PRETTIFY = 'debug.prettify';
    const EVENT_STREAM_WRAP = 'debug.streamWrap';

    const META = "\x00meta\x00";
    const VERSION = '3.4';

    /** @var array<string,mixed> */
    protected $cfg = array(
        'channelIcon' => ':log:',
        'channelName' => 'general', // channel or tab name
        'channels' => array(
            /*
            channelName => array(
                'channelIcon' => '',
                'channelShow' => 'bool'
                'nested' => 'bool'
                etc
            )
            */
        ),
        'channelShow' => true, // whether initially filtered or not
        'channelSort' => 0, // if non-nested channel (tab), sort order
                            //   higher = first
                            //   tabs with same sort will be sorted alphabetically
        'collect'   => false,
        'emailFrom' => null,    // null = use php's default (php.ini: sendmail_from)
        'emailFunc' => 'mail',  // callable
        'emailLog' => false,    // Whether to email a debug log.  (requires 'collect' to also be true)
                                //   false:             email will not be sent
                                //   true or 'always':  email sent (if log is not output)
                                //   'onError':         email sent if error occurred (unless output)
        'emailTo' => 'default', // will default to $_SERVER['SERVER_ADMIN'] if non-empty, null otherwise
        'enableProfiling' => false,
        'errorLogNormal' => false, // whether php should also log the error when debugging is active
        'errorMask' => 0,       // which error types appear as "error" in debug console...
                                //   all other errors are "warn"
                                //   (default set in constructor)
        'exitCheck' => true,
        'extensionsCheck' => ['curl', 'mbString'],
        'headerMaxAll' => 250000,
        'headerMaxPer' => null,
        'icons' => array(
            'asynchronous' => 'fa fa-random',
            'authorize' => 'fa fa-handshake-o',
            'cache' => 'fa fa-cube',
            'component' => 'fa fa-puzzle-piece',
            'config' => 'fa fa-cogs',
            'database' => 'fa fa-database',
            'email' => 'fa fa-envelope-o',
            'error' => 'fa fa-times-circle',
            'error-suppressed' => 'fa fa-at fa-lg',
            'event' => 'fa fa-bell-o',
            'files' => 'fa fa-files-o',
            'github' => 'fa fa-github fa-lg',
            'loading' => 'fa fa-spinner fa-pulse fa-2x fa-fw',
            'log' => 'fa fa-list-ul',
            'models' => 'fa fa-cubes',
            'php' => '<i class="fa" style="position:relative; top:2px; font-size:15px;">🐘</i>',
            'receive' => 'fa fa-arrow-left',
            'security' => 'fa fa-shield',
            'send' => 'fa fa-arrow-right',
            'send-receive' => 'fa fa-exchange',
            'session' => 'fa fa-suitcase',
            'shutdown' => 'fa fa-power-off',
            'template' => 'fa fa-file-text-o',
            'user' => 'fa fa-user-o',
            'warning' => 'fa fa-warning',
        ),
        'key' => null,
        'logEnvInfo' => array(  // may be set by passing a list
            'errorReporting' => true,
            'files' => true,
            'gitInfo' => true,
            'phpInfo' => true,
            'serverVals' => true,
            'session' => true,
        ),
        'logRequestInfo' => array(
            'cookies' => true,
            'files' => true,
            'headers' => true,
            'post' => true,
        ),
        'logResponse' => 'auto',
        'logResponseMaxLen' => '1 MB',
        'logRuntime' => true,
        'logServerKeys' => ['DOCUMENT_ROOT','REMOTE_ADDR','REQUEST_TIME','REQUEST_URI','SERVER_ADDR','SERVER_NAME'],
        'onBootstrap' => null,      // callable
        'onLog' => null,            // callable
        'onOutput' => null,         // callable
        'output'    => false,       // output the log?
        'outputHeaders' => true,    // ie, ChromeLogger and/or firePHP headers
        'plugins' => array(
            'channel' => array( 'class' => 'bdk\Debug\Plugin\Channel' ),
            'configEvents' => array( 'class' => 'bdk\Debug\Plugin\ConfigEvents' ),
            'internalEvents' => array( 'class' => 'bdk\Debug\Plugin\InternalEvents' ),
            'logEnv' => array( 'class' => 'bdk\Debug\Plugin\LogEnv' ),
            'logFiles' => array( 'class' => 'bdk\Debug\Plugin\LogFiles' ),
            'logPhp' => array( 'class' => 'bdk\Debug\Plugin\LogPhp' ),
            'logRequest' => array( 'class' => 'bdk\Debug\Plugin\LogRequest' ),
            'logResponse' => array( 'class' => 'bdk\Debug\Plugin\LogResponse' ),
            'methodAlert' => array( 'class' => 'bdk\Debug\Plugin\Method\Alert' ),
            'methodBasic' => array( 'class' => 'bdk\Debug\Plugin\Method\Basic' ),
            'methodClear' => array( 'class' => 'bdk\Debug\Plugin\Method\Clear' ),
            'methodCount' => array( 'class' => 'bdk\Debug\Plugin\Method\Count' ),
            'methodGeneral' => array( 'class' => 'bdk\Debug\Plugin\Method\General' ),
            'methodGroup' => array( 'class' => 'bdk\Debug\Plugin\Method\Group' ),
            'methodOutput' => array( 'class' => 'bdk\Debug\Plugin\Method\Output' ),
            'methodProfile' => array( 'class' => 'bdk\Debug\Plugin\Method\Profile' ),
            'methodReqRes' => array( 'class' => 'bdk\Debug\Plugin\Method\ReqRes' ),
            'methodTable' => array( 'class' => 'bdk\Debug\Plugin\Method\Table' ),
            'methodTime' => array( 'class' => 'bdk\Debug\Plugin\Method\Time' ),
            'methodTrace' => array( 'class' => 'bdk\Debug\Plugin\Method\Trace' ),
            'prettify' => array( 'class' => 'bdk\Debug\Plugin\Prettify' ),
            'redaction' => array( 'class' => 'bdk\Debug\Plugin\Redaction' ),
            'route' => array( 'class' => 'bdk\Debug\Plugin\Route' ),
            'runtime' => array( 'class' => 'bdk\Debug\Plugin\Runtime' ),
        ),
        'redactKeys' => [           // case-insensitive
            'password',
        ],
        // 'redactReplace'          // callable (default defined in Plugin/Redaction)
        'route' => 'auto',          // 'auto', 'chromeLogger', 'firephp', 'html', 'serverLog', 'script', 'steam', 'text', or RouteInterface,
                                    //   if 'auto', will be determined automatically
                                    //   if null, no output (unless output plugin added manually)
        'routeNonHtml' => 'serverLog',
        'serviceProvider' => array(), // ServiceProviderInterface, array, or callable that receives Container as param
        'sessionName' => null,      // if logging session data (see logEnvInfo), optionally specify session name
        'slowQueryDurationMs' => 500,
        'wampPublisher' => array(
            // wampPublisher
            //    required if using Wamp route
            //    must be installed separately
            'realm' => 'debug',
        ),
    );

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        $this->cfg['errorMask'] = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR;
        parent::__construct($cfg);
    }

    /**
     * Retrieve a configuration value
     *
     * @param string      $path what to get
     * @param null|string $opt  (@internal)
     *
     * @return mixed value
     */
    public function getCfg($path = null, $opt = null)
    {
        if ($path === 'route' && $this->cfg['route'] === 'auto') {
            return $this->getDefaultRoute(); // returns string
        }
        return $opt === self::CONFIG_DEBUG
            ? $this->arrayUtil->pathGet($this->cfg, $path)
            : $this->config->get($path, $opt === self::CONFIG_INIT);
    }

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @param array $cfg optional config
     *
     * @return static
     */
    public static function getInstance($cfg = array())
    {
        if (!isset(self::$instance)) {
            // self::$instance set in __construct
            new static($cfg);
        } elseif ($cfg) {
            self::$instance->setCfg($cfg, self::CONFIG_NO_RETURN);
        }
        return self::$instance;
    }

    /**
     * "metafy" value/values
     *
     * accepts
     *  * `array('key'=>value)`
     *  * 'cfg', option, value  (shortcut for setting single config value)
     *  * 'key', value
     *  * 'key'                 (value defaults to true)
     *
     * @param mixed ...$arg arguments
     *
     * @return array special array encapsulating "meta" values
     */
    public static function meta($arg = null)
    {
        $args = \func_get_args();
        /** @var mixed[] make psalm happy */
        $args = \array_replace([null, true, true], $args);
        if (\is_array($args[0])) {
            $args[0]['debug'] = self::META;
            return $args[0];
        }
        if (\is_string($args[0]) === false) {
            // invalid / return empty meta array
            return array('debug' => self::META);
        }
        if ($args[0] === 'cfg') {
            return self::metaCfg($args[1], $args[2]);
        }
        return array(
            $args[0] => $args[1],
            'debug' => self::META,
        );
    }

    /**
     * Set one or more config values
     *
     * `setCfg('key', 'value')`
     * `setCfg('level1.level2', 'value')`
     * `setCfg(array('k1'=>'v1', 'k2'=>'v2'))`
     *
     * @param string|array $path    path
     * @param mixed        $value   value
     * @param int          $options bitmask of CONFIG_NO_PUBLISH, CONFIG_NO_RETURN
     *
     * @return mixed previous value(s)
     */
    public function setCfg($path, $value = null, $options = 0)
    {
        return $this->config->set($path, $value, $options);
    }

    /**
     * Create config meta argument/value
     *
     * @param string|array $key key or array of key/values
     * @param mixed        $val config value
     *
     * @return array
     */
    private static function metaCfg($key, $val)
    {
        if (\is_array($key)) {
            return array(
                'cfg' => $key,
                'debug' => self::META,
            );
        }
        if (\is_string($key)) {
            return array(
                'cfg' => array(
                    $key => $val,
                ),
                'debug' => self::META,
            );
        }
        // invalid cfg key / return empty meta array
        return array('debug' => self::META);
    }
}
