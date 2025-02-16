<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Route\Stream;
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
    /** @var Debug|null */
    private $debug;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        /*
            OnShutDownHigh2 subscribes to Debug::EVENT_LOG (onDebugLogShutdown)
              so... if any log entry is added in php's shutdown phase, we'll have a
              "php.shutdown" log entry
        */
        return array(
            Debug::EVENT_DUMP_CUSTOM => ['onDumpCustom', -1],
            Debug::EVENT_OUTPUT => [
                ['onOutput', 1],
                ['onOutputHeaders', -1],
            ],
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
            ErrorHandler::EVENT_ERROR => ['onError', -1],
            EventManager::EVENT_PHP_SHUTDOWN => [
                ['onShutdownHigh', PHP_INT_MAX],
                ['onShutdownHigh2', PHP_INT_MAX - 10],
                ['onShutdownLow', PHP_INT_MAX * -1],
            ],
        );
    }

    /**
     * Listen for a log entry occurring after EventManager::EVENT_PHP_SHUTDOWN...
     *
     * @return void
     */
    public function onDebugLogShutdown()
    {
        $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, [$this, __FUNCTION__]);
        $this->debug->info('php.shutdown', $this->debug->meta(array(
            'attribs' => array(
                'class' => 'php-shutdown',
            ),
            'icon' => ':shutdown:',
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
        if ($event['return']) {
            // return already defined..   prev subscriber should have stopped propagation
            return;
        }
        $values = $abs->getValues();
        \ksort($values);
        $event['return'] = $event['valDumper']->dump($values);
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
            // subscriber should have stopped error propagation
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
                $this->debug->setCfg($cfgWas, Debug::CONFIG_NO_RETURN);
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
        if ($event['isTarget'] === false) {
            return;
        }
        $this->debug->data->set('headers', array());
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
     * Debug::EVENT_PLUGIN_INIT subscriber
     *
     * @param Event $event Debug::EVENT_PLUGIN_INIT Event instance
     *
     * @return void
     */
    public function onPluginInit(Event $event)
    {
        $this->debug = $event->getSubject();
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
        $this->debug->eventManager->subscribe(Debug::EVENT_LOG, [$this, 'onDebugLogShutdown']);
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
        $this->debug->eventManager->unsubscribe(Debug::EVENT_LOG, [$this, 'onDebugLogShutdown']);
        if ($this->testEmailLog()) {
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
     * This check is only possible if xdebug is installed & enabled
     *
     * @return void
     */
    private function exitCheck()
    {
        if ($this->shouldExitCheck() === false) {
            return;
        }
        $findExit = $this->debug->findExit;
        $findExit->setSkipClasses([
            __CLASS__,
            \get_class($this->debug->eventManager),
        ]);
        $info = $findExit->find();
        if ($info) {
            $this->debug->warn(
                'Potentially shutdown via ' . $info['found'] . ': ',
                \sprintf('%s (line %s)', $info['file'], $info['line']),
                $this->debug->meta(array(
                    'file' => $info['file'],
                    'line' => $info['line'],
                ))
            );
        }
    }

    /**
     * Should we force output for the given error
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
        /*
            specify rootInstance as there's nothing to prevent calling Internal::onError() directly (from another instance)
        */
        $this->debug->rootInstance->getChannel('phpError')->{$method}(
            $error['typeStr'] . ':',
            $error['message'],
            $error['fileAndLine'],
            $this->debug->meta($this->errorMetaValues($error))
        );
        // We've captured the error and are logging / viewing it with debugger.
        //    typically no reason for php to log the error...
        //    This value can be overridden via 'errorLogNormal' config or via error event subscriber
        $error['continueToNormal'] = $this->debug->getCfg('errorLogNormal', Debug::CONFIG_DEBUG);
        $error['inConsole'] = true;
        // Prevent ErrorHandler\Plugin\Emailer from sending email.
        // Since we're collecting log info, we send email on shutdown
        $error['email'] = false;
    }

    /**
     * Get error LogEntry meta values
     *
     * @param Error $error Error instance
     *
     * @return array
     */
    private function errorMetaValues(Error $error)
    {
        return array(
            'context' => $error['category'] === Error::CAT_FATAL && $error['backtrace'] === null
                ? $error['context']
                : null,
            'errorCat' => $error['category'],
            'errorHash' => $error['hash'],
            'errorType' => $error['type'],
            'evalLine' => $error['evalLine'],
            'file' => $error['file'],
            'icon' => $error['isSuppressed']
                ? ':error-suppressed:'
                : null,
            'isSuppressed' => $error['isSuppressed'], // set via event subscriber vs "@"" code prefix
            'line' => $error['line'],
            'sanitize' => $error['isHtml'] === false,
            'trace' => $error['backtrace'],
        );
    }

    /**
     * Determine if should perform `exit` search
     *
     * @return bool
     */
    private function shouldExitCheck()
    {
        if ($this->debug->getCfg('exitCheck', Debug::CONFIG_DEBUG) === false) {
            return false;
        }
        if ($this->debug->data->get('outputSent')) {
            return false;
        }
        $lastError = $this->debug->errorHandler->getLastError();
        $isParseError = $lastError && ($lastError['type'] === E_PARSE || $lastError['exception'] instanceof \ParseError);
        return $isParseError === false;
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
            // don't email log if we're outputting it
            return false;
        }
        if (!$this->debug->hasLog()) {
            return false;
        }
        $emailLog = $this->debug->getCfg('emailLog', Debug::CONFIG_DEBUG);
        if (\in_array($emailLog, [true, 'always'], true)) {
            return true;
        }
        if ($emailLog === 'onError') {
            // see if we handled any unsuppressed errors of types specified with emailMask
            $errors = $this->debug->errorHandler->get('errors');
            $emailMask = $this->debug->errorHandler->emailer->getCfg('emailMask');
            $emailableErrors = \array_filter($errors, static function ($error) use ($emailMask) {
                return !$error['isSuppressed'] && ($error['type'] & $emailMask);
            });
            return !empty($emailableErrors);
        }
        return false;
    }
}
