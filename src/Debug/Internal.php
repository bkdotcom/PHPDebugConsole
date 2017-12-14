<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

use bdk\PubSub\Event;
use bdk\Debug;

/**
 * Methods that are internal to the debug class
 *
 * a) Don't want to clutter the debug class
 * b) avoiding a base class as it would necessitate we first load the base or have
 *       an autoloader in place to bootstrap the debug class
 * c) a trait for code not meant to be "reusable" seems like an anti-pattern
 *       doesn't solve the bootstrap/autoload issue
 */
class Internal
{

    private $debug;
    private $error;     // store error object when logging an error

    /**
     * Constructor
     *
     * @param Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->debug = $debug;
        $this->debug->eventManager->subscribe('debug.bootstrap', array($this, 'onBootstrap'), -1);
        $this->debug->eventManager->subscribe('debug.log', array($this, 'onLog'), -1);
        $this->debug->eventManager->subscribe('debug.output', array($this, 'onOutput'));
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorAddEmailData'), 1);
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array($this, 'onError'));
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorEmail'), -1);
        register_shutdown_function(array($this, 'onShutdown'));
    }

    /**
     * Send an email
     *
     * @param string $emailTo to
     * @param string $subject subject
     * @param string $body    body
     *
     * @return void
     */
    public function email($emailTo, $subject, $body)
    {
        call_user_func($this->debug->getCfg('emailFunc'), $emailTo, $subject, $body);
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
                'backtrace' => $this->error['backtrace'] ?: array(),
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
     * Returns meta-data and removes it from the passed arguments
     *
     * @param array $args args to check
     *
     * @return array meta information
     */
    public static function getMetaArg(&$args)
    {
        $end = end($args);
        if (is_array($end) && ($key = array_search(Debug::META, $end, true)) !== false) {
            array_pop($args);
            unset($end[$key]);
            return $end;
        }
        return array();
    }

    /**
     * Do we have log entries?
     *
     * @return boolean
     */
    public function hasLog()
    {
        $entryCountInitial = $this->debug->getData('entryCountInitial');
        $entryCountCurrent = $this->debug->getData('entryCount');
        return $entryCountCurrent > $entryCountInitial;
    }

    /**
     * debug.init subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        if ($this->debug->getCfg('logEnvInfo')) {
            $collectWas = $this->debug->setCfg('collect', true);
            $this->debug->group('environment');
            $this->debug->groupUncollapse();
            foreach ($this->debug->getCfg('logServerKeys') as $k) {
                if ($k == 'REQUEST_TIME') {
                    $this->debug->info($k, date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']));
                } elseif (isset($_SERVER[$k])) {
                    $this->debug->info($k, $_SERVER[$k]);
                } else {
                    $this->debug->info($k, null);
                }
            }
            $this->debug->info('PHP Version', PHP_VERSION);
            $this->debug->info('memory_limit', $this->debug->utilities->memoryLimit());
            $this->debug->info('session.cache_limiter', ini_get('session.cache_limiter'));
            if (!empty($_COOKIE)) {
                $this->debug->info('$_COOKIE', $_COOKIE);
            }
            if (!empty($_POST)) {
                $this->debug->info('$_POST', $_POST);
            }
            if (!empty($_FILES)) {
                $this->debug->info('$_FILES', $_FILES);
            }
            $this->debug->groupEnd();
            $this->debug->setCfg('collect', $collectWas);
        }
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
            $error['logError'] = false; // no need to error_log()..  we've captured it here
            $error['inConsole'] = true;
            // Prevent errorEmailer from sending email.
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
     * debug.log event subscriber
     *
     * Given low priority so this will be ran after other subscribers
     *
     * @param Event $event debug.log event
     *
     * @return void
     */
    public function onLog(Event $event)
    {
        $method = $event['method'];
        $meta = $event['meta'];
        if ($method == 'groupUncollapse') {
            // don't append to log
            $event->stopPropagation();
            return;
        }
        $isSummaryBookend = $method == 'groupSummary' || !empty($meta['closesSummary']);
        if ($isSummaryBookend) {
            $event->stopPropagation();
        }
    }

    /**
     * debug.output event subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        $this->debug->groupSummary(1);
        $this->debug->info('Built In '.$this->debug->timeEnd('debugInit', true).' sec');
        $this->debug->info(
            'Peak Memory Usage'
                .($this->debug->getCfg('output/outputAs') == 'html'
                    ? ' <span title="Includes debug overhead">?&#x20dd;</span>'
                    : ''
                )
                .': '
                .$this->debug->utilities->getBytes(memory_get_peak_usage(true)).' / '
                .$this->debug->utilities->getBytes($this->debug->utilities->memoryLimit())
        );
        $this->debug->groupEnd();
    }

    /**
     * Email Log if emailLog is 'always' or 'onError'
     * output log if not already output
     *
     * @return void
     */
    public function onShutdown()
    {
        if ($this->hasLog() && !$this->debug->getCfg('output') && $this->debug->getCfg('emailTo')) {
            /*
                We have log data, it's not being output and we have an emailTo addr
            */
            $email = false;
            if ($this->debug->getCfg('emailLog') === 'always') {
                $email = true;
            } elseif ($this->debug->getCfg('emailLog') === 'onError') {
                $errors = $this->debug->errorHandler->get('errors');
                $emailMask = $this->debug->errorHandler->getCfg('emailMask');
                $emailableErrors = array_filter($errors, function ($error) use ($emailMask) {
                    return !$error['isSuppressed'] && ($error['type'] & $emailMask);
                });
                $email = !empty($emailableErrors);
            }
            if ($email) {
                $this->debug->output->emailLog();
            }
        }
        if (!$this->debug->getData('outputSent')) {
            echo $this->debug->output();
        }
        return;
    }
}
