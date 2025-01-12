<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b1
 */

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Collector\Pdo;
use bdk\Debug\Framework\Yii2\LogTarget;
use bdk\Debug\LogEntry;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\Event as YiiEvent;
use yii\base\Module as BaseModule;

/**
 * PhpDebugConsole Yii 2 Module
 */
class Module extends BaseModule implements BootstrapInterface
{
    /** @var \bdk\Debug */
    public $debug;

    /** @var LogTarget */
    public $logTarget;

    private $configDefault = array(
        'channels' => array(
            'events' => array(
                'channelIcon' => ':event:',
                'nested' => false,
            ),
            'PDO' => array(
                'channelIcon' => ':database:',
                'channelShow' => false,
            ),
            'Session' => array(
                'channelIcon' => ':suitcase:',
                'nested' => false,
            ),
            'User' => array(
                'channelIcon' => ':user:',
                'nested' => false,
            ),
        ),
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
            'events' => true,
            'log' => true,
            'pdo' => true,
            'session' => true,
            'user' => true,
        ),
    );

    private $collectEvents;
    private $eventSubscribers;

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
        $debugRootInstance->setCfg($config, Debug::CONFIG_NO_RETURN);
        $this->debug = $debugRootInstance->getChannel('Yii');
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

        $this->collectEvents = new CollectEvents($this);
        $this->eventSubscribers = new EventSubscribers($this);
        $debugRootInstance->eventManager->addSubscriberInterface($this->collectEvents);
        $debugRootInstance->eventManager->addSubscriberInterface($this->eventSubscribers);
        /*
            Debug error handler may have been registered first -> reregister
        */
        $debugRootInstance->errorHandler->unregister();
        $debugRootInstance->errorHandler->register();
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
        $this->debug->rootInstance->setCfg($cfg, Debug::CONFIG_NO_RETURN);
    }

    /**
     * {@inheritDoc}
     */
    public function bootstrap($app)
    {
        // setAlias needed for Console app
        Yii::setAlias('@' . \str_replace('\\', '/', __NAMESPACE__), __DIR__);
        $this->collectEvents->bootstrap();
        $this->collectLog();
        $this->collectPdo();
        $this->logSession();
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
     * Collect Yii log messages
     *
     * @return void
     */
    protected function collectLog()
    {
        $this->logTarget = new LogTarget($this->debug);
        if ($this->shouldCollect('log') === false) {
            return;
        }
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
                'type' => Type::TYPE_IDENTIFIER,
                'typeMore' => Type::TYPE_IDENTIFIER_CLASSNAME,
            )
        ));

        $sessionVals = array();
        foreach ($session as $k => $v) {
            $sessionVals[$k] = $v;
        }
        \ksort($sessionVals);
        $debug->log($sessionVals);
    }
}
