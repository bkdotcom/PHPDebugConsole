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
use bdk\Debug\Route\Stream;
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

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
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
            );
        }
        /*
            OnShutDownHigh2 subscribes to Debug::EVENT_LOG (onDebugLogShutdown)
              so... if any log entry is added in php's shutdown phase, we'll have a
              "php.shutdown" log entry
        */
        return array(
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
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
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
        if ($error['throw']) {
            return;
        }
        $cfgWas = $this->forceErrorOutput($error)
            ? $this->debug->setCfg(array(
                'collect' => true,
                'output' => true,
            ))
            : null;
        if ($this->debug->getCfg('collect', Debug::CONFIG_DEBUG)) {
            $this->logError($error);
            if ($cfgWas) {
                $this->debug->setCfg($cfgWas);
            }
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
     * @param LogEntry $logEntry LogEntry instance
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
        /*
            All channels share the same data.
            We only need to do this via the channel that called output
        */
        if (!$event['isTarget']) {
            return;
        }
        $this->debug->data->set('headers', array());
        $debug = $event->getSubject();
        if (!$debug->parentInstance) {
            // this is the root instance
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
            $event->getSubject()->data->set('headers', \array_merge(
                $event->getSubject()->data->get('headers'),
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
            $string = $this->debug->stringUtil->prettyJson($string);
        } elseif ($type === 'sql') {
            $string = $this->debug->stringUtil->prettySql($string);
        } elseif ($type === 'xml') {
            $string = $this->debug->stringUtil->prettyXml($string);
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
        if ($this->debug->data->get('outputSent')) {
            $this->debug->obEnd();
            return;
        }
        echo $this->debug->output();
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
        if ($this->debug->data->get('outputSent')) {
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
     * Log error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    private function logError(Error $error)
    {
        $method = $error['type'] & $this->debug->getCfg('errorMask', Debug::CONFIG_DEBUG)
            ? 'error'
            : 'warn';
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
        /*
            specify rootInstance as there's nothing to prevent calling Internal::onError() directly (from aanother instance)
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
        /** @psalm-suppress TypeDoesNotContainType */
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
                . ($vals['memoryLimit'] === '-1'
                    ? '∞'
                    : $this->debug->utility->getBytes($vals['memoryLimit'])
                ),
            $this->debug->meta('sanitize', false)
        );
        $this->debug->groupEnd();
    }

    /**
     * Shoule we force output for the given error
     *
     * @param Error $error Error instance
     *
     * @return bool
     */
    private function forceErrorOutput(Error $error)
    {
        return $error->isFatal() && $this->debug->isCli() && $this->debug->getCfg('route') instanceof Stream;
    }

    /**
     * Get/store values such as runtime & peak memory usage
     *
     * @return array
     */
    private function runtimeVals()
    {
        $vals = $this->debug->data->get('runtime');
        if (!$vals) {
            $vals = array(
                'memoryPeakUsage' => \memory_get_peak_usage(true),
                'memoryLimit' => $this->debug->utility->memoryLimit(),
                'runtime' => $this->debug->timeEnd('requestTime', $this->debug->meta('silent')),
            );
            $this->debug->data->set('runtime', $vals);
        }
        return $vals;
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
}
