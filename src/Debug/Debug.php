<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk;

use bdk\ErrorHandler;
use bdk\ErrorHandler\ErrorEmailer;
use bdk\PubSub\SubscriberInterface;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use ReflectionMethod;

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
 * @property Output        $output        lazy-loaded Output instance
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
    protected $rootInstance;
    protected static $methodDefaultArgs = array();

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
    const VERSION = '2.3';

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
            'file'      => null,            // if a filepath, will receive log data
            'key'       => null,
            'output'    => false,           // output the log?
            'channelName' => 'general',
            'enableProfiling' => false,
            // which error types appear as "error" in debug console... all other errors are "warn"
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR,
            'emailFrom' => null,    // null = use php's default (php.ini: sendmail_from)
            'emailFunc' => 'mail',  // callable
            'emailLog'  => false,   // Whether to email a debug log.  (requires 'collect' to also be true)
                                    //
                                    //   false:               email will not be sent
                                    //   true or 'onError':   email will be sent (if log is not output)
                                    //   'always':            email sent regardless of whether error occured or log output
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'logEnvInfo' => array(
                'cookies' => true,
                'headers' => true,
                'phpInfo' => true,
                'post' => true,
                'serverVals' => true,
            ),
            'logRuntime' => true,
            'logServerKeys' => array('REMOTE_ADDR','REQUEST_TIME','REQUEST_URI','SERVER_ADDR','SERVER_NAME'),
            'onLog' => null,    // callable
            'factories' => $this->getDefaultFactories(),
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
            'requestId'         => '', // set below
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
        $this->__get('config')->setCfg($cfg);   // since is defined (albeit null), we need to call __get to initialize
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
            $this->data['requestId'] = $this->utilities->requestId();
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
        return $this->appendLog(
            $methodName,
            $args,
            array('isCustomMethod' => true)
        );
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
                    ie _setCfg(array('outputAs'=>'html')) via command line
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
     * @return property value
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
        $getter = 'get'.\ucfirst($property);
        if (\method_exists($this, $getter)) {
            return $this->{$getter}();
        }
        return null;
    }

    /*
        Debugging Methods
    */

    /**
     * Add an alert to top of log
     *
     * @param string  $message     message
     * @param string  $class       (danger), info, success, warning
     * @param boolean $dismissible (false)
     *
     * @return void
     */
    public function alert($message, $class = 'danger', $dismissible = false)
    {
        // "use" our function params so things don't complain
        array($class, $dismissible);
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array(
                'message' => null,
                'class' => 'danger',
                'dismissible' => false,
            ),
            array('class','dismissible')
        );
        \extract($args);
        $this->setLogDest('alerts');
        $this->appendLog(
            'alert',
            array($message),
            $meta
        );
        $this->setLogDest('auto');
    }

    /**
     * Log a message and stack trace to console if first argument is false.
     *
     * Only appends log when assertion fails
     *
     * Supports styling/substitutions
     *
     * @param boolean $assertion argument checked for truthyness
     * @param mixed   $msg,...   (optional) variable num of addititional values to output
     *
     * @return void
     */
    public function assert($assertion, $msg = null)
    {
        // "use" our function params so things don't complain
        array($msg);
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals($args);
        $assertion = \array_shift($args);
        if (!$assertion) {
            if (!$args) {
                // add default message
                $callerInfo = $this->utilities->getCallerInfo();
                $args = array(
                    'Assertion failed:',
                    $callerInfo['file'].' (line '.$callerInfo['line'].')',
                );
            }
            $this->appendLog('assert', $args, $meta);
        }
    }

    /**
     * Clear the log
     *
     * This method executes even if `collect` is false
     *
     * @param integer $flags (self::CLEAR_LOG) specify what to clear (bitmask)
     *                         CLEAR_ALERTS
     *                         CLEAR_LOG (excluding warn & error)
     *                         CLEAR_LOG_ERRORS
     *                         CLEAR_SUMMARY (excluding warn & error)
     *                         CLEAR_SUMMARY_ERRORS
     *                         CLEAR_ALL
     *                         CLEAR_SILENT (don't add log entry)
     *
     * @return void
     */
    public function clear($flags = self::CLEAR_LOG)
    {
        // "use" our function params so things don't complain
        array($flags);
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array('flags' => self::CLEAR_LOG),
            array('flags' => 'bitmask')
        );
        $event = $this->methodClear->onLog(new Event($this, array(
            'method' => __FUNCTION__,
            'args' => array(),
            'meta' => $meta,
        )));
        // even if cleared from within summary, let's log this in primary log
        $this->setLogDest('log');
        $collect = $this->cfg['collect'];
        $this->cfg['collect'] = true;
        if ($event['appendLog']) {
            $this->appendLog(
                $event['method'],
                $event['args'],
                $event['meta']
            );
        } elseif ($event['publish']) {
            /*
                Publish the debug.log event (regardless of cfg.collect)
                don't actually log
            */
            $this->internal->publishBubbleEvent('debug.log', $event);
        }
        $this->cfg['collect'] = $collect;
        $this->setLogDest('auto');
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * If `label` is omitted, logs the number of times `count()` has been called at this particular line.
     *
     * Count is maintained even when `collect` is false
     *
     * @param mixed   $label label
     * @param integer $flags (optional)
     *                          A bitmask of
     *                          \bdk\Debug::COUNT_NO_INC : don't increment the counter
     *                                                       (ie, just get the current count)
     *                          \bdk\Debug::COUNT_NO_OUT : don't output/log
     *
     * @return integer The count
     */
    public function count($label = null, $flags = 0)
    {
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName'])
        );
        // label may be ommitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        if (\count($args) == 1 && \is_int($args[0])) {
            $label = null;
            $flags = $args[0];
        } else {
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
            $meta = \array_merge(array(
                'file' => $callerInfo['file'],
                'line' => $callerInfo['line'],
            ), $meta);
            $label = 'count';
            $dataLabel = $meta['file'].': '.$meta['line'];
        }
        if (!isset($this->data['counts'][$dataLabel])) {
            $this->data['counts'][$dataLabel] = 0;
        }
        if (!($flags & self::COUNT_NO_INC)) {
            $this->data['counts'][$dataLabel]++;
        }
        $count = $this->data['counts'][$dataLabel];
        if (!($flags & self::COUNT_NO_OUT)) {
            $this->appendLog(
                'count',
                array(
                    (string) $label,
                    $count,
                ),
                $meta
            );
        }
        return $count;
    }

    /**
     * Resets the counter.
     *
     * @param mixed   $label label
     * @param integer $flags (optional)
     *                          currently only one option
     *                          \bdk\Debug::COUNT_NO_OUT : don't output/log
     *
     * @return void
     */
    public function countReset($label = 'default', $flags = null)
    {
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName'])
        );
        // label may be ommitted and only flags passed as a single argument
        //   (excluding potential meta argument)
        if (\count($args) == 1 && \is_int($args[0])) {
            $label = 'default';
            $flags = $args[0];
        } else {
            $args = \array_combine(
                array('label', 'flags'),
                \array_replace(array('default', 0), $args)
            );
            \extract($args);
        }
        if (isset($this->data['counts'][$label])) {
            $this->data['counts'][$label] = 0;
            $args = array(
                (string) $label,
                0,
            );
        } else {
            $args = array('Counter \''.$label.'\' doesn\'t exist.');
        }
        if (!($flags & self::COUNT_NO_OUT)) {
            $this->appendLog(
                'countReset',
                $args,
                $meta
            );
        }
    }

    /**
     * Log an error message.
     *
     * Supports styling/substitutions
     *
     * @param mixed $label,... error message / values
     *
     * @return void
     */
    public function error()
    {
        $this->appendLog(
            'error',
            \func_get_args(),
            $this->internal->getErrorCaller()
        );
    }

    /**
     * Create a new inline group
     *
     * @param mixed $label,... label / values
     *
     * @return void
     */
    public function group()
    {
        $this->doGroup('group', \func_get_args());
    }

    /**
     * Create a new inline group
     *
     * @param mixed $label,... label / values
     *
     * @return void
     */
    public function groupCollapsed()
    {
        $this->doGroup('groupCollapsed', \func_get_args());
    }

    /**
     * Close current group
     *
     * @param mixed $value Value
     *
     * @return void
     */
    public function groupEnd($value = \bdk\Debug\Abstracter::UNDEFINED)
    {
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array('value' => \bdk\Debug\Abstracter::UNDEFINED)
        );
        \extract($args);
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
            $meta['closesSummary'] = true;
            $event = new Event($this, array(
                'method' => __FUNCTION__,
                'args' => array(),
                'meta' => $meta,
            ));
            $this->internal->publishBubbleEvent('debug.log', $event);
        } elseif ($haveOpenGroup) {
            if ($value !== \bdk\Debug\Abstracter::UNDEFINED) {
                $this->appendLog(
                    'groupEndValue',
                    array('return', $value)
                );
            }
            $this->appendLog('groupEnd', array(), $meta);
        }
        $errorCaller = $this->errorHandler->get('errorCaller');
        if ($errorCaller && isset($errorCaller['groupDepth']) && $this->getGroupDepth() < $errorCaller['groupDepth']) {
            $this->errorHandler->setErrorCaller(false);
        }
    }

    /**
     * Initiate the beginning of "summary" log entries
     *
     * Debug methods called while a groupSummary is open will appear at the top of the log
     * call groupEnd() to close summary
     *
     * groupSummary can be used multiple times
     * All groupSummary groups will appear together in a single group
     *
     * @param integer $priority (0) The higher the priority, the ealier it will appear.
     *
     * @return void
     */
    public function groupSummary($priority = 0)
    {
        // "use" our function params so things don't complain
        array($priority);
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array('priority' => 0),
            array('priority')
        );
        $this->data['groupPriorityStack'][] = $meta['priority'];
        $this->setLogDest('summary');
        /*
            Publish the debug.log event (regardless of cfg.collect)
            don't actually log
        */
        $event = new Event($this, array(
            'method' => __FUNCTION__,
            'args' => array(),
            'meta' => $meta,
        ));
        $this->internal->publishBubbleEvent('debug.log', $event);
    }

    /**
     * Set ancestor groups to uncollapsed
     *
     * This will only occur if `cfg['collect']` is currently true
     *
     * @return void
     */
    public function groupUncollapse()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName'])
        );
        $curDepth = 0;
        foreach ($this->rootInstance->groupStackRef as $group) {
            $curDepth += (int) $group['collect'];
        }
        $entryKeys = \array_keys($this->internal->getCurrentGroups($this->rootInstance->logRef, $curDepth));
        foreach ($entryKeys as $key) {
            $this->rootInstance->logRef[$key][0] = 'group';
        }
        /*
            Publish the debug.log event (regardless of cfg.collect)
            don't actually log
        */
        $event = new Event($this, array(
            'method' => __FUNCTION__,
            'args' => array(),
            'meta' => $meta,
        ));
        $this->internal->publishBubbleEvent('debug.log', $event);
    }

    /**
     * Log some informative information
     *
     * Supports styling/substitutions
     *
     * @return void
     */
    public function info()
    {
        $this->appendLog('info', \func_get_args());
    }

    /**
     * Log general information
     *
     * Supports styling/substitutions
     *
     * @return void
     */
    public function log()
    {
        $this->appendLog('log', \func_get_args());
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
            $this->appendLog(
                __FUNCTION__,
                array('Profile: Unable to start - enableProfiling opt not set.  ' . $callerInfo['file'] .' on line ' . $callerInfo['line'] . '.')
            );
            return;
        }
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array('name' => null),
            array('name')   // move name to meta
        );
        if ($meta['name'] === null) {
            $meta['name'] = 'Profile '.$this->data['profileAutoInc'];
            $this->data['profileAutoInc']++;
        }
        $name = $meta['name'];
        $message = '';
        if (isset($this->data['profileInstances'][$name])) {
            $instance = $this->data['profileInstances'][$name];
            $instance->end();
            $instance->start();
            // move it to end (last started)
            unset($this->data['profileInstances'][$name]);
            $this->data['profileInstances'][$name] = $instance;
            $message = 'Profile \''.$name.'\' restarted';
        } else {
            $this->data['profileInstances'][$name] = $this->methodProfile; // factory
            $message = 'Profile \''.$name.'\' started';
        }
        $this->appendLog(
            __FUNCTION__,
            array(
                $message,
            ),
            $meta
        );
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
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array('name' => null),
            array('name')
        );
        if ($meta['name'] === null) {
            \end($this->data['profileInstances']);
            $meta['name'] = \key($this->data['profileInstances']);
        }
        $name = $meta['name'];
        if (isset($this->data['profileInstances'][$name])) {
            $instance = $this->data['profileInstances'][$name];
            $data = $instance->end();
            $caption = 'Profile \''.$name.'\' Results';
            if ($data) {
                $args = array( $data );
                $meta['sortable'] = true;
                $meta['caption'] = $caption;
                $meta['totalCols'] = array('ownTime');
                $meta['columns'] = array();
            } else {
                $args = array($caption, 'no data');
            }
            unset($this->data['profileInstances'][$name]);
        } else {
            $args = array( $name !== null
                ? 'profileEnd: No such Profile: '.$name
                : 'profileEnd: Not currently profiling'
            );
        }
        $this->appendLog(
            __FUNCTION__,
            $args,
            $meta
        );
    }

    /**
     * Output array as a table
     *
     * Accepts array of arrays or array of objects
     *
     * Arguments:
     *   1st encountered array (or traversable) is the data
     *   2nd encountered array (optional) specifies columns to output
     *   1st encountered string is a label/caption
     *
     * @return void
     */
    public function table()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName'])
        );
        $event = $this->methodTable->onLog(new Event($this, array(
            'method' => __FUNCTION__,
            'args' => $args,
            'meta' => $meta,
        )));
        $this->appendLog(
            $event['method'],
            $event['args'],
            $event['meta']
        );
    }

    /**
     * Start a timer identified by label
     *
     * Label passed
     *    if doesn't exist: starts timer
     *    if does exist: unpauses (does not reset)
     * Label not passed
     *    timer will be added to a no-label stack
     *
     * Does not append log.  Use timeEnd or timeGet to get time
     *
     * @param string $label unique label
     *
     * @return void
     */
    public function time($label = null)
    {
        $args = \func_get_args();
        $this->internal->getMetaVals(
            $args,
            array(),
            array('label' => null)
        );
        \extract($args);
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
     * Behaves like a stopwatch.. returns running time
     *    If label is passed, timer is "paused"
     *    If label is not passed, timer is removed from timer stack
     *
     * @param string         $label            unique label
     * @param string|boolean $returnOrTemplate string: "%label: %time"
     *                                         boolean:  If true, only return time, rather than log it
     * @param integer        $precision        rounding precision (pass null for no rounding)
     *
     * @return float|string (numeric)
     */
    public function timeEnd($label = null, $returnOrTemplate = false, $precision = 4)
    {
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array(
                'label' => null,
                'returnOrTemplate' => false,
                'precision' => 4,
            )
        );
        \extract($args);
        if (\is_bool($label) || \strpos($label, '%time') !== false) {
            if (\is_numeric($returnOrTemplate)) {
                $precision = $returnOrTemplate;
            }
            $returnOrTemplate = $label;
            $label = null;
        }
        $ret = $this->timeGet($label, true, null); // get non-rounded running time
        if (isset($label)) {
            if (isset($this->data['timers']['labels'][$label])) {
                $this->data['timers']['labels'][$label] = array(
                    $ret,  // store the new "running" time
                    null,  // "pause" the timer
                );
            }
        } else {
            $label = 'time';
            \array_pop($this->data['timers']['stack']);
        }
        if (\is_int($precision)) {
            // use number_format rather than round(), which may still run decimals-a-plenty
            $ret = \number_format($ret, $precision, '.', '');
        }
        $this->doTime($ret, $returnOrTemplate, $label, $meta);
        return $ret;
    }

    /**
     * Log/get the running time without stopping/pausing the timer
     *
     * This method does not have a web console API equivalent
     *
     * @param string         $label            (optional) unique label
     * @param string|boolean $returnOrTemplate string: "%label: %time"
     *                                         boolean:  If true, only return time, rather than log it
     * @param integer        $precision        rounding precision (pass null for no rounding)
     *
     * @return float|string|false returns false if specified label does not exist
     */
    public function timeGet($label = null, $returnOrTemplate = false, $precision = 4)
    {
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName']),
            array(
                'label' => null,
                'returnOrTemplate' => false,
                'precision' => 4,
            )
        );
        \extract($args);
        if (\is_bool($label) || \strpos($label, '%time') !== false) {
            if (\is_numeric($returnOrTemplate)) {
                $precision = $returnOrTemplate;
            }
            $returnOrTemplate = $label;
            $label = null;
        }
        $microT = 0;
        $ellapsed = 0;
        if (!isset($label)) {
            $label = 'time';
            if (!$this->data['timers']['stack']) {
                list($ellapsed, $microT) = $this->data['timers']['labels']['debugInit'];
            } else {
                $microT = \end($this->data['timers']['stack']);
            }
        } elseif (isset($this->data['timers']['labels'][$label])) {
            list($ellapsed, $microT) = $this->data['timers']['labels'][$label];
        } else {
            if ($returnOrTemplate !== true) {
                $this->appendLog(
                    'time',
                    array('Timer \''.$label.'\' does not exist'),
                    $meta
                );
            }
            return false;
        }
        if ($microT) {
            $ellapsed += \microtime(true) - $microT;
        }
        if (\is_int($precision)) {
            // use number_format rather than round(), which may still run decimals-a-plenty
            $ellapsed = \number_format($ellapsed, $precision, '.', '');
        }
        $this->doTime($ellapsed, $returnOrTemplate, $label, $meta);
        return $ellapsed;
    }

    /**
     * Log the running time without stopping/pausing the timer
     * also logs additional arguments
     *
     * Added to web console api in Firefox 62
     * Added to PHPDebugConsole in v2.3
     *
     * @param string $label   (optional) unique label
     * @param mixed  $arg,... (optional) additional values to be logged with time
     *
     * @return void
     */
    public function timeLog($label = null, $args = null)
    {
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName'])
        );
        $microT = 0;
        $ellapsed = 0;
        if (\count($args) === 0) {
            $args[0] = 'time';
            if (!$this->data['timers']['stack']) {
                list($ellapsed, $microT) = $this->data['timers']['labels']['debugInit'];
            } else {
                $microT = \end($this->data['timers']['stack']);
            }
        } elseif (isset($this->data['timers']['labels'][$label])) {
            list($ellapsed, $microT) = $this->data['timers']['labels'][$label];
        } else {
            $args = array('Timer \''.$label.'\' does not exist');
        }
        if ($microT) {
            $args[0] .= ': ';
            $ellapsed += \microtime(true) - $microT;
            $ellapsed = \number_format($ellapsed, 4, '.', '');
            \array_splice($args, 1, 0, $ellapsed.' sec');
        }
        $this->appendLog(
            'timeLog',
            $args,
            $meta
        );
    }

    /**
     * Log a stack trace
     *
     * @return void
     */
    public function trace()
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $args = \func_get_args();
        $meta = $this->internal->getMetaVals(
            $args,
            array(
                'caption' => 'trace',
                'channel' => $this->cfg['channelName'],
                'columns' => array('file','line','function'),
            )
        );
        $backtrace = $this->errorHandler->backtrace();
        // toss "internal" frames
        for ($i = 1, $count = \count($backtrace)-1; $i < $count; $i++) {
            $frame = $backtrace[$i];
            $function = isset($frame['function']) ? $frame['function'] : '';
            if (!\preg_match('/^'.\preg_quote(__CLASS__).'(::|->)/', $function)) {
                break;
            }
        }
        $backtrace = \array_slice($backtrace, $i-1);
        // keep the calling file & line, but toss ->trace or ::_trace
        unset($backtrace[0]['function']);
        $this->appendLog('trace', array($backtrace), $meta);
    }

    /**
     * Log a warning
     *
     * Supports styling/substitutions
     *
     * @return void
     */
    public function warn()
    {
        $this->appendLog(
            'warn',
            \func_get_args(),
            $this->internal->getErrorCaller()
        );
    }

    /*
        "Non-Console" Methods
    */

    /**
     * Extend debug with a plugin
     *
     * @param SubscriberInterface $plugin object implementing SubscriberInterface
     *
     * @return void
     */
    public function addPlugin(SubscriberInterface $plugin)
    {
        $this->eventManager->addSubscriberInterface($plugin);
    }

    /**
     * Retrieve a configuration value
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function getCfg($path = null)
    {
        return $this->config->getCfg($path);
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
                'output',
            )));
            unset($cfg['debug']['onBootstrap']);
            // set channel values
            $cfg['debug']['channelName'] = $this->parentInstance
                ? $this->cfg['channelName'].'.'.$channelName
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
        $data = $this->utilities->arrayPathGet($this->data, $path);
        /*
            some array nodes may be references
            this is only a concern when calling getData externally
            serialize/unserialize is expensive.. only do so when requesting the below
        */
        return \in_array($path, array('logSummary','groupStacks'))
            ? \unserialize(\serialize($data))
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
     * @param array $options Override any output options
     *
     * @return string|null
     */
    public function output($options = array())
    {
        $cfgRestore = $this->config->setCfg($options);
        if (!$this->cfg['output']) {
            $this->config->setCfg($cfgRestore);
            return null;
        }
        $outputAs = $this->output->getCfg('outputAs');
        if (\is_string($outputAs)) {
            $this->output->setCfg('outputAs', $outputAs);
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
        $this->config->setCfg($cfgRestore);
        return $event['return'];
    }

    /**
     * Remove plugin
     *
     * @param SubscriberInterface $plugin object implementing SubscriberInterface
     *
     * @return void
     */
    public function removePlugin(SubscriberInterface $plugin)
    {
        $this->eventManager->RemoveSubscriberInterface($plugin);
    }

    /**
     * Set one or more config values
     *
     * If setting a value via method a or b, old value is returned
     *
     * Setting/updating 'key' will also set 'collect' and 'output'
     *
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $path   path
     * @param mixed        $newVal value
     *
     * @return mixed
     */
    public function setCfg($path, $newVal = null)
    {
        return $this->config->setCfg($path, $newVal);
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
            'bdk\\PubSub\\' => __DIR__.'/../PubSub',
            'bdk\\ErrorHandler\\' => __DIR__.'/../ErrorHandler',
        );
        foreach ($psr4Map as $namespace => $dir) {
            if (\strpos($className, $namespace) === 0) {
                $rel = \substr($className, \strlen($namespace));
                $rel = \str_replace('\\', '/', $rel);
                require $dir.'/'.$rel.'.php';
                return;
            }
        }
        $classMap = array(
            'bdk\\ErrorHandler' => __DIR__.'/../ErrorHandler/ErrorHandler.php',
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
     * @param string $method error, info, log, warn, etc
     * @param array  $args   arguments passed to method
     * @param array  $meta   meta data
     *
     * @return void
     */
    protected function appendLog($method, $args = array(), $meta = array())
    {
        if (!$this->cfg['collect']) {
            return;
        }
        $cfgRestore = array();
        $meta = \array_merge(
            array('channel' => $this->cfg['channelName']),
            $meta,
            $this->internal->getMetaVals($args)
        );
        if (isset($meta['cfg'])) {
            $cfgRestore = $this->config->setCfg($meta['cfg']);
            unset($meta['cfg']);
        }
        foreach ($args as $i => $v) {
            if ($this->abstracter->needsAbstraction($v)) {
                $args[$i] = $this->abstracter->getAbstraction($v, $method);
            }
        }
        $event = $this->eventManager->publish(
            'debug.log',
            $this,
            array(
                'method' => $method,
                'args' => $args,
                'meta' => $meta,
                'appendLog' => true,
                'return' => null,
            )
        );
        if ($cfgRestore) {
            $this->config->setCfg($cfgRestore);
        }
        if (!$event->getValue('appendLog')) {
            return $event->getValue('return');
        }
        if ($this->parentInstance) {
            return $this->parentInstance->appendLog(
                $event->getValue('method'),
                $event->getValue('args'),
                $event->getValue('meta')
            );
        }
        $this->rootInstance->logRef[] = array(
            $event->getValue('method'),
            $event->getValue('args'),
            \array_diff_assoc($event->getValue('meta'), array(
                'channel' => $this->cfg['channelName'],
            )),
        );
        return $event->getValue('return');
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
        $meta = $this->internal->getMetaVals(
            $args,
            array('channel' => $this->cfg['channelName'])
        );
        $this->rootInstance->groupStackRef[] = array(
            'channel' => $meta['channel'],
            'collect' => $this->cfg['collect'],
        );
        if (!$this->cfg['collect']) {
            return;
        }
        if (empty($args)) {
            // give a default label
            $caller = $this->utilities->getCallerInfo();
            if (isset($caller['class'])) {
                $args[] = $caller['class'].$caller['type'].$caller['function'];
                $meta['isMethodName'] = true;
            } elseif (isset($caller['function'])) {
                $args[] = $caller['function'];
            } else {
                $args[] = 'group';
            }
        }
        $this->appendLog($method, $args, $meta);
    }

    /**
     * Log timeEnd() and timeGet()
     *
     * @param float  $seconds          seconds
     * @param mixed  $returnOrTemplate false: log the time with default template (default)
     *                                  true: do not log
     *                                  string: log using passed template
     * @param string $label            label
     * @param array  $meta             meta values
     *
     * @return void
     */
    protected function doTime($seconds, $returnOrTemplate = false, $label = 'time', $meta = array())
    {
        if (\is_string($returnOrTemplate)) {
            $str = $returnOrTemplate;
            $str = \str_replace('%label', $label, $str);
            $str = \str_replace('%time', $seconds, $str);
        } elseif ($returnOrTemplate === true) {
            return;
        } else {
            $str = $label.': '.$seconds.' sec';
        }
        $this->appendLog('time', array($str), $meta);
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
                return new Debug\Abstracter($debug, $debug->config->getCfgLazy('abstracter'));
            },
            'config' => function (Debug $debug) {
                return new Debug\Config($debug, $debug->cfg);    // cfg is passed by reference
            },
            'errorEmailer' => function (Debug $debug) {
                return new ErrorEmailer($debug->config->getCfgLazy('errorEmailer'));
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
                return new Debug\Logger($debug);
            },
            'methodClear' => function (Debug $debug) {
                return new Debug\MethodClear($debug, $debug->data);
            },
            'methodTable' => function () {
                return new Debug\MethodTable();
            },
            'output' => function (Debug $debug) {
                $output = new Debug\Output($debug, $debug->config->getCfgLazy('output'));
                $debug->eventManager->addSubscriberInterface($output);
                return $output;
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
