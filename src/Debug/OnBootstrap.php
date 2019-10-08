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

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\PubSub\Event;

/**
 * Log eenvironent info
 */
class OnBootstrap
{

    private $debug;
    private static $input;  // populate me for unit tests in lieu of php://input

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
        $route = $this->debug->getCfg('route');
        if ($route === 'stream') {
            $this->debug->setCfg('route', $route);
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
        self::$input = null;
    }

    /**
     * returns self::$input or php://input contents
     *
     * @return string;
     */
    private function getInput()
    {
        if (self::$input) {
            return self::$input;
        }
        self::$input = \file_get_contents('php://input');
        return self::$input;
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
     * log php://input
     *
     * @param string $contentType Content-Type
     *
     * @return void
     */
    private function logInput($contentType = null)
    {
        $event = $this->debug->rootInstance->eventManager->publish('debug.prettify', $this->debug, array(
            'value' => self::$input,
            'contentType' => $contentType,
        ));
        $input = $event['value'];
        $this->debug->log(
            'php://input %c%s',
            'font-style: italic; opacity: 0.8;',
            $input instanceof Abstraction
                ? '(prettified)'
                : '',
            $input,
            $this->debug->meta('redact')
        );
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
            $this->debug->warn(
                'These common extensions are not loaded:',
                $extensionsCheck,
                $this->debug->meta(array(
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                ))
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
            $msgLines[] = 'PHP\'s %cerror_reporting%c is set to `%c' . ErrorLevel::toConstantString() . '%c` rather than `%cE_ALL | E_STRICT%c`';
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
                    . '`%c' . ErrorLevel::toConstantString($errorReporting) . '%c`';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            } else {
                $msgLines[] = 'PHPDebugConsole\'s errorHandler is using a errorReporting value of '
                    . '`%c' . ErrorLevel::toConstantString($errorReporting) . '%c`';
                $styles[] = $styleMono;
                $styles[] = $styleReset;
            }
        }
        if ($msgLines) {
            $args = array(\implode("\n", $msgLines));
            $args = \array_merge($args, $styles);
            $args[] = $this->debug->meta(array(
                'detectFiles' => false,
                'file' => null,
                'line' => null,
            ));
            \call_user_func_array(array($this->debug, 'warn'), $args);
        }
    }

    /**
     * Log $_POST or php://input & $_FILES
     *
     * @return void
     */
    private function logPost()
    {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }
        $havePostVals = false;
        $contentType = isset($_SERVER['CONTENT_TYPE'])
            ? $_SERVER['CONTENT_TYPE']
            : null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $correctContentType = $this->testPostContentType($contentType);
            if (!$correctContentType) {
                $this->debug->warn(
                    'It appears ' . $contentType . ' was posted with the wrong Content-Type' . "\n"
                        . 'Pay no attention to $_POST and instead use php://input',
                    $this->debug->meta(array(
                        'detectFiles' => false,
                        'file' => null,
                        'line' => null,
                    ))
                );
            } elseif ($_POST) {
                $havePostVals = true;
                $this->debug->log('$_POST', $_POST, $this->debug->meta('redact'));
            }
        }
        if (!$havePostVals) {
            // Not POST, empty $_POST, or not application/x-www-form-urlencoded or multipart/form-data
            $input = $this->getInput();
            if ($input) {
                $this->logInput($contentType);
            } elseif (empty($_FILES)) {
                $this->debug->warn(
                    $_SERVER['REQUEST_METHOD'] . ' request with no body',
                    $this->debug->meta(array(
                        'detectFiles' => false,
                        'file' => null,
                        'line' => null,
                    ))
                );
            }
        }
        if (!empty($_FILES)) {
            $this->debug->log('$_FILES', $_FILES);
        }
    }

    /**
     * Log request headers, Cookie, Post, & Files data
     *
     * @return void
     */
    private function logRequest()
    {
        $this->logRequestHeaders();
        $logEnvInfo = $this->debug->getCfg('logEnvInfo');
        if ($logEnvInfo['cookies']) {
            $cookieVals = $_COOKIE;
            \ksort($cookieVals, SORT_NATURAL);
            $this->debug->log('$_COOKIE', $cookieVals, $this->debug->meta('redact'));
        }
        // don't expect a request body for these methods
        $noBodyMethods = array('CONNECT','GET','HEAD','OPTIONS','TRACE');
        $expectBody = isset($_SERVER['REQUEST_METHOD'])
            && !\in_array($_SERVER['REQUEST_METHOD'], $noBodyMethods);
        if ($logEnvInfo['cookies'] && $expectBody) {
            $this->logPost();
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
        $this->debug->log('request headers', $headers, $this->debug->meta('redact'));
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
        $this->debug->log('$_SERVER', $vals, $this->debug->meta('redact'));
    }

    /**
     * Test if $_POST is properly populated or not
     *
     * If JSON or XML is posted using the default application/x-www-form-urlencoded Content-Type
     * $_POST will be improperly populated
     *
     * @param string $contentType Will get populated with detected content type
     *
     * @return boolean
     */
    private function testPostContentType(&$contentType)
    {
        if (!empty($_SERVER['CONTENT_TYPE'])) {
            \preg_match('#^([^;]+)#', $_SERVER['CONTENT_TYPE'], $matches);
            $contentType = $matches[1];
        }
        if (!$_POST) {
            // nothing in $_POST means it can't be wrong
            return true;
        }
        /*
        $_POST is populated...
            which means Content-Type was application/x-www-form-urlencoded or multipart/form-data
            if we detect php://input is json or XML, then must have been
            posted with wrong Content-Type
        */
        $input = $this->getInput();
        $json = \json_decode($input, true);
        $isJson = \json_last_error() === JSON_ERROR_NONE && \is_array($json);
        if ($isJson) {
            $contentType = 'application/json';
            return false;
        }
        if ($this->debug->utilities->isXml($input)) {
            $contentType = 'text/xml';
            return false;
        }
        return true;
    }
}
