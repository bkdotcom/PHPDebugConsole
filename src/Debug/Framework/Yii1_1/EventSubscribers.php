<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Framework\Yii1_1\LogRoute;
use bdk\Debug\Framework\Yii1_1\UserInfo;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;
use CActiveRecord;
use CApplicationComponent;
use CDbCommand;
use CDbConnection;
use ReflectionObject;

/**
 * Handle Events
 */
class EventSubscribers implements SubscriberInterface
{
	protected $component;

	/**
	 * Constructor
	 *
	 * @param CApplicationComponent $component Debug component
	 */
	public function __construct(CApplicationComponent $component)
	{
		$this->component = $component;
	}

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            'yii.componentInit' => 'onComponentInit',
            Debug::EVENT_CUSTOM_METHOD => 'onCustomMethod',
            Debug::EVENT_OBJ_ABSTRACT_END => 'onDebugObjAbstractEnd',
            Debug::EVENT_OBJ_ABSTRACT_START => 'onDebugObjAbstractStart',
            Debug::EVENT_OUTPUT => ['onDebugOutput', 1],
            Debug::EVENT_OUTPUT_LOG_ENTRY => 'onDebugOutputLogEntry',
        );
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
            $this->component->pdoCollector->collect($event->getSubject());
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
                $this->component->logRoute = $this->component->logRoute ?: LogRoute::getInstance();
                $this->component->logRoute->enabled = $arg0;
                $logEntry->stopPropagation();
                $logEntry['handled'] = true;
                break;
            case 'logPdo':
                $debug->getChannel('PDO')->setCfg('collect', $arg0, Debug::CONFIG_NO_RETURN);
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
        $userInfo = new UserInfo($this->component);
        $userInfo->log();
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
        \array_walk_recursive($logEntry['args'][0]['value'], [$this, 'stylizeFileAbstraction']);
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
            $refObj = new ReflectionObject($obj);
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
