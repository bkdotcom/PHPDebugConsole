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
use bdk\Debug\Abstraction\Abstracter;
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

    private static $profilingEnabled = false;

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
        return array(
            'debug.bootstrap' => array('onBootstrap', PHP_INT_MAX * -1),
            'debug.config' => array('onConfig', PHP_INT_MAX),
            'debug.dumpCustom' => 'onDumpCustom',
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
        $lastEntryMethod = $this->debug->getData('log/__end__/method');
        return $haveLog && $lastEntryMethod !== 'clear';
    }

    /**
     * debug.init subscriber
     *
     * @return void
     */
    public function onBootstrap()
    {
        if ($this->debug->parentInstance) {
            // only record php/request info for root instance
            return;
        }
        $collectWas = $this->debug->setCfg('collect', true);
        $this->debug->groupSummary();
        $this->debug->group('environment', $this->debug->meta(array(
            'hideIfEmpty' => true,
            'level' => 'info',
        )));
        $this->logGitInfo();
        $this->logPhpInfo();
        $this->logServerVals();
        $this->logRequest();    // headers, cookies, post
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->setCfg('collect', $collectWas);
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
            if (isset($this->cfg['onLog'])) {
                $this->debug->eventManager->unsubscribe('debug.log', $this->cfg['onLog']);
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
     * debug.output event subscriber
     *
     * @return void
     */
    public function onOutput()
    {
        if ($this->debug->parentInstance) {
            // only record runtime info for root instance
            return;
        }
        $vals = $this->runtimeVals();
        $this->debug->groupSummary(1);
        $this->debug->info('Built In '.$vals['runtime'].' sec');
        $this->debug->info(
            'Peak Memory Usage'
                .($this->debug->getCfg('output/outputAs') == 'html'
                    ? ' <span title="Includes debug overhead">?&#x20dd;</span>'
                    : '')
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
     * Log git branch (if applicable)
     *
     * @return void
     */
    private function logGitInfo()
    {
        if (!$this->debug->getCfg('logEnvInfo.gitInfo')) {
            return;
        }
        \exec('git branch', $outputLines, $returnStatus);
        if ($returnStatus === 0) {
            $lines = \implode("\n", $outputLines);
            \preg_match('#^\* (.+)$#m', $lines, $matches);
            $branch = $matches[1];
            $this->debug->groupSummary(1);
            $this->debug->log(
                // '<i class="fa fa-github fa-lg" aria-hidden="true"></i> %cgit branch: %c%s',
                '%cgit branch: %c%s',
                'font-weight:bold;',
                'font-size:1.5em; background-color:#DDD; padding:0 .3em;',
                $branch,
                $this->debug->meta('icon', 'fa fa-github fa-lg')
            );
            $this->debug->groupEnd();
        }
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
        $this->debug->log('ini location', \php_ini_loaded_file(), $this->debug->meta('detectFiles', true));
        $this->debug->log('memory_limit', $this->debug->utilities->getBytes($this->debug->utilities->memoryLimit()));
        $this->debug->log('session.cache_limiter', \ini_get('session.cache_limiter'));
        if (\session_module_name() === 'files') {
            $this->debug->log('session_save_path', \session_save_path() ?: \sys_get_temp_dir());
        }
        $extensionsCheck = array('curl','mbstring');
        $extensionsCheck = \array_filter($extensionsCheck, function ($extension) {
            return !\extension_loaded($extension);
        });
        if ($extensionsCheck) {
            $this->debug->warn('These common extensions are not loaded:', $extensionsCheck);
        }
        $this->logPhpInfoEr();
    }

    /**
     * Log if
     * PHP's error reporting !== (E_ALL | E_STRICT)
     * PHPDebugConsole is not logging (E_ALL | E_STRICT)
     *
     * @return void
     */
    private function logPhpInfoEr()
    {
        $errorReportingRaw = $this->debug->getCfg('errorReporting');
        $errorReporting = $errorReportingRaw === 'system'
            ? \error_reporting()
            : $errorReportingRaw;
        $msgLines = array();
        $styles = array();
        $styleMono = 'font-family:monospace; opacity:0.8;';
        $styleReset = 'font-family:inherit; white-space:pre-wrap;';
        if (\error_reporting() !== (E_ALL | E_STRICT)) {
            $msgLines[] = 'PHP\'s %cerror_reporting%c is set to `%c'.ErrorLevel::toConstantString().'%c` rather than `%cE_ALL | E_STRICT%c`';
            $styles = array(
                $styleMono, $styleReset, // wraps "error_reporting"
                $styleMono, $styleReset, // wraps actual
                $styleMono, $styleReset, // wraps E_ALL | E_STRICT
            );
            if ($errorReporting === (E_ALL | E_STRICT)) {
                $msgLines[] = 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            }
        }
        if ($errorReporting !== (E_ALL | E_STRICT)) {
            if ($errorReportingRaw === 'system') {
                $msgLines[] = 'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)';
            } elseif ($errorReporting === \error_reporting()) {
                $msgLines[] = 'PHPDebugConsole\'s errorHandler is also using a errorReporting value of '
                    .'`%c'.ErrorLevel::toConstantString($errorReporting).'%c`';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            } else {
                $msgLines[] = 'PHPDebugConsole\'s errorHandler is using a errorReporting value of '
                    .'`%c'.ErrorLevel::toConstantString($errorReporting).'%c`';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            }
        }
        if ($msgLines) {
            $args = array(\implode("\n", $msgLines));
            $args = \array_merge($args, $styles);
            $args[] = $this->debug->meta(array(
                'file' => null,
                'line' => null,
            ));
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
                } elseif (isset($_SERVER['REQUEST_METHOD']) && empty($_FILES)) {
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
        $headers = $this->debug->utilities->getAllHeaders();
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
                $vals[$k] = Abstracter::UNDEFINED;
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
