<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2018 Brad Kent
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
    private $error;     // store error object when logging an error

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
        $this->debug->eventManager->addSubscriberInterface($this);
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
                'errorHash' => $this->error['hash'],
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
            if (\is_array($v) && \array_intersect_assoc(array('debug'=>Debug::META), $v)) {
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
        $entryCountCurrent = $this->debug->getData('log/__count__');
        $haveLog = $entryCountCurrent > $entryCountInitial;
        $lastEntryMethod = $this->debug->getData('log/__end__/0');
        return $haveLog && $lastEntryMethod !== 'clear';
    }

    /**
     * debug.init subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        $logEnvInfo = $this->debug->getCfg('logEnvInfo');
        if (\is_bool($logEnvInfo)) {
            $keys = array('cookies','headers','phpInfo','post','serverVals');
            $logEnvInfo = \array_fill_keys($keys, $logEnvInfo);
            $this->debug->setCfg('logEnvInfo', $logEnvInfo);
        }
        $collectWas = $this->debug->setCfg('collect', true);
        $this->debug->groupSummary();
        $this->debug->group('environment', $this->debug->meta(array(
            'hideIfEmpty' => true,
            'level' => 'info',
        )));
        $this->logPhpInfo();
        $this->logServerVals();
        $this->logRequest();    // headers, cookies, post
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->setCfg('collect', $collectWas);
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
     * Log some PHP info
     *
     * @return void
     */
    private function logPhpInfo()
    {
        if (!$this->debug->getCfg('logEnvInfo.phpInfo')) {
            return;
        }
        $this->debug->log('PHP Version', PHP_VERSION);
        $this->debug->log('memory_limit', $this->debug->utilities->getBytes($this->debug->utilities->memoryLimit()));
        $this->debug->log('session.cache_limiter', \ini_get('session.cache_limiter'));
        if (\error_reporting() !== E_ALL) {
            $styleMono = 'font-family:monospace;';
            $styleReset = 'font-family:inherit; white-space:pre-wrap;';
            $msgLines = array(
                'PHP\'s %cerror_reporting%c is not set to E_ALL',
            );
            $styles = array($styleMono, $styleReset);
            $errorReporting = $this->debug->getCfg('errorReporting');
            if ($errorReporting === E_ALL) {
                $msgLines[] = 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            } elseif ($errorReporting === "system") {
                $msgLines[] = 'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)';
            } elseif ($errorReporting !== \error_reporting()) {
                $msgLines[] = 'PHPDebugConsole\'s errorHandler is using a errorReporting value that differs from %cerror_reporting%c';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            }
            $args = array(\implode("\n", $msgLines));
            $args = \array_merge($args, $styles);
            \call_user_func_array(array($this->debug, 'warn'), $args);
        }
    }

    /**
     * Log Cookie, Post, & Files data
     *
     * @return void
     */
    private function logRequest()
    {
        $this->logRequestHeaders();
        if ($this->debug->getCfg('logEnvInfo.cookies')) {
            $cookieVals = $_COOKIE;
            \ksort($cookieVals, SORT_NATURAL);
            $this->debug->log('$_COOKIE', $cookieVals);
        }
        // don't expect a request body for these methods
        $noBody = !isset($_SERVER['REQUEST_METHOD'])
            || \in_array($_SERVER['REQUEST_METHOD'], array('CONNECT','GET','HEAD','OPTIONS','TRACE'));
        if ($this->debug->getCfg('logEnvInfo.post') && !$noBody) {
            if ($_POST) {
                $this->debug->log('$_POST', $_POST);
            } else {
                $input = \file_get_contents('php://input');
                if ($input) {
                    $this->debug->log('php://input', $input);
                } elseif (isset($_SERVER['REQUEST_METHOD'])) {
                    $this->debug->warn($_SERVER['REQUEST_METHOD'].' request with no body');
                }
            }
            if (!empty($_FILES)) {
                $this->debug->log('$_FILES', $_FILES);
            }
        }
    }

    /**
     * Log Request Headers
     *
     * @return void
     */
    private function logRequestHeaders()
    {
        if (!$this->debug->getCfg('logEnvInfo.headers')) {
            return;
        }
        if (!empty($_SERVER['argv'])) {
            return;
        }
        $headers = array();
        foreach ($_SERVER as $k => $v) {
            if (\strpos($k, 'HTTP_') !== false) {
                $headers[$k] = $v;
            }
        }
        \ksort($headers, SORT_NATURAL);
        $this->debug->log('request headers', $headers);
    }

    /**
     * Log $_SERVER values specified by `logServerKeys` config option
     *
     * @return void
     */
    private function logServerVals()
    {
        $logEnvInfo = $this->debug->getCfg('logEnvInfo');
        if (!$logEnvInfo['serverVals']) {
            return;
        }
        $logServerKeys = $this->debug->getCfg('logServerKeys');
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            $logServerKeys[] = 'REQUEST_METHOD';
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $logServerKeys[] = 'CONTENT_LENGTH';
            $logServerKeys[] = 'CONTENT_TYPE';
        }
        if (!$logEnvInfo['headers']) {
            $logServerKeys[] = 'HTTP_HOST';
        }
        $logServerKeys = \array_unique($logServerKeys);
        if (!$logServerKeys) {
            return;
        }
        $vals = array();
        foreach ($logServerKeys as $k) {
            if (!\array_key_exists($k, $_SERVER)) {
                $vals[$k] = $this->debug->abstracter->UNDEFINED;
            } elseif ($k == 'REQUEST_TIME') {
                $vals[$k] = \date('Y-m-d H:i:s T', $_SERVER['REQUEST_TIME']);
            } else {
                $vals[$k] = $_SERVER[$k];
            }
        }
        \ksort($vals, SORT_NATURAL);
        $this->debug->log('$_SERVER', $vals);
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
