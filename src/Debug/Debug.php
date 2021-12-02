<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\Container;
use bdk\Container\ServiceProviderInterface;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\ConfigurableInterface;
use bdk\Debug\LogEntry;
use bdk\Debug\Route\RouteInterface;
use bdk\Debug\ServiceProvider;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use ReflectionMethod;

/**
 * Web-browser/javascript like console class for PHP
 *
 * @method Abstraction|string prettify(string $string, string $contentType)
 * @method void email($toAddr, $subject, $body)
 * @method string getInterface()
 * @method string getResponseCode()
 * @method string getResponseHeader($header = 'Content-Type')
 * @method array|string getResponseHeaders($asString = false)
 * @method mixed getServerParam($name, $default = null)
 * @method bool hasLog()
 * @method mixed redact($val, $key = null)
 * @method string requestId()
 *
 * @property Abstracter           $abstracter    lazy-loaded Abstracter instance
 * @property \bdk\Debug\Utility\ArrayUtil $arrayUtil lazy-loaded array utilitys
 * @property \bdk\Backtrace       $backtrace     lazy-loaded Backtrace instance
 * @property \bdk\ErrorHandler\ErrorEmailer $errorEmailer lazy-loaded ErrorEmailer instance
 * @property \bdk\ErrorHandler    $errorHandler  lazy-loaded ErrorHandler instance
 * @property \bdk\PubSub\Manager  $eventManager  lazy-loaded Event Manager instance
 * @property Debug\Utility\Html   $html          lazy=loaded Html Utility instance
 * @property Debug\Psr3\Logger    $logger        lazy-loaded PSR-3 instance
 * @property Debug\Method\Clear   $methodClear   lazy-loaded MethodClear instance
 * @property Debug\Method\Profile $methodProfile lazy-loaded MethodProfile instance
 * @property Debug\Method\Table   $methodTable   lazy-loaded MethodTable instance
 * @property \bdk\Debug|null      $parentInstance parent "channel"
 * @property \Psr\Http\Message\ResponseInterface $response lazy-loaded ResponseInterface (set via writeToResponse)
 * @property Debug\Psr7lite\ServerRequest $request lazy-loaded ServerRequest
 * @property \bdk\Debug           $rootInstance  root "channel"
 * @property \bdk\Debug\Utility\StringUtil $stringUtil lazy-loaded string utilitys
 * @property Debug\Utility\StopWatch $stopWatch  lazy-loaded StopWatch instance
 * @property Debug\Utility\Utf8   $utf8          lazy-loaded Utf8 instance
 * @property Debug\Utility        $utility       lazy-loaded Utility instance
 *
 * @psalm-consistent-constructor
 */
class Debug
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
    const VERSION = '3.0.0-b1';

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
        // which error types appear as "error" in debug console... all other errors are "warn"
        'errorMask' => 0,
        'emailFrom' => null,    // null = use php's default (php.ini: sendmail_from)
        'emailFunc' => 'mail',  // callable
        'emailLog' => false,    // Whether to email a debug log.  (requires 'collect' to also be true)
                                //   false:             email will not be sent
                                //   true or 'always':  email sent (if log is not output)
                                //   'onError':         email sent if error occured (unless output)
        'emailTo' => 'default', // will default to $_SERVER['SERVER_ADMIN'] if non-empty, null otherwise
        'exitCheck' => true,
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
        'redactReplace' => null,        // closure
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

    protected $config;
    protected $container;
    protected $internal;
    private static $instance;
    protected static $methodDefaultArgs = array();
    protected $parentInstance;
    protected $readOnly = array(
        'parentInstance',
        'rootInstance',
    );
    protected $rootInstance;
    protected $serviceContainer;

    /**
     * Constructor
     *
     * @param array $cfg config
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function __construct($cfg = array())
    {
        $this->cfg['errorMask'] = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                        | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR;
        $this->cfg['redactReplace'] = function ($str, $key) {
            // "use" our function params so things (ie phpmd) don't complain
            array($str, $key);
            return '█████████';
        };
        $this->bootstrap($cfg);
    }

    /**
     * Magic method... inaccessible method called.
     *
     * If method not found in internal class, treat as a custom method.
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public function __call($methodName, $args)
    {
        $callable = array($this->internal, $methodName);
        if (\is_callable($callable)) {
            return \call_user_func_array($callable, $args);
        }
        $logEntry = new LogEntry(
            $this,
            $methodName,
            $args
        );
        $this->internal->publishBubbleEvent(self::EVENT_CUSTOM_METHOD, $logEntry);
        if ($logEntry['handled'] !== true) {
            $logEntry->setMeta('isCustomMethod', true);
            $this->appendLog($logEntry);
        }
        return $logEntry['return'];
    }

    /**
     * Magic method to allow us to call instance methods statically
     *
     * Prefix the instance method with an underscore ie
     *    \bdk\Debug::_log('logged via static method');
     *
     * @param string $methodName Inaccessible method name
     * @param array  $args       Arguments passed to method
     *
     * @return mixed
     */
    public static function __callStatic($methodName, $args)
    {
        $methodName = \ltrim($methodName, '_');
        if (!self::$instance) {
            if ($methodName === 'setCfg') {
                /*
                    Treat as a special case
                    Want to initialize with the passed config vs initialize, then setCfg
                    ie _setCfg(array('route'=>'html')) via command line
                    we don't want to first initialize with default STDERR output
                */
                $cfg = \is_array($args[0])
                    ? $args[0]
                    : array($args[0] => $args[1]);
                new static($cfg);
                return;
            }
            new static();
        }
        /*
            Add 'statically' meta arg
            Not all methods expect meta args... so make sure it comes after expected args
        */
        $defaultArgs = self::getMethodDefaultArgs($methodName);
        $args = \array_replace($defaultArgs, $args);
        $args[] = self::meta('statically');
        return \call_user_func_array(array(self::$instance, $methodName), $args);
    }

    /**
     * Magic method to get inaccessible / undefined properties
     * Lazy load child classes
     *
     * @param string $property property name
     *
     * @return mixed property value
     */
    public function __get($property)
    {
        if (\in_array($property, array('config', 'internal'))) {
            $caller = $this->backtrace->getCallerInfo();
            $this->errorHandler->handleError(
                E_USER_NOTICE,
                'property "' . $property . '" is not accessible',
                $caller['file'],
                $caller['line']
            );
            return;
        }
        if ($this->serviceContainer->has($property)) {
            return $this->serviceContainer[$property];
        }
        if ($this->container->has($property)) {
            return $this->container[$property];
        }
        /*
            "Read-only" properties
        */
        if (\in_array($property, $this->readOnly)) {
            return $this->{$property};
        }
        /*
            Check getter method (although not utilized)
        */
        $getter = 'get' . \ucfirst($property);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
        return null;
    }

    /*
        Debugging Methods
    */

    /**
     * Display an alert at the top of the log
     *
     * Can use styling & substitutions.
     * If using substitutijons, will need to pass $level & $dismissible as meta values
     *
     * @param string $message     message
     * @param string $level       (error), info, success, warn
     *                               "danger" and "warning" are still accepted, however deprecated
     * @param bool   $dismissible (false) Whether to display a close icon/button
     *
     * @return void
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function alert($message, $level = 'error', $dismissible = false)
    {
        $args = \func_get_args();
        /*
            Create a temporary LogEntry so we can test if we passed substitutions
        */
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            $args
        );
        $levelsAllowed = array('danger','error','info','success','warn','warning');
        $haveSubstitutions = $logEntry->containsSubstitutions() && \array_key_exists(1, $args) && !\in_array($args[1], $levelsAllowed);
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            $args,
            array(
                'level' => 'error',
                'dismissible' => false,
            ),
            $haveSubstitutions
                ? array()
                : array(
                    'message' => null,
                    'level' => 'error',
                    'dismissible' => false,
                ),
            array('level','dismissible')
        );
        $this->internal->alertLevel($logEntry);
        $this->data->set('logDest', 'alerts');
        $this->appendLog($logEntry);
        $this->data->set('logDest', 'auto');
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
     * @return void
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
            return;
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
    }

    /**
     * Clear the log
     *
     * This method executes even if `collect` is false
     *
     * @param int $flags A bitmask of options
     *                     `self::CLEAR_ALERTS` : Clear alerts generated with `alert()`
     *                     `self::CLEAR_LOG` : **default** Clear log entries (excluding warn & error)
     *                     `self::CLEAR_LOG_ERRORS` : Clear log, warn, & error
     *                     `self::CLEAR_SUMMARY` : Clear summary entries (excluding warn & error)
     *                     `self::CLEAR_SUMMARY_ERRORS` : Clear summary warn & error
     *                     `self::CLEAR_ALL` :  clear all everything
     *                     `self::CLEAR_SILENT` : Don't add log entry
     *
     * @return void
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function clear($flags = self::CLEAR_LOG)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            array(
                'bitmask' => self::CLEAR_LOG,
            ),
            array('bitmask')
        );
        $this->methodClear->doClear($logEntry);
        // even if cleared from within summary, let's log this in primary log
        $this->data->set('logDest', 'main');
        $this->appendLog($logEntry);
        $this->data->set('logDest', 'auto');
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * Count is maintained even when `collect` is false
     * If collect = false, `count()` will be performed "silently"
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
     * Counter is reset even when debugging is disabled (ie collect = false).
     *
     * @param mixed $label (optional) specify the counter to reset
     * @param int   $flags (optional) currently only one option :
     *                       \bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return void
     */
    public function countReset($label = 'default', $flags = 0)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodCount->countReset($logEntry);
    }

    /**
     * Log an error message.
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return void
     */
    public function error()
    {
        $this->internal->doError(__FUNCTION__, \func_get_args());
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
     * @return void
     */
    public function group()
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodGroup->methodGroup($logEntry);
    }

    /**
     * Create a new inline group
     *
     * Unline group(), groupCollapsed(), will initially be collapsed
     *
     * @param mixed $arg,... label / values
     *
     * @return void
     */
    public function groupCollapsed()
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodGroup->methodGroup($logEntry);
    }

    /**
     * Close current group
     *
     * Every call to `group()` and `groupCollapsed()` should be paired with `groupEnd()`
     *
     * The optional return value will be visible when the group is both expanded and collapsed.
     *
     * @param mixed $value (optional) "return" value
     *
     * @return void
     */
    public function groupEnd($value = Abstracter::UNDEFINED)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            array(
                $value,
            )
        );
        $this->methodGroup->methodGroupEnd($logEntry);
    }

    /**
     * Open a "summary" group
     *
     * Debug methods called from within a groupSummary will appear at the top of the log.
     * Call `groupEnd()` to close the summary group
     *
     * All groupSummary groups will appear together in a single group
     *
     * @param int $priority (0) The higher the priority, the earlier it will appear.
     *
     * @return void
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
            array(
                'priority' => 0,
            ),
            array('priority')
        );
        $this->methodGroup->methodGroupSummary($logEntry);
    }

    /**
     * Uncollapse ancestor groups
     *
     * This will only occur if `cfg['collect']` is currently `true`
     *
     * @return void
     */
    public function groupUncollapse()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodGroup->methodGroupUncollapse($logEntry);
    }

    /**
     * Log some informative information
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return void
     */
    public function info()
    {
        $this->appendLog(new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        ));
    }

    /**
     * Log general information
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return void
     */
    public function log()
    {
        $args = \func_get_args();
        if (\count($args) === 1) {
            if ($args[0] instanceof LogEntry) {
                $this->appendLog($args[0]);
                return;
            }
            if ($args[0] instanceof Error) {
                $this->container['internalEvents']->onError($args[0]);
                return;
            }
        }
        $this->appendLog(new LogEntry(
            $this,
            __FUNCTION__,
            $args
        ));
    }

    /**
     * Starts recording a performance profile
     *
     * @param string $name Optional Profile name
     *
     * @return void
     */
    public function profile($name = null)
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            array(
                'name' => null,
            ),
            array('name')
        );
        $this->methodProfile->doProfile($logEntry);
    }

    /**
     * Stops recording profile info & adds info to the log
     *
     *  * if name is passed and it matches the name of a profile being recorded, then that profile is stopped.
     *  * if name is passed and it does not match the name of a profile being recorded, nothing will be done
     *  * if name is not passed, the most recently started profile is stopped (named, or non-named).
     *
     * @param string $name Optional Profile name
     *
     * @return void
     */
    public function profileEnd($name = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            array(
                'name' => null
            ),
            array('name')
        );
        $this->methodProfile->profileEnd($logEntry);
    }

    /**
     * Output array or object as a table
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
     * @return void
     */
    public function table()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $this->methodTable->doTable($logEntry);
        $this->appendLog($logEntry);
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
     * @return void
     */
    public function time($label = null, $duration = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            array(
                'label' => null,
                'duration' => null,
            )
        );
        $this->methodTime->doTime($logEntry);
    }

    /**
     * Behaves like a stopwatch.. logs and returns running time
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
     * @param string $label (optional) unique label
     * @param bool   $log   (true) log it, or return only
     *                        if passed, takes precedence over silent meta val
     *
     * @return float|false The duration (in sec).
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function timeEnd($label = null, $log = true)
    {
        $logEntry = $this->internal->timeLogEntry(__FUNCTION__, \func_get_args());
        return $this->methodTime->timeEnd($logEntry);
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
     * @param string $label (optional) unique label
     * @param bool   $log   (true) log it, or return only
     *                        if passed, takes precedence over silent meta val
     *
     * @return float|false The duration (in sec).  `false` if specified label does not exist
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function timeGet($label = null, $log = true)
    {
        $logEntry = $this->internal->timeLogEntry(__FUNCTION__, \func_get_args());
        return $this->methodTime->timeGet($logEntry);
    }

    /**
     * Logs the current value of a timer that was previously started via `time()`
     *
     * also logs additional arguments
     *
     * @param string $label   (optional) unique label
     * @param mixed  $arg,... (optional) additional values to be logged with time
     *
     * @return void
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
            array(
                'label' => null,
            )
        );
        $this->methodTime->timeLog($logEntry);
    }

    /**
     * Log a stack trace
     *
     * Essentially PHP's `debug_backtrace()`, but displayed as a table
     *
     * @param bool   $inclContext Include code snippet
     * @param string $caption     (optional) Specify caption for the trace table
     *
     * @return void
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function trace($inclContext = false, $caption = 'trace')
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'columns' => array('file','line','function'),
                'detectFiles' => true,
                'inclArgs' => true, // incl arguments with context?
                                    //   may want to set meta['cfg']['objectsExclude'] = '*'
                'sortable' => false,
                'trace' => null,  // set to specify trace
            ),
            array(
                'inclContext' => false,
                'caption' => 'trace',
            ),
            array(
                'caption',
                'inclContext',
            )
        );
        $this->internal->doTrace($logEntry);
    }

    /**
     * Log a warning
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... message / values
     *
     * @return void
     */
    public function warn()
    {
        $this->internal->doError(__FUNCTION__, \func_get_args());
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
            return $this->internal->getDefaultRoute(); // returns string
        }
        if ($opt === self::CONFIG_DEBUG) {
            return $this->arrayUtil->pathGet($this->cfg, $path);
        }
        return $this->config->get($path, $opt === self::CONFIG_INIT);
    }

    /**
     * Get dumper
     *
     * @param string $name      classname
     * @param bool   $checkOnly (false) only check if initialized
     *
     * @return \bdk\Debug\Dump\Base|bool
     *
     * @psalm-return ($checkOnly is true ? bool : \bdk\Debug\Dump\Base)
     */
    public function getDump($name, $checkOnly = false)
    {
        /** @var \bdk\Debug\Dump\Base|bool */
        return $this->getDumpRoute('dump', $name, $checkOnly);
    }

    /**
     * Get and clear headers that need to be output
     *
     * @return array headerName=>value array
     */
    public function getHeaders()
    {
        $headers = $this->data->get('headers');
        $this->data->set('headers', array());
        return $headers;
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
     * Get route
     *
     * @param string $name      classname
     * @param bool   $checkOnly (false) only check if initialized
     *
     * @return RouteInterface|bool
     *
     * @psalm-return ($checkOnly is true ? bool : RouteInterface)
     */
    public function getRoute($name, $checkOnly = false)
    {
        /** @var RouteInterface|bool */
        return $this->getDumpRoute('route', $name, $checkOnly);
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
        $count = \count($args);
        /** @var mixed[] make psalm happy */
        $args = \array_replace(array(null, null, null), $args);
        if (\is_array($args[0])) {
            $args[0]['debug'] = self::META;
            return $args[0];
        }
        if (!\is_string($args[0])) {
            return array('debug' => self::META);
        }
        if ($args[0] === 'cfg') {
            if (\is_array($args[1])) {
                return array(
                    'cfg' => $args[1],
                    'debug' => self::META,
                );
            }
            if (!\is_string($args[1])) {
                // invalid cfg key
                return array('debug' => self::META);
            }
            return array(
                'cfg' => array(
                    $args[1] => $count > 2
                        ? $args[2]
                        : true,
                ),
                'debug' => self::META,
            );
        }
        return array(
            $args[0] => $count > 1
                ? $args[1]
                : true,
            'debug' => self::META,
        );
    }

    /**
     * Update dependencies
     *
     * This is called during bootstrap and from Internal::onConfig
     *    Internal::onConfig has higher priority than our own onConfig handler
     *
     * @param \bdk\Container\ServiceProviderInterface|callable|array $val dependency definitions
     *
     * @return array
     */
    public function onCfgServiceProvider($val)
    {
        $getContainerRawVals = function (Container $container) {
            $keys = $container->keys();
            $return = array();
            foreach ($keys as $key) {
                $return[$key] = $container->raw($key);
            }
            return $return;
        };

        if ($val instanceof ServiceProviderInterface) {
            /*
                convert to array
            */
            $containerTmp = new Container();
            $containerTmp->registerProvider($val);
            $val = $getContainerRawVals($containerTmp);
        } elseif (\is_callable($val)) {
            /*
                convert to array
            */
            $containerTmp = new Container();
            \call_user_func($val, $containerTmp);
            $val = $getContainerRawVals($containerTmp);
        } elseif (!\is_array($val)) {
            return $val;
        }

        $services = $this->container['services'];
        foreach ($val as $k => $v) {
            if (\in_array($k, $services)) {
                $this->serviceContainer[$k] = $v;
                unset($val[$k]);
                continue;
            }
            $this->container[$k] = $v;
        }

        return $val;
    }

    /**
     * Debug::EVENT_CONFIG event listener
     *
     * Since setCfg() passes config through Config, we need a way for Config to pass values back.
     *
     * @param Event $event Debug::EVENT_CONFIG Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event['debug'];
        if (!$cfg) {
            return;
        }
        $valActions = array(
            'logServerKeys' => function ($val) {
                // don't append, replace
                $this->cfg['logServerKeys'] = array();
                return $val;
            },
            'route' => array($this, 'onCfgRoute'),
        );
        $valActions = \array_intersect_key($valActions, $cfg);
        foreach ($valActions as $key => $callable) {
            /** @psalm-suppress TooManyArguments */
            $cfg[$key] = $callable($cfg[$key]);
        }
        $this->cfg = $this->arrayUtil->mergeDeep($this->cfg, $cfg);
        /*
            propagate updated vals to child channels
        */
        $channels = $this->getChannels(false, true);
        if ($channels) {
            $event['debug'] = $cfg;
            $cfg = $this->internal->getPropagateValues($event->getValues());
            foreach ($channels as $channel) {
                $channel->config->set($cfg);
            }
        }
    }

    /**
     * Return debug log output
     *
     * Publishes Debug::EVENT_OUTPUT event and returns event's 'return' value
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
            $this->internal->obEnd();
            return null;
        }
        $route = $this->getCfg('route');
        if (\is_string($route)) {
            // Internal::onConfig will convert to route object
            $this->config->set('route', $route);
        }
        /*
            Publish Debug::EVENT_OUTPUT
                on all descendant channels
                rootInstance
                finally ourself
            This isn't outputing each channel, but for performing any per-channel "before output" activities
        */
        $channels = $this->getChannels(true);
        if ($this !== $this->rootInstance) {
            $channels[] = $this->rootInstance;
        }
        $channels[] = $this;
        foreach ($channels as $channel) {
            $event = $channel->eventManager->publish(
                self::EVENT_OUTPUT,
                $channel,
                array(
                    'headers' => array(),
                    'isTarget' => $channel === $this,
                    'return' => '',
                )
            );
        }
        if (!$this->parentInstance) {
            $this->data->set('outputSent', true);
        }
        $this->config->set($cfgRestore);
        $this->internal->obEnd();
        return $event['return'];
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

    /**
     * A wrapper for errorHandler->setErrorCaller
     *
     * @param array $caller (optional) null (default) determine automatically
     *                      empty value (false, "", 0, array()) clear
     *                      array manually set
     *
     * @return void
     */
    public function setErrorCaller($caller = null)
    {
        if ($caller === null) {
            $caller = $this->backtrace->getCallerInfo(1);
            $caller = array(
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        }
        if ($caller) {
            // groupEnd will check depth and potentially clear errorCaller
            $caller['groupDepth'] = $this->methodGroup->getDepth();
        }
        $this->errorHandler->setErrorCaller($caller);
    }

    /*
        Non-Public methods
    */

    /**
     * Debug class autoloader
     *
     * @param string $className classname to attempt to load
     *
     * @return void
     */
    protected function autoloader($className)
    {
        $className = \ltrim($className, '\\'); // leading backslash _shouldn't_ have been passed
        $psr4Map = array(
            'bdk\\Debug\\' => __DIR__,
            'bdk\\Container\\' => __DIR__ . '/../Container',
            'bdk\\ErrorHandler\\' => __DIR__ . '/../ErrorHandler',
            'bdk\\PubSub\\' => __DIR__ . '/../PubSub',
        );
        $classMap = array(
            'bdk\\Backtrace' => __DIR__ . '/../Backtrace/Backtrace.php',
            'bdk\\Container' => __DIR__ . '/../Container/Container.php',
            'bdk\\Debug\\Utility' => __DIR__ . '/Utility/Utility.php',
            'bdk\\ErrorHandler' => __DIR__ . '/../ErrorHandler/ErrorHandler.php',
        );
        if (isset($classMap[$className])) {
            require $classMap[$className];
            return;
        }
        foreach ($psr4Map as $namespace => $dir) {
            if (\strpos($className, $namespace) === 0) {
                $rel = \substr($className, \strlen($namespace));
                $rel = \str_replace('\\', '/', $rel);
                require $dir . '/' . $rel . '.php';
                return;
            }
        }
    }

    /**
     * Store the arguments
     * if collect is false -> does nothing
     * otherwise:
     *   + abstracts values
     *   + publishes Debug::EVENT_LOG event
     *   + appends log (if event propagation not stopped)
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return bool whether or not entry got appended
     */
    protected function appendLog(LogEntry $logEntry)
    {
        if (!$this->cfg['collect'] && !$logEntry['forcePublish']) {
            return false;
        }
        $cfgRestore = array();
        if (isset($logEntry['meta']['cfg'])) {
            $cfgRestore = $this->config->set($logEntry['meta']['cfg']);
            $logEntry->setMeta('cfg', null);
        }
        if (\count($logEntry['args']) === 1 && $this->utility->isThrowable($logEntry['args'][0])) {
            $exception = $logEntry['args'][0];
            $logEntry['args'][0] = $exception->getMessage();
            $logEntry->setMeta(array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->backtrace->get(null, 0, $exception),
            ));
        }
        foreach ($logEntry['args'] as $i => $v) {
            $logEntry['args'][$i] = $this->abstracter->crate($v, $logEntry['method']);
        }
        $this->internal->publishBubbleEvent(self::EVENT_LOG, $logEntry);
        if ($cfgRestore) {
            $this->config->set($cfgRestore);
        }
        if ($logEntry['appendLog']) {
            $this->data->appendLog($logEntry);
            return true;
        }
        return false;
    }

    /**
     * Initialize autoloader, container, & config
     *
     * @param array $cfg passed cfg
     *
     * @return void
     */
    private function bootstrap($cfg)
    {
        if (!isset(self::$instance)) {
            /*
               self::getInstance() will always return initial/first instance
            */
            self::$instance = $this;
            /*
                Only register autoloader:
                  a. on initial instance (even though re-registering function does't re-register)
                  b. if we're unable to to find our Config class (must not be using Composer)
            */
            if (\class_exists('bdk\\ErrorHandler') === false) {
                \spl_autoload_register(array($this, 'autoloader'));
            }
        }

        $this->bootstrapInstance($cfg);
        $this->bootstrapContainer($cfg);

        $this->config = $this->container['config'];
        $this->container->setCfg('onInvoke', array($this->config, 'onContainerInvoke'));
        $this->serviceContainer->setCfg('onInvoke', array($this->config, 'onContainerInvoke'));
        $this->internal = $this->container['internal'];
        $this->eventManager->addSubscriberInterface($this->container['addonMethods']);
        $this->addPlugin($this->container['configEventSubscriber']);
        $this->addPlugin($this->container['internalEvents']);
        $this->addPlugin($this->container['redaction']);
        $this->eventManager->subscribe(self::EVENT_CONFIG, array($this, 'onConfig'));

        $this->serviceContainer['errorHandler'];

        $this->config->set($cfg);

        if (!$this->parentInstance) {
            // we're the root instance
            // this is the root instance
            $this->data->set('requestId', $this->internal->requestId());
            $this->data->set('entryCountInitial', $this->data->get('log/__count__'));

            $this->addPlugin($this->container['logEnv']);
            $this->addPlugin($this->container['logReqRes']);
        }
        $this->eventManager->publish(self::EVENT_BOOTSTRAP, $this);
    }

    /**
     * Initialize dependancy containers
     *
     * @param array $cfg Raw config passed to constructor
     *
     * @return void
     */
    private function bootstrapContainer(&$cfg)
    {
        $containerCfg = array();
        if (isset($cfg['debug']['container'])) {
            $containerCfg = $cfg['debug']['container'];
        } elseif (isset($cfg['container'])) {
            $containerCfg = $cfg['container'];
        }

        $this->container = new Container(
            array(
                'debug' => $this,
            ),
            $containerCfg
        );
        $this->container->registerProvider(new ServiceProvider());

        if (empty($cfg['debug']['parent'])) {
            // root instance
            $this->serviceContainer = new Container(
                array(
                    'debug' => $this,
                ),
                $containerCfg
            );
            foreach ($this->container['services'] as $service) {
                $this->serviceContainer[$service] = $this->container->raw($service);
                unset($this->container[$service]);
            }
        }
        $this->serviceContainer = $this->rootInstance->serviceContainer;

        /*
            Now populate with overrides
        */
        $serviceProvider = $this->cfg['serviceProvider'];
        if (isset($cfg['debug']['serviceProvider'])) {
            $serviceProvider = $cfg['debug']['serviceProvider'];
            // unset so we don't do this again with setCfg
            unset($cfg['debug']['serviceProvider']);
        } elseif (isset($cfg['serviceProvider'])) {
            $serviceProvider = $cfg['serviceProvider'];
            // unset so we don't do this again with setCfg
            unset($cfg['serviceProvider']);
        }

        $this->cfg['serviceProvider'] = $this->onCfgServiceProvider($serviceProvider);
    }

    /**
     * Set instance, rootInstance, & parentInstance
     *
     * @param array $cfg Raw config passed to constructor
     *
     * @return void
     */
    private function bootstrapInstance($cfg)
    {
        $this->rootInstance = $this;
        if (isset($cfg['debug']['parent'])) {
            $this->parentInstance = $cfg['debug']['parent'];
            while ($this->rootInstance->parentInstance) {
                $this->rootInstance = $this->rootInstance->parentInstance;
            }
            unset($this->cfg['parent']);
            return;
        }
    }

    /**
     * Get Dump or Route instance
     *
     * @param 'dump'|'route' $cat       "Category" (dump or route)
     * @param string         $name      html, text, etc)
     * @param bool           $checkOnly Only check if initialized?
     *
     * @return \bdk\Debug\Dump\Base|RouteInterface|bool
     *
     * @psalm-return ($checkOnly is true ? bool : \bdk\Debug\Dump\Base|RouteInterface)
     */
    private function getDumpRoute($cat, $name, $checkOnly)
    {
        $property = $cat . \ucfirst($name);
        $containerHas = $this->container->has($property);
        if ($checkOnly) {
            return $containerHas;
        }
        if ($containerHas) {
            return $this->container[$property];
        }
        $classname = 'bdk\\Debug\\' . \ucfirst($cat) . '\\' . \ucfirst($name);
        if (\class_exists($classname)) {
            /** @var \bdk\Debug\Dump\Base|RouteInterface */
            $val = new $classname($this);
            if ($val instanceof ConfigurableInterface) {
                $cfg = $this->config->get($property, self::CONFIG_INIT);
                $val->setCfg($cfg);
            }
            $this->container[$property] = $val;
            return $val;
        }
        $caller = $this->backtrace->getCallerInfo();
        $this->errorHandler->handleError(
            E_USER_NOTICE,
            '"' . $property . '" is not accessible',
            $caller['file'],
            $caller['line']
        );
    }

    /**
     * Get Method's default argument list
     *
     * @param string $methodName Name of the method
     *
     * @return array
     */
    private static function getMethodDefaultArgs($methodName)
    {
        $defaultArgs = array();
        if (isset(self::$methodDefaultArgs[$methodName])) {
            return self::$methodDefaultArgs[$methodName];
        }
        if (\method_exists(self::$instance, $methodName) === false) {
            return $defaultArgs;
        }
        $refMethod = new ReflectionMethod(self::$instance, $methodName);
        $params = $refMethod->getParameters();
        foreach ($params as $refParameter) {
            $defaultArgs[] = $refParameter->isOptional()
                ? $refParameter->getDefaultValue()
                : null;
        }
        self::$methodDefaultArgs[$methodName] = $defaultArgs;
        return $defaultArgs;
    }

    /**
     * If "core" route, store in container
     *
     * @param mixed $val       route value
     * @param bool  $addPlugin (true) Should we add as plugin?
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRoute($val, $addPlugin = true)
    {
        if (!($val instanceof RouteInterface)) {
            return $val;
        }
        if ($addPlugin) {
            $this->addPlugin($val);
        }
        $classname = \get_class($val);
        $prefix = __NAMESPACE__ . '\\Debug\\Route\\';
        $containerName = \strpos($classname, $prefix) === 0
            ? 'route' . \substr($classname, \strlen($prefix))
            : null;
        if ($containerName && !$this->container->has($containerName)) {
            $this->container->offsetSet($containerName, $val);
        }
        if ($val->appendsHeaders()) {
            $this->internal->obStart();
        }
        return $val;
    }
}
