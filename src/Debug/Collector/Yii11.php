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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Debug\Collector\Yii11LogRoute;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use CActiveRecord;
use CDbCommand;
use CEvent;
use Yii;

/**
 * Yii v1.1 debug helper
 */
class Yii11 implements SubscriberInterface
{

    public $yiiApp;
    public $debug;

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
            'errorHandler.error' => array('onError', -1),
        );
    }

    /**
     * debug.pluginInit subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function init(Event $event)
    {
        $this->debug = $event->getSubject()->getChannel('Yii');
        $this->yiiApp = Yii::app();
        $this->usePdoCollector();
        $this->addDebugProp();
        $this->debug->yiiRouteEnable();
    }

    /**
     * Setup up PDO collector
     * Log to PDO channel
     *
     * @return void
     */
    public function usePdoCollector()
    {
        $db = $this->yiiApp->db;
        $pdo = $db->pdoInstance;
        // nest the PDO channel under our Yii channel
        $pdoChannel = $this->debug->getChannel('PDO', array('channelIcon' => 'fa fa-database'));
        $pdoCollector = new \bdk\Debug\Collector\Pdo($pdo, $pdoChannel);
        $pdoProp = new \ReflectionProperty($db, '_pdo');
        $pdoProp->setAccessible(true);
        $pdoProp->setValue($db, $pdoCollector);
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
                // __FILE__,
                '/vendor/',
                '/framework/',
                '/protected/components/system/',
                // '/protected/extensions',
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
                return \bdk\Debug\Utilities::buildTag(
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
     * errorHandler.error event subscriber
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onError(Error $error)
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
