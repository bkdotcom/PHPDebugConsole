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

use bdk\PubSub\Event;

/**
 * Log eenvironent info
 */
class OnBootstrap
{

    private $debug;

    /**
     * Magic callable method
     *
     * @param Event $event event instance
     *
     * @return void
     */
    public function __invoke(Event $event)
    {
        $this->debug = $event->getSubject();
        $outputAs = $this->debug->getCfg("outputAs");
        if ($outputAs === 'stream') {
            $this->debug->setCfg('outputAs', $outputAs);
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
        $this->debug->log('ini location', \php_ini_loaded_file());
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
            $this->debug->warn(
                'These common extensions are not loaded:',
                $extensionsCheck
            );
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
}
