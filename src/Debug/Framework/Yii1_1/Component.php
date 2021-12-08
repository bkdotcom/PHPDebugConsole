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

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
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
    private $debugConfig = array(
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
        $logEntries = \array_filter($logEntries, function (LogEntry $logEntry) {
            return $logEntry->getChannelName() !== 'Session';
        });
        $debugRootInstance->data->set('log', \array_values($logEntries));

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
        $this->pdoCollect();
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
            $this->pdoCollect($event->getSubject());
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
        // embolden the primary files
        \array_walk_recursive($logEntry['args'][0]['value'], array($this, 'stylizeFileAbstraction'));
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
        if ($this->isIgnorableError($error)) {
            $error->stopPropagation();          // don't log it now
            $error['isSuppressed'] = true;
            $this->ignoredErrors[] = $error['hash'];
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
            $this->republishShutdown();
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
     * Test if error is a minor internal framework error
     *
     * @param Error $error Error instance
     *
     * @return bool
     */
    private function isIgnorableError(Error $error)
    {
        $ignorableCats = array(Error::CAT_DEPRECATED, Error::CAT_NOTICE, Error::CAT_STRICT);
        if (\in_array($error['category'], $ignorableCats) === false) {
            return false;
        }
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
                return true;
            }
        }
        return false;
    }

    /**
     * Log auth & access manager info
     *
     * @return void
     */
    private function logAuthClass()
    {
        $debug = $this->debug->rootInstance->getChannel('User');
        try {
            if (!($this->yiiApp instanceof CWebApplication)) {
                return;
            }
            $authManager = $this->yiiApp->getAuthManager();
            $debug->log('authManager class', $debug->abstracter->crateWithVals(
                \get_class($authManager),
                array(
                    'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
                )
            ));

            $accessManager = $this->yiiApp->getComponent('accessManager');
            if ($accessManager) {
                $debug->log('accessManager class', $debug->abstracter->crateWithVals(
                    \get_class($accessManager),
                    array(
                        'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
                    )
                ));
            }
        } catch (\Exception $e) {
            $debug->error('Exception logging user info');
        }
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
                'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
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
        $this->logAuthClass();
    }

    /**
     * Setup up PDO collector
     * Log to PDO channel
     *
     * @param CDbConnection $dbConnection CDbConnection instance
     *
     * @return void
     */
    protected function pdoCollect(CDbConnection $dbConnection = null)
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
        $pdoChannel = $this->pdoGetChannel($dbConnection);
        $pdoCollector = new Pdo($pdo, $pdoChannel);
        $this->pdoAttachCollector($dbConnection, $pdoCollector);
    }

    /**
     * Get PDO Debug Channel for given db connection
     *
     * @param CDbConnection $dbConnection CDbConnection instance
     *
     * @return Debug
     */
    private function pdoGetChannel(CDbConnection $dbConnection)
    {
        $channelName = 'PDO';
        if (\strpos($dbConnection->connectionString, 'master=true')) {
            $channelName .= ' (master)';
        } elseif (\strpos($dbConnection->connectionString, 'slave=true')) {
            $channelName .= ' (slave)';
        }
        // nest the PDO channel under our Yii channel
        return $this->debug->getChannel($channelName, array(
            'channelIcon' => 'fa fa-database',
            'channelShow' => false,
        ));
    }

    /**
     * Attache PDO Collector to db connection
     *
     * @param CDbConnection $dbConnection CDbConnection instance
     * @param Pdo           $pdoCollector PDO collector instance
     *
     * @return void
     */
    private function pdoAttachCollector(CDbConnection $dbConnection, Pdo $pdoCollector)
    {
        $dbRefObj = new ReflectionObject($dbConnection);
        while (!$dbRefObj->hasProperty('_pdo')) {
            $dbRefObj = $dbRefObj->getParentClass();
            if ($dbRefObj === false) {
                $this->debug->warn('unable to initiate PDO collector');
            }
        }
        $pdoPropObj = $dbRefObj->getProperty('_pdo');
        $pdoPropObj->setAccessible(true);
        $pdoPropObj->setValue($dbConnection, $pdoCollector);
    }

    /**
     * Ensure all shutdown handlers are called
     * Yii's error handler exits (for reasons)
     * Exit within shutdown procedure (fatal error handler) = immediate exit
     * Remedy
     *  * unsubscribe the callables that have already been called
     *  * re-publish the shutdown event
     *  * finally: calling yii's error handler
     *
     * @return void
     */
    private function republishShutdown()
    {
        $eventManager = $this->debug->rootInstance->eventManager;
        foreach ($eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN) as $callable) {
            $eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $callable);
            if (\is_array($callable) && $callable[0] === $this->debug->rootInstance->errorHandler) {
                break;
            }
        }
        $eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
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

    /**
     * Add style attrib to controller and view files
     *
     * @param Abstraction $abs Abstraction instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function stylizeFileAbstraction(Abstraction $abs)
    {
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
    }
}
