<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Framework;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Collector\Pdo;
use bdk\Debug\Framework\Yii11LogRoute;
use bdk\Debug\Utilities;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use CActiveRecord;
use CApplicationComponent;
use CDbCommand;
use CDbConnection;
use CEvent;
use Yii;
use ReflectionObject;

/**
 * Yii v1.1 Component
 */
class Yii11 extends CApplicationComponent implements SubscriberInterface
{

    public $yiiApp;
    public $debug;
    protected $ignoredErrors = array();

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            'debug.log' => 'onDebugLog',
            'debug.objAbstractStart' => 'onDebugObjAbstractStart',
            'debug.objAbstractEnd' => 'onDebugObjAbstractEnd',
            'debug.output' => array('onDebugOutput', 1),
            'debug.outputLogEntry' => 'onDebugOutputLogEntry',
            'debug.pluginInit' => 'init',
            'errorHandler.error' => array(
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
        $this->debug = Debug::getInstance()->getChannel('Yii');
        Debug::getInstance()->eventManager->addSubscriberInterface($this);
        $this->yiiApp = Yii::app();
        $this->usePdoCollector();
        $this->addDebugProp();
        $this->debug->yiiRouteEnable();
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
            $this->usePdoCollector($event->getSubject());
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
                Yii11LogRoute::toggle($enable);
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
     * debug.output subscriber
     *
     * Log included files before outputting
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugOutput(Event $event)
    {
        $debug = $event->getSubject();
        $files = $debug->utilities->getIncludedFiles();
        $files = \array_filter($files, function ($file) {
            $exclude = array(
                '/framework/',
                '/protected/components/system/',
                '/vendor/',
            );
            $return = true;
            foreach ($exclude as $str) {
                if (\strpos($file, $str) !== false) {
                    $return = false;
                    break;
                }
            }
            return $return;
        });
        $files = \array_values($files);
        $debug->log('files', $files, $debug->meta('detectFiles', true));
        if ($this->ignoredErrors) {
            $hashes = \array_unique($this->ignoredErrors);
            $count = \count($hashes);
            $debug->groupSummary();
            $debug->group(
                $count == 1
                    ? '1 ignored error'
                    : $count . ' ignored errors'
            );
            foreach ($hashes as $hash) {
                $error = $this->debug->errorHandler->get('error', $hash);
                $debug->onError($error);
            }
            $debug->groupEnd();
            $debug->groupEnd();
        }
    }

    /**
     * debug.outputLogEntry event subscriber
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return void
     */
    public function onDebugOutputLogEntry(LogEntry $logEntry)
    {
        $debug = $logEntry->getSubject();
        if ($logEntry['method'] == 'log' && $logEntry['args'][0] == 'files') {
            // let's embolden the primary files
            $root = \realpath(YII_PATH . '/..');
            $html = $debug->dumpHtml->processLogEntry($logEntry);
            $html = \preg_replace_callback('#(<span class="file t_string">)(.*?)(</span>)#', function ($matches) use ($root) {
                $filepath = $matches[2];
                $filepathRel = \str_replace($root, '.', $filepath);
                $isController = \preg_match('#/protected/controllers/.+.php#', $filepathRel);
                $isView = \preg_match('#/protected/views(?:(?!/layout).)+.php#', $filepathRel);
                $embolden = $isController || $isView;
                return Utilities::buildTag(
                    'span',
                    array(
                        'class' => 'file t_string',
                        'data-file' => $filepath,
                        'style' => $embolden
                            ? 'font-weight:bold;'
                            : null,
                    ),
                    $filepathRel
                );
            }, $html);
            $route = \get_class($logEntry['route']);
            if (\in_array($route, array('bdk\Debug\Route\Wamp', 'bdk\Debug\Route\Html'))) {
                $logEntry->setMeta('format', 'html');
                $logEntry['return'] = $html;
            }
            $logEntry->stopPropagation();
        }
    }

    /**
     * debug.objAbstractStart event subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugObjAbstractStart(Event $event)
    {
        if ($event->getSubject() instanceof CActiveRecord) {
            $model = $event->getSubject();
            $refObj = new \ReflectionObject($model);
            while (!$refObj->hasProperty('_models')) {
                $refObj = $refObj->getParentClass();
            }
            $refProp = $refObj->getProperty('_models');
            $refProp->setAccessible(true);
            $event['propertyOverrideValues'] = array(
                '_models' => \array_map(function ($val) {
                    return \get_class($val) . ' (not inspected)';
                }, $refProp->getValue($model)),
            );
            \ksort($event['propertyOverrideValues']['_models']);
        }
    }

    /**
     * debug.objAbstractEnd event subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugObjAbstractEnd(Event $event)
    {
        if ($event->getSubject() instanceof CActiveRecord) {
            $event['properties']['_attributes']['forceShow'] = true;
        } elseif ($event->getSubject() instanceof CDbCommand) {
            $event['properties']['_paramLog']['forceShow'] = true;
            $event['properties']['_text']['forceShow'] = true;
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
        if (\in_array($error['category'], array('deprecated','notice','strict'))) {
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
    }

    /**
     * errorHandler.error event subscriber
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
        Yii11LogRoute::toggle(false);
        if ($error['exception']) {
            $this->yiiApp->handleException($error['exception']);
        } elseif ($error['category'] === 'fatal') {
            // Yii's error handler exits (for reasons)
            //    exit within shutdown procedure (that's us) = immediate exit
            //    so... unsubscribe the callables that have already been called and
            //    re-publish the shutdown event before calling yii's error handler
            foreach ($this->debug->rootInstance->eventManager->getSubscribers('php.shutdown') as $callable) {
                $this->debug->rootInstance->eventManager->unsubscribe('php.shutdown', $callable);
                if (\is_array($callable) && $callable[0] == $this->debug->rootInstance->errorHandler) {
                    break;
                }
            }
            $this->debug->rootInstance->eventManager->publish('php.shutdown');
            $this->yiiApp->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * Setup up PDO collector
     * Log to PDO channel
     *
     * @return void
     */
    public function usePdoCollector(CDbConnection $db = null)
    {
        $db = $db ?: $this->yiiApp->db;
        $db->active = true; // creates pdo obj
        $pdo = $db->pdoInstance;
        if ($pdo instanceof Pdo) {
            // already wrapped
            return;
        }
        // nest the PDO channel under our Yii channel
        $channelName = 'PDO';
        if (\strpos($db->connectionString, 'master=true')) {
            $channelName .= ' (master)';
        } elseif (\strpos($db->connectionString, 'slave=true')) {
            $channelName .= ' (slave)';
        }
        $pdoChannel = $this->debug->getChannel($channelName, array(
            'channelIcon' => 'fa fa-database',
            'channelShow' => false,
        ));
        $pdoCollector = new Pdo($pdo, $pdoChannel);
        $dbRef = new ReflectionObject($db);
        while (!$dbRef->hasProperty('_pdo')) {
            $dbRef = $dbRef->getParentClass();
            if ($dbRef === false) {
                $this->debug->warn('unable initiate PDO collector');
            }
        }
        $pdoProp = $dbRef->getProperty('_pdo');
        $pdoProp->setAccessible(true);
        $pdoProp->setValue($db, $pdoCollector);
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
}
