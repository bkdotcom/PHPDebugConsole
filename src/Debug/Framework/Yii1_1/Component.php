<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Collector\Pdo;
use bdk\Debug\Framework\Yii1_1\ErrorLogger;
use bdk\Debug\Framework\Yii1_1\LogRoute;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
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
        $logEntries = \array_filter($logEntries, static function (LogEntry $logEntry) {
            return $logEntry->getChannelName() !== 'Session';
        });
        $debugRootInstance->data->set('log', \array_values($logEntries));

        $this->yiiApp = Yii::app();
        $this->debug = $debugRootInstance->getChannel('Yii');
        $debugRootInstance->eventManager->addSubscriberInterface($this);
        $debugRootInstance->addPlugin(new ErrorLogger($this));
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
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
            Debug::EVENT_OBJ_ABSTRACT_START => 'onDebugObjAbstractStart',
            Debug::EVENT_OBJ_ABSTRACT_END => 'onDebugObjAbstractEnd',
            Debug::EVENT_OUTPUT => array('onDebugOutput', 1),
            Debug::EVENT_OUTPUT_LOG_ENTRY => 'onDebugOutputLogEntry',
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
        if (\class_exists('Debug') === false) {
            \class_alias('bdk\Debug', 'Debug');
        }

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
    public function onCustomMethod(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $arg0 = isset($args[0]) ? $args[0] : true;
        switch ($method) {
            case 'yiiRouteEnable':
                $this->logRoute = $this->logRoute ?: LogRoute::getInstance();
                $this->logRoute->enabled = $arg0;
                $logEntry->stopPropagation();
                $logEntry['handled'] = true;
                break;
            case 'logPdo':
                $debug->getChannel('PDO')->setCfg('collect', $arg0);
                $logEntry->stopPropagation();
                $logEntry['handled'] = true;
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
                '_models' => \array_map(static function ($val) {
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
