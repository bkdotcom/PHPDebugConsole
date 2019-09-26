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

namespace bdk\Debug;

use bdk\Debug;
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
    private $bootstraped = false;
    private $error;     // store error object when logging an error
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
     * Get calling line/file for error and warn
     *
     * @return array
     */
    public function getErrorCaller()
    {
        $meta = array();
        if ($this->error) {
            // no need to store originating file/line... it's part of error message
            $meta = array(
                'errorType' => $this->error['type'],
                'errorCat' => $this->error['category'],
                'errorHash' => $this->error['hash'],
                'backtrace' => $this->error['backtrace'] ?: array(),
                'sanitize' => $this->error['isHtml'] === false,
                'channel' => 'phpError',
            );
        } else {
            $meta = $this->debug->utilities->getCallerInfo();
            $meta = array(
                'file' => $meta['file'],
                'line' => $meta['line'],
            );
        }
        return $meta;
    }

    /**
     * Return the group & groupCollapsed ("ancestors")
     *
     * @param array   $logEntries log entries
     * @param integer $curDepth   current group depth
     *
     * @return array key => logEntry array
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
            $method = $logEntries[$i][0];
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
     * Extracts meta-data from args
     *
     * Extract meta-data added via meta() method..
     * all meta args are merged together and returned
     * meta args are removed from passed args
     *
     * @param array $args        args to check
     * @param array $defaultMeta default meta values
     * @param array $defaultArgs default arg values
     * @param array $argsToMeta  args to convert to meta
     *
     * @return array meta values
     */
    public static function getMetaVals(&$args, $defaultMeta = array(), $defaultArgs = array(), $argsToMeta = array())
    {
        $meta = array();
        foreach ($args as $i => $v) {
            if (\is_array($v) && isset($v['debug']) && $v['debug'] === Debug::META) {
                unset($v['debug']);
                $meta = \array_merge($meta, $v);
                unset($args[$i]);
            }
        }
        $args = \array_values($args);
        if ($defaultArgs) {
            $args = \array_slice($args, 0, \count($defaultArgs));
            $args = \array_combine(
                \array_keys($defaultArgs),
                \array_replace(\array_values($defaultArgs), $args)
            );
        }
        foreach ($argsToMeta as $argk => $metak) {
            if (\is_int($argk)) {
                $argk = $metak;
            }
            $defaultMeta[$metak] = $args[$argk];
            unset($args[$argk]);
        }
        $meta = \array_merge($defaultMeta, $meta);
        return $meta;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        if ($this->debug->parentInstance) {
            // we are a child channel
            return array(
                'debug.output' => array(
                    array('onOutput', 1),
                    array('onOutputHeaders', -1),
                ),
                'debug.config' => array('onConfig', PHP_INT_MAX),
            );
        } else {
            return array(
                'debug.bootstrap' => array('onBootstrap', PHP_INT_MAX * -1),
                'debug.config' => array('onConfig', PHP_INT_MAX),
                'debug.dumpCustom' => 'onDumpCustom',
                'debug.output' => array(
                    array('onOutput', 1),
                    array('onOutputHeaders', -1),
                ),
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
        $lastEntryMethod = $this->debug->getData('log/__end__/0');
        return $entryCountCurrent > $entryCountInitial && $lastEntryMethod !== 'clear';
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
        $this->bootstraped = true;
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
        $cfg = $event['config'];
        if (!isset($cfg['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfg = $cfg['debug'];
        if (isset($cfg['file'])) {
            $this->debug->addPlugin($this->debug->output->file);
        }
        if (isset($cfg['onBootstrap'])) {
            if (!$this->bootstraped) {
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
     * @param Event $error error/event object
     *
     * @return void
     */
    public function onError(Event $error)
    {
        if ($this->debug->getCfg('collect')) {
            /*
                temporarily store error so that we can easily determine error/warn
                 a) came via error handler
                 b) calling info
            */
            $this->error = $error;
            $errInfo = $error['typeStr'].': '.$error['file'].' (line '.$error['line'].')';
            $errMsg = $error['message'];
            if ($error['type'] & $this->debug->getCfg('errorMask')) {
                $this->debug->error($errInfo.': ', $errMsg);
            } else {
                $this->debug->warn($errInfo.': ', $errMsg);
            }
            $error['continueToNormal'] = false; // no need for PHP to log the error, we've captured it here
            $error['inConsole'] = true;
            // Prevent ErrorHandler\ErrorEmailer from sending email.
            // Since we're collecting log info, we send email on shutdown
            $error['email'] = false;
            $this->error = null;
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
     * debug.output subscriber
     *
     * Merge event headers into data['headers'] or output them
     *
     * @param Event $event debug.output event object
     *
     * @return void
     */
    public function onOutputHeaders(Event $event)
    {
        $headers = $event['headers'];
        $outputHeaders = $event->getSubject()->getCfg('outputHeaders');
        if (!$outputHeaders || !$headers) {
            $event->getSubject()->setData('headers', \array_merge($event->getSubject()->getData('headers'), $headers));
        } elseif (\headers_sent($file, $line)) {
            \trigger_error('PHPDebugConsole: headers already sent: '.$file.', line '.$line, E_USER_NOTICE);
        } else {
            foreach ($headers as $nameVal) {
                \header($nameVal[0].': '.$nameVal[1]);
            }
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
     * @param string $eventName      event name
     * @param mixed  $eventOrSubject passed to subscribers
     * @param array  $values         values to attach to event
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
                $meta = array(
                    'channel' => $info['channel'],
                );
                if ($priority === 'main') {
                    $data['log'][] = array('groupEnd', array(), $meta);
                } else {
                    $data['logSummary'][$priority][] = array('groupEnd', array(), $meta);
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
        $data['headers'] = array();
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
     * As we're only subscribed to root debug instance's debug.output event, this info
     *   will not be output for any sub-channels output directly
     *
     * @return void
     */
    private function onOutputLogRuntime()
    {
        if (!$this->debug->getCfg('logRuntime')) {
            return;
        }
        $vals = $this->runtimeVals();
        $outputAs = $this->debug->getCfg('outputAs');
        $outputAsHtml = $outputAs && \get_class($outputAs) == 'bdk\\Debug\\Route\\Html';
        $this->debug->groupSummary(1);
        $this->debug->info('Built In '.$vals['runtime'].' sec');
        $this->debug->info(
            'Peak Memory Usage'
                .($outputAsHtml
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
            $method = $logEntry[0];
            /*
                pushing/popping to/from groupStack led to unexplicable warning:
                "Cannot add element to the array as the next element is already occupied"
            */
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[$groupStackCount] = array(
                    'i' => $i,
                    'meta' => !empty($logEntry[2]) ? $logEntry[2] : array(),
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
            // see if we handled any unsupressed errors of types specified with emailMask
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
            $method = $log[$i][0];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method == 'groupEnd') {
                \array_pop($groupStack);
            } elseif (\in_array($method, array('error', 'warn'))) {
                foreach ($groupStack as $i2) {
                    $log[$i2][0] = 'group';
                }
            }
        }
    }
}
