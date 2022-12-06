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

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Collector\Pdo;
use bdk\Debug\Framework\Yii2\LogTarget;
use bdk\Debug\LogEntry;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event as YiiEvent;
use yii\base\Model;
use yii\base\Module as BaseModule;

/**
 * PhpDebugBar Yii 2 Module
 */
class Module extends BaseModule implements SubscriberInterface, BootstrapInterface
{
    /** @var \bdk\Debug */
    public $debug;

    /** @var LogTarget */
    public $logTarget;

    private $collectedEvents = array();

    private $configDefault = array(
        'channels' => array(
            'events' => array(
                'channelIcon' => 'fa fa-bell-o',
                'nested' => false,
            ),
            'PDO' => array(
                'channelIcon' => 'fa fa-database',
                'channelShow' => false,
            ),
            'Session' => array(
                'channelIcon' => 'fa fa-suitcase',
                'nested' => false,
            ),
            'User' => array(
                'channelIcon' => 'fa fa-user-o',
                'nested' => false,
            ),
        ),
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
            'events' => true,
            'log' => true,
            'pdo' => true,
            'session' => true,
            'user' => true,
        ),
    );

    /**
     * Constructor
     *
     * @param string $id     the ID of this module.
     * @param Module $parent the parent module (if any).
     * @param array  $config name-value pairs that will be used to initialize the object properties.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct($id, $parent, $config = array())
    {
        $debugRootInstance = Debug::getInstance($this->configDefault);
        $debugRootInstance->setCfg($config);
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
        $debugRootInstance->eventManager->addSubscriberInterface($this);
        /*
            Debug error handler may have been registered first -> reregister
        */
        $debugRootInstance->errorHandler->unregister();
        $debugRootInstance->errorHandler->register();
        $this->debug = $debugRootInstance->getChannel('Yii');
        parent::__construct($id, $parent, array());
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
    public function bootstrap($app)
    {
        // setAlias needed for Console app
        Yii::setAlias('@' . \str_replace('\\', '/', __NAMESPACE__), __DIR__);
        $this->collectEvent();
        $this->collectLog();
        $this->collectPdo();
        $this->logSession();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OBJ_ABSTRACT_END => 'onDebugObjAbstractEnd',
            Debug::EVENT_OUTPUT => array('onDebugOutput', 1),
            ErrorHandler::EVENT_ERROR => array(
                array('onErrorLow', -1),
                array('onErrorHigh', 1),
            ),
        );
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
        if ($abs->getSubject() instanceof \yii\db\BaseActiveRecord) {
            $abs['properties']['_attributes']['forceShow'] = true;
        }
    }

    /**
     * PhpDebugConsole output event listener
     *
     * @param Event $event Event instance
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function onDebugOutput(Event $event)
    {
        $this->logCollectedEvents();
        $this->logUser();
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
        if (\in_array($error['category'], array(Error::CAT_DEPRECATED, Error::CAT_NOTICE, Error::CAT_STRICT), true)) {
            /*
                "Ignore" minor internal framework errors
            */
            if (\strpos($error['file'], YII2_PATH) === 0) {
                $error->stopPropagation();          // don't log it now
                $error['isSuppressed'] = true;
                $this->ignoredErrors[] = $error['hash'];
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
        // Yii's handler will log the error.. we can ignore that
        $this->logTarget->enabled = false;
        if ($error['exception']) {
            $this->module->errorHandler->handleException($error['exception']);
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
            $this->module->errorHandler->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        $this->logTarget->enabled = true;
    }

    /**
     * Collect Yii events
     *
     * @return void
     */
    protected function collectEvent()
    {
        if ($this->shouldCollect('events') === false) {
            return;
        }
        /*
            $this->module->getVersion() returns the application "module" version vs framework version ¯\_(ツ)_/¯
        */
        $yiiVersion = Yii::getVersion();  // Framework version
        if (\version_compare($yiiVersion, '2.0.14', '<')) {
            return;
        }
        YiiEvent::on('*', '*', function (YiiEvent $event) {
            $this->collectedEvents[] = array(
                'index' => \count($this->collectedEvents),
                // 'time' => \microtime(true),
                'senderClass' => \is_object($event->sender)
                    ? \get_class($event->sender)
                    : $event->sender,
                'name' => $event->name,
                'eventClass' => \get_class($event),
                'isStatic' => \is_object($event->sender) === false,
            );
        });
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
        $this->logTarget = new LogTarget($this->debug);
        $log = $this->module->getLog();
        $log->flushInterval = 1;
        $log->targets['phpDebugConsole'] = $this->logTarget;
    }

    /**
     * Collect PDO queries
     *
     * @return void
     */
    protected function collectPdo()
    {
        if ($this->shouldCollect('pdo') === false) {
            return;
        }
        YiiEvent::on('yii\\db\\Connection', 'afterOpen', function (YiiEvent $event) {
            $connection = $event->sender;
            $pdo = $connection->pdo;
            if ($pdo instanceof Pdo) {
                // already wrapped
                return;
            }
            $pdoChannel = $this->debug->getChannel('PDO');
            $connection->pdo = new Pdo($pdo, $pdoChannel);
        });
    }

    /**
     * Get collectedEvents table rows
     *
     * @return array
     */
    private function getEventTableData()
    {
        $tableData = array();
        foreach ($this->collectedEvents as $info) {
            $key = $info['senderClass'] . $info['name'];
            if (isset($tableData[$key])) {
                $tableData[$key]['count']++;
                continue;
            }
            $info['count'] = 1;
            $tableData[$key] = $info;
        }

        \usort($tableData, static function ($infoA, $infoB) {
            $cmp = \strcmp($infoA['senderClass'], $infoB['senderClass']);
            if ($cmp) {
                return $cmp;
            }
            return $infoA['index'] - $infoB['index'];
        });
        return $tableData;
    }

    /**
     * Output collected event info
     *
     * @return void
     */
    protected function logCollectedEvents()
    {
        $tableData = $this->getEventTableData();
        foreach ($tableData as &$info) {
            unset($info['index']);
            $info['senderClass'] = $this->debug->abstracter->crateWithVals($info['senderClass'], array(
                'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
            ));
            $info['eventClass'] = $this->debug->abstracter->crateWithVals($info['eventClass'], array(
                'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
            ));
        }

        $debug = $this->debug->rootInstance->getChannel('events');
        $debug->table(\array_values($tableData));
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

        $session = $this->module->get('session', false);
        if ($session === null) {
            return;
        }

        $session->open();

        $debug = $this->debug->rootInstance->getChannel('Session');

        $debug->log('session id', $session->id);
        $debug->log('session name', $session->name);
        $debug->log('session class', $debug->abstracter->crateWithVals(
            \get_class($session),
            array(
                'typeMore' => Abstracter::TYPE_STRING_CLASSNAME,
            )
        ));

        $sessionVals = array();
        foreach ($session as $k => $v) {
            $sessionVals[$k] = $v;
        }
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

        $user = $this->module->get('user', false);
        if ($user === null || $user->isGuest) {
            return;
        }

        $debug = $this->debug->rootInstance->getChannel('User');
        $debug->eventManager->subscribe(Debug::EVENT_LOG, function (LogEntry $logEntry) {
            $captions = array('roles', 'permissions');
            $isRolesPermissions = $logEntry['method'] === 'table' && \in_array($logEntry->getMeta('caption'), $captions, true);
            if (!$isRolesPermissions) {
                return;
            }
            $logEntry['args'] = array($this->tableTsToString($logEntry['args'][0]));
        });

        $this->logUserIdentity($debug);
        $this->logUserRolesPermissions($debug);
    }

    /**
     * Convert unix-timestamps to strings
     *
     * @param array $rows table rows
     *
     * @return array rows
     */
    private function tableTsToString($rows)
    {
        foreach ($rows as $i => $row) {
            $tsCols = array('createdAt', 'updatedAt');
            $nonEmptyTsVals = \array_filter(\array_intersect_key($row, \array_flip($tsCols)));
            foreach ($nonEmptyTsVals as $key => $val) {
                $val = $val instanceof Abstraction
                    ? $val['value']
                    : $val;
                $datetime = new \DateTime('@' . $val);
                $rows[$i][$key] = \str_replace('+0000', '', $datetime->format('Y-m-d H:i:s T'));
            }
        }
        return $rows;
    }

    /**
     * Log user Identity attributes
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logUserIdentity(Debug $debug)
    {
        $user = $this->module->get('user', false);
        $identityData = $user->identity->attributes;
        if ($user->identity instanceof Model) {
            $identityData = array();
            foreach ($user->identity->attributes as $key => $val) {
                $key = $user->identity->getAttributeLabel($key);
                $identityData[$key] = $val;
            }
        }
        $debug->table($identityData);
    }

    /**
     * Log user permissions
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logUserRolesPermissions(Debug $debug)
    {
        try {
            $authManager = Yii::$app->getAuthManager();
            if (!($authManager instanceof \yii\rbac\ManagerInterface)) {
                return;
            }
            $user = $this->module->get('user', false);
            $cols = array(
                'description',
                'ruleName',
                'data',
                'createdAt',
                'updatedAt'
            );
            $debug->table('roles', $authManager->getRolesByUser($user->id), $cols);
            $debug->table('permissions', $authManager->getPermissionsByUser($user->id), $cols);
        } catch (\Exception $e) {
            $debug->error('Exception logging user roles and permissions', $e);
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
