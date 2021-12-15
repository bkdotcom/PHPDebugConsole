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

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log PHP info, Session, & included files
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
            Debug::EVENT_OUTPUT => array('logFiles', PHP_INT_MAX),
        );
    }

    /**
     * Log files required during request
     *
     * @return void
     */
    public function logFiles()
    {
        if (!$this->debug->getCfg('logEnvInfo.files', Debug::CONFIG_DEBUG)) {
            return;
        }
        $this->debug->logFiles->output();
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

        $this->logGitInfo();
        $this->logPhp();
        $this->logSession();

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
        $setting = $this->assertSettingPrep($setting);
        $assert = $setting['operator'] === '=='
            ? $setting['valActual'] === $setting['valCompare']
            : $setting['valActual'] !== $setting['valCompare'];
        $params = array(
            $assert,
            '%c' . $setting['name'] . '%c ' . $setting['msg'],
        );
        $cCount = \substr_count($params[1], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace;';
            $params[] = '';
        }
        \call_user_func_array(array($this->debug, 'assert'), $params);
    }

    /**
     * Merge default values
     *
     * @param array $setting setting name, "type", comparison value, operator
     *
     * @return array
     */
    private function assertSettingPrep($setting)
    {
        $setting = \array_merge(array(
            'filter' => FILTER_VALIDATE_BOOLEAN,
            'msg' => '',
            'name' => '',
            'operator' => '==',
            'valActual' => '__use_ini_val__',
            'valCompare' => true,
        ), $setting);
        if ($setting['valActual'] === '__use_ini_val__') {
            $setting['valActual'] = \filter_var(\ini_get($setting['name']), $setting['filter']);
        }
        $valFriendly = $setting['filter'] === FILTER_VALIDATE_BOOLEAN
            ? ($setting['valCompare'] ? 'enabled' : 'disabled')
            : $setting['valCompare'];
        $msgDefault = $setting['operator'] === '=='
            ? 'should be ' . $valFriendly
            : 'should not be ' . $valFriendly;
        $setting['msg'] = $setting['msg'] ?: $msgDefault;
        return $setting;
    }

    /**
     * Log if common extensions are not loaded
     *
     * @return void
     */
    private function checkCommonExtensions()
    {
        $extensionsCheck = array('curl','mbstring');
        foreach ($extensionsCheck as $extension) {
            if (\extension_loaded($extension) === true) {
                continue;
            }
            $this->debug->warn(
                $extension . ' extension is not loaded',
                $this->debug->meta(array(
                    'detectFiles' => false,
                    'file' => null,
                    'line' => null,
                ))
            );
        }
    }

    /**
     * Check request for probable sessionId cookie or query-param
     *
     * @return string|null
     */
    private function getPassedSessionName()
    {
        $name = $this->debug->getCfg('sessionName', Debug::CONFIG_DEBUG);
        $names = $name
            ? array($name)
            : array('PHPSESSID', 'SESSIONID', 'SESSION_ID', 'SESSID', 'SESS_ID');
        $namesFound = array();
        $useCookies = \filter_var(\ini_get('session.use_cookies'), FILTER_VALIDATE_BOOLEAN);
        if ($useCookies) {
            $cookies = $this->debug->request->getCookieParams();
            $keys = \array_keys($cookies);
            $namesFound = \array_intersect($names, $keys);
        }
        if ($namesFound) {
            return \array_shift($namesFound);
        }
        $useOnlyCookies = \filter_var(\ini_get('session.use_only_cookies'), FILTER_VALIDATE_BOOLEAN);
        if ($useOnlyCookies === false) {
            $queryParams = $this->debug->request->getQueryParams();
            $keys = \array_keys($queryParams);
            $namesFound = \array_intersect($names, $keys);
        }
        if ($namesFound) {
            return \array_shift($namesFound);
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
        $branch = $this->debug->utility->gitBranch();
        if (!$branch) {
            return;
        }
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
     * Log PHP info, error reporting, server vals
     *
     * @return void
     */
    private function logPhp()
    {
        $debugWas = $this->debug;
        $channelOpts = array(
            'channelIcon' => '<i class="fa" style="position:relative; top:2px; font-size:15px;">🐘</i>',
            'channelSort' => 10,
            'nested' => false,
        );
        $this->debug = $this->debug->rootInstance->getChannel('php', $channelOpts);
        $this->logPhpInfo();
        $this->logPhpEr();
        $this->logServerVals();
        $this->debug = $debugWas;
    }

    /**
     * Log Error Reporting settings if
     * PHP's error reporting !== (E_ALL | E_STRICT)
     * PHPDebugConsole is not logging (E_ALL | E_STRICT)
     *
     * @return void
     */
    private function logPhpEr()
    {
        if (!$this->debug->getCfg('logEnvInfo.errorReporting', Debug::CONFIG_DEBUG)) {
            return;
        }
        $this->logPhpErPhp();
        $this->logPhpErDebug();
    }

    /**
     * Log Debug's errorReporting setting
     *
     * @return void
     */
    private function logPhpErDebug()
    {
        $msgLines = array();
        $eAll = E_ALL | E_STRICT;
        $errorReporting = $this->debug->errorHandler->errorReporting();
        $errorReportingRaw = $this->debug->errorHandler->getCfg('errorReporting');
        if ($errorReporting !== $eAll) {
            $errReportingStr = $this->debug->errorLevel->toConstantString($errorReporting);
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
        $msg = \implode("\n", $msgLines);
        if ($msg) {
            $this->logWithSubstitution($msg);
        }
    }

    /**
     * Log PHP's error_reporting setting
     *
     * @return void
     */
    private function logPhpErPhp()
    {
        $msgLines = array();
        $eAll = E_ALL | E_STRICT;
        if (\in_array(\error_reporting(), array(-1, $eAll)) === false) {
            $errorReporting = $this->debug->errorHandler->errorReporting();
            $msgLines[] = 'PHP\'s %cerror_reporting%c is set to `%c' . $this->debug->errorLevel->toConstantString() . '%c` rather than `%cE_ALL | E_STRICT%c`';
            if ($errorReporting === $eAll) {
                $msgLines[] = 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)';
            }
        }
        $msg = \implode("\n", $msgLines);
        if ($msg) {
            $this->logWithSubstitution($msg);
        }
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
        $this->logPhpIni();
        $this->logTimezone();
        $this->logPhpMemoryLimit();
        $this->assertSetting(array(
            'name' => 'expose_php',
            'valCompare' => false,
        ));
        $this->checkCommonExtensions();
        $this->logXdebug();
    }

    /**
     * Log ini file location + the ini files returned by `php_ini_scanned_files()`
     *
     * @return void
     */
    private function logPhpIni()
    {
        $iniFiles = \array_merge(
            array(\php_ini_loaded_file()),
            \preg_split('#\s*[,\r\n]+\s*#', \trim(\php_ini_scanned_files()))
        );
        if (\count($iniFiles) === 1) {
            $this->debug->log('ini location', $iniFiles[0], $this->debug->meta('detectFiles'));
            return;
        }
        $this->debug->log(
            'ini files',
            $this->debug->abstracter->crateWithVals(
                $iniFiles,
                array(
                    'options' => array(
                        'showListKeys' => false,
                        // 'expand' => true,
                    ),
                )
            ),
            $this->debug->meta('detectFiles')
        );
    }

    /**
     * Log Php's memory_limit setting
     *
     * @return void
     */
    private function logPhpMemoryLimit()
    {
        $memoryLimit = $this->debug->utility->memoryLimit();
        $memoryLimit === '-1'
            // overkill, but lets use assertSetting, which applies some styling
            ? $this->assertSetting(array(
                'name' => 'memory_limit',
                'valActual' => '-1',
                'valCompare' => '-1',
                'operator' => '!=',
                'msg' => 'should not be -1 (no limit)',
            ))
            : $this->debug->log('memory_limit', $this->debug->utility->getBytes($memoryLimit));
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
        /** @var string[] make psalm happy */
        $logServerKeys = \array_unique($logServerKeys);
        if (empty($logServerKeys)) {
            return;
        }
        $vals = \array_fill_keys($logServerKeys, Abstracter::UNDEFINED);
        $serverParams = $this->debug->request->getServerParams();
        $serverParams = \array_intersect_key($serverParams, $vals);
        $vals = \array_merge($vals, $serverParams);
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
        if ($this->debug->isCli()) {
            return;
        }

        $namePassed = $this->getPassedSessionName();

        $debugWas = $this->debug;
        $this->debug = $this->debug->rootInstance->getChannel('Session', array(
            'channelIcon' => 'fa fa-suitcase',
            'nested' => false,
        ));
        $this->logSessionSettings($namePassed);
        $this->logSessionVals($namePassed);
        $this->debug = $debugWas;
    }

    /**
     * Asserts recommended session ini settings
     *
     * @param string|null $namePassed detected session name
     *
     * @return void
     */
    private function logSessionSettings($namePassed)
    {
        $settings = array(
            array('name' => 'session.cookie_httponly'),
            array('name' => 'session.cookie_lifetime',
                'filter' => FILTER_VALIDATE_INT,
                'valCompare' => 0,
            ),
            array('name' => 'session.name',
                'filter' => FILTER_DEFAULT,
                'valActual' => $namePassed ?: \ini_get('session.name'),
                'valCompare' => 'PHPSESSID',
                'operator' => '!=',
                'msg' => 'should not be PHPSESSID (just as %cexpose_php%c should be disabled)',
            ),
            array('name' => 'session.use_only_cookies'),
            array('name' => 'session.use_strict_mode'),
            array('name' => 'session.use_trans_sid',
                'valCompare' => false
            ),
        );
        \array_walk($settings, array($this, 'assertSetting'));
        $this->debug->log('session.cache_limiter', \ini_get('session.cache_limiter'));
        if (\session_module_name() === 'files') {
            // aka session.save_handler
            $this->debug->log('session save_path', \session_save_path() ?: \sys_get_temp_dir());
        }
    }

    /**
     * Log session name, id, and session values
     *
     * @param string $sessionNamePassed Name of session name passed in request
     *
     * @return void
     */
    private function logSessionVals($sessionNamePassed)
    {
        $namePrev = null;
        if (\session_status() !== PHP_SESSION_ACTIVE) {
            if ($sessionNamePassed === null) {
                $this->debug->log('Session Inactive / No session id passed in request');
                return;
            }
            $namePrev = \session_name($sessionNamePassed);
            \session_start();
        }
        if (\session_status() === PHP_SESSION_ACTIVE) {
            $this->debug->log('session name', \session_name());
            $this->debug->log('session id', \session_id());
            $sessionVals = $_SESSION;
            \ksort($sessionVals);
            $this->debug->log('$_SESSION', $sessionVals, $this->debug->meta('redact'));
        }
        if ($namePrev) {
            /*
                PHPDebugConsole started session... close it.
                "<jedi>we were never here</jedi>"

                Note:  There is a side-effect.
                session_id() will  continue to return the id
            */
            \session_abort();
            \session_name($namePrev);
            unset($_SESSION);
        }
    }

    /**
     * Log date.timezone setting (if set)
     * otherwize log date_default_timezone_get()
     *
     * @return void
     */
    private function logTimezone()
    {
        $dateTimezone = \ini_get('date.timezone');
        if ($dateTimezone) {
            $this->debug->log('date.timezone', $dateTimezone);
            return;
        }
        $this->debug->assert(false, '%cdate.timezone%c is not set', 'font-family:monospace;', '');
        $this->debug->log('date_default_timezone_get()', \date_default_timezone_get());
    }

    /**
     * Log the message with style substitution
     *
     * @param string $msg Message with "%c wordsToMonospace %c"
     *
     * @return void
     */
    private function logWithSubstitution($msg)
    {
        $args = array($msg);
        $styleMono = 'font-family:monospace; opacity:0.8;';
        $styleReset = 'font-family:inherit; white-space:pre-wrap;';
        $cCount = \substr_count($msg, '%c');
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
     * Log whether xdebug is installed & version
     *
     * @return void
     */
    private function logXdebug()
    {
        $haveXdebug = \extension_loaded('xdebug');
        if (!$haveXdebug) {
            $this->debug->log('Xdebug is not installed');
            return;
        }
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '3.0.0', '>=')) {
            $mode = \ini_get('xdebug.mode') ?: 'off';
            $this->debug->log('Xdebug v' . $xdebugVer . ' is installed (mode: ' . $mode . ')');
            return;
        }
        $this->debug->log('Xdebug v' . $xdebugVer . ' is installed');
    }
}
