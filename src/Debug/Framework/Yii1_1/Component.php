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

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Framework\Yii1_1\ErrorLogger;
use bdk\Debug\Framework\Yii1_1\LogRoute;
use bdk\Debug\Framework\Yii1_1\PdoCollector;
use bdk\Debug\LogEntry;
use CApplication;
use CApplicationComponent;
use CEmailLogRoute;
use CLogRoute;
use Yii;

/**
 * Yii v1.1 Component
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class Component extends CApplicationComponent
{
    /** @var Debug */
    public $debug;

    /** @var LogRoute */
    public $logRoute;

    /** @var CApplication */
    public $yiiApp;

    /** @var PdoCollector */
    public $pdoCollector;

    /** @var array<string,mixed> */
    private $debugConfig = array(
        'logEnvInfo' => array(
            'session' => false,
        ),
        'logFiles' => array(
            'filesExclude' => [
                '/framework/',
                '/protected/components/system/',
                '/vendor/',
            ],
        ),
        'yii' => array(
            'ignoredErrors' => true,
            'log' => true,
            'pathsIgnoreError' => [
                ':system:',
                // ':webroot:/protected/extensions',
                ':webroot:/protected/components',
            ],
            'pdo' => true,
            'session' => true,
            'user' => true,
        ),
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        $debugRootInstance = Debug::getInstance($this->debugConfig);
        /*
            Debug instance may have already been instantiated
            remove any session info that may have been logged
            (already output to wamp & real-time) routes
        */
        $logEntries = $debugRootInstance->data->get('log');
        $logEntries = \array_filter($logEntries, static function (LogEntry $logEntry) {
            return $logEntry->getChannelName() !== 'Session';
        });
        $debugRootInstance->data->set('log', \array_values($logEntries));

        $this->yiiApp = Yii::app();
        $this->debug = $debugRootInstance->getChannel('Yii');
        $eventSubscribers = new EventSubscribers($this);
        $debugRootInstance->eventManager->addSubscriberInterface($eventSubscribers);
        $debugRootInstance->addPlugin(new ErrorLogger($this));
        $this->pdoCollector = new PdoCollector($this);
    }

    /**
     * Magic setter
     *
     * Allows us to specify config values in the debug component config array
     *
     * @param string $name  property name
     * @param mixed  $value property value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $cfg = $name === 'config'
            ? $value
            : array($name => $value);
        $this->debug->rootInstance->setCfg($cfg, Debug::CONFIG_NO_RETURN);
    }

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        if ($this->isInitialized) {
            // we may have initialized via __set()
            return;
        }
        /*
            Since Yii doesn't use namespaces, we can usually use Debug::log()
        */
        if (\class_exists('Debug') === false) {
            \class_alias('bdk\Debug', 'Debug');
        }

        $this->addDebugProp();
        $this->collectLog();
        $this->pdoCollector->collect();
        $this->logSession();

        parent::init();
    }

    /**
     * Config get wrapper
     *
     * @param string $name    option name
     * @param mixed  $default default vale
     *
     * @return bool
     */
    public function shouldCollect($name, $default = false)
    {
        $val = $this->debug->rootInstance->getCfg('yii.' . $name);
        return $val !== null
            ? $val
            : $default;
    }

    /**
     * Make Yii::app()->debug a thing
     *
     * @return void
     */
    private function addDebugProp()
    {
        $refClass = new \ReflectionClass($this->yiiApp);
        while ($refClass = $refClass->getParentClass()) {
            if (!$refClass->hasProperty('_m')) {
                continue;
            }
            $mProp = $refClass->getProperty('_m');
            $mProp->setAccessible(true);
            $val = $mProp->getValue($this->yiiApp);
            $val['debug'] = $this->debug;
            $mProp->setValue($this->yiiApp, $val);
            break;
        }
    }

    /**
     * Collect Yii log messages
     *
     * @return void
     */
    protected function collectLog()
    {
        if ($this->shouldCollect('log') === false) {
            return;
        }
        $this->logRoute = LogRoute::getInstance();
        if ($this->debug->rootInstance->getCfg('output') === false) {
            return;
        }
        // we're outputting log info -> disable email log route
        $routes = $this->yiiApp->getComponent('log')->getRoutes()->toArray();
        $disabled = 0;
        \array_walk($routes, static function (CLogRoute $route) use (&$disabled) {
            if ($route instanceof CEmailLogRoute) {
                $route->enabled = false;
                $disabled++;
            }
        });
        if ($disabled > 0) {
            $this->debug->groupSummary();
            $this->debug->log('Disabled Yii email log route(s)');
            $this->debug->groupEnd();
        }
    }

    /**
     * Log session information
     *
     * @return void
     */
    protected function logSession()
    {
        if ($this->shouldCollect('session') === false) {
            return;
        }

        $session = $this->yiiApp->getComponent('session');

        if ($session === null) {
            return;
        }

        $debug = $this->debug->rootInstance->getChannel('Session', array(
            'channelIcon' => ':session:',
            'nested' => false,
        ));

        $debug->log('session id', $session->sessionID);
        $debug->log('session name', $session->sessionName);
        $debug->log('session class', $debug->abstracter->crateWithVals(
            \get_class($session),
            array(
                'type' => Type::TYPE_IDENTIFIER,
                'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
            )
        ));

        $sessionVals = $session->toArray() ?: array();
        \ksort($sessionVals);
        $debug->log($sessionVals);
    }
}
