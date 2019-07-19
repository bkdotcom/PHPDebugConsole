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

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\Route\RouteInterface;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Methods that are internal to the debug class
 *
 * a) Don't want to clutter the debug class
 * b) avoiding a base class as it would necessitate we first load the base or have
 *       an autoloader in place to bootstrap the debug class
 * c) a trait for code not meant to be "reusable" seems like an anti-pattern
 *       doesn't solve the bootstrap/autoload issue
 */
class Internal implements SubscriberInterface
{

    private $debug;
    private $inShutdown = false;
    private static $profilingEnabled = false;

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->debug->eventManager->addSubscriberInterface($this);
        if ($debug->parentInstance) {
            return;
        }
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorHighPri'), PHP_INT_MAX);
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorLowPri'), PHP_INT_MAX * -1);
        /*
            Initial setCfg has already occured... so we missed the initial debug.config event
            manually call onConfig here
        */
        $this->onConfig(new Event(
            $this->debug,
            $this->debug->getCfg()
        ));
    }

    /**
     * Send an email
     *
     * @param string $toAddr  to
     * @param string $subject subject
     * @param string $body    body
     *
     * @return void
     */
    public function email($toAddr, $subject, $body)
    {
        $addHeadersStr = '';
        $fromAddr = $this->debug->getCfg('emailFrom');
        if ($fromAddr) {
            $addHeadersStr .= 'From: '.$fromAddr;
        }
        \call_user_func($this->debug->getCfg('emailFunc'), $toAddr, $subject, $body, $addHeadersStr);
    }

    /**
     * Serializes and emails log
     *
     * @return void
     */
    public function emailLog()
    {
        /*
            List errors that occured
        */
        $errorStr = $this->buildErrorList();
        /*
            Build Subject
        */
        $subject = 'Debug Log';
        $subjectMore = '';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $subjectMore .= ' '.$_SERVER['HTTP_HOST'];
        }
        if ($errorStr) {
            $subjectMore .= ' '.($subjectMore ? '(Error)' : 'Error');
        }
        $subject = \rtrim($subject.':'.$subjectMore, ':');
        /*
            Build body
        */
        $body = (!isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['argv'])
            ? 'Command: '. \implode(' ', $_SERVER['argv'])
            : 'Request: '.$_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']
        )."\n\n";
        if ($errorStr) {
            $body .= 'Error(s):'."\n"
                .$errorStr."\n";
        }
        /*
            "attach" serialized log to body
        */
        $data = \array_intersect_key($this->debug->getData(), \array_flip(array(
            'alerts',
            'log',
            'logSummary',
            'requestId',
            'runtime',
        )));
        $data['rootChannel'] = $this->debug->getCfg('channelName');
        $data['channels'] = \array_map(function (Debug $channel) {
            return array(
                'channelIcon' => $channel->getCfg('channelIcon'),
                'channelShow' => $channel->getCfg('channelShow'),
            );
        }, $this->debug->getChannels(true));
        $body .= $this->debug->utilities->serializeLog($data);
        /*
            Now email
        */
        $this->email($this->debug->getCfg('emailTo'), $subject, $body);
        return;
    }

    /**
     * get error statistics from errorHandler
     * how many errors were captured in/out of console
     * breakdown per error category
     *
     * @return array
     */
    public function errorStats()
    {
        $errors = $this->debug->errorHandler->get('errors');
        $stats = array(
            'inConsole' => 0,
            'inConsoleCategories' => 0,
            'notInConsole' => 0,
            'counts' => array(),
        );
        foreach ($errors as $error) {
            if ($error['isSuppressed']) {
                continue;
            }
            $category = $error['category'];
            if (!isset($stats['counts'][$category])) {
                $stats['counts'][$category] = array(
                    'inConsole' => 0,
                    'notInConsole' => 0,
                );
            }
            $k = $error['inConsole'] ? 'inConsole' : 'notInConsole';
            $stats['counts'][$category][$k]++;
        }
        foreach ($stats['counts'] as $a) {
            $stats['inConsole'] += $a['inConsole'];
            $stats['notInConsole'] += $a['notInConsole'];
            if ($a['inConsole']) {
                $stats['inConsoleCategories']++;
            }
        }
        $order = array(
            'fatal',
            'error',
            'warning',
            'deprecated',
            'notice',
            'strict',
        );
        $stats['counts'] = \array_intersect_key(\array_merge(\array_flip($order), $stats['counts']), $stats['counts']);
        return $stats;
    }

    /**
     * Return the group & groupCollapsed ("ancestors")
     *
     * @param array   $logEntries log entries
     * @param integer $curDepth   current group depth
     *
     * @return LogEntry[] kwys are maintained
     */
    public static function getCurrentGroups(&$logEntries, $curDepth)
    {
        /*
            curDepth will fluctuate as we go back through log
            minDepth will decrease as we work our way down/up the groups
        */
        $minDepth = $curDepth;
        $entries = array();
        for ($i = \count($logEntries) - 1; $i >= 0; $i--) {
            if ($curDepth < 1) {
                break;
            }
            $method = $logEntries[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $curDepth--;
                if ($curDepth < $minDepth) {
                    $minDepth--;
                    $entries[$i] = $logEntries[$i];
                }
            } elseif ($method == 'groupEnd') {
                $curDepth++;
            }
        }
        return $entries;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        if ($this->debug->parentInstance) {
            // all channels should subscribe
            return array(
                'debug.output' => array('onOutput', 1),
                'debug.config' => array('onConfig', PHP_INT_MAX),
            );
        } else {
            /*
                OnShutDownHigh subscribes to 'debug.log' (onDebugLogShutdown)
                  so... if any log entry is added in php's shutdown phase, we'll have a
                  "php.shutdown" log entry
            */
            return array(
                'debug.bootstrap' => array('onBootstrap', PHP_INT_MAX * -1),
                'debug.config' => array('onConfig', PHP_INT_MAX),
                'debug.dumpCustom' => 'onDumpCustom',
                'debug.output' => array('onOutput', 1),
                'errorHandler.error' => 'onError',
                'php.shutdown' => array(
                    array('onShutdownHigh', PHP_INT_MAX),
                    array('onShutdownLow', PHP_INT_MAX * -1)
                ),
            );
        }
    }

    /**
     * Do we have log entries?
     *
     * @return boolean
     */
    public function hasLog()
    {
        $entryCountInitial = $this->debug->getData('entryCountInitial');
        $entryCountCurrent = $this->debug->getData('log/__count__');
        $haveLog = $entryCountCurrent > $entryCountInitial;
        $lastEntryMethod = $this->debug->getData('log/__end__/method');
        return $haveLog && $lastEntryMethod !== 'clear';
    }

    /**
     * debug.bootstrap subscriber
     *
     * @param Event $event debug.bootstrap event instance
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $onBootstrap = new OnBootstrap();
        $onBootstrap($event);
    }

    /**
     * debug.config subscriber
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event->getValues();
        if (!isset($cfg['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfg = $cfg['debug'];
        if (isset($cfg['outputAs'])) {
            $event['debug']['outputAs'] = $this->setOutputAs($cfg['outputAs']);
        }
        if (isset($cfg['onBootstrap'])) {
            if (!$this->debug->data) {
                // we're initializing
                $this->debug->eventManager->subscribe('debug.bootstrap', $cfg['onBootstrap']);
            } else {
                // boostrap has already occured, so go ahead and call
                \call_user_func($cfg['onBootstrap'], new Event($this->debug));
            }
        }
        if (isset($cfg['onLog'])) {
            /*
                Replace - not append - subscriber set via setCfg
            */
            $onLogPrev = $this->debug->getCfg('onLog');
            if ($onLogPrev) {
                $this->debug->eventManager->unsubscribe('debug.log', $onLogPrev);
            }
            $this->debug->eventManager->subscribe('debug.log', $cfg['onLog']);
        }
        if (isset($cfg['stream'])) {
            $this->debug->addPlugin($this->debug->routeStream);
        }
        if (!static::$profilingEnabled) {
            $cfg = $this->debug->getCfg('debug/*');
            if ($cfg['enableProfiling'] && $cfg['collect']) {
                static::$profilingEnabled = true;
                $pathsExclude = array(
                    __DIR__,
                );
                FileStreamWrapper::register($pathsExclude);
            }
        }
    }

    /**
     * Listen for a log entry occuring after php.shutdown...
     *
     * @return void
     */
    public function onDebugLogShutdown()
    {
        $this->debug->eventManager->unsubscribe('debug.log', array($this, __FUNCTION__));
        $this->debug->info('php.shutdown', $this->debug->meta(array(
            'attribs' => array(
                'class' => 'php-shutdown',
            ),
            'icon' => 'fa fa-power-off',
        )));
    }

    /**
     * debug.dumpCustom subscriber
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function onDumpCustom(Event $event)
    {
        $abs = $event->getSubject();
        if ($abs['return']) {
            // return already defined..   prev subscriber should have stopped propagation
            return;
        }
        $event['return'] = \print_r($abs->getValues(), true);
        $event['typeMore'] = 't_string';
    }

    /**
     * errorHandler.error event subscriber
     * adds error to console as error or warn
     *
     * @param Error $error error/event object
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($this->debug->getCfg('collect')) {
            $errLoc = $error['file'].' (line '.$error['line'].')';
            $meta = $this->debug->meta(array(
                'backtrace' => $error['backtrace'],
                'errorCat' => $error['category'],
                'errorHash' => $error['hash'],
                'errorType' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
                'sanitize' => $error['isHtml'] === false,
            ));
            $method = $error['type'] & $this->debug->getCfg('errorMask')
                ? 'error'
                : 'warn';
            $this->debug->getChannel('phpError')->{$method}(
                $error['typeStr'].':',
                $errLoc,
                $error['message'],
                $meta
            );
            $error['continueToNormal'] = false; // no need for PHP to log the error, we've captured it here
            $error['inConsole'] = true;
            // Prevent ErrorHandler\ErrorEmailer from sending email.
            // Since we're collecting log info, we send email on shutdown
            $error['email'] = false;
        } elseif ($this->debug->getCfg('output')) {
            $error['email'] = false;
            $error['inConsole'] = false;
        } else {
            $error['inConsole'] = false;
        }
    }

    /**
     * debug.output subscriber
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    public function onOutput(Event $event)
    {
        if ($event['isTarget']) {
            /*
                All channels share the same data.
                We only need to do this via the channel that called output
            */
            $this->onOutputCleanup();
        }
        if (!$this->debug->parentInstance) {
            $this->onOutputLogRuntime();
        }
    }

    /**
     * php.shutdown subscriber (high priority)
     *
     * @return void
     */
    public function onShutdownHigh()
    {
        $this->inShutdown = true;
        $this->closeOpenGroups();
        $this->debug->eventManager->subscribe('debug.log', array($this, 'onDebugLogShutdown'));
    }

    /**
     * php.shutdown subscriber (low priority)
     * Email Log if emailLog is 'always' or 'onError'
     * output log if not already output
     *
     * @return void
     */
    public function onShutdownLow()
    {
        $this->debug->eventManager->unsubscribe('debug.log', array($this, 'onDebugLogShutdown'));
        if ($this->testEmailLog()) {
            $this->runtimeVals();
            $this->emailLog();
        }
        if (!$this->debug->getData('outputSent')) {
            echo $this->debug->output();
        }
        return;
    }

    /**
     * Publish/Trigger/Dispatch event
     * Event will get published on ancestor channels if propagation not stopped
     *
     * @param string $eventName event name
     * @param Event  $event     event instance
     * @param Debug  $debug     specify Debug instance to start on
     *                            if not specified will check if getSubject returns Debug instance
     *                            fallback this->debug
     *
     * @return Event
     */
    public function publishBubbleEvent($eventName, Event $event, Debug $debug = null)
    {
        if (!$debug) {
            $subject = $event->getSubject();
            $debug = $subject instanceof Debug
                ? $subject
                : $this->debug;
        }
        do {
            $debug->eventManager->publish($eventName, $event);
            if (!$debug->parentInstance) {
                break;
            }
            $debug = $debug->parentInstance;
        } while (!$event->isPropagationStopped());
        return $event;
    }

    /**
     * Build list of errors for email
     *
     * @return string
     */
    private function buildErrorList()
    {
        $errorStr = '';
        $errors = $this->debug->errorHandler->get('errors');
        \uasort($errors, function ($a1, $a2) {
            return \strcmp($a1['file'].$a1['line'], $a2['file'].$a2['line']);
        });
        $lastFile = '';
        foreach ($errors as $error) {
            if ($error['isSuppressed']) {
                continue;
            }
            if ($error['file'] !== $lastFile) {
                $errorStr .= $error['file'].':'."\n";
                $lastFile = $error['file'];
            }
            $typeStr = $error['type'] === E_STRICT
                ? 'Strict'
                : $error['typeStr'];
            $errorStr .= '  Line '.$error['line'].': ('.$typeStr.') '.$error['message']."\n";
        }
        return $errorStr;
    }

    /**
     * Close any unclosed groups
     *
     * We may have forgotten to end a group or the script may have exited
     *
     * @return void
     */
    private function closeOpenGroups()
    {
        if ($this->inShutdown) {
            // we already closed
            return;
        }
        $data = $this->debug->getData();
        $data['groupPriorityStack'][] = 'main';
        while ($data['groupPriorityStack']) {
            $priority = \array_pop($data['groupPriorityStack']);
            foreach ($data['groupStacks'][$priority] as $i => $info) {
                if (!$info['collect']) {
                    continue;
                }
                unset($data['groupStacks'][$priority][$i]);
                $logEntry = new LogEntry(
                    $info['channel'],
                    'groupEnd'
                );
                if ($priority === 'main') {
                    $data['log'][] = $logEntry;
                } else {
                    $data['logSummary'][$priority][] = $logEntry;
                }
            }
        }
        $this->debug->setData($data);
    }

    /**
     * "cleanup"
     *    close open groups
     *    remove "hide-if-empty" groups
     *    uncollapse errors
     *
     * @return void
     */
    private function onOutputCleanup()
    {
        $this->closeOpenGroups();
        $data = $this->debug->getData();
        $this->removeHideIfEmptyGroups($data['log']);
        $this->uncollapseErrors($data['log']);
        foreach ($data['logSummary'] as &$log) {
            $this->removeHideIfEmptyGroups($log);
            $this->uncollapseErrors($log);
        }
        $this->debug->setData($data);
    }

    /**
     * Log our runtime info in a summary group
     *
     * As we're only subscribed to root debug instance's debug.output event,  this info
     *   will not be output for any sub-channels output directly
     *
     * @return void
     */
    private function onOutputLogRuntime()
    {
        $vals = $this->runtimeVals();
        $this->debug->groupSummary(1);
        $this->debug->info('Built In '.$vals['runtime'].' sec');
        $this->debug->info(
            'Peak Memory Usage'
                .(\get_class($this->debug->getCfg('outputAs')) == 'bdk\\Debug\\Route\\Html'
                    ? ' <span title="Includes debug overhead">?&#x20dd;</span>'
                    : '')
                .': '
                .$this->debug->utilities->getBytes($vals['memoryPeakUsage']).' / '
                .$this->debug->utilities->getBytes($vals['memoryLimit']),
            $this->debug->meta('sanitize', false)
        );
        $this->debug->groupEnd();
    }

    /**
     * Remove empty groups with 'hideIfEmpty' meta value
     *
     * @param array $log log or summary
     *
     * @return void
     */
    private function removeHideIfEmptyGroups(&$log)
    {
        $groupStack = array();
        $groupStackCount = 0;
        $removed = false;
        for ($i = 0, $count = \count($log); $i < $count; $i++) {
            $logEntry = $log[$i];
            $method = $logEntry['method'];
            /*
                pushing/popping to/from groupStack led to unexplicable warning:
                "Cannot add element to the array as the next element is already occupied"
            */
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[$groupStackCount] = array(
                    'i' => $i,
                    'meta' => $logEntry['meta'],
                    'hasEntries' => false,
                );
                $groupStackCount ++;
            } elseif ($groupStackCount) {
                if ($method == 'groupEnd') {
                    $groupStackCount--;
                    $group = $groupStack[$groupStackCount];
                    if (!$group['hasEntries'] && !empty($group['meta']['hideIfEmpty'])) {
                        unset($log[$group['i']]);   // remove open entry
                        unset($log[$i]);            // remove end entry
                        $removed = true;
                    }
                } else {
                    $groupStack[$groupStackCount - 1]['hasEntries'] = true;
                }
            }
        }
        if ($removed) {
            $log = \array_values($log);
        }
    }

    /**
     * Get/store values such as runtime & peak memory usage
     *
     * @return array
     */
    private function runtimeVals()
    {
        $vals = $this->debug->getData('runtime');
        if (!$vals) {
            $vals = array(
                'memoryPeakUsage' => \memory_get_peak_usage(true),
                'memoryLimit' => $this->debug->utilities->memoryLimit(),
                'runtime' => $this->debug->timeEnd('debugInit', true),
            );
            $this->debug->setData('runtime', $vals);
        }
        return $vals;
    }

    /**
     * Set outputAs value
     * instantiate object if necessary & addPlugin if not already subscribed
     *
     * @param OutputInterface|string $outputAs OutputInterface instance, or (short) classname
     *
     * @return OutputInterface|null
     */
    private function setOutputAs($outputAs)
    {
        $outputAsPrev = $this->debug->getCfg('outputAs');
        if (\is_object($outputAsPrev)) {
            /*
                unsubscribe current OutputInterface
                there can only be one 'outputAs' at a time
                if multiple output routes are desired, use debug->addPlugin()
            */
            $this->debug->removePlugin($outputAsPrev);
        }
        if (\is_string($outputAs)) {
            $prop = 'route'.\ucfirst($outputAs);
            $outputAs = $this->debug->{$prop};
        }
        if ($outputAs instanceof RouteInterface) {
            $this->debug->addPlugin($outputAs);
            $classname = \get_class($outputAs);
            $prefix = __NAMESPACE__.'\\Route\\';
            if (\strpos($classname, $prefix) === 0) {
                $prop = 'route'.\substr($classname, \strlen($prefix));
                $this->debug->{$prop} = $outputAs;
            }
        } else {
            $outputAs = null;
        }
        return $outputAs;
    }

    /**
     * Test if conditions are met to email the log
     *
     * @return boolean
     */
    private function testEmailLog()
    {
        if (!$this->debug->getCfg('emailTo')) {
            return false;
        }
        if ($this->debug->getCfg('output')) {
            // don't email log if we're outputing it
            return false;
        }
        if (!$this->hasLog()) {
            return false;
        }
        if ($this->debug->getCfg('emailLog') === 'always') {
            return true;
        }
        if ($this->debug->getCfg('emailLog') === 'onError') {
            $errors = $this->debug->errorHandler->get('errors');
            $emailMask = $this->debug->errorEmailer->getCfg('emailMask');
            $emailableErrors = \array_filter($errors, function ($error) use ($emailMask) {
                return !$error['isSuppressed'] && ($error['type'] & $emailMask);
            });
            return !empty($emailableErrors);
        }
        return false;
    }

    /**
     * Uncollapse groups containing errors.
     *
     * @param array $log log or summary
     *
     * @return void
     */
    private function uncollapseErrors(&$log)
    {
        $groupStack = array();
        for ($i = 0, $count = \count($log); $i < $count; $i++) {
            $method = $log[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method == 'groupEnd') {
                \array_pop($groupStack);
            } elseif (\in_array($method, array('error', 'warn'))) {
                foreach ($groupStack as $i2) {
                    $log[$i2]['method'] = 'group';
                }
            }
        }
    }
}
