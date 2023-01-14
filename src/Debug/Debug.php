<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\Debug\AbstractDebug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler\Error;

/**
 * Web-browser/javascript like console class for PHP
 *
 * @method Abstraction|string prettify(string $string, string $contentType)
 * @method bool email($toAddr, $subject, $body)
 * @method string getInterface()
 * @method string getResponseCode()
 * @method array|string getResponseHeader($header = 'Content-Type', $delimiter = ', ')
 * @method array|string getResponseHeaders($asString = false)
 * @method mixed getServerParam($name, $default = null)
 * @method bool hasLog()
 * @method void obEnd()
 * @method void obStart()
 * @method mixed redact($val, $key = null)
 * @method string requestId()
 * @method void setErrorCaller(array $callerInfo)
 *
 * @property Abstracter           $abstracter    lazy-loaded Abstracter instance
 * @property \bdk\Debug\Utility\ArrayUtil $arrayUtil lazy-loaded array utilitys
 * @property \bdk\Backtrace       $backtrace     lazy-loaded Backtrace instance
 * @property \bdk\ErrorHandler    $errorHandler  lazy-loaded ErrorHandler instance
 * @property \bdk\PubSub\Manager  $eventManager  lazy-loaded Event Manager instance
 * @property Debug\Utility\Html   $html          lazy=loaded Html Utility instance
 * @property Debug\Psr3\Logger    $logger        lazy-loaded PSR-3 instance
 * @property Debug\Method\Clear   $methodClear   lazy-loaded MethodClear instance
 * @property Debug\Method\Profile $methodProfile lazy-loaded MethodProfile instance
 * @property Debug\Method\Table   $methodTable   lazy-loaded MethodTable instance
 * @property \bdk\Debug|null      $parentInstance parent "channel"
 * @property \Psr\Http\Message\ResponseInterface $response lazy-loaded ResponseInterface (set via writeToResponse)
 * @property HttpMessage\ServerRequest $serverRequest lazy-loaded ServerRequest
 * @property \bdk\Debug           $rootInstance  root "channel"
 * @property \bdk\Debug\Utility\StringUtil $stringUtil lazy-loaded string utilitys
 * @property Debug\Utility\StopWatch $stopWatch  lazy-loaded StopWatch instance
 * @property Debug\Utility\Utf8   $utf8          lazy-loaded Utf8 instance
 * @property Debug\Utility        $utility       lazy-loaded Utility instance
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
    const VERSION = '3.0.2';

    protected $cfg = array(
        'collect'   => false,
        'key'       => null,
        'output'    => false,           // output the log?
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
        'channelIcon' => 'fa fa-list-ul',
        'channelName' => 'general',     // channel or tab name
        'channelShow' => true,          // wheter initially filtered or not
        'channelSort' => 0, // if non-nested channel (tab), sort order
                        // higher = first
                        // tabs with same sort will be sorted alphabetically
        'enableProfiling' => false,
        'errorLogNormal' => false,   // whether php shoyld also log the error when debugging is active
        'errorMask' => 0,       // which error types appear as "error" in debug console...
                                //    all other errors are "warn"
                                //    (default set in constructor)
        'emailFrom' => null,    // null = use php's default (php.ini: sendmail_from)
        'emailFunc' => 'mail',  // callable
        'emailLog' => false,    // Whether to email a debug log.  (requires 'collect' to also be true)
                                //   false:             email will not be sent
                                //   true or 'always':  email sent (if log is not output)
                                //   'onError':         email sent if error occured (unless output)
        'emailTo' => 'default', // will default to $_SERVER['SERVER_ADMIN'] if non-empty, null otherwise
        'exitCheck' => true,
        'extensionsCheck' => array('curl', 'mbString'),
        'headerMaxAll' => 250000,
        'headerMaxPer' => null,
        'logEnvInfo' => array(      // may be set by passing a list
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
        'logServerKeys' => array('REMOTE_ADDR','REQUEST_TIME','REQUEST_URI','SERVER_ADDR','SERVER_NAME'),
        'onBootstrap' => null,          // callable
        'onLog' => null,                // callable
        'onOutput' => null,             // callable
        'outputHeaders' => true,        // ie, ChromeLogger and/or firePHP headers
        'redactKeys' => array(          // case-insensitive
            'password',
        ),
        // 'redactReplace'              // closure (default defined in PLugin/Redaction)
        'route' => 'auto',              // 'auto', 'chromeLogger', 'firephp', 'html', 'serverLog', 'script', 'steam', 'text', or RouteInterface,
                                        //   if 'auto', will be determined automatically
                                        //   if null, no output (unless output plugin added manually)
        'routeNonHtml' => 'serverLog',
        'serviceProvider' => array(), // ServiceProviderInterface, array, or callable that receives Container as param
        'sessionName' => null,  // if logging session data (see logEnvInfo), optionally specify session name
        'wampPublisher' => array(
            // wampPuglisher
            //    required if using Wamp route
            //    must be installed separately
            'realm' => 'debug'
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

    /*
        Debugging Methods
    */

    /**
     * Display an alert at the top of the log
     *
     * Can use styling & substitutions.
     * If using substitutions, will need to pass $level & $dismissible as meta values
     *
     * @param string $message     message to be displayed
     * @param string $level       (error), info, success, warn
     *                               "danger" and "warning" are still accepted, however deprecated
     * @param bool   $dismissible (false) Whether to display a close icon/button
     *
     * @return $this
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function alert($message, $level = 'error', $dismissible = false)
    {
        $args = \func_get_args();
        $hasSubstitutions = $this->methodHelper->alertHasSubstitutions($args);
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            $args,
            array(
                'level' => 'error',
                'dismissible' => false,
            ),
            $hasSubstitutions
                ? array()
                : $this->getMethodDefaultArgs(__FUNCTION__),
            array('level','dismissible')
        );
        $logEntry['args'] = \array_values($logEntry['args']);
        $this->methodHelper->alertLevel($logEntry);
        $this->data->set('logDest', 'alerts');
        $this->appendLog($logEntry);
        $this->data->set('logDest', 'auto');
        return $this;
    }

    /**
     * If first argument evaluates `false`, log the remaining paramaters
     *
     * Supports styling & substitutions
     *
     * @param bool  $assertion Any boolean expression. If the assertion is false, the message is logged
     * @param mixed $msg,...   (optional) variable num of values to output if assertion fails
     *                           if none provided, will use calling file & line num
     *
     * @return $this
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function assert($assertion, $msg = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $args = $logEntry['args'];
        $assertion = \array_shift($args);
        if ($assertion) {
            return $this;
        }
        if (!$args) {
            // add default message
            $callerInfo = $this->backtrace->getCallerInfo();
            $args = array(
                'Assertion failed:',
                \sprintf('%s (line %s)', $callerInfo['file'], $callerInfo['line']),
            );
            $logEntry->setMeta('detectFiles', true);
        }
        $logEntry['args'] = $args;
        $this->appendLog($logEntry);
        return $this;
    }

    /**
     * Clear the log
     *
     * This method executes even if `collect` is false
     *
     * @param int $bitmask A bitmask of options
     *                     `self::CLEAR_ALERTS` : Clear alerts generated with `alert()`
     *                     `self::CLEAR_LOG` : **default** Clear log entries (excluding warn & error)
     *                     `self::CLEAR_LOG_ERRORS` : Clear warn & error
     *                     `self::CLEAR_SUMMARY` : Clear summary entries (excluding warn & error)
     *                     `self::CLEAR_SUMMARY_ERRORS` : Clear warn & error within summary groups
     *                     `self::CLEAR_ALL` : Clear all log entries
     *                     `self::CLEAR_SILENT` : Don't add log entry
     *
     * @return $this
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function clear($bitmask = self::CLEAR_LOG)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->getMethodDefaultArgs(__FUNCTION__),
            array('bitmask')
        );
        $this->methodClear->doClear($logEntry);
        // even if cleared from within summary, let's log this in primary log
        $this->data->set('logDest', 'main');
        $this->appendLog($logEntry);
        $this->data->set('logDest', 'auto');
        return $this;
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * Count is maintained even when `collect` is false
     * If `collect` = false, `count()` will be performed "silently"
     *
     * @param mixed $label Label.  If omitted, logs the number of times `count()` has been called at this particular line.
     * @param int   $flags (optional) A bitmask of
     *                        \bdk\Debug::COUNT_NO_INC` : don't increment the counter
     *                                                     (ie, just get the current count)
     *                        \bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return int The new count (or current count when using `COUNT_NO_INC`)
     */
    public function count($label = null, $flags = 0)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        return $this->methodCount->doCount($logEntry);
    }

    /**
     * Resets the counter
     *
     * Counter is reset even when debugging is disabled (ie `collect` is false).
     *
     * @param mixed $label (optional) specify the counter to reset
     * @param int   $flags (optional) currently only one option :
     *                       \bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return $this
     */
    public function countReset($label = 'default', $flags = 0)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodCount->countReset($logEntry);
        return $this;
    }

    /**
     * Log an error message.
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function error()
    {
        $this->methodHelper->doError(__FUNCTION__, \func_get_args());
        return $this;
    }

    /**
     * Create a new inline group
     *
     * Groups generally get indented and will receive an expand/collapse toggle.
     *
     * applicable meta args:
     *      argsAsParams: true
     *      boldLabel: true
     *      hideIfEmpty: false
     *      isFuncName: (bool)
     *      level: (string)
     *      ungroup: false  // when closed: if no children, convert to plain log entry
     *                      // when closed: if only one child, remove the containing group
     *
     * @param mixed $arg,... label / values
     *
     * @return $this
     */
    public function group()
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodGroup->methodGroup($logEntry);
        return $this;
    }

    /**
     * Create a new inline group
     *
     * Unline group(), groupCollapsed(), will initially be collapsed
     *
     * @param mixed $arg,... label / values
     *
     * @return $this
     */
    public function groupCollapsed()
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodGroup->methodGroup($logEntry);
        return $this;
    }

    /**
     * Close current group
     *
     * Every call to `group()`, `groupCollapsed()`, and `groupSummary()` should be paired with `groupEnd()`
     *
     * The optional return value will be visible when the group is both expanded and collapsed.
     *
     * @param mixed $value (optional) "return" value
     *
     * @return $this
     */
    public function groupEnd($value = Abstracter::UNDEFINED)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->getMethodDefaultArgs(__FUNCTION__)
        );
        $this->methodGroup->methodGroupEnd($logEntry);
        return $this;
    }

    /**
     * Open a "summary" group
     *
     * Debug methods called from within a groupSummary will appear at the top of the log.
     * Call `groupEnd()` to close the summary group
     *
     * All groupSummary groups will appear together at the top of the output
     *
     * @param int $priority (0) The higher the priority, the earlier the group will appear in output
     *
     * @return $this
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function groupSummary($priority = 0)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->getMethodDefaultArgs(__FUNCTION__),
            array('priority')
        );
        $this->methodGroup->methodGroupSummary($logEntry);
        return $this;
    }

    /**
     * Uncollapse ancestor groups
     *
     * This will only occur if `cfg['collect']` is currently `true`
     *
     * @return $this
     */
    public function groupUncollapse()
    {
        if (!$this->cfg['collect']) {
            return $this;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodGroup->methodGroupUncollapse($logEntry);
        return $this;
    }

    /**
     * Log some informative information
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function info()
    {
        $this->appendLog(new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        ));
        return $this;
    }

    /**
     * Log general information
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function log()
    {
        $args = \func_get_args();
        if (\count($args) === 1) {
            if ($args[0] instanceof LogEntry) {
                $this->appendLog($args[0]);
                return $this;
            }
            if ($args[0] instanceof Error) {
                $this->container['internalEvents']->onError($args[0]);
                return $this;
            }
        }
        $this->appendLog(new LogEntry(
            $this,
            __FUNCTION__,
            $args
        ));
        return $this;
    }

    /**
     * Starts recording a performance profile
     *
     * @param string $name Optional profile name
     *
     * @return $this
     */
    public function profile($name = null)
    {
        if (!$this->cfg['collect']) {
            return $this;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->getMethodDefaultArgs(__FUNCTION__),
            array('name')
        );
        $this->methodProfile->doProfile($logEntry);
        return $this;
    }

    /**
     * Stops recording profile info & adds info to the log
     *
     *  * if name is passed and it matches the name of a profile being recorded, then that profile is stopped.
     *  * if name is passed and it does not match the name of a profile being recorded, nothing will be done
     *  * if name is not passed, the most recently started profile is stopped (named, or non-named).
     *
     * @param string $name Optional profile name
     *
     * @return $this
     */
    public function profileEnd($name = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->getMethodDefaultArgs(__FUNCTION__),
            array('name')
        );
        $this->methodProfile->profileEnd($logEntry);
        return $this;
    }

    /**
     * Output an array or object as a table
     *
     * Accepts array of arrays or array of objects
     *
     * Parameters:
     *   1st encountered array (or traversable) is the data
     *   2nd encountered array (optional) specifies columns to output
     *   1st encountered string is a label/caption
     *
     * @param mixed $arg,... traversable, [option array], [caption] in no particular order
     *
     * @return $this
     */
    public function table()
    {
        if (!$this->cfg['collect']) {
            return $this;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodTable->doTable($logEntry);
        $this->appendLog($logEntry);
        return $this;
    }

    /**
     * Start a timer identified by label
     *
     * ## Label passed
     *  * if doesn't exist: starts timer
     *  * if does exist: unpauses (does not reset)
     *
     * ## Label not passed
     *  * timer will be added to a no-label stack
     *
     * Does not append log (unless duration is passed).
     *
     * Use `timeEnd` or `timeGet` to get time
     *
     * @param string $label    unique label
     * @param float  $duration (optional) duration (in seconds).  Use this param to log a duration obtained externally.
     *
     * @return $this
     */
    public function time($label = null, $duration = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            $this->getMethodDefaultArgs(__FUNCTION__)
        );
        $this->methodTime->doTime($logEntry);
        return $this;
    }

    /**
     * Behaves like a stopwatch.. logs and (optionaly) returns running time
     *
     *    If label is passed, timer is "paused" (not ended/cleared)
     *    If label is not passed, timer is removed from timer stack
     *
     * Meta options
     *    precision: 4 (how many decimal places)
     *    silent: (false) only return / don't log
     *    template: '%label: %time'
     *    unit: ('auto'), 'sec', 'ms', or 'us'
     *
     * @param string $label  (optional) unique label
     * @param bool   $log    (true) log it, or return only
     *                         if passed, takes precedence over silent meta val
     * @param bool   $return ('auto') whether to return the value (vs returning $this))
     *                          'auto' : !$log
     *
     * @return $this|float|false The duration (in sec).
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     * @psalm-return ($return is true ? float|false : $this)
     */
    public function timeEnd($label = null, $log = true, $return = 'auto')
    {
        $logEntry = $this->methodTime->timeLogEntry($this, __FUNCTION__, \func_get_args());
        $value = $this->methodTime->timeEnd($logEntry);
        return $logEntry['meta']['return']
            ? $value
            : $this;
    }

    /**
     * Log/get the running time without stopping/pausing the timer
     *
     * Meta options
     *    precision: 4 (how many decimal places)
     *    silent: (false) only return / don't log
     *    template: '%label: %time'
     *    unit: ('auto'), 'sec', 'ms', or 'us'
     *
     * This method does not have a web console API equivalent
     *
     * @param string $label  (optional) unique label
     * @param bool   $log    (true) log it
     * @param bool   $return ('auto') whether to return the value (vs returning $this))
     *                          'auto' : !$log
     *
     * @return $this|float|false The duration (in sec).  `false` if specified label does not exist
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     * @psalm-return ($return is true ? float|false : $this)
     */
    public function timeGet($label = null, $log = true, $return = 'auto')
    {
        $logEntry = $this->methodTime->timeLogEntry($this, __FUNCTION__, \func_get_args());
        $value = $this->methodTime->timeGet($logEntry);
        return $logEntry['meta']['return']
            ? $value
            : $this;
    }

    /**
     * Logs the current value of a timer that was previously started via `time()`
     *
     * also logs additional arguments
     *
     * @param string $label   (optional) unique label
     * @param mixed  $arg,... (optional) additional values to be logged with time
     *
     * @return $this
     */
    public function timeLog($label = null, $args = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'precision' => 4,
                'unit' => 'auto',
            ),
            $this->getMethodDefaultArgs(__FUNCTION__)
        );
        $this->methodTime->timeLog($logEntry);
        return $this;
    }

    /**
     * Log a stack trace
     *
     * Essentially PHP's `debug_backtrace()`, but displayed as a table
     *
     * @param bool   $inclContext Include code snippet
     * @param string $caption     (optional) Specify caption for the trace table
     *
     * @return $this
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function trace($inclContext = false, $caption = 'trace')
    {
        if (!$this->cfg['collect']) {
            return $this;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'columns' => array('file','line','function'),
                'detectFiles' => true,
                'inclArgs' => null,  // incl arguments with context?
                                     // will default to $inclContext
                                     //   may want to set meta['cfg']['objectsExclude'] = '*'
                'sortable' => false,
                'trace' => null,  // set to specify trace
            ),
            $this->getMethodDefaultArgs(__FUNCTION__),
            array(
                'caption',
                'inclContext',
            )
        );
        if ($logEntry->getMeta('inclArgs') === null) {
            $logEntry->setMeta('inclArgs', $logEntry->getMeta('inclContext'));
        }
        $this->methodHelper->doTrace($logEntry);
        return $this;
    }

    /**
     * Log a warning
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return $this
     */
    public function warn()
    {
        $this->methodHelper->doError(__FUNCTION__, \func_get_args());
        return $this;
    }

    /*
        "Non-Console" Methods
    */

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
            return $this->container['internal']->getDefaultRoute(); // returns string
        }
        if ($opt === self::CONFIG_DEBUG) {
            return $this->arrayUtil->pathGet($this->cfg, $path);
        }
        return $this->config->get($path, $opt === self::CONFIG_INIT);
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
            self::$instance->setCfg($cfg);
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
     * @param mixed $args,... arguments
     *
     * @return array special array storing "meta" values
     */
    public static function meta()
    {
        $args = \func_get_args();
        /** @var mixed[] make psalm happy */
        $args = \array_replace(array(null, true, true), $args);
        if (\is_array($args[0])) {
            $args[0]['debug'] = self::META;
            return $args[0];
        }
        if (\is_string($args[0]) === false) {
            // invalid / return empty meta array
            return array('debug' => self::META);
        }
        if ($args[0] === 'cfg') {
            return self::$instance->container['internal']->metaCfg($args[1], $args[2]);
        }
        return array(
            $args[0] => $args[1],
            'debug' => self::META,
        );
    }

    /**
     * Return debug log output
     *
     * Publishes `Debug::EVENT_OUTPUT` event and returns event's 'return' value
     *
     * @param array $cfg Override any config values
     *
     * @return string|null
     */
    public function output($cfg = array())
    {
        $cfgRestore = $this->config->set($cfg);
        if (!$this->cfg['output']) {
            $this->config->set($cfgRestore);
            $this->obEnd();
            return null;
        }
        $route = $this->getCfg('route');
        if (\is_string($route)) {
            $this->config->set('route', $route);
        }
        $output = $this->container['internal']->publishOutputEvent();
        if (!$this->parentInstance) {
            $this->data->set('outputSent', true);
        }
        $this->config->set($cfgRestore);
        $this->obEnd();
        return $output;
    }

    /**
     * Set one or more config values
     *
     * `setCfg('key', 'value')`
     * `setCfg('level1.level2', 'value')`
     * `setCfg(array('k1'=>'v1', 'k2'=>'v2'))`
     *
     * @param string|array $path  path
     * @param mixed        $value value
     *
     * @return mixed previous value(s)
     */
    public function setCfg($path, $value = null)
    {
        return $this->config->set($path, $value);
    }
}
