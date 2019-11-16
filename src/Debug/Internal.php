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
use bdk\Debug\LogEntry;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Plugin\Prism;
use bdk\Debug\Route\RouteInterface;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use Exception;

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
    private $inShutdown = false;
    private $prismAdded = false;
    private static $profilingEnabled = false;

    // duplicate/store frequently used cfg vals here
    private $cfg = array(
        'logResponse' => false,
        'redactKeys' => array(),
        'redactReplace' => null,
    );

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
            $addHeadersStr .= 'From: ' . $fromAddr;
        }
        \call_user_func($this->debug->getCfg('emailFunc'), $toAddr, $subject, $body, $addHeadersStr);
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
     * Return the response Content-Type
     *
     * Content type is pulled from PSR-7 response interface (if `Debug::writeToResponse()` is being used)
     * otherwise, content-type is pulled from emitted headers via `headers_list()`
     *
     * @return string (empty string if Content-Type header not found)
     */
    public function getResponseContentType()
    {
        return $this->debug->response
            ? $this->debug->response->getHeaderLine('Content-Type')
            : $this->debug->utilities->getEmittedHeader();
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
            /*
                OnShutDownHigh subscribes to 'debug.log' (onDebugLogShutdown)
                  so... if any log entry is added in php's shutdown phase, we'll have a
                  "php.shutdown" log entry
            */
            return array(
                'debug.bootstrap' => array('onBootstrap', PHP_INT_MAX * -1),
                'debug.config' => array('onConfig', PHP_INT_MAX),
                'debug.dumpCustom' => 'onDumpCustom',
                'debug.log' => array('onLog', PHP_INT_MAX),
                'debug.output' => array(
                    array('onOutput', 1),
                    array('onOutputHeaders', -1),
                ),
                'debug.prettify' => array('onPrettify', -1),
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
        $lastEntryMethod = $this->debug->getData('log/__end__/method');
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
        $cfg = $event->getValues();
        if (isset($cfg['routeStream']['stream'])) {
            $this->debug->addPlugin($this->debug->routeStream);
        }
        if (!isset($cfg['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfg = $cfg['debug'];
        $valActions = array(
            'logResponse' => array($this, 'onCfgLogResponse'),
            'onBootstrap' => array($this, 'onCfgOnBootstrap'),
            'onLog' => array($this, 'onCfgOnLog'),
            'redactKeys' => array($this, 'onCfgRedactKeys'),
            'route' => function ($val, Event $event) {
                $event['debug']['route'] = $this->setRoute($val);
            },
        );
        foreach ($valActions as $key => $callable) {
            if (isset($cfg[$key])) {
                $callable($cfg[$key], $event);
            }
        }
        if (!static::$profilingEnabled) {
            $cfgAll = $this->debug->getCfg('debug/*');
            if ($cfgAll['enableProfiling'] && $cfgAll['collect']) {
                static::$profilingEnabled = true;
                $pathsExclude = array(
                    __DIR__,
                );
                FileStreamWrapper::register($pathsExclude);
            }
        }
        $this->cfg = \array_merge(
            $this->cfg,
            \array_intersect_key($cfg, \array_flip(array('redactReplace')))
        );
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
            $errLoc = $error['file'] . ' (line ' . $error['line'] . ')';
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
                $error['typeStr'] . ':',
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
     * debug.log subscriber
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if ($logEntry->getMeta('redact')) {
            $logEntry['args'] = $this->redact($logEntry['args']);
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
            $event->getSubject()->setData('headers', \array_merge(
                $event->getSubject()->getData('headers'),
                $headers
            ));
        } elseif (\headers_sent($file, $line)) {
            \trigger_error('PHPDebugConsole: headers already sent: ' . $file . ', line ' . $line, E_USER_NOTICE);
        } else {
            foreach ($headers as $nameVal) {
                \header($nameVal[0] . ': ' . $nameVal[1]);
            }
        }
    }

    /**
     * Prettify a string if known content-type
     *
     * @param Event $event debug.prettyify event object
     *
     * @return void
     */
    public function onPrettify(Event $event)
    {
        if (\preg_match('#\b(html|json|xml)\b#', $event['contentType'], $matches)) {
            $string = $event['value'];
            $lang = $type = $matches[1];
            if ($type === 'html') {
                $lang = 'markup';
            } elseif ($type === 'json') {
                $string = $this->debug->utilities->prettyJson($string);
            } elseif ($type === 'xml') {
                $string = $this->debug->utilities->prettyXml($string);
            }
            if (!$this->prismAdded) {
                $this->debug->addPlugin(new Prism());
                $this->prismAdded = true;
            }
            $event['value'] = new Abstraction(array(
                'type' => 'string',
                'attribs' => array(
                    'class' => 'language-' . $lang . ' prism',
                ),
                'addQuotes' => false,
                'visualWhiteSpace' => false,
                'value' => $string,
            ));
            $event->stopPropagation();
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
        $this->logResponse();
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
            $this->debug->routeEmail->processLogEntries();
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
     * Redact
     *
     * @param mixed $val value to scrub
     * @param mixed $key array key, or property name
     *
     * @return mixed
     */
    public function redact($val, $key = null)
    {
        if (\is_string($val)) {
            return $this->redactString($val, $key);
        }
        if ($val instanceof Abstraction) {
            if ($val['type'] == 'object') {
                $props = $val['properties'];
                foreach ($props as $name => $prop) {
                    $props[$name]['value'] = $this->redact($prop['value'], $name);
                }
                $val['properties'] = $props;
                $val['stringified'] = $this->redact($val['stringified']);
                if (isset($val['methods']['__toString']['returnValue'])) {
                    $val['methods']['__toString']['returnValue'] = $this->redact($val['methods']['__toString']['returnValue']);
                }
            } elseif ($val['value']) {
                $val['value'] = $this->redact($val['value']);
            }
        }
        if (\is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = $this->redact($v, $k);
            }
        }
        return $val;
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
     * log response
     *
     * @return void
     */
    private function logResponse()
    {
        if (!$this->cfg['logResponse']) {
            return;
        }
        $contentType = $this->getResponseContentType();
        if (!\preg_match('#\b(json|xml)\b#', $contentType)) {
            // we're not interested in logging response
            \ob_end_flush();
            return;
        }
        $response = \ob_get_clean();
        if ($this->debug->response) {
            try {
                $stream = $this->debug->response->getBody();
                $response = $this->debug->utilities->getStreamContents($stream);
            } catch (Exception $e) {
                $this->debug->warn('Exception', array(
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ));
                return;
            }
        }
        $event = $this->debug->rootInstance->eventManager->publish('debug.prettify', $this->debug, array(
            'value' => $response,
            'contentType' => $contentType,
        ));
        $this->debug->log(
            'response (%c%s) %c%s',
            'font-family: monospace;',
            $contentType,
            'font-style: italic; opacity: 0.8;',
            $event['value'] instanceof Abstraction
                ? '(prettified)'
                : '',
            $event['value'],
            $this->debug->meta('redact')
        );
        echo $response;
    }

    /**
     * Handle "logResponse" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgLogResponse($val)
    {
        if ($val === 'auto') {
            $server = \array_merge(array(
                'HTTP_ACCEPT' => null,
                'HTTP_SOAPACTION' => null,
                'HTTP_USER_AGENT' => null,
            ), $_SERVER);
            $val = \count(\array_filter(array(
                $this->debug->utilities->getInterface() == 'ajax',
                \strpos($server['HTTP_ACCEPT'], 'html') === 0,
                $server['HTTP_SOAPACTION'],
                \stripos($server['HTTP_USER_AGENT'], 'curl') !== false,
            ))) > 0;
        }
        if ($val) {
            if (!$this->cfg['logResponse']) {
                \ob_start();
            }
        } elseif ($this->cfg['logResponse']) {
            \ob_end_flush();
        }
        $this->cfg['logResponse'] = $val;
    }

    /**
     * Handle "onBootstrap" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnBootstrap($val)
    {
        if (!$this->bootstraped) {
            // we're initializing
            $this->debug->eventManager->subscribe('debug.bootstrap', $val);
        } else {
            // boostrap has already occured, so go ahead and call
            \call_user_func($val, new Event($this->debug));
        }
    }

    /**
     * Handle "onLog" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnLog($val)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        $onLogPrev = $this->debug->getCfg('onLog');
        if ($onLogPrev) {
            $this->debug->eventManager->unsubscribe('debug.log', $onLogPrev);
        }
        $this->debug->eventManager->subscribe('debug.log', $val);
    }

    /**
     * Handle "redactKeys" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgRedactKeys($val)
    {
        $keys = array();
        foreach ($val as $key) {
            $keys[$key] = $this->redactBuildRegex($key);
        }
        $this->cfg['redactKeys'] = $keys;
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
        $route = $this->debug->getCfg('route');
        $isRouteHtml = $route && \get_class($route) == 'bdk\\Debug\\Route\\Html';
        $this->debug->groupSummary(1);
        $this->debug->info('Built In ' . $this->debug->utilities->formatDuration($vals['runtime']));
        $this->debug->info(
            'Peak Memory Usage'
                . ($isRouteHtml
                    ? ' <span title="Includes debug overhead">?&#x20dd;</span>'
                    : '')
                . ': '
                . $this->debug->utilities->getBytes($vals['memoryPeakUsage']) . ' / '
                . $this->debug->utilities->getBytes($vals['memoryLimit']),
            $this->debug->meta('sanitize', false)
        );
        $this->debug->groupEnd();
    }

    /**
     * Build Regex that will search for key=val in string
     *
     * @param string $key key to redact
     *
     * @return string
     */
    private function redactBuildRegex($key)
    {
        return '#(?:'
            // xml
            . '<' . $key . '\b.*?>\s*([^<]*?)\s*</' . $key . '>'
            . '|'
            // json
            . \json_encode($key) . '\s*:\s*"([^"]*?)"'
            . '|'
            // url encoded
            . '\b' . $key . '=([^\s&]+\b)'
            . ')#';
    }

    /**
     * Redact string or portions within
     *
     * @param string $val string to redact
     * @param string $key if array value: the key. if object property: the prop name
     *
     * @return string
     */
    protected function redactString($val, $key = null)
    {
        if (\is_string($key)) {
            // do exact match against array key or object property
            foreach (\array_keys($this->cfg['redactKeys']) as $redactKey) {
                if ($redactKey === $key) {
                    return \call_user_func($this->cfg['redactReplace'], $val, $key);
                }
            }
        }
        foreach ($this->cfg['redactKeys'] as $key => $regex) {
            $val = \preg_replace_callback($regex, function ($matches) use ($key) {
                $matches = \array_filter($matches, 'strlen');
                $substr = \end($matches);
                $replacement = \call_user_func($this->cfg['redactReplace'], $substr, $key);
                return \str_replace($substr, $replacement, $matches[0]);
            }, $val);
        }
        return $val;
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
                $groupStackCount++;
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
                'runtime' => $this->debug->timeEnd('debugInit', $this->debug->meta('silent')),
            );
            $this->debug->setData('runtime', $vals);
        }
        return $vals;
    }

    /**
     * Set route value
     * instantiate object if necessary & addPlugin if not already subscribed
     *
     * @param RouteInterface|string $route RouteInterface instance, or (short) classname
     *
     * @return RouteInterface|string
     */
    private function setRoute($route)
    {
        if ($this->bootstraped) {
            /*
                Only need to wory about previous route if we're bootstrapped
                There can only be one 'route' at a time:
                If multiple output routes are desired, use debug->addPlugin()
                unsubscribe current OutputInterface
            */
            $routePrev = $this->debug->getCfg('route');
            if (\is_object($routePrev)) {
                $this->debug->removePlugin($routePrev);
            }
        }
        if (\is_string($route) && $route !== 'auto') {
            $prop = 'route' . \ucfirst($route);
            $route = $this->debug->{$prop};
        }
        if ($route instanceof RouteInterface) {
            $this->debug->addPlugin($route);
            $classname = \get_class($route);
            $prefix = __NAMESPACE__ . '\\Route\\';
            if (\strpos($classname, $prefix) === 0) {
                $prop = 'route' . \substr($classname, \strlen($prefix));
                $this->debug->{$prop} = $route;
            }
        } else {
            $route = 'auto';
        }
        return $route;
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
        $emailLog = $this->debug->getCfg('emailLog');
        if (\in_array($emailLog, array(true, 'always'), true)) {
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
