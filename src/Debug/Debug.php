<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\AssetProviderInterface;
use bdk\Debug\ConfigurableInterface;
use bdk\Debug\LogEntry;
use bdk\Debug\Utilities;
use bdk\ErrorHandler;
use bdk\ErrorHandler\ErrorEmailer;
use bdk\PubSub\SubscriberInterface;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use Psr\Http\Message\ResponseInterface; // PSR-7
use ReflectionMethod;
use SplObjectStorage;

/**
 * Web-browser/javascript like console class for PHP
 *
 * @property Abstracter    $abstracter    lazy-loaded Abstracter instance
 * @property ErrorEmailer  $errorEmailer  lazy-loaded ErrorEmailer instance
 * @property ErrorHandler  $errorHandler  lazy-loaded ErrorHandler instance
 * @property EventManager  $eventManager  lazy-loaded EventManager instance
 * @property Internal      $internal      lazy-loaded Internal instance
 * @property Logger        $logger        lazy-loaded PSR-3 instance
 * @property MethodClear   $methodClear   lazy-loaded MethodClear instance
 * @property MethodProfile $methodProfile lazy-loaded MethodProfile instance
 * @property MethodTable   $methodTable   lazy-loaded MethodTable instance
 * @property Utf8          $utf8          lazy-loaded Utf8 instance
 * @property Utilities     $utilities     lazy-loaded Utilities instance
 */
class Debug
{

    private static $instance;
    private $channels = array();
    protected $cfg = array();
    protected $data = array();
    protected $groupStackRef;   // points to $this->groupStacks[x] (where x = 'main' or (int) priority)
    protected $logRef;          // points to either log or logSummary[priority]
    protected $config;          // config instance
    protected $parentInstance;
    protected $registeredPlugins;   // SplObjectHash
    protected $rootInstance;
    protected static $methodDefaultArgs = array();

    /**
     * @var ResponseInterface
     */
    protected $response;

    const CLEAR_ALERTS = 1;
    const CLEAR_LOG = 2;
    const CLEAR_LOG_ERRORS = 4;
    const CLEAR_SUMMARY = 8;
    const CLEAR_SUMMARY_ERRORS = 16;
    const CLEAR_ALL = 31;
    const CLEAR_SILENT = 32;
    const COUNT_NO_INC = 1;
    const COUNT_NO_OUT = 2;
    const META = "\x00meta\x00";
    const VERSION = '3.0';

    /**
     * Constructor
     *
     * @param array        $cfg          config
     * @param EventManager $eventManager optional - specify EventManager instance
     *                                      will use new instance if not specified
     * @param ErrorHandler $errorHandler optional - specify ErrorHandler instance
     *                                      if not specified, will use singleton or new instance
     */
    public function __construct($cfg = array(), EventManager $eventManager = null, ErrorHandler $errorHandler = null)
    {
        $this->cfg = array(
            'collect'   => false,
            'key'       => null,
            'output'    => false,           // output the log?
            'arrayShowListKeys' => true,
            'channelIcon' => null,
            'channelName' => 'general',
            'channelShow' => true,          // wheter initially filtered or not
            'enableProfiling' => false,
            // which error types appear as "error" in debug console... all other errors are "warn"
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR,
            'emailFrom' => null,    // null = use php's default (php.ini: sendmail_from)
            'emailFunc' => 'mail',  // callable
            'emailLog'  => false,   // Whether to email a debug log.  (requires 'collect' to also be true)
                                    //
                                    //   false:             email will not be sent
                                    //   true or 'always':  email sent (if log is not output)
                                    //   'onError':         email sent if error occured (unless output)
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'factories' => $this->getDefaultFactories(),
            'headerMaxAll' => 250000,
            'headerMaxPer' => null,
            'logEnvInfo' => array(      // may be set by passing a list
                'cookies' => true,
                'gitInfo' => true,
                'headers' => true,
                'phpInfo' => true,
                'post' => true,
                'serverVals' => true,
            ),
            'logResponse' => 'auto',
            'logRuntime' => true,
            'logServerKeys' => array('REMOTE_ADDR','REQUEST_TIME','REQUEST_URI','SERVER_ADDR','SERVER_NAME'),
            'onBootstrap' => null,          // callable
            'onLog' => null,                // callable
            'onOutput' => null,             // callable
            'outputHeaders' => true,        // ie, ChromeLogger and/or firePHP headers
            'redactKeys' => array(),        // case-insensitive
            'redactReplace' => function ($str, $key) {
                return '█████████';
            },
            'route' => 'auto',              // 'auto', chromeLogger', 'firephp', 'html', 'script', 'steam', 'text', or RouteInterface,
                                            //   if 'auto', will be determined automatically
                                            //   if null, no output (unless output plugin added manually)
            'routeNonHtml' => 'chromeLogger',
            'services' => $this->getDefaultServices(),
        );
        $this->data = array(
            'alerts'            => array(), // alert entries.  alerts will be shown at top of output when possible
            'counts'            => array(), // count method
            'entryCountInitial' => 0,       // store number of log entries created during init
            'groupStacks' => array(
                'main' => array(),  // array('channel' => Debug, 'collect' => bool)[]
            ),
            'groupPriorityStack' => array(), // array of priorities
                                            //   used to return to the previous summary when groupEnd()ing out of a summary
                                            //   this allows calling groupSummary() while in a groupSummary
            'headers'           => array(), // headers that need to be output (ie chromeLogger & firePhp)
            'log'               => array(),
            'logSummary'        => array(), // summary log entries subgrouped by priority
            'outputSent'        => false,
            'profileAutoInc'    => 1,
            'profileInstances'  => array(),
            'requestId'         => $this->utilities->requestId(),
            'runtime'           => array(
                // memoryPeakUsage, memoryLimit, & memoryLimit get stored here
            ),
            'timers' => array(      // timer method
                'labels' => array(
                    // label => array(accumulatedTime, lastStartedTime|null)
                    'debugInit' => array(
                        0,
                        isset($_SERVER['REQUEST_TIME_FLOAT'])
                            ? $_SERVER['REQUEST_TIME_FLOAT']
                            : \microtime(true)
                    ),
                ),
                'stack' => array(),
            ),
        );
        $this->registeredPlugins = new SplObjectStorage();
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
            if (!\class_exists('\\bdk\\Debug\\Config')) {
                \spl_autoload_register(array($this, 'autoloader'));
            }
        }
        if ($eventManager) {
            $cfg['services']['eventManager'] = $eventManager;
        }
        if ($errorHandler) {
            $cfg['services']['errorHandler'] = $errorHandler;
        }
        /*
            Order is important
            a) setCfg (so that this->parentInstance gets set
            b) initialize this->internal after all properties have been initialized
        */
        $this->__get('config')->setValues($cfg);   // since it's defined (albeit null), we need to call __get to initialize
        $this->rootInstance = $this;
        if (isset($this->cfg['parent'])) {
            $this->parentInstance = $this->cfg['parent'];
            while ($this->rootInstance->parentInstance) {
                $this->rootInstance = $this->rootInstance->parentInstance;
            }
            $this->data = &$this->rootInstance->data;
            unset($this->cfg['parent']);
        } else {
            $this->setLogDest();
            $this->data['entryCountInitial'] = \count($this->data['log']);
        }
        /*
            Initialize Internal
        */
        $this->internal;
        /*
            Publish bootstrap event
        */
        $this->eventManager->publish('debug.bootstrap', $this);
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
        if (\method_exists($this->internal, $methodName)) {
            return \call_user_func_array(array($this->internal, $methodName), $args);
        }
        return $this->appendLog(new LogEntry(
            $this,
            $methodName,
            $args,
            array('isCustomMethod' => true)
        ));
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
            if ($methodName == 'setCfg') {
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
            } else {
                new static();
            }
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
        if (isset($this->cfg['services'][$property])) {
            $val = $this->cfg['services'][$property];
            if ($val instanceof \Closure) {
                $val = $val($this);
            }
            $this->{$property} = $val;
            return $val;
        }
        if (isset($this->cfg['factories'][$property])) {
            $val = $this->cfg['factories'][$property];
            if ($val instanceof \Closure) {
                $val = $val($this);
            }
            return $val;
        }
        if (isset($this->{$property})) {
            return $this->{$property};
        }
        $getter = 'get' . \ucfirst($property);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
        $val = null;
        if (\strpos($property, 'route') === 0) {
            $classname = '\\bdk\\Debug\\Route\\' . \substr($property, 5);
            $val = new $classname($this);
            $this->{$property} = $val;
        }
        if (\strpos($property, 'dump') === 0) {
            $classname = '\\bdk\\Debug\\Dump\\' . \substr($property, 4);
            $val = new $classname($this);
            $this->{$property} = $val;
        }
        if ($val instanceof ConfigurableInterface) {
            $val->setCfg($this->config->getValues($property));
        }
        return $val;
    }

    /*
        Debugging Methods
    */

    /**
     * Display an alert at the top of the log
     *
     * @param string  $message     message
     * @param string  $level       (error), info, success, warn
     *                               "danger" and "warning" are still accepted, however deprecated
     * @param boolean $dismissible (false) Whether to display a close icon/button
     *
     * @return void
     */
    public function alert($message, $level = 'error', $dismissible = false)
    {
        // "use" our function params so things (ie phpmd) don't complain
        array($message, $dismissible);
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(),
            array(
                'message' => null,
                'level' => 'error',
                'dismissible' => false,
            ),
            array('level','dismissible')
        );
        $level = $logEntry->getMeta('level');
        /*
            Continue to allow bootstrap "levels"
        */
        $levelTrans = array(
            'danger' => 'error',
            'warning' => 'warn',
        );
        if (isset($levelTrans[$level])) {
            $level = $levelTrans[$level];
        } elseif (!\in_array($level, array('error','info','success','warn'))) {
            $level = 'error';
        }
        $logEntry->setMeta('level', $level);
        $this->setLogDest('alerts');
        $this->appendLog($logEntry);
        $this->setLogDest('auto');
    }

    /**
     * If first argument evaluates `false`, log the remaining paramaters
     *
     * Supports styling & substitutions
     *
     * @param boolean $assertion Any boolean expression. If the assertion is false, the message is logged
     * @param mixed   $msg,...   (optional) variable num of values to output if assertion fails
     *                             if none provided, will use calling file & line num
     *
     * @return void
     */
    public function assert($assertion, $msg = null)
    {
        // "use" our function params so things (ie phpmd) don't complain
        array($msg);
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        $args = $logEntry['args'];
        $assertion = \array_shift($args);
        if (!$assertion) {
            if (!$args) {
                // add default message
                $callerInfo = $this->utilities->getCallerInfo();
                $args = array(
                    'Assertion failed:',
                    $callerInfo['file'] . ' (line ' . $callerInfo['line'] . ')',
                );
                $logEntry->setMeta('detectFiles', true);
            }
            $logEntry['args'] = $args;
            $this->appendLog($logEntry);
        }
    }

    /**
     * Clear the log
     *
     * This method executes even if `collect` is false
     *
     * @param integer $flags A bitmask of options
     *                         `self::CLEAR_ALERTS` : Clear alerts generated with `alert()`
     *                         `self::CLEAR_LOG` : **default** Clear log entries (excluding warn & error)
     *                         `self::CLEAR_LOG_ERRORS` : Clear log warn & error
     *                         `self::CLEAR_SUMMARY` : Clear summary entries (excluding warn & error)
     *                         `self::CLEAR_SUMMARY_ERRORS` : Clear summary warn & error
     *                         `self::CLEAR_ALL` :  clear all everything
     *                         `self::CLEAR_SILENT` : Don't add log entry
     *
     * @return void
     */
    public function clear($flags = self::CLEAR_LOG)
    {
        // "use" our function params so things (ie phpmd) don't complain
        array($flags);
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
        $this->methodClear->onLog($logEntry);
        // even if cleared from within summary, let's log this in primary log
        $this->setLogDest('log');
        $this->appendLog($logEntry, $logEntry['publish']);
        $this->setLogDest('auto');
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * Count is maintained even when `collect` is false
     * If collect = false, `count()` will be performed "silently"
     *
     * @param mixed   $label Label.  If omitted, logs the number of times `count()` has been called at this particular line.
     * @param integer $flags (optional) A bitmask of
     *                          `\bdk\Debug::COUNT_NO_INC` : don't increment the counter
     *                                                       (ie, just get the current count)
     *                          `\bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return integer The new count (or current count when using `COUNT_NO_INC`)
     */
    public function count($label = null, $flags = 0)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        // label may be ommitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        $args = $logEntry['args'];
        if (\count($args) == 1 && \is_int($args[0])) {
            $label = null;
            $flags = $args[0];
        } else {
            $args = \array_slice($args, 0, 2);
            $args = \array_combine(
                array('label', 'flags'),
                \array_replace(array(null, 0), $args)
            );
            \extract($args);
        }
        if (isset($label)) {
            $dataLabel = (string) $label;
        } else {
            // determine calling file & line
            $callerInfo = $this->utilities->getCallerInfo();
            $logEntry['meta'] = \array_merge(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ), $logEntry['meta']);
            $label = 'count';
            $dataLabel = $logEntry['meta']['file'] . ': ' . $logEntry['meta']['line'];
        }
        if (!isset($this->data['counts'][$dataLabel])) {
            $this->data['counts'][$dataLabel] = 0;
        }
        if (!($flags & self::COUNT_NO_INC)) {
            $this->data['counts'][$dataLabel]++;
        }
        $count = $this->data['counts'][$dataLabel];
        if (!($flags & self::COUNT_NO_OUT)) {
            $logEntry['args'] = array(
                (string) $label,
                $count,
            );
            $this->appendLog($logEntry);
        }
        return $count;
    }

    /**
     * Resets the counter
     *
     * Counter is reset even when debugging is disabled (ie collect = false).
     *
     * @param mixed   $label (optional) specify the counter to reset
     * @param integer $flags (optional) currently only one option :
     *                          `\bdk\Debug::COUNT_NO_OUT` : don't output/log
     *
     * @return void
     */
    public function countReset($label = 'default', $flags = null)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args()
        );
        // label may be ommitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        $args = $logEntry['args'];
        if (\count($args) == 1 && \is_int($args[0])) {
            $label = 'default';
            $flags = $args[0];
        } else {
            $args = \array_slice($args, 0, 2);
            $args = \array_combine(
                array('label', 'flags'),
                \array_replace(array('default', 0), $args)
            );
            \extract($args);
        }
        if (isset($this->data['counts'][$label])) {
            $this->data['counts'][$label] = 0;
            $logEntry['args'] = array(
                (string) $label,
                0,
            );
        } else {
            $logEntry['args'] = array('Counter \'' . $label . '\' doesn\'t exist.');
        }
        if (!($flags & self::COUNT_NO_OUT)) {
            $this->appendLog($logEntry);
        }
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
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'detectFiles' => true,
            )
        );
        // file & line meta may already be set (ie coming via errorHandler)
        // file & line may also be defined as null
        $default = "\x00default\x00";
        if ($logEntry->getMeta('file', $default) === $default) {
            $callerInfo = $this->utilities->getCallerInfo();
            $logEntry->setMeta(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ));
        }
        $this->appendLog($logEntry);
    }

    /**
     * Create a new inline group
     *
     * Groups generally get indented and will receive an expand/collapse toggle.
     *
     * Supports styling & substitutions
     *
     * @param mixed $arg,... label / values
     *
     * @return void
     */
    public function group()
    {
        $this->doGroup(__FUNCTION__, \func_get_args());
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
        $this->doGroup(__FUNCTION__, \func_get_args());
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
            \func_get_args(),
            array(),
            array('value' => Abstracter::UNDEFINED)
        );
        $value = $logEntry['args'][0];
        $logEntry['args'] = array();
        $groupStackWas = $this->rootInstance->groupStackRef;
        $haveOpenGroup = false;
        if ($groupStackWas && \end($groupStackWas)['collect'] == $this->cfg['collect']) {
            \array_pop($this->rootInstance->groupStackRef);
            $haveOpenGroup = $this->cfg['collect'];
        }
        if ($this->data['groupPriorityStack'] && !$groupStackWas) {
            // we're closing a summary group
            $priorityClosing = \array_pop($this->data['groupPriorityStack']);
            // not really necessary to remove this empty placeholder, but lets keep things tidy
            unset($this->data['groupStacks'][$priorityClosing]);
            $this->setLogDest('auto');
            /*
                Publish the debug.log event (regardless of cfg.collect)
                don't actually log
            */
            $logEntry['appendLog'] = false;
            $logEntry->setMeta('closesSummary', true);
            $this->appendLog($logEntry, true);
        } elseif ($haveOpenGroup) {
            if ($value !== Abstracter::UNDEFINED) {
                $this->appendLog(new LogEntry(
                    $this,
                    'groupEndValue',
                    array('return', $value)
                ));
            }
            $this->appendLog($logEntry);
        }
        $errorCaller = $this->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['groupDepth']) && $this->getGroupDepth() < $errorCaller['groupDepth']) {
            $this->errorHandler->setErrorCaller(false);
        }
    }

    /**
     * Open a "summary" group
     *
     * Debug methods called from within a groupSummary will appear at the top of the log.
     * Call `groupEnd()` to close the summary group
     *
     * All groupSummary groups will appear together in a single group
     *
     * @param integer $priority (0) The higher the priority, the earlier it will appear.
     *
     * @return void
     */
    public function groupSummary($priority = 0)
    {
        // "use" our function params so things (ie phpmd) don't complain
        array($priority);
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
        $this->data['groupPriorityStack'][] = $logEntry['meta']['priority'];
        $this->setLogDest('summary');
        /*
            Publish the debug.log event (regardless of cfg.collect)
            don't actually log
        */
        $logEntry['appendLog'] = false;
        // groupSumary's debug.log event should happen on the root instance
        $this->rootInstance->appendLog($logEntry, true);
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
        $curDepth = 0;
        foreach ($this->rootInstance->groupStackRef as $group) {
            $curDepth += (int) $group['collect'];
        }
        $entryKeys = \array_keys($this->internal->getCurrentGroups($this->rootInstance->logRef, $curDepth));
        foreach ($entryKeys as $key) {
            $this->rootInstance->logRef[$key]['method'] = 'group';
        }
        /*
            Publish the debug.log event (regardless of cfg.collect)
            don't actually log
        */
        $logEntry['appendLog'] = false;
        $this->appendLog($logEntry, true);
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
        $logEntry = \count($args) === 1 && $args[0] instanceof LogEntry
            ? $args[0]
            : new LogEntry(
                $this,
                __FUNCTION__,
                $args
            );
        $this->appendLog($logEntry);
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
        if (!$this->cfg['enableProfiling']) {
            $callerInfo = $this->utilities->getCallerInfo();
            $this->appendLog(new LogEntry(
                $this,
                __FUNCTION__,
                array('Profile: Unable to start - enableProfiling opt not set.  ' . $callerInfo['file'] . ' on line ' . $callerInfo['line'] . '.')
            ));
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
        if ($logEntry['meta']['name'] === null) {
            $logEntry['meta']['name'] = 'Profile ' . $this->data['profileAutoInc'];
            $this->data['profileAutoInc']++;
        }
        $name = $logEntry['meta']['name'];
        $message = '';
        if (isset($this->data['profileInstances'][$name])) {
            $instance = $this->data['profileInstances'][$name];
            $instance->end();
            $instance->start();
            // move it to end (last started)
            unset($this->data['profileInstances'][$name]);
            $this->data['profileInstances'][$name] = $instance;
            $message = 'Profile \'' . $name . '\' restarted';
        } else {
            $this->data['profileInstances'][$name] = $this->methodProfile; // factory
            $message = 'Profile \'' . $name . '\' started';
        }
        $logEntry['args'] = array($message);
        $this->appendLog($logEntry);
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
        if ($logEntry['meta']['name'] === null) {
            \end($this->data['profileInstances']);
            $logEntry['meta']['name'] = \key($this->data['profileInstances']);
        }
        $name = $logEntry['meta']['name'];
        if (isset($this->data['profileInstances'][$name])) {
            $instance = $this->data['profileInstances'][$name];
            $data = $instance->end();
            /*
                So that our row keys can receive 'callable' formatting,
                set special '__key' value
            */
            foreach ($data as $k => &$row) {
                $row['__key'] = new Abstraction(array(
                    'type' => 'callable',
                    'value' => $k,
                    'hideType' => true, // don't output 'callable'
                ));
            }
            $caption = 'Profile \'' . $name . '\' Results';
            if ($data) {
                $args = array( $data );
                $logEntry->setMeta(array(
                    'sortable' => true,
                    'caption' => $caption,
                    'totalCols' => array('ownTime'),
                    'columns' => array(),
                ));
            } else {
                $args = array($caption, 'no data');
            }
            unset($this->data['profileInstances'][$name]);
        } else {
            $args = array( $name !== null
                ? 'profileEnd: No such Profile: ' . $name
                : 'profileEnd: Not currently profiling'
            );
        }
        $logEntry['args'] = $args;
        $this->appendLog($logEntry);
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
        $this->methodTable->onLog($logEntry);
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
            array(
                // these meta values are used if duration is passed
                'precision' => 4,
                'silent' => false,
                'template' => '%label: %time',
                'unit' => 'auto',
            ),
            array(
                'label' => null,
                'duration' => null,
            )
        );
        $args = $logEntry['args'];
        $floats = \array_filter($args, function ($val) {
            return \is_float($val);
        });
        $args = \array_values(\array_diff_key($args, $floats));
        $label = $args[0];
        if ($floats) {
            $duration = \reset($floats);
            $logEntry['args'] = array($label);
            $this->doTime($duration, $logEntry);
            return;
        }
        if (isset($label)) {
            $timers = &$this->data['timers']['labels'];
            if (!isset($timers[$label])) {
                // new label
                $timers[$label] = array(0, \microtime(true));
            } elseif (!isset($timers[$label][1])) {
                // no microtime -> the timer is currently paused -> unpause
                $timers[$label][1] = \microtime(true);
            }
        } else {
            $this->data['timers']['stack'][] = \microtime(true);
        }
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
     * @param string  $label (optional) unique label
     * @param boolean $log   (true) log it, or return only
     *                           if passed, takes precedence over silent meta val
     *
     * @return float The duration (in sec).
     */
    public function timeEnd($label = null, $log = true)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'precision' => 4,
                'silent' => false,
                'template' => '%label: %time',
                'unit' => 'auto',
            ),
            array(
                'label' => null,
                'log' => true,
            )
        );
        $numArgs = $logEntry['numArgs'];
        $args = $logEntry['args'];
        $label = $args[0];
        $log = $args[1];
        if ($numArgs == 1 && \is_bool($label)) {
            // log passed as single arg
            $logEntry->setMeta('silent', !$label);
            $label = null;
        } elseif ($numArgs == 2) {
            $logEntry->setMeta('silent', !$log);
        }
        // get non-rounded running time (in seconds)
        $ret = $this->timeGet($label, $this->meta(array(
            'silent' => true,
            'precision' => null,
        )));
        if (isset($label)) {
            if (isset($this->data['timers']['labels'][$label])) {
                $this->data['timers']['labels'][$label] = array(
                    $ret,  // store the new "running" time
                    null,  // "pause" the timer
                );
            }
        } else {
            \array_pop($this->data['timers']['stack']);
        }
        return $this->doTime($ret, $logEntry);
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
     * @param string  $label (optional) unique label
     * @param boolean $log   (true) log it, or return only
     *                           if passed, takes precedence over silent meta val
     *
     * @return float|false The duration (in sec).  `false` if specified label does not exist
     */
    public function timeGet($label = null, $log = true)
    {
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'precision' => 4,
                'silent' => false,
                'template' => '%label: %time',
                'unit' => 'auto',
            ),
            array(
                'label' => null,
                'log' => true,
            )
        );
        $numArgs = $logEntry['numArgs'];
        $args = $logEntry['args'];
        $label = $args[0];
        $log = $args[1];
        if ($numArgs == 1 && \is_bool($label)) {
            // log passed as single arg
            $logEntry->setMeta('silent', !$label);
            $label = null;
        } elseif ($numArgs == 2) {
            $logEntry->setMeta('silent', !$log);
        }
        $microT = 0;
        $elapsed = 0;
        if ($label === null) {
            if (!$this->data['timers']['stack']) {
                list($elapsed, $microT) = $this->data['timers']['labels']['debugInit'];
            } else {
                $microT = \end($this->data['timers']['stack']);
            }
        } elseif (isset($this->data['timers']['labels'][$label])) {
            list($elapsed, $microT) = $this->data['timers']['labels'][$label];
        } else {
            if ($logEntry->getMeta('silent') === false) {
                $this->appendLog(new LogEntry(
                    $this,
                    __FUNCTION__,
                    array('Timer \'' . $label . '\' does not exist'),
                    $logEntry['meta']
                ));
            }
            return false;
        }
        if ($microT) {
            $elapsed += \microtime(true) - $microT;
        }
        return $this->doTime($elapsed, $logEntry);
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
            )
        );
        $args = $logEntry['args'];
        $label = isset($args[0])
            ? $args[0]
            : null;
        $microT = 0;
        $elapsed = 0;
        if ($label === null) {
            $args[0] = 'time';
            if (!$this->data['timers']['stack']) {
                list($elapsed, $microT) = $this->data['timers']['labels']['debugInit'];
            } else {
                $microT = \end($this->data['timers']['stack']);
            }
        } elseif (isset($this->data['timers']['labels'][$label])) {
            list($elapsed, $microT) = $this->data['timers']['labels'][$label];
        } else {
            $args = array('Timer \'' . $label . '\' does not exist');
        }
        $meta = $logEntry['meta'];
        if ($microT) {
            $args[0] .= ': ';
            $elapsed = $this->utilities->formatDuration(
                $elapsed + \microtime(true) - $microT,
                $meta['unit'],
                $meta['precision']
            );
            \array_splice($args, 1, 0, $elapsed);
        }
        $logEntry['args'] = $args;
        $logEntry['meta'] = \array_diff_key($meta, \array_flip(array('precision','unit')));
        $this->appendLog($logEntry);
    }

    /**
     * Log a stack trace
     *
     * Essentially PHP's `debug_backtrace()`, but displayed as a table
     *
     * @param string $caption (optional) Specify caption for the trace table
     *
     * @return void
     */
    public function trace($caption = 'trace')
    {
        if (!$this->cfg['collect']) {
            return;
        }
        // "use" our function params so things (ie phpmd) don't complain
        array($caption);
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'columns' => array('file','line','function'),
                'detectFiles' => true,
            ),
            array(
                'caption' => 'trace',
            ),
            array(
                'caption',
            )
        );
        $backtrace = $this->errorHandler->backtrace();
        // toss "internal" frames
        for ($i = 1, $count = \count($backtrace) - 1; $i < $count; $i++) {
            $frame = $backtrace[$i];
            $function = isset($frame['function']) ? $frame['function'] : '';
            if (!\preg_match('/^' . \preg_quote(__CLASS__) . '(::|->)/', $function)) {
                break;
            }
        }
        $backtrace = \array_slice($backtrace, $i - 1);
        // keep the calling file & line, but toss ->trace or ::_trace
        unset($backtrace[0]['function']);
        $logEntry['args'] = array($backtrace);
        $this->appendLog($logEntry);
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
        $logEntry = new LogEntry(
            $this,
            __FUNCTION__,
            \func_get_args(),
            array(
                'detectFiles' => true,
            )
        );
        // file & line meta may already be set (ie coming via errorHandler)
        // file & line may also be defined as null
        $default = "\x00default\x00";
        if ($logEntry->getMeta('file', $default) === $default) {
            $callerInfo = $this->utilities->getCallerInfo();
            $logEntry->setMeta(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ));
        }
        $this->appendLog($logEntry);
    }

    /*
        "Non-Console" Methods
    */

    /**
     * Extend debug with a plugin
     *
     * @param AssetProviderInterface|SubscriberInterface $plugin object implementing SubscriberInterface and/or AssetProviderInterface
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function addPlugin($plugin)
    {
        if ($this->registeredPlugins->contains($plugin)) {
            return $this;
        }
        $isPlugin = false;
        if ($plugin instanceof AssetProviderInterface) {
            $isPlugin = true;
            $this->rootInstance->routeHtml->addAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $isPlugin = true;
            $this->eventManager->addSubscriberInterface($plugin);
            $subscriptions = $plugin->getSubscriptions();
            if (isset($subscriptions['debug.pluginInit'])) {
                /*
                    plugin we just added subscribes to debug.pluginInit
                    call subscriber directly
                */
                \call_user_func(
                    array($plugin, $subscriptions['debug.pluginInit']),
                    new Event($this),
                    'debug.pluginInit',
                    $this->eventManager
                );
            }
        }
        if (!$isPlugin) {
            throw new \InvalidArgumentException('addPlugin expects \\bdk\\Debug\\AssetProviderInterface and/or \\bdk\\PubSub\\SubscriberInterface');
        }
        $this->registeredPlugins->attach($plugin);
        return $this;
    }

    /**
     * Emit headers queued for output directly using `header()`
     *
     * @return void
     */
    public function emitHeaders()
    {
        if (\headers_sent($file, $line)) {
            \trigger_error('PHPDebugConsole: headers already sent: ' . $file . ', line ' . $line, E_USER_NOTICE);
        }
        foreach ($this->getHeaders() as $nameVal) {
            \header($nameVal[0] . ': ' . $nameVal[1]);
        }
    }

    /**
     * Retrieve a configuration value
     *
     * @param string $path    what to get
     * @param mixed  $default (optional) default value
     *
     * @return mixed value
     */
    public function getCfg($path = null, $default = null)
    {
        return $this->config->getValue($path, $default);
    }

    /**
     * Return a named subinstance... if channel does not exist, it will be created
     *
     * Channels can be used to categorize log data... for example, may have a framework channel, database channel, library-x channel, etc
     * Channels may have subchannels
     *
     * @param string $channelName channel name
     * @param array  $config      channel specific configuration
     *
     * @return static new or existing `Debug` instance
     */
    public function getChannel($channelName, $config = array())
    {
        if (\strpos($channelName, '.') !== false) {
            $this->error('getChannel(): channelName should not contain period (.)');
            return $this;
        }
        if (!isset($this->channels[$channelName])) {
            // get inherited config
            $cfg = $this->getCfg();
            // remove config values that channel should not inherit
            $cfg = \array_diff_key($cfg, \array_flip(array(
                'errorEmailer',
                'errorHandler',
            )));
            unset($cfg['debug']['onBootstrap']);
            // set channel values
            $cfg['debug']['channelName'] = $this->parentInstance
                ? $this->cfg['channelName'] . '.' . $channelName
                : $channelName;
            $cfg['debug']['parent'] = $this;
            // instantiate channel
            $this->channels[$channelName] = new static($cfg);
        }
        if ($config) {
            $this->channels[$channelName]->setCfg($config);
        }
        return $this->channels[$channelName];
    }

    /**
     * Return array of channels
     *
     * If $allDescendants == true :  key = "fully qualified" channel name
     *
     * Does not return self
     *
     * @param boolean $allDescendants (false) include all descendants?
     *
     * @return static[]
     */
    public function getChannels($allDescendants = false)
    {
        if ($allDescendants) {
            $channels = array();
            foreach ($this->channels as $channel) {
                $channels = \array_merge(
                    $channels,
                    array($channel->getCfg('channelName') => $channel),
                    $channel->getChannels(true)
                );
            }
            return $channels;
        }
        return $this->channels;
    }

    /**
     * Advanced usage
     *
     * @param string $path path
     *
     * @return mixed
     */
    public function getData($path = null)
    {
        if (!$path) {
            $data = $this->utilities->arrayCopy($this->data, false);
            $data['logSummary'] = $this->utilities->arrayCopy($data['logSummary'], false);
            $data['groupStacks'] = $this->utilities->arrayCopy($data['groupStacks'], false);
            return $data;
        }
        $data = $this->utilities->arrayPathGet($this->data, $path);
        return \is_array($data) && \in_array($path, array('logSummary','groupStacks'))
            ? $this->utilities->arrayCopy($data, false)
            : $data;
    }

    /**
     * Get and clear headers that need to be output
     *
     * @return array headerName=>value array
     */
    public function getHeaders()
    {
        $headers = $this->data['headers'];
        $this->data['headers'] = array();
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
     * Return debug log output
     *
     * Publishes debug.output event and returns event's 'return' value
     *
     * @param array $cfg Override any config values
     *
     * @return string|null
     */
    public function output($cfg = array())
    {
        $cfgRestore = $this->config->setValues($cfg);
        if (!$this->cfg['output']) {
            $this->config->setValues($cfgRestore);
            return null;
        }
        $route = $this->getCfg('route');
        if (\is_string($route)) {
            $this->setCfg('route', $route);
        }
        /*
            Publish debug.output on all descendant channels and then ourself
        */
        $channels = $this->getChannels(true);
        $channels[] = $this;
        foreach ($channels as $channel) {
            $event = $channel->eventManager->publish(
                'debug.output',
                $channel,
                array(
                    'headers' => array(),
                    'isTarget' => $channel === $this,
                    'return' => '',
                )
            );
        }
        if (!$this->parentInstance) {
            $this->data['outputSent'] = true;
        }
        $this->config->setValues($cfgRestore);
        return $event['return'];
    }

    /**
     * Remove plugin
     *
     * @param SubscriberInterface $plugin object implementing SubscriberInterface
     *
     * @return $this
     */
    public function removePlugin(SubscriberInterface $plugin)
    {
        $this->registeredPlugins->detach($plugin);
        if ($plugin instanceof AssetProviderInterface) {
            $this->rootInstance->routeHtml->removeAssetProvider($plugin);
        }
        if ($plugin instanceof SubscriberInterface) {
            $this->eventManager->RemoveSubscriberInterface($plugin);
        }
        return $this;
    }

    /**
     * Set one or more config values
     *
     * `setCfg('key', 'value')`
     * `setCfg('level1.level2', 'value')`
     * `setCfg(array('k1'=>'v1', 'k2'=>'v2'))`
     *
     * @param string|array $path   path
     * @param mixed        $newVal value
     *
     * @return mixed previous value(s)
     */
    public function setCfg($path, $newVal = null)
    {
        return \is_array($path)
            ? $this->config->setValues($path)
            : $this->config->setValue($path, $newVal);
    }

    /**
     * Advanced usage
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path  path
     * @param mixed        $value value
     *
     * @return void
     */
    public function setData($path, $value = null)
    {
        if (\is_string($path)) {
            $path = \preg_split('#[\./]#', $path);
            $ref = &$this->data;
            foreach ($path as $k) {
                $ref = &$ref[$k];
            }
            $ref = $value;
        } else {
            $this->data = \array_merge($this->data, $path);
        }
        if (!$this->data['log']) {
            $this->data['groupStacks']['main'] = array();
        }
        if (!$this->data['logSummary']) {
            $this->data['groupStacks'] = \array_intersect_key(
                $this->data['groupStacks'],
                array('main' => true)
            );
            $this->data['groupPriorityStack'] = array();
        }
        $this->setLogDest();
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
            $caller = $this->utilities->getCallerInfo(1);
            $caller = array(
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        }
        if ($caller) {
            // groupEnd will check depth and potentially clear errorCaller
            $caller['groupDepth'] = $this->getGroupDepth();
        }
        $this->errorHandler->setErrorCaller($caller);
    }

    /**
     * Appends debug output (if applicable) and adds headers (if applicable)
     *
     * You should call this at the end of the request/response cycle in your PSR-7 project,
     * e.g. immediately before emitting the Response.
     *
     * @param ResponseInterface $response PSR-7 Response
     *
     * @return ResponseInterface
     */
    public function writeToResponse(ResponseInterface $response)
    {
        $this->response = $response;
        $this->cfg['outputHeaders'] = false;
        $output = $this->output();
        $stream = $response->getBody();
        $stream->seek(0, SEEK_END);
        $stream->write($output);
        $stream->rewind();
        $headers = $this->getHeaders();
        foreach ($headers as $nameVal) {
            $response = $response->withHeader($nameVal[0], $nameVal[1]);
        }
        return $response;
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
        if (!\strpos($className, '\\')) {
            // className is not namespaced
            return;
        }
        $psr4Map = array(
            'bdk\\Debug\\' => __DIR__,
            'bdk\\PubSub\\' => __DIR__ . '/../PubSub',
            'bdk\\ErrorHandler\\' => __DIR__ . '/../ErrorHandler',
        );
        foreach ($psr4Map as $namespace => $dir) {
            if (\strpos($className, $namespace) === 0) {
                $rel = \substr($className, \strlen($namespace));
                $rel = \str_replace('\\', '/', $rel);
                require $dir . '/' . $rel . '.php';
                return;
            }
        }
        $classMap = array(
            'bdk\\ErrorHandler' => __DIR__ . '/../ErrorHandler/ErrorHandler.php',
        );
        if (isset($classMap[$className])) {
            require $classMap[$className];
        }
    }

    /**
     * Store the arguments
     * if collect is false -> does nothing
     * otherwise:
     *   + abstracts values
     *   + publishes debug.log event
     *   + appends log (if event propagation not stopped)
     *
     * @param LogEntry $logEntry     log entry instance
     * @param boolean  $forcePublish (false) publish event event if collect is false
     *
     * @return boolean whether or not entry got appended
     */
    protected function appendLog(LogEntry $logEntry, $forcePublish = false)
    {
        if (!$this->cfg['collect'] && !$forcePublish) {
            return false;
        }
        $cfgRestore = array();
        if (isset($logEntry['meta']['cfg'])) {
            $cfgRestore = $this->config->setValues($logEntry['meta']['cfg']);
            $logEntry->setMeta('cfg', null);
        }
        foreach ($logEntry['args'] as $i => $v) {
            if ($this->abstracter->needsAbstraction($v)) {
                $logEntry['args'][$i] = $this->abstracter->getAbstraction($v, $logEntry['method']);
            }
        }
        $this->internal->publishBubbleEvent('debug.log', $logEntry);
        if ($cfgRestore) {
            $this->config->setValues($cfgRestore);
        }
        if ($logEntry['appendLog']) {
            $this->rootInstance->logRef[] = $logEntry;
            return true;
        }
        return false;
    }

    /**
     * Append group or groupCollapsed to log
     *
     * @param string $method 'group' or 'groupCollapsed'
     * @param array  $args   arguments passed to group or groupCollapsed
     *
     * @return void
     */
    private function doGroup($method, $args)
    {
        $logEntry = new LogEntry(
            $this,
            $method,
            $args
        );
        $this->rootInstance->groupStackRef[] = array(
            'channel' => $this,
            'collect' => $this->cfg['collect'],
        );
        if (!$this->cfg['collect']) {
            return;
        }
        if (!$logEntry['args']) {
            // give a default label
            $args = array();
            $caller = $this->utilities->getCallerInfo(0, Utilities::INCL_ARGS);
            if (isset($caller['function'])) {
                $args[] = isset($caller['class'])
                    ? $caller['class'] . $caller['type'] . $caller['function']
                    : $caller['function'];
                $args = \array_merge($args, $caller['args']);
                $logEntry->setMeta('isFuncName', true);
            } else {
                $args[] = 'group';
            }
            $logEntry['args'] = $args;
        }
        $appended = $this->appendLog($logEntry);
        if ($appended) {
            $this->doGroupStringify($logEntry);
        }
    }

    /**
     * Use string representation for group args if available
     *
     * @param LogEntry $logEntry Log entry
     *
     * @return void
     */
    private function doGroupStringify(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        foreach ($args as $k => $v) {
            if (!$this->abstracter->isAbstraction($v, 'object')) {
                continue;
            }
            if ($v['stringified']) {
                $v = $v['stringified'];
            } elseif (isset($v['methods']['__toString']['returnValue'])) {
                $v = $v['methods']['__toString']['returnValue'];
            }
            $args[$k] = $v;
        }
        $logEntry['args'] = $args;
    }

    /**
     * Log timeEnd() and timeGet()
     *
     * @param float    $elapsed  elapsed time in seconds
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return mixed
     */
    protected function doTime($elapsed, LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        if ($meta['silent']) {
            return $elapsed;
        }
        $label = isset($logEntry['args'][0])
            ? $logEntry['args'][0]
            : 'time';
        $str = \strtr($meta['template'], array(
            '%label' => $label,
            '%time' => $this->utilities->formatDuration($elapsed, $meta['unit'], $meta['precision']),
        ));
        $this->appendLog(new LogEntry(
            $this,
            'time',
            array($str),
            \array_diff_key($meta, \array_flip(array('precision','silent','template','unit')))
        ));
        return $elapsed;
    }

    /**
     * Calculate total group depth
     *
     * @return integer
     */
    protected function getGroupDepth()
    {
        $depth = 0;
        foreach ($this->data['groupStacks'] as $stack) {
            $depth += \count($stack);
        }
        $depth += \count($this->data['groupPriorityStack']);
        return $depth;
    }

    /**
     * Set "container" factories
     *
     * @return array
     */
    private function getDefaultFactories()
    {
        return array(
            'methodProfile' => function () {
                return new Debug\MethodProfile();
            },
        );
    }

    /**
     * Set "container" services
     *
     * @return array
     */
    private function getDefaultServices()
    {
        return array(
            'abstracter' => function (Debug $debug) {
                return new Abstracter($debug, $debug->config->getValues('abstracter'));
            },
            'config' => function (Debug $debug) {
                return new Debug\Config($debug, $debug->cfg);    // cfg is passed by reference
            },
            'errorEmailer' => function (Debug $debug) {
                return new ErrorEmailer($debug->config->getValues('errorEmailer'));
            },
            'errorHandler' => function (Debug $debug) {
                if (ErrorHandler::getInstance()) {
                    return ErrorHandler::getInstance();
                } else {
                    $errorHandler = new ErrorHandler($debug->eventManager);
                    /*
                        log E_USER_ERROR to system_log without halting script
                    */
                    $errorHandler->setCfg('onEUserError', 'log');
                    return $errorHandler;
                }
            },
            'eventManager' => function () {
                return new EventManager();
            },
            'internal' => function (Debug $debug) {
                return new Debug\Internal($debug);
            },
            'logger' => function (Debug $debug) {
                return new Debug\Collector\Logger($debug);
            },
            'methodClear' => function (Debug $debug) {
                return new Debug\MethodClear($debug, $debug->data);
            },
            'methodTable' => function () {
                return new Debug\MethodTable();
            },
            'middleware' => function (Debug $debug) {
                return new Debug\Middleware($debug);
            },
            'utf8' => function () {
                return new Debug\Utf8();
            },
            'utilities' => function () {
                return new Debug\Utilities();
            },
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
            $defaultArgs = self::$methodDefaultArgs[$methodName];
        } elseif (\method_exists(self::$instance, $methodName)) {
            $reflectionMethod = new ReflectionMethod(self::$instance, $methodName);
            $params = $reflectionMethod->getParameters();
            foreach ($params as $reflectionParameter) {
                $defaultArgs[] = $reflectionParameter->isOptional()
                    ? $reflectionParameter->getDefaultValue()
                    : null;
            }
            self::$methodDefaultArgs[$methodName] = $defaultArgs;
        }
        return $defaultArgs;
    }

    /**
     * Set where appendLog appends to
     *
     * @param string $where ('auto'), 'alerts', log', or 'summary'
     *
     * @return void
     */
    private function setLogDest($where = 'auto')
    {
        if ($where == 'auto') {
            $where = $this->data['groupPriorityStack']
                ? 'summary'
                : 'log';
        }
        if ($where == 'log') {
            $this->rootInstance->logRef = &$this->rootInstance->data['log'];
            $this->rootInstance->groupStackRef = &$this->rootInstance->data['groupStacks']['main'];
        } elseif ($where == 'alerts') {
            $this->rootInstance->logRef = &$this->rootInstance->data['alerts'];
        } else {
            $priority = \end($this->data['groupPriorityStack']);
            if (!isset($this->data['logSummary'][$priority])) {
                $this->data['logSummary'][$priority] = array();
                $this->data['groupStacks'][$priority] = array();
            }
            $this->rootInstance->logRef = &$this->rootInstance->data['logSummary'][$priority];
            $this->rootInstance->groupStackRef = &$this->rootInstance->data['groupStacks'][$priority];
        }
    }
}
