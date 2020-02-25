<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 *
 * @link http://www.github.com/bkdotcom/PHPDebugConsole
 * @link https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Utility\ErrorLevel;
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
        $this->logSessionSettings();
        $this->logSession();
        $this->debug->groupEnd();
        $this->debug->groupEnd();
        $this->debug->setCfg('collect', $collectWas);
        self::$input = null;
    }

    /**
     * Assert ini setting is/is-not specified value
     *
     * @param array $setting setting name, "type", comparison value, operator
     *
     * @return void
     */
    private function assertSetting($setting)
    {
        $setting = \array_merge(array(
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'msg' => '',
            'name' => '',
            'operator' => '==',
            'val' => true,
        ), $setting);
        $actual = \filter_var(\ini_get($setting['name']), $setting['filter']);
        $assert = $actual === $setting['val'];
        $valFriendly = $setting['val'];
        if ($setting['filter'] === FILTER_VALIDATE_BOOLEAN) {
            $valFriendly = $setting['val']
                ? 'enabled'
                : 'disabled';
        }
        $msgDefault = 'should be ' . $valFriendly;
        if ($setting['operator'] === '!=') {
            $assert = $actual !== $setting['val'];
            $msgDefault = 'should not be ' . $valFriendly;
        }
        $msg = $setting['msg'] ?: $msgDefault;
        $params = array(
            $assert,
            '%c' . $setting['name'] . '%c ' . $msg,
        );
        $cCount = \substr_count($params[1], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace;';
            $params[] = '';
        }
        \call_user_func_array(array($this->debug, 'assert'), $params);
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
     * Check request for probable sessionId cookie or query-param
     *
     * @return string|null
     */
    private function getSessionName()
    {
        $name = $this->debug->getCfg('sessionName');
        $names = $name
            ? array($name)
            : array('PHPSESSID', 'SESSIONID', 'SESSION_ID', 'SESSID', 'SESS_ID');
        $cookies = $this->debug->request->getCookieParams();
        $queryParams = $this->debug->request->getQueryParams();
        $useCookies = \filter_var(\ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN);
        $useOnlyCookies = \filter_var(\ini_get('session.use_only_cookies'), FILTER_VALIDATE_BOOLEAN);
        if ($useCookies) {
            foreach ($names as $name) {
                if (isset($cookies[$name])) {
                    return $name;
                }
            }
        }
        if ($useOnlyCookies === false) {
            foreach ($names as $name) {
                if (isset($queryParams[$name])) {
                    return $name;
                }
            }
        }
        return null;
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
        $redirect = \stripos(PHP_OS, 'WIN') !== 0
            ? '2>/dev/null'
            : '2> nul';
        \exec('git branch ' . $redirect, $outputLines, $returnStatus);
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
        $this->assertSetting(array(
            'name' => 'expose_php',
            'val' => false,
        ));
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
        if (!$this->debug->getCfg('logEnvInfo.errorReporting')) {
            return;
        }
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
        $method = $this->debug->request->getMethod();
        $contentType = $this->debug->request->getHeaderLine('Content-Type');
        if ($method === 'GET') {
            return;
        }
        $havePostVals = false;
        if ($method === 'POST') {
            $isCorrectContentType = $this->testPostContentType($contentType);
            $post = $this->debug->request->getParsedBody();
            if (!$isCorrectContentType) {
                $this->debug->warn(
                    'It appears ' . $contentType . ' was posted with the wrong Content-Type' . "\n"
                        . 'Pay no attention to $_POST and instead use php://input',
                    $this->debug->meta(array(
                        'detectFiles' => false,
                        'file' => null,
                        'line' => null,
                    ))
                );
            } elseif ($post) {
                $havePostVals = true;
                $this->debug->log('$_POST', $post, $this->debug->meta('redact'));
            }
        }
        if (!$havePostVals) {
            // Not POST, empty $_POST, or not application/x-www-form-urlencoded or multipart/form-data
            $input = $this->getInput();
            if ($input) {
                $this->logInput($contentType);
            } elseif (!$this->debug->request->getUploadedFiles()) {
                $this->debug->warn(
                    $method . ' request with no body',
                    $this->debug->meta(array(
                        'detectFiles' => false,
                        'file' => null,
                        'line' => null,
                    ))
                );
            }
        }
        if ($this->debug->request->getUploadedFiles()) {
            $this->debug->log('$_FILES', $this->debug->request->getUploadedFiles());
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
            $cookieVals = $this->debug->request->getCookieParams();
            \ksort($cookieVals, SORT_NATURAL);
            $this->debug->log('$_COOKIE', $cookieVals, $this->debug->meta('redact'));
        }
        // don't expect a request body for these methods
        $noBodyMethods = array('CONNECT','GET','HEAD','OPTIONS','TRACE');
        $expectBody = !\in_array($this->debug->request->getMethod(), $noBodyMethods);
        if ($logEnvInfo['post'] && $expectBody) {
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
        $headers = \array_map(function ($vals) {
            return \join(', ', $vals);
        }, $this->debug->request->getHeaders());
        if ($headers) {
            \ksort($headers, SORT_NATURAL);
            $this->debug->log('request headers', $headers, $this->debug->meta('redact'));
        }
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
        $serverParams = $this->debug->request->getServerParams();
        if ($this->debug->request->getMethod() !== 'GET') {
            $logServerKeys[] = 'REQUEST_METHOD';
        }
        if (isset($serverParams['CONTENT_LENGTH'])) {
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
            $val = Abstracter::UNDEFINED;
            if (\array_key_exists($k, $serverParams)) {
                $val = $serverParams[$k];
                if ($k === 'REQUEST_TIME') {
                    $val = \date('Y-m-d H:i:s T', $val);
                }
            }
            $vals[$k] = $val;
        }
        \ksort($vals, SORT_NATURAL);
        $this->debug->log('$_SERVER', $vals, $this->debug->meta('redact'));
    }

    /**
     * Log $_SESSION data
     *
     * @return void
     */
    private function logSession()
    {
        if (!$this->debug->getCfg('logEnvInfo.session')) {
            return;
        }
        $namePrev = null;
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            $name = $this->getSessionName();
            if ($name === null) {
                $this->debug->log('Session:  No session id passed in request');
                return;
            }
            $namePrev = \session_name($name);
            \session_start();
        }
        if (\session_status() === PHP_SESSION_ACTIVE) {
            $this->debug->log('$_SESSION', $_SESSION, $this->debug->meta('redact'));
        }
        if ($namePrev) {
            /*
                PHPDebugConsole started session... close it.
                "<jedi>we were never here</jedi>"
            */
            \session_abort();
            \session_name($namePrev);
            unset($_SESSION);
        }
    }

    /**
     * Asserts recommended session ini settings
     *
     * @return void
     */
    private function logSessionSettings()
    {
        if (!$this->debug->getCfg('logEnvInfo.session')) {
            return;
        }
        $settings = array(
            array('name' => 'session.cookie_httponly'),
            array(
                'name' => 'session.cookie_lifetime',
                'filter' => FILTER_VALIDATE_INT,
                'val' => 0,
            ),
            array(
                'name' => 'session.name',
                'filter' => FILTER_DEFAULT,
                'val' => 'PHPSESSID',
                'operator' => '!=',
                'msg' => 'should not be PHPSESSID (just as %cexpose_php%c should be disabled)',
            ),
            array('name' => 'session.use_only_cookies'),
            array('name' => 'session.use_strict_mode'),
            array(
                'name' => 'session.use_trans_sid',
                'val' => false
            ),
        );
        foreach ($settings as $setting) {
            $this->assertSetting($setting);
        }
        $this->debug->log('session.cache_limiter', \ini_get('session.cache_limiter'));
        if (\session_module_name() === 'files') {
            // aka session.save_handler
            $this->debug->log('session save_path', \session_save_path() ?: \sys_get_temp_dir());
        }
    }

    /**
     * Test if $_POST is properly populated or not
     *
     * If JSON or XML is posted using the default application/x-www-form-urlencoded Content-Type
     * $_POST will be improperly populated
     *
     * @param string $contentType Will get populated with detected content type
     *
     * @return bool
     */
    private function testPostContentType(&$contentType)
    {
        $contentTypeRaw = $this->debug->request->getHeaderLine('Content-Type');
        if ($contentTypeRaw) {
            \preg_match('#^([^;]+)#', $contentTypeRaw, $matches);
            $contentType = $matches[1];
        }
        if (!$this->debug->request->getParsedBody()) {
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
