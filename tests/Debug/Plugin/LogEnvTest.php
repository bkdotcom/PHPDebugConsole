<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Plugin\LogEnv;
use bdk\HttpMessage\ServerRequest;
use bdk\PubSub\Event;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for LogEnv plugin
 *
 * @covers \bdk\Debug\Plugin\LogEnv
 */
class LogEnvTest extends DebugTestFramework
{
    public function testDoNotLogSession()
    {
        $debug = new Debug(array(
            'logEnvInfo' => array(),
            'logRequestInfo' => array(),
            'serviceProvider' => array(
                'serverRequest' => (new ServerRequest(
                    'GET',
                    null,
                    array()
                ))
                    ->withCookieParams(array(
                        'SESSION_ID' => 'abcdefghijklmnopqrstuvwxyz',
                    )),
            ),
        ));
        // Debug::varDump('noIdPassed', $this->helper->deObjectifyData($debug->data->get('log')));
        $debug->obEnd();
        self::assertEmpty($debug->data->get('log'));
    }

    public function testLogSessionIsCli()
    {
        $debug = new Debug(array(
            'logEnvInfo' => array('session'),
            'logRequestInfo' => array(),
            'serviceProvider' => array(
                'serverRequest' => (new ServerRequest(
                    'GET',
                    null,
                    array(
                        'argv' => array('foo', 'bar'),
                        'path' => 'foo; bar',
                    )
                )),
            ),
        ));
        // Debug::varDump('isCli', $this->helper->deObjectifyData($debug->data->get('log')));
        self::assertEmpty($this->helper->deObjectifyData($debug->data->get('log')));
    }

    public function testLogSessionNoIdPassed()
    {
        $debug = new Debug(array(
            'logEnvInfo' => array('session'),
            'logRequestInfo' => array(),
            'serviceProvider' => array(
                'serverRequest' => (new ServerRequest(
                    'GET',
                    null,
                    array()
                )),
            ),
        ));
        // Debug::varDump('noIdPassed', $this->helper->deObjectifyData($debug->data->get('log')));
        $debug->obEnd();
        self::assertSame('Session Inactive / No session id passed in request', $debug->data->get('log/__end__/args/0'));
    }

    public function testSpecifySessionName()
    {
        $_SESSION = array(
            'username' => 'bkdotcom',
            'role' => 'developer',
        );
        $debug = new Debug(array(
            'logEnvInfo' => array('session'),
            'logRequestInfo' => array(),
            'serviceProvider' => array(
                'serverRequest' => (new ServerRequest(
                    'GET',
                    null,
                    array()
                ))
                    ->withCookieParams(array(
                        'custSessionName' => 'zyxwvutsrqponmlkjihgfedcba',
                    )),
            ),
            'sessionName' => 'custSessionName',
        ));
        $debug->obEnd();
        self::assertLogEntries(array(
            array(
                'method' => 'log',
                'args' => array(
                    '$_SESSION',
                    array(
                        'role' => 'developer',
                        'username' => 'bkdotcom',
                    ),
                ),
                'meta' => array(
                    'channel' => 'Session',
                    'redact' => true,
                ),
            ),
        ), array($this->helper->deObjectifyData($debug->data->get('log/__end__'))));
    }

    public function testLogSession()
    {
        $_SESSION = array(
            'username' => 'bkdotcom',
            'role' => 'developer',
        );
        $debug = new Debug(array(
            'logEnvInfo' => array('session'),
            'logRequestInfo' => array(),
            'serviceProvider' => array(
                'serverRequest' => (new ServerRequest(
                    'GET',
                    null,
                    array()
                ))
                    ->withCookieParams(array(
                        'SESSION_ID' => 'abcdefghijklmnopqrstuvwxyz',
                    )),
            ),
        ));
        // Debug::varDump('log session', $this->helper->deObjectifyData($debug->data->get('log')));
        self::assertLogEntries(array(
            array(
                'method' => 'log',
                'args' => array(
                    '$_SESSION',
                    array(
                        'role' => 'developer',
                        'username' => 'bkdotcom',
                    ),
                ),
                'meta' => array(
                    'channel' => 'Session',
                    'redact' => true,
                ),
            ),
        ), array($this->helper->deObjectifyData($debug->data->get('log/__end__'))));

        $GLOBALS['sessionMock']['status'] = PHP_SESSION_NONE;
        $logEnv = new LogEnv();
        \bdk\Debug\Utility\Reflection::propSet($logEnv, 'iniValues', array(
            'sessionUseCookies' => true,
            'sessionUseOnlyCookies' => false,
        ));
        $debug->data->set('log', array());
        $debug->setCfg('serviceProvider', array(
            'serverRequest' => (new ServerRequest(
                'GET',
                null,
                array()
            ))
                ->withQueryParams(array(
                    'SESSION_ID' => 'randomSessionId',
                )),
        ));
        $logEnv->onBootstrap(new Event($debug));
        // Debug::varDump('sessionId in query', $this->helper->deObjectifyData($debug->data->get('log')));
        $debug->obEnd();
        self::assertLogEntries(array(
            array(
                'method' => 'log',
                'args' => array(
                    '$_SESSION',
                    array(
                        'role' => 'developer',
                        'username' => 'bkdotcom',
                    ),
                ),
                'meta' => array(
                    'channel' => 'Session',
                    'redact' => true,
                ),
            ),
        ), array($this->helper->deObjectifyData($debug->data->get('log/__end__'))));
    }

    public function testDoNotLogGitInfo()
    {
        $this->debug->setCfg(array(
            'logEnvInfo' => array(),
            // 'logRequestInfo' => array(),
        ));
        $logEnv = new LogEnv();
        $logEnv->onBootstrap(new Event($this->debug));
        self::assertEmpty($this->debug->data->get('log'));
    }

    public function testLogGitInfoNone()
    {
        $this->debug->setCfg(array(
            'logEnvInfo' => array('gitInfo'),
            // 'logRequestInfo' => array(),
        ));
        \bdk\Test\Debug\Mock\Utility::$gitBranch = '';
        $logEnv = new LogEnv();
        $logEnv->onBootstrap(new Event($this->debug));
        self::assertEmpty($this->debug->data->get('log'));
    }

    public function testLogGitInfo()
    {
        $this->debug->setCfg(array(
            'logEnvInfo' => array('gitInfo'),
        ));
        \bdk\Test\Debug\Mock\Utility::$gitBranch = 'UnitTest';
        $logEnv = new LogEnv();
        $logEnv->onBootstrap(new Event($this->debug));
        self::assertLogEntries(array(
            array(
                'method' => 'log',
                'args' => array(
                    '%%cgit branch: %%c%%s',
                    'font-weight:bold;',
                    'font-size:1.5em; background-color:#DDD; padding:0 .3em;',
                    'UnitTest',
                ),
                'meta' => array(
                    'icon' => 'fa fa-github fa-lg',
                ),
            ),
        ), $this->debug->data->get('logSummary/1'));
    }
}
