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

namespace bdk\Debug\Framework\Yii2;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Framework\Yii2\Module as DebugModule;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;

/**
 * Handle various debug & error events
 */
class EventSubscribers implements SubscriberInterface
{
    /** @var Debug */
    protected $debug;

    /** @var DebugModule */
	protected $debugModule;

    /** @var list<string> Error hashes */
	protected $ignoredErrors = array();

	/**
	 * Constructor
	 *
	 * @param DebugModule $debugModule DebugModule instance
	 */
	public function __construct(DebugModule $debugModule)
	{
		$this->debug = $debugModule->debug;
		$this->debugModule = $debugModule;
	}

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OBJ_ABSTRACT_END => 'onDebugObjAbstractEnd',
            Debug::EVENT_OUTPUT => ['onDebugOutput', 1],
            ErrorHandler::EVENT_ERROR => [
                ['onErrorLow', -1],
                ['onErrorHigh', 1],
            ],
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
     * @return void
     */
    public function onDebugOutput()
    {
        $user = new LogUser($this->debugModule);
        $user->log();
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
        if (\in_array($error['category'], [Error::CAT_DEPRECATED, Error::CAT_NOTICE, Error::CAT_STRICT], true)) {
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
        $this->debugModule->logTarget->enabled = false;
        if ($error['exception']) {
            $this->debugModule->module->errorHandler->handleException($error['exception']);
        } elseif ($error['category'] === Error::CAT_FATAL) {
            // Yii's error handler exits (for reasons)
            //    exit within shutdown procedure (that's us) = immediate exit
            //    so... unsubscribe the callables that have already been called and
            //    re-publish the shutdown event before calling yii's error handler
        	$this->removeSubscribers(EventManager::EVENT_PHP_SHUTDOWN);
            $this->debug->rootInstance->eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
            $this->debugModule->module->errorHandler->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        $this->debugModule->logTarget->enabled = true;
    }

    /**
     * Remove subscribers.   stop after we find errorHandler instance
     *
     * @param string $eventName Event name to unsubscribe from
     *
     * @return void
     */
    private function removeSubscribers($eventName)
    {
        foreach ($this->debug->rootInstance->eventManager->getSubscribers($eventName) as $subscriberInfo) {
            $callable = $subscriberInfo['callable'];
            $this->debug->rootInstance->eventManager->unsubscribe($eventName, $callable);
            if (\is_array($callable) && $callable[0] === $this->debug->rootInstance->errorHandler) {
                break;
            }
        }
    }
}
