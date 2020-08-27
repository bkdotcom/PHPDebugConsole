<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Utility\ErrorLevel;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log environment info
 */
class LogEnv implements SubscriberInterface
{

    private $debug;

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_PLUGIN_INIT => 'onPluginInit',
        );
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
        $collectWas = $this->debug->setCfg('collect', true);
        $this->debug->groupSummary();

        $this->debug->group('environment', $this->debug->meta(array(
            'hideIfEmpty' => true,
            'level' => 'info',
        )));
        $this->logGitInfo();
        $this->logPhpInfo();
        $this->logPhpInfoEr();
        $this->logServerVals();
        $this->debug->groupEnd(); // end environment

        $this->debug->group('session', $this->debug->meta(array(
            'hideIfEmpty' => true,
            'level' => 'info',
        )));
        $this->logSessionSettings();
        $this->logSession();
        $this->debug->groupEnd(); // end session

        $this->debug->groupEnd(); // end groupSummary
        $this->debug->setCfg('collect', $collectWas);
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
     * Check request for probable sessionId cookie or query-param
     *
     * @return string|null
     */
    private function getSessionName()
    {
        $name = $this->debug->getCfg('sessionName', Debug::CONFIG_DEBUG);
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
        if (!$this->debug->getCfg('logEnvInfo.gitInfo', Debug::CONFIG_DEBUG)) {
            return;
        }
        $redirect = \stripos(PHP_OS, 'WIN') !== 0
            ? '2>/dev/null'
            : '2> nul';
        $outputLines = array();
        $returnStatus = 0;
        $matches = array();
        \exec('git branch ' . $redirect, $outputLines, $returnStatus);
        if ($returnStatus !== 0) {
            return;
        }
        $allLines = \implode("\n", $outputLines);
        \preg_match('#^\* (.+)$#m', $allLines, $matches);
        if (!$matches) {
            return;
        }
        $branch = $matches[1];
        $this->debug->groupSummary(1);
        $this->debug->log(
            '%cgit branch: %c%s',
            'font-weight:bold;',
            'font-size:1.5em; background-color:#DDD; padding:0 .3em;',
            $branch,
            $this->debug->meta('icon', 'fa fa-github fa-lg')
        );
        $this->debug->groupEnd();
    }

    /**
     * Log some PHP info
     *
     * @return void
     */
    private function logPhpInfo()
    {
        if (!$this->debug->getCfg('logEnvInfo.phpInfo', Debug::CONFIG_DEBUG)) {
            return;
        }
        $this->debug->log('PHP Version', PHP_VERSION);
        $this->debug->log('ini location', \php_ini_loaded_file(), $this->debug->meta('detectFiles', true));
        $this->debug->log('memory_limit', $this->debug->utility->getBytes($this->debug->utility->memoryLimit()));
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
        if (!$this->debug->getCfg('logEnvInfo.errorReporting', Debug::CONFIG_DEBUG)) {
            return;
        }
        $msgLines = array();
        $errorReportingRaw = $this->debug->errorHandler->getCfg('errorReporting');
        $errorReporting = $this->debug->errorHandler->errorReporting();
        if (\in_array(\error_reporting(), array(-1, E_ALL | E_STRICT)) === false) {
            $msgLines[] = 'PHP\'s %cerror_reporting%c is set to `%c' . ErrorLevel::toConstantString() . '%c` rather than `%cE_ALL | E_STRICT%c`';
            if ($errorReporting === (E_ALL | E_STRICT)) {
                $msgLines[] = 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)';
            }
        }
        if ($errorReporting !== (E_ALL | E_STRICT)) {
            $errReportingStr = ErrorLevel::toConstantString($errorReporting);
            $msgLine = 'PHPDebugConsole\'s errorHandler is using a errorReporting value of '
                . '`%c' . $errReportingStr . '%c`';
            if ($errorReportingRaw === 'system') {
                $msgLine = 'PHPDebugConsole\'s errorHandler is set to "system" (not all errors will be shown)';
            } elseif ($errorReporting === \error_reporting()) {
                $msgLine = 'PHPDebugConsole\'s errorHandler is also using a errorReporting value of '
                    . '`%c' . $errReportingStr . '%c`';
            }
            $msgLines[] = $msgLine;
        }
        if (!$msgLines) {
            return;
        }
        $msgLines = \implode("\n", $msgLines);
        $args = array($msgLines);
        $styleMono = 'font-family:monospace; opacity:0.8;';
        $styleReset = 'font-family:inherit; white-space:pre-wrap;';
        $cCount = \substr_count($msgLines, '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $args[] = $styleMono;
            $args[] = $styleReset;
        }
        $args[] = $this->debug->meta(array(
            'detectFiles' => false,
            'file' => null,
            'line' => null,
        ));
        \call_user_func_array(array($this->debug, 'warn'), $args);
    }

    /**
     * Log $_SERVER values specified by `logServerKeys` config option
     *
     * @return void
     */
    private function logServerVals()
    {
        if ($this->debug->getCfg('logEnvInfo.serverVals', Debug::CONFIG_DEBUG) === false) {
            return;
        }
        $logServerKeys = $this->debug->getCfg('logServerKeys', Debug::CONFIG_DEBUG);
        $serverParams = $this->debug->request->getServerParams();
        if ($this->debug->request->getMethod() !== 'GET') {
            $logServerKeys[] = 'REQUEST_METHOD';
        }
        if (isset($serverParams['CONTENT_LENGTH'])) {
            $logServerKeys[] = 'CONTENT_LENGTH';
            $logServerKeys[] = 'CONTENT_TYPE';
        }
        if ($this->debug->getCfg('logRequestInfo.headers', Debug::CONFIG_DEBUG) === false) {
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
        if (!$this->debug->getCfg('logEnvInfo.session', Debug::CONFIG_DEBUG)) {
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
        if (!$this->debug->getCfg('logEnvInfo.session', Debug::CONFIG_DEBUG)) {
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
}
