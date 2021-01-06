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

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Collector\Pdo;
use bdk\Debug\Framework\Yii1_1\LogRoute;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;
use CActiveRecord;
use CApplicationComponent;
use CDbCommand;
use CDbConnection;
use CWebApplication;
use ReflectionObject;
use Yii;

/**
 * Yii v1.1 Component
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class Component extends CApplicationComponent implements SubscriberInterface
{

    public $debug;
    public $logRoute;
    public $yiiApp;

    protected $ignoredErrors = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $debugRootInstance = Debug::getInstance(array(
            'logEnvInfo' => array(
                'session' => false,
            ),
            'logFiles' => array(
                'filesExclude' => array(
                    '/framework/',
                    '/protected/components/system/',
                    '/vendor/',
                ),
            ),
            'yii' => array(
                'ignoredErrors' => true,
                'log' => true,
                'pdo' => true,
                'session' => true,
                'user' => true,
            ),
        ));
        $debugRootInstance->eventManager->addSubscriberInterface($this);
        /*
            Debug error handler may have been registered first -> reregister
        */
        $debugRootInstance->errorHandler->unregister();
        $debugRootInstance->errorHandler->register();
        $this->debug = $debugRootInstance->getChannel('Yii');
        $this->yiiApp = Yii::app();
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
        $this->debug->rootInstance->setCfg($cfg);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_LOG => 'onDebugLog',
            Debug::EVENT_OBJ_ABSTRACT_START => 'onDebugObjAbstractStart',
            Debug::EVENT_OBJ_ABSTRACT_END => 'onDebugObjAbstractEnd',
            Debug::EVENT_OUTPUT => array('onDebugOutput', 1),
            Debug::EVENT_OUTPUT_LOG_ENTRY => 'onDebugOutputLogEntry',
            ErrorHandler::EVENT_ERROR => array(
                array('onErrorLow', -1),
                array('onErrorHigh', 1),
            ),
            'yii.componentInit' => 'onComponentInit',
        );
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
            Since Yii doesn't use namespaces, we can usually use Debug::_log()
        */
        \class_alias('bdk\Debug', 'Debug');

        $this->addDebugProp();
        $this->collectLog();
        $this->collectPdo();
        $this->logSession();

        parent::init();
    }

    /**
     * Handle our custom yii event
     *
     * Optionally update YiiBase::createComponent to
     * `Debug::getInstance()->eventManager->publish('yii.componentInit', $object, is_array($config) ? $config : array());`
     * Before returning $object
     *
     * We can now tweak component behavior when they're created
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onComponentInit(Event $event)
    {
        if ($event->getSubject() instanceof CDbConnection) {
            $this->collectPdo($event->getSubject());
        }
    }

    /**
     * Handle custom Yii debug calls
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onDebugLog(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        switch ($method) {
            case 'yiiRouteEnable':
                $enable = isset($args[0]) ? $args[0] : true;
                LogRoute::getInstance()->enabled = $enable;
                $logEntry->stopPropagation();
                $logEntry['appendLog'] = false;
                break;
            case 'logPdo':
                $collect = isset($args[0]) ? $args[0] : true;
                $debug->getChannel('PDO')->setCfg('collect', $collect);
                $logEntry->stopPropagation();
                $logEntry['appendLog'] = false;
                break;
        }
    }

    /**
     * Debug::EVENT_OUTPUT subscriber
     *
     * Log included files before outputting
     *
     * @return void
     */
    public function onDebugOutput()
    {
        $this->logIgnoredErrors();
        $this->logUser();
    }

    /**
     * Debug::EVENT_OUTPUT_LOG_ENTRY event subscriber
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onDebugOutputLogEntry(LogEntry $logEntry)
    {
        if ($logEntry['method'] !== 'log') {
            return;
        }
        if ($logEntry->getChannelName() !== 'Files') {
            return;
        }
        if (!$logEntry->getMeta('detectFiles')) {
            return;
        }
        // let's embolden the primary files
        \array_walk_recursive($logEntry['args'][0]['value'], function ($abs) {
            if (!isset($abs['attribs']['data-file'])) {
                return;
            }
            $file = $abs['attribs']['data-file'];
            $isController = \preg_match('#/protected/controllers/.+.php#', $file);
            $isView = \preg_match('#/protected/views(?:(?!/layout).)+.php#', $file);
            $embolden = $isController || $isView;
            if ($embolden) {
                $abs['attribs']['style'] = 'font-weight:bold; color:#88bb11;';
            }
        });
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_START event subscriber
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onDebugObjAbstractStart(Abstraction $abs)
    {
        $obj = $abs->getSubject();
        if ($obj instanceof CActiveRecord) {
            $refObj = new \ReflectionObject($obj);
            while (!$refObj->hasProperty('_models')) {
                $refObj = $refObj->getParentClass();
            }
            $refProp = $refObj->getProperty('_models');
            $refProp->setAccessible(true);
            $abs['propertyOverrideValues'] = array(
                '_models' => \array_map(function ($val) {
                    return \get_class($val) . ' (not inspected)';
                }, $refProp->getValue($obj)),
            );
            \ksort($abs['propertyOverrideValues']['_models']);
        }
    }

    /**
     * Debug::EVENT_OBJ_ABSTRACT_END event subscriber
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     */
    public function onDebugObjAbstractEnd(Abstraction $abs)
    {
        if ($abs->getSubject() instanceof CActiveRecord) {
            $abs['properties']['_attributes']['forceShow'] = true;
        } elseif ($abs->getSubject() instanceof CDbCommand) {
            $abs['properties']['_paramLog']['forceShow'] = true;
            $abs['properties']['_text']['forceShow'] = true;
        }
    }

    /**
     * Intercept minor framework issues and ignore them
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorHigh(Error $error)
    {
        if (\in_array($error['category'], array(Error::CAT_DEPRECATED, Error::CAT_NOTICE, Error::CAT_STRICT))) {
            /*
                "Ignore" minor internal framework errors
            */
            $pathsIgnore = array(
                Yii::getPathOfAlias('system'),
                Yii::getPathOfAlias('webroot') . '/protected/extensions',
                Yii::getPathOfAlias('webroot') . '/protected/components',
            );
            foreach ($pathsIgnore as $pathIgnore) {
                if (\strpos($error['file'], $pathIgnore) === 0) {
                    $error->stopPropagation();          // don't log it now
                    $error['isSuppressed'] = true;
                    $this->ignoredErrors[] = $error['hash'];
                    break;
                }
            }
        }
        if ($error['category'] !== Error::CAT_FATAL) {
            /*
                Don't pass error to Yii's handler... it will exit for #reasons
            */
            $error['continueToPrevHandler'] = false;
        }
    }

    /**
     * ErrorHandler::EVENT_ERROR event subscriber
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorLow(Error $error)
    {
        if (!\class_exists('Yii') || !Yii::app()) {
            return;
        }
        // Yii's handler will log the error.. we can ignore that
        $this->logRoute->enabled = false;
        if ($error['exception']) {
            $this->yiiApp->handleException($error['exception']);
        } elseif ($error['category'] === Error::CAT_FATAL) {
            // Yii's error handler exits (for reasons)
            //    exit within shutdown procedure (that's us) = immediate exit
            //    so... unsubscribe the callables that have already been called and
            //    re-publish the shutdown event before calling yii's error handler
            foreach ($this->debug->rootInstance->eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN) as $callable) {
                $this->debug->rootInstance->eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $callable);
                if (\is_array($callable) && $callable[0] === $this->debug->rootInstance->errorHandler) {
                    break;
                }
            }
            $this->debug->rootInstance->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
            $this->yiiApp->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        $this->logRoute->enabled = true;
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
    }

    /**
     * Setup up PDO collector
     * Log to PDO channel
     *
     * @param CDbConnection $dbConnection CDbConnection instance
     *
     * @return void
     */
    protected function collectPdo(CDbConnection $dbConnection = null)
    {
        if ($this->shouldCollect('pdo') === false) {
            return;
        }

        $dbConnection = $dbConnection ?: $this->yiiApp->db;
        $dbConnection->active = true; // creates pdo obj
        $pdo = $dbConnection->pdoInstance;
        if ($pdo instanceof Pdo) {
            // already wrapped
            return;
        }
        // nest the PDO channel under our Yii channel
        $channelName = 'PDO';
        if (\strpos($dbConnection->connectionString, 'master=true')) {
            $channelName .= ' (master)';
        } elseif (\strpos($dbConnection->connectionString, 'slave=true')) {
            $channelName .= ' (slave)';
        }
        $pdoChannel = $this->debug->getChannel($channelName, array(
            'channelIcon' => 'fa fa-database',
            'channelShow' => false,
        ));
        $pdoCollector = new Pdo($pdo, $pdoChannel);
        $dbRef = new ReflectionObject($dbConnection);
        while (!$dbRef->hasProperty('_pdo')) {
            $dbRef = $dbRef->getParentClass();
            if ($dbRef === false) {
                $this->debug->warn('unable initiate PDO collector');
            }
        }
        $pdoProp = $dbRef->getProperty('_pdo');
        $pdoProp->setAccessible(true);
        $pdoProp->setValue($dbConnection, $pdoCollector);
    }

    /**
     * Log files we ignored
     *
     * @return void
     */
    private function logIgnoredErrors()
    {
        if ($this->shouldCollect('ignoredErrors') === false) {
            return;
        }
        if (!$this->ignoredErrors) {
            return;
        }
        $hashes = \array_unique($this->ignoredErrors);
        $count = \count($hashes);
        $debug = $this->debug;
        $debug->groupSummary();
        $debug->group(
            $count === 1
                ? '1 ignored error'
                : $count . ' ignored errors'
        );
        foreach ($hashes as $hash) {
            $error = $this->debug->errorHandler->get('error', $hash);
            $debug->rootInstance->log($error);
        }
        $debug->groupEnd();
        $debug->groupEnd();
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

        $channelOpts = array(
            'channelIcon' => 'fa fa-suitcase',
            'nested' => false,
        );
        $debug = $this->debug->rootInstance->getChannel('Session', $channelOpts);

        $debug->log('session id', $session->sessionID);
        $debug->log('session name', $session->sessionName);
        $debug->log('session class', $debug->abstracter->crateWithVals(
            \get_class($session),
            array(
                'typeMore' => 'classname',
            )
        ));

        $sessionVals = $session->toArray();
        \ksort($sessionVals);
        $debug->log($sessionVals);
    }

    /**
     * Log current user info
     *
     * @return void
     */
    protected function logUser()
    {
        if ($this->shouldCollect('user') === false) {
            return;
        }

        $user = $this->yiiApp->user;
        if (\method_exists($user, 'getIsGuest') && $user->getIsGuest()) {
            return;
        }

        $channelOpts = array(
            'channelIcon' => 'fa fa-user-o',
            'nested' => false,
        );
        $debug = $this->debug->rootInstance->getChannel('User', $channelOpts);

        $identityData = $user->model->attributes;
        if ($user->model instanceof \CModel) {
            $identityData = array();
            foreach ($user->model->attributes as $key => $val) {
                $key = $user->model->getAttributeLabel($key);
                $identityData[$key] = $val;
            }
        }
        $debug->table(\get_class($user), $identityData);

        try {
            if (!($this->yiiApp instanceof CWebApplication)) {
                return;
            }
            $authManager = $this->yiiApp->getAuthManager();
            $debug->log('authManager class', $debug->abstracter->crateWithVals(
                \get_class($authManager),
                array(
                    'typeMore' => 'classname',
                )
            ));

            $accessManager = $this->yiiApp->getComponent('accessManager');
            if ($accessManager) {
                $debug->log('accessManager class', $debug->abstracter->crateWithVals(
                    \get_class($accessManager),
                    array(
                        'typeMore' => 'classname',
                    )
                ));
            }
        } catch (\Exception $e) {
            $debug->error('Exception logging user info');
        }
    }

    /**
     * Config get wrapper
     *
     * @param string $name    option name
     * @param mixed  $default default vale
     *
     * @return bool
     */
    protected function shouldCollect($name, $default = false)
    {
        $val = $this->debug->rootInstance->getCfg('yii.' . $name);
        return $val !== null
            ? $val
            : $default;
    }
}
