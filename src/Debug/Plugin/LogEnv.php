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
use bdk\Debug\Plugin\AssertSettingTrait;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Log Git info & Session
 */
class LogEnv implements SubscriberInterface
{
    use AssertSettingTrait;

    /** @var Debug|null */
    private $debug;

    /** @var array<string|mixed> */
    private $iniValues = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->iniValues = array(
            'sessionUseCookies' => \ini_get('session.use_cookies'),
            'sessionUseOnlyCookies' => \ini_get('session.use_only_cookies'),
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
        $this->debug = $event->getSubject();

        $collectWas = $this->debug->setCfg('collect', true);

        $this->logGitInfo();
        $this->logSession();

        $this->debug->setCfg('collect', $collectWas, Debug::CONFIG_NO_RETURN);
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
            ? [$name]
            : ['PHPSESSID', 'SESSIONID', 'SESSION_ID', 'SESSID', 'SESS_ID'];
        $namesFound = array();
        $useCookies = \filter_var($this->iniValues['sessionUseCookies'], FILTER_VALIDATE_BOOLEAN);
        if ($useCookies) {
            $cookies = $this->debug->serverRequest->getCookieParams();
            $keys = \array_keys($cookies);
            $namesFound = \array_intersect($names, $keys);
        }
        if ($namesFound) {
            return \array_shift($namesFound);
        }
        $useOnlyCookies = \filter_var($this->iniValues['sessionUseOnlyCookies'], FILTER_VALIDATE_BOOLEAN);
        if ($useOnlyCookies === false) {
            $queryParams = $this->debug->serverRequest->getQueryParams();
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
            'font-size:1.5em; background-color:#CCC; color:#000; padding:0 .3em;',
            $branch,
            $this->debug->meta('icon', ':github:')
        );
        $this->debug->groupEnd();
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
            'channelIcon' => ':session:',
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
            array(
                'name' => 'session.cookie_lifetime',
                'valCompare' => 0,
            ),
            array(
                'msg' => 'should not be PHPSESSID (just as %cexpose_php%c should be disabled)',
                'name' => 'session.name',
                'operator' => '!=',
                'valActual' => $namePassed ?: \ini_get('session.name'),
                'valCompare' => 'PHPSESSID',
            ),
            array('name' => 'session.use_only_cookies'),
            array('name' => 'session.use_strict_mode'),
            array('name' => 'session.use_trans_sid',
                'valCompare' => false,
            ),
        );
        \array_walk($settings, [$this, 'assertSetting']);
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
     *
     * @phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedGlobalFunctions.NonFullyQualified
     */
    private function logSessionVals($sessionNamePassed)
    {
        $namePrev = null;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if ($sessionNamePassed === null) {
                $this->debug->log('Session Inactive / No session id passed in request');
                return;
            }
            $namePrev = session_name($sessionNamePassed);
            session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->debug->log('session name', session_name());
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
            if (PHP_VERSION_ID >= 50600) {
                \session_abort();
            }
            session_name($namePrev);
            unset($_SESSION);
        }
    }
}
