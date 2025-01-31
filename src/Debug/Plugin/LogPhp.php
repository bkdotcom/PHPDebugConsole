<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Plugin\AssertSettingTrait;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log PHP info, Error Reporting and $_SERVER vals
 */
class LogPhp implements SubscriberInterface
{
    use AssertSettingTrait;

    /** @var array<string,mixed> */
    protected $cfg = array(
        'channelOpts' => array(
            'channelIcon' => ':php:',
            'channelSort' => 10,
            'nested' => false,
        ),
    );

    /** @var Debug|null */
    private $debug;
    /** @var array<string,mixed> */
    private $iniValues = array();
    /** @var array<string,mixed> */
    private $detectFilesFalseMeta = array(
        'detectFiles' => false,
        'file' => null,
        'line' => null,
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iniValues = array(
            'dateTimezone' => \ini_get('date.timezone'),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_BOOTSTRAP => 'onBootstrap',
        );
    }

    /**
     * Debug::EVENT_BOOTSTRAP subscriber
     *
     * @param Event $event Debug::EVENT_BOOTSTRAP Event instance
     *
     * @return void
     */
    public function onBootstrap(Event $event)
    {
        $this->debug = $event->getSubject()->getChannel('php', $this->cfg['channelOpts']);
        $collectWas = $this->debug->setCfg('collect', true);
        $this->logPhpInfo();
        $this->logPhpEr();
        $this->logServerVals();
        $this->debug->setCfg('collect', $collectWas, Debug::CONFIG_NO_RETURN);
    }

    /**
     * Log some PHP info
     *
     * @return void
     */
    protected function logPhpInfo()
    {
        if (!$this->debug->getCfg('logEnvInfo.phpInfo', Debug::CONFIG_DEBUG)) {
            return;
        }
        $this->logPhpVersion();
        $this->logPhpIni();
        $this->logTimezone();
        $this->logPhpMemoryLimit();
        $this->assertSetting('expose_php', false);
        $this->assertSetting('default_charset', '', array(
            'operator' => '!=',
        ));
        $this->assertExtensions();
        $this->assertMbStringSettings();
        $this->logXdebug();
    }

    /**
     * Log Error Reporting settings
     *
     * Log if PHP's error reporting !== E_ALL
     * Or if PHPDebugConsole is not logging E_ALL
     *
     * @return void
     */
    protected function logPhpEr()
    {
        if (!$this->debug->getCfg('logEnvInfo.errorReporting', Debug::CONFIG_DEBUG)) {
            return;
        }
        $this->logPhpErPhp();
        $this->logPhpErDebug();
    }

    /**
     * Log php version
     *
     * @return void
     */
    protected function logPhpVersion()
    {
        $buildDate = $this->debug->php->buildDate();
        $this->debug->log('PHP Version', PHP_VERSION);
        $this->debug->log('Server API', PHP_SAPI);
        if ($buildDate) {
            $ts = \strtotime($buildDate);
            $buildDateTime = \date('Y-m-d H:i:s T', $ts);
            $this->debug->log('Build Date', $buildDateTime);
        }
        $this->debug->log('Thread Safe', PHP_ZTS ? 'yes' : 'no');
    }

    /**
     * Log $_SERVER values specified by `logServerKeys` config option
     *
     * @return void
     */
    protected function logServerVals()
    {
        if ($this->debug->getCfg('logEnvInfo.serverVals', Debug::CONFIG_DEBUG) === false) {
            return;
        }
        $logServerKeys = $this->debug->getCfg('logServerKeys', Debug::CONFIG_DEBUG);
        $serverParams = $this->debug->serverRequest->getServerParams();
        if ($this->debug->serverRequest->getMethod() !== 'GET') {
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
        $serverParams = \array_intersect_key($serverParams, $vals);
        $vals = \array_merge($vals, $serverParams);
        \ksort($vals, SORT_NATURAL);
        $this->debug->log('$_SERVER', $vals, $this->debug->meta('redact'));
    }

    /**
     * Log if common extensions are not loaded
     *
     * @return void
     */
    private function assertExtensions()
    {
        $extensionsCheck = $this->debug->getCfg('extensionsCheck', Debug::CONFIG_DEBUG);
        foreach ($extensionsCheck as $extension) {
            if (\extension_loaded($extension) === true) {
                continue;
            }
            $this->debug->warn(
                $extension . ' extension is not loaded',
                $this->debug->meta($this->detectFilesFalseMeta)
            );
        }
    }

    /**
     * Assert MbString settings
     *
     * @return void
     */
    private function assertMbStringSettings()
    {
        if (PHP_VERSION_ID < 50600) {
            // default_charset should be used for php >= 5.6
            $this->assertSetting('mb_string.internal_encoding', '', array(
                'operator' => '!=',
            ));
        }
        $this->assertSetting('mbstring.func_overload', false, array(
            'msg' => 'Multi-byte string function overloading is enabled (is evil)',
        ));
    }

    /**
     * Log Debug's errorReporting setting
     *
     * @return void
     */
    private function logPhpErDebug()
    {
        $msgLines = array();
        $errorReporting = $this->debug->errorHandler->errorReporting();
        $errorReportingRaw = $this->debug->errorHandler->getCfg('errorReporting');
        if ($errorReporting !== E_ALL) {
            $errReportingStr = $this->debug->errorLevel->toConstantString($errorReporting);
            $msgLine = 'PHPDebugConsole\'s errorHandler is using a errorReporting value of `%c' . $errReportingStr . '%c`';
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
        $preferred = PHP_VERSION_ID >= 80000
            ? 'E_ALL'
            : 'E_ALL | E_STRICT';
        if (\in_array(\error_reporting(), [-1, E_ALL], true) === false) {
            $errorReporting = $this->debug->errorHandler->errorReporting();
            $msgLines[] = 'PHP\'s %cerror_reporting%c is set to '
                . '`%c' . $this->debug->errorLevel->toConstantString() . '%c` '
                . 'rather than `%c' . $preferred . '%c`';
            if ($errorReporting === E_ALL) {
                $msgLines[] = 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)';
            }
        }
        $msg = \implode("\n", $msgLines);
        if ($msg) {
            $this->logWithSubstitution($msg);
        }
    }

    /**
     * Log ini file location + the ini files returned by `php_ini_scanned_files()`
     *
     * @return void
     */
    private function logPhpIni()
    {
        $iniFiles = $this->debug->php->getIniFiles();
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
        $memoryLimit = $this->debug->php->memoryLimit();
        $memoryLimit === '-1'
            // overkill, but lets use assertSetting, which applies some styling
            ? $this->assertSetting(array(
                'msg' => 'should not be -1 (no limit)',
                'name' => 'memory_limit',
                'operator' => '!=',
                'valActual' => '-1',
                'valCompare' => '-1',
            ))
            : $this->debug->log('memory_limit', $this->debug->utility->getBytes($memoryLimit));
    }

    /**
     * Log date.timezone setting (if set)
     * otherwise log date_default_timezone_get()
     *
     * @return void
     */
    private function logTimezone()
    {
        $dateTimezone = $this->iniValues['dateTimezone'];
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
        $args = [$msg];
        $styleMono = 'font-family:monospace; opacity:0.8;';
        $styleReset = 'font-family:inherit; white-space:pre-wrap;';
        $cCount = \substr_count($msg, '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $args[] = $styleMono;
            $args[] = $styleReset;
        }
        $args[] = $this->debug->meta($this->detectFilesFalseMeta);
        \call_user_func_array([$this->debug, 'warn'], $args);
    }

    /**
     * Log whether xdebug is installed & version
     *
     * @return void
     */
    private function logXdebug()
    {
        $xdebugVer = \phpversion('xdebug');
        $msg = $xdebugVer
            ? 'Xdebug v' . $xdebugVer . ' is installed'
            : 'Xdebug is not installed';
        if (\version_compare($xdebugVer, '3.0.0', '>=')) {
            $msg .= ' (mode: ' . (\ini_get('xdebug.mode') ?: 'off') . ')';
        }
        $this->debug->log($msg);
    }
}
