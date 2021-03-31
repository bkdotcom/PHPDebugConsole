<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Plugin\Highlight;
use bdk\Debug\Utility\FindExit;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;

/**
 * Handle debug events
 */
class InternalEvents implements SubscriberInterface
{

    private $debug;

    private $highlightAdded = false;
    private $inShutdown = false;
    protected $log = array();

    /**
     * duplicate/store frequently used cfg vals
     *
     * @var array
     */
    private $cfg = array(
        'logResponse' => false,
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
        $this->debug->errorHandler->eventManager->subscribe(ErrorHandler::EVENT_ERROR, array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorHighPri'), PHP_INT_MAX);
        $this->debug->errorHandler->eventManager->subscribe(ErrorHandler::EVENT_ERROR, array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorLowPri'), PHP_INT_MAX * -1);
        $this->setInitialConfig();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        if ($this->debug->parentInstance) {
            // we are a child channel
            return array(
                Debug::EVENT_OUTPUT => array(
                    array('onOutput', 1),
                    array('onOutputHeaders', -1),
                ),
                Debug::EVENT_CONFIG => 'onConfig',
            );
        }
        /*
            OnShutDownHigh2 subscribes to Debug::EVENT_LOG (onDebugLogShutdown)
              so... if any log entry is added in php's shutdown phase, we'll have a
              "php.shutdown" log entry
        */
        return array(
            Debug::EVENT_CONFIG => 'onConfig',
            Debug::EVENT_DUMP_CUSTOM => 'onDumpCustom',
            Debug::EVENT_LOG => array('onLog', PHP_INT_MAX),
            Debug::EVENT_OUTPUT => array(
                array('onOutput', 1),
                array('onOutputHeaders', -1),
            ),
            Debug::EVENT_PRETTIFY => array('onPrettify', -1),
            Debug::EVENT_STREAM_WRAP => 'onStreamWrap',
            ErrorHandler::EVENT_ERROR => 'onError',
            EventManager::EVENT_PHP_SHUTDOWN => array(
                array('onShutdownHigh', PHP_INT_MAX),
                array('onShutdownHigh2', PHP_INT_MAX - 10),
                array('onShutdownLow', PHP_INT_MAX * -1)
            ),
        );
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        $cfg = $event->getValues();
        if (empty($cfg['debug'])) {
            // no debug config values have changed
            return;
        }
        $cfg = $cfg['debug'];
        $valActions = array(
            'logResponse' => array($this, 'onCfgLogResponse'),
            'onLog' => array($this, 'onCfgOnLog'),
            'onMiddleware' => array($this, 'onCfgOnMiddleware'),
            'onOutput' => array($this, 'onCfgOnOutput'),
        );
        foreach ($valActions as $key => $callable) {
            if (isset($cfg[$key])) {
                $val = $callable($cfg[$key], $event);
                if ($val) {
                    $cfg[$key] = $val;
                }
            }
        }
        $event['debug'] = $cfg;
    }

    /**
     * Listen for a log entry occuring after EventManager::EVENT_PHP_SHUTDOWN...
     *
     * @return void
     */
    public function onDebugLogShutdown()
    {
        $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, array($this, __FUNCTION__));
        $this->debug->info('php.shutdown', $this->debug->meta(array(
            'attribs' => array(
                'class' => 'php-shutdown',
            ),
            'icon' => 'fa fa-power-off',
        )));
    }

    /**
     * Debug::EVENT_DUMP_CUSTOM subscriber
     *
     * @param Event $event Event instance
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
     * ErrorHandler::EVENT_ERROR event subscriber
     * adds error to console as error or warn
     *
     * @param Error $error error/event object
     *
     * @return void
     */
    public function onError(Error $error)
    {
        if ($this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            $meta = $this->debug->meta(array(
                'context' => $error['category'] === 'fatal' && $error['backtrace'] === null
                    ? $error['context']
                    : null,
                'errorCat' => $error['category'],
                'errorHash' => $error['hash'],
                'errorType' => $error['type'],
                'file' => $error['file'],
                'isSuppressed' => $error['isSuppressed'], // set via event subscriber vs "@"" code prefix
                'line' => $error['line'],
                'sanitize' => $error['isHtml'] === false,
                'trace' => $error['backtrace'],
            ));
            $method = $error['type'] & $this->debug->getCfg('errorMask', Debug::CONFIG_DEBUG)
                ? 'error'
                : 'warn';
            /*
                specify rootInstance as there's nothing to prevent calling Internal::onError() dirrectly (from aanother instance)
            */
            $this->debug->rootInstance->getChannel('phpError')->{$method}(
                $error['typeStr'] . ':',
                $error['message'],
                \sprintf('%s (line %s)', $error['file'], $error['line']),
                $meta
            );
            $error['continueToNormal'] = false; // no need for PHP to log the error, we've captured it here
            $error['inConsole'] = true;
            // Prevent ErrorHandler\ErrorEmailer from sending email.
            // Since we're collecting log info, we send email on shutdown
            $error['email'] = false;
            return;
        }
        if ($this->debug->getCfg('output', Debug::CONFIG_DEBUG)) {
            $error['email'] = false;
            $error['inConsole'] = false;
            return;
        }
        $error['inConsole'] = false;
    }

    /**
     * Debug::EVENT_LOG subscriber
     *
     * @param LogEntry $logEntry log entry instance
     *
     * @return void
     */
    public function onLog(LogEntry $logEntry)
    {
        if ($logEntry->getMeta('redact')) {
            $debug = $logEntry->getSubject();
            $logEntry['args'] = $debug->redact($logEntry['args']);
        }
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * @param Event $event Debug::EVENT_OUTPUT event object
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
     * Debug::EVENT_OUTPUT subscriber
     *
     * Merge event headers into data['headers'] or output them
     *
     * @param Event $event Debug::EVENT_OUTPUT event object
     *
     * @return void
     * @throws \RuntimeException if error emitting headers
     */
    public function onOutputHeaders(Event $event)
    {
        $headers = $event['headers'];
        $outputHeaders = $event->getSubject()->getCfg('outputHeaders', Debug::CONFIG_DEBUG);
        if (!$outputHeaders || !$headers) {
            $event->getSubject()->setData('headers', \array_merge(
                $event->getSubject()->getData('headers'),
                $headers
            ));
            return;
        }
        $this->debug->utility->emitHeaders($headers);
    }

    /**
     * Prettify a string if known content-type
     *
     * @param Event $event Debug::EVENT_PRETTIFY event object
     *
     * @return void
     */
    public function onPrettify(Event $event)
    {
        $matches = array();
        if (!\preg_match('#\b(html|json|sql|xml)\b#', $event['contentType'], $matches)) {
            return;
        }
        $string = $event['value'];
        $type = $matches[1];
        $lang = $type;
        if ($type === 'html') {
            $lang = 'markup';
        } elseif ($type === 'json') {
            $string = $this->debug->utility->prettyJson($string);
        } elseif ($type === 'sql') {
            $string = $this->debug->utility->prettySql($string);
        } elseif ($type === 'xml') {
            $string = $this->debug->utility->prettyXml($string);
        }
        if (!$this->highlightAdded) {
            $this->debug->addPlugin(new Highlight());
            $this->highlightAdded = true;
        }
        $isPrettified = $string !== $event['value'];
        $event['value'] = $this->debug->abstracter->crateWithVals($string, array(
            'attribs' => array(
                'class' => 'highlight language-' . $lang,
            ),
            'addQuotes' => false,
            'contentType' => $event['contentType'],
            'prettified' => $isPrettified,
            'prettifiedTag' => $isPrettified,
            'visualWhiteSpace' => false,
        ));
        $event->stopPropagation();
    }

    /**
     * If profiling, inject `declare(ticks=1)`
     *
     * @param Event $event Debug::EVENT_STREAM_WRAP event object
     *
     * @return void
     */
    public function onStreamWrap(Event $event)
    {
        $declare = 'declare(ticks=1);';
        $event['content'] = \preg_replace(
            '/^(<\?php)\s*$/m',
            '$0 ' . $declare,
            $event['content'],
            1
        );
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN subscriber (high priority)
     *
     * @return void
     */
    public function onShutdownHigh()
    {
        $this->exitCheck();
        $this->closeOpenGroups();
        $this->inShutdown = true;
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN subscriber (not-so-high priority).. come after other internal...
     *
     * @return void
     */
    public function onShutdownHigh2()
    {
        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, array($this, 'onDebugLogShutdown'));
    }

    /**
     * EventManager::EVENT_PHP_SHUTDOWN subscriber (low priority)
     * Email Log if emailLog is 'always' or 'onError'
     * output log if not already output
     *
     * @return void
     */
    public function onShutdownLow()
    {
        $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, array($this, 'onDebugLogShutdown'));
        if ($this->testEmailLog()) {
            $this->runtimeVals();
            $this->debug->getRoute('email')->processLogEntries(new Event($this->debug));
        }
        if ($this->debug->getData('outputSent')) {
            $this->debug->obEnd();
            return;
        }
        echo $this->debug->output();
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
        $groupPriorityStack = \array_merge(array('main'), $this->debug->getData('groupPriorityStack'));
        $groupStacks = $this->debug->getData('groupStacks');
        while ($groupPriorityStack) {
            $priority = \array_pop($groupPriorityStack);
            foreach ($groupStacks[$priority] as $info) {
                $info['channel']->groupEnd();
            }
            if (\is_int($priority)) {
                // close the summary
                $this->debug->groupEnd();
            }
        }
    }

    /**
     * Check if php was shutdown via exit() or die()
     * This check is only possible if xdebug is instaned & enabled
     *
     * @return void
     */
    private function exitCheck()
    {
        if ($this->debug->getCfg('exitCheck', Debug::CONFIG_DEBUG) === false) {
            return;
        }
        if ($this->debug->getData('outputSent')) {
            return;
        }
        $lastError = $this->debug->errorHandler->getLastError();
        if ($lastError && ($lastError['type'] === E_PARSE || $lastError['exception'] instanceof \ParseError)) {
            return;
        }
        $findExit = new FindExit(array(
            __CLASS__,
            \get_class($this->debug->eventManager),
        ));
        $info = $findExit->find();
        if ($info) {
            $this->debug->warn(
                'Potentialy shutdown via ' . $info['found'] . ': ',
                \sprintf('%s (line %s)', $info['file'], $info['line']),
                $this->debug->meta(array(
                    'file' => $info['file'],
                    'line' => $info['line'],
                ))
            );
        }
    }

    /**
     * Handle "logResponse" config update
     *
     * @param mixed $val config value
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgLogResponse($val)
    {
        if ($val === 'auto') {
            $serverParams = \array_merge(array(
                'HTTP_ACCEPT' => null,
                'HTTP_SOAPACTION' => null,
                'HTTP_USER_AGENT' => null,
            ), $this->debug->request->getServerParams());
            $val = \count(
                \array_filter(array(
                    \strpos($this->debug->getInterface(), 'http') !== false,
                    $serverParams['HTTP_SOAPACTION'],
                    \stripos($serverParams['HTTP_USER_AGENT'], 'curl') !== false,
                ))
            ) > 0;
        }
        if ($val) {
            $this->debug->obStart();
        }
        $this->cfg['logResponse'] = $val;
        return $val;
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
        $prev = $this->debug->getCfg('onLog', Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, $prev);
        }
        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, $val);
    }

    /**
     * Handle "onOutput" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnOutput($val)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        $prev = $this->debug->getCfg('onOutput', Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_OUTPUT, $prev);
        }
        $this->debug->eventManager->subscribe(Debug::EVENT_OUTPUT, $val);
    }

    /**
     * Handle "onMiddleware" config update
     *
     * @param mixed $val config value
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function onCfgOnMiddleware($val)
    {
        /*
            Replace - not append - subscriber set via setCfg
        */
        $prev = $this->debug->getCfg('onMiddleware', Debug::CONFIG_DEBUG);
        if ($prev) {
            $this->debug->eventManager->unsubscribe(Debug::EVENT_MIDDLEWARE, $prev);
        }
        $this->debug->eventManager->subscribe(Debug::EVENT_MIDDLEWARE, $val);
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
        $this->log = &$data['log'];
        $this->onOutputGroups();
        $this->uncollapseErrors();
        $summaryKeys = \array_keys($data['logSummary']);
        foreach ($summaryKeys as $key) {
            $this->log = &$data['logSummary'][$key];
            $this->onOutputGroups();
            $this->uncollapseErrors();
        }
        $this->debug->setData($data);
    }

    /**
     * Remove empty groups having 'hideIfEmpty' meta value
     * Convert empty groups having "ungroup" meta value to log entries
     *
     * @return void
     */
    private function onOutputGroups()
    {
        $groupStack = array(
            array(
                // dummy / root group
                //  eliminates need to test if entry has parent group
                'childCount' => 0,
                'groupCount' => 0,
            )
        );
        $groupStackCount = 1;
        $reindex = false;
        for ($i = 0, $count = \count($this->log); $i < $count; $i++) {
            $logEntry = $this->log[$i];
            $method = $logEntry['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = array(
                    'childCount' => 0,  // includes any child groups
                    'groupCount' => 0,
                    'i' => $i,
                    'iEnd' => null,
                    'meta' => $logEntry['meta'],
                    'parent' => null,
                );
                $groupStack[$groupStackCount - 1]['childCount']++;
                $groupStack[$groupStackCount - 1]['groupCount']++;
                $groupStack[$groupStackCount]['parent'] = &$groupStack[$groupStackCount - 1];
                $groupStackCount++;
                continue;
            }
            if ($method === 'groupEnd') {
                $group = \array_pop($groupStack);
                $group['iEnd'] = $i;
                $groupStackCount--;
                $reindex = $this->onOutputGroup($group) || $reindex;
                continue;
            }
            $groupStack[$groupStackCount - 1]['childCount']++;
        }
        if ($reindex) {
            $this->log = \array_values($this->log);
        }
    }

    /**
     * Handle group hideIfEmpty & ungroup meta options
     *
     * @param array $group Group info collected in onOutputGroups
     *
     * @return bool Whether log needs re-indexed
     */
    private function onOutputGroup(&$group = array())
    {
        if (!empty($group['meta']['hideIfEmpty'])) {
            if ($group['childCount'] === 0) {
                unset($this->log[$group['i']]);     // remove open entry
                unset($this->log[$group['iEnd']]);  // remove end entry
                $group['parent']['childCount']--;
                $group['parent']['groupCount']--;
                return true;
            }
        }
        if (!empty($group['meta']['ungroup'])) {
            if ($group['childCount'] === 0) {
                $this->log[$group['i']]['method'] = 'log';
                unset($this->log[$group['iEnd']]);  // remove end entry
                $group['parent']['groupCount']--;
                return true;
            } elseif ($group['childCount'] === 1 && $group['groupCount'] === 0) {
                unset($this->log[$group['i']]);     // remove open entry
                unset($this->log[$group['iEnd']]);  // remove end entry
                $group['parent']['groupCount']--;
                return true;
            }
        }
        return false;
    }

    /**
     * Log our runtime info in a summary group
     *
     * As we're only subscribed to root debug instance's Debug::EVENT_OUTPUT event, this info
     *   will not be output for any sub-channels output directly
     *
     * @return void
     */
    private function onOutputLogRuntime()
    {
        if (!$this->debug->getCfg('logRuntime', Debug::CONFIG_DEBUG)) {
            return;
        }
        $vals = $this->runtimeVals();
        $route = $this->debug->getCfg('route');
        $isRouteHtml = $route && \get_class($route) === 'bdk\\Debug\\Route\\Html';
        $this->debug->groupSummary(1);
        $this->debug->info('Built In ' . $this->debug->utility->formatDuration($vals['runtime']));
        $this->debug->info(
            'Peak Memory Usage'
                . ($isRouteHtml
                    ? ' <span title="Includes debug overhead">?&#x20dd;</span>'
                    : '')
                . ': '
                . $this->debug->utility->getBytes($vals['memoryPeakUsage']) . ' / '
                . $this->debug->utility->getBytes($vals['memoryLimit']),
            $this->debug->meta('sanitize', false)
        );
        $this->debug->groupEnd();
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
                'memoryLimit' => $this->debug->utility->memoryLimit(),
                'runtime' => $this->debug->timeEnd('requestTime', $this->debug->meta('silent')),
            );
            $this->debug->setData('runtime', $vals);
        }
        return $vals;
    }

    /**
     * Set Config
     *
     * We are constructed after Debug's Initial setCfg has already occured...
     * So we missed the initial Debug::EVENT_CONFIG event
     *       manually call onConfig here
     *
     * @return void
     */
    private function setInitialConfig()
    {
        $cfgDebugInit = $this->debug->getCfg(null, Debug::CONFIG_DEBUG);
        $event = new Event(
            $this->debug,
            array(
                'debug' => $cfgDebugInit,
            )
        );
        $this->onConfig($event);
        $cfgDebugDiff = array();
        foreach ($event['debug'] as $k => $v) {
            if ($v !== $cfgDebugInit[$k]) {
                $cfgDebugDiff[$k] = $v;
            }
        }
        if ($cfgDebugDiff) {
            $this->debug->onConfig(new Event(
                $this->debug,
                array(
                    'debug' => $cfgDebugDiff,
                )
            ));
        }
    }

    /**
     * Test if conditions are met to email the log
     *
     * @return bool
     */
    private function testEmailLog()
    {
        if (!$this->debug->getCfg('emailTo', Debug::CONFIG_DEBUG)) {
            return false;
        }
        if ($this->debug->getCfg('output', Debug::CONFIG_DEBUG)) {
            // don't email log if we're outputing it
            return false;
        }
        if (!$this->debug->hasLog()) {
            return false;
        }
        $emailLog = $this->debug->getCfg('emailLog', Debug::CONFIG_DEBUG);
        if (\in_array($emailLog, array(true, 'always'), true)) {
            return true;
        }
        if ($emailLog === 'onError') {
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
     * @return void
     */
    private function uncollapseErrors()
    {
        $groupStack = array();
        for ($i = 0, $count = \count($this->log); $i < $count; $i++) {
            $method = $this->log[$i]['method'];
            if (\in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method === 'groupEnd') {
                \array_pop($groupStack);
            } elseif (\in_array($method, array('error', 'warn'))) {
                if ($this->log[$i]->getMeta('uncollapse') === false) {
                    continue;
                }
                foreach ($groupStack as $i2) {
                    $this->log[$i2]['method'] = 'group';
                }
            }
        }
    }
}
