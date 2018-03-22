<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
 * @version   v2.1.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
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
class Internal implements SubscriberInterface
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
        $this->debug->eventManager->addSubscriberInterface($this);
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorAddEmailData'), PHP_INT_MAX);
        $this->debug->errorHandler->eventManager->subscribe('errorHandler.error', array(function () {
            // this closure lazy-loads the subscriber object
            return $this->debug->errorEmailer;
        }, 'onErrorEmail'), PHP_INT_MAX * -1);
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
        \call_user_func($this->debug->getCfg('emailFunc'), $emailTo, $subject, $body);
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
        $body .= $this->debug->utilities->serializeLog(array(
            'alerts' => $this->debug->getData('alerts'),
            'log' => $this->debug->getData('log'),
            'logSummary' => $this->debug->getData('logSummary'),
            'requestId' => $this->debug->getData('requestId'),
            'runtime' => $this->debug->getData('runtime'),
        ));
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
        \ksort($stats['counts']);
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
     * Extracts meta-data from args
     *
     * Extract meta-data added via meta() method..
     * all meta args are merged together and returned
     * meta args are removed from passed args
     *
     * @param array $args args to check
     *
     * @return array meta information
     */
    public static function getMetaVals(&$args)
    {
        $meta = array();
        foreach ($args as $i => $v) {
            if (\is_array($v) && \array_intersect_assoc(array('debug'=>Debug::META), $v)) {
                unset($v['debug']);
                $meta = \array_merge($meta, $v);
                unset($args[$i]);
            }
        }
        $args = \array_values($args);
        return $meta;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.bootstrap' => array('onBootstrap', PHP_INT_MAX * -1),
            'debug.output' => 'onOutput',
            'errorHandler.error' => 'onError',
            'php.shutdown' => 'onShutdown',
        );
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
                    $this->debug->info($k, \date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']));
                } elseif (isset($_SERVER[$k])) {
                    $this->debug->info($k, $_SERVER[$k]);
                } else {
                    $this->debug->info($k, null);
                }
            }
            $this->debug->info('PHP Version', PHP_VERSION);
            $this->debug->info('memory_limit', $this->debug->utilities->memoryLimit());
            $this->debug->info('session.cache_limiter', \ini_get('session.cache_limiter'));
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
     * debug.output event subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        $vals = $this->runtimeVals();
        $this->debug->groupSummary(1);
        $this->debug->info('Built In '.$vals['runtime'].' sec');
        $this->debug->info(
            'Peak Memory Usage'
                .($this->debug->getCfg('output/outputAs') == 'html'
                    ? ' <span title="Includes debug overhead">?&#x20dd;</span>'
                    : ''
                )
                .': '
                .$this->debug->utilities->getBytes($vals['memoryPeakUsage']).' / '
                .$this->debug->utilities->getBytes($vals['memoryLimit'])
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
        $this->runtimeVals();
        if ($this->testEmailLog()) {
            $this->emailLog();
        }
        if (!$this->debug->getData('outputSent')) {
            echo $this->debug->output();
        }
        return;
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
