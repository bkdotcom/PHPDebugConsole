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
use bdk\Debug\Framework\Yii1_1\LogRoute;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;
use Yii;

/**
 * Yii v1.1 Component
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class ErrorLogger implements SubscriberInterface
{
    protected $debug;
    protected $component;
    protected $ignoredErrors = array();

    /**
     * Constructor
     *
     * @param Debug $debugComponent PHPDebugConsole component
     */
    public function __construct($debugComponent)
    {
        $this->component = $debugComponent;
        $this->debug = $debugComponent->debug->rootInstance;
        /*
            Debug error handler may have been registered first -> reregister
        */
        $this->debug->errorHandler->unregister();
        $this->debug->errorHandler->register();
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_OUTPUT => array('onDebugOutput', 1),
            ErrorHandler::EVENT_ERROR => array(
                array('onErrorLow', -1),
                array('onErrorHigh', 1),
            ),
        );
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
            $error['continueToNormal'] = false;
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
        $logRoute = LogRoute::getInstance();
        $enabledWas = $logRoute->enabled;
        $logRoute->enabled = false;
        if ($error['exception']) {
            $this->component->yiiApp->handleException($error['exception']);
        } elseif ($error['category'] === Error::CAT_FATAL) {
            $this->republishShutdown();
            $this->component->yiiApp->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        $logRoute->enabled = $enabledWas;
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
        if (\in_array($error['category'], $ignorableCats, true) === false) {
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
     * Log files we ignored
     *
     * @return void
     */
    private function logIgnoredErrors()
    {
        if ($this->component->shouldCollect('ignoredErrors') === false) {
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
            $debug->log($error);
        }
        $debug->groupEnd();
        $debug->groupEnd();
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
        $eventManager = $this->debug->eventManager;
        foreach ($eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN) as $callable) {
            $eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $callable);
            if (\is_array($callable) && $callable[0] === $this->debug->errorHandler) {
                break;
            }
        }
        $eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
    }
}
