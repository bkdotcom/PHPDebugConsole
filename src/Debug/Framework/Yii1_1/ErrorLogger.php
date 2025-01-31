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

namespace bdk\Debug\Framework\Yii1_1;

use bdk\Debug;
use bdk\Debug\Framework\Yii1_1\Component as DebugComponent;
use bdk\Debug\Framework\Yii1_1\LogRoute;
use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use Bdk\PubSub\Event;
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
    /** @var Debug */
    protected $debug;

    /** @var DebugComponent */
    protected $component;

    /** @var list<string> Error hashes */
    protected $ignoredErrors = array();

    /** @var list<string> list of paths... we will "ignore" errors occurring in these paths */
    private $pathsIgnoreError = array();

    /**
     * Constructor
     *
     * @param DebugComponent $debugComponent PHPDebugConsole component
     */
    public function __construct(DebugComponent $debugComponent)
    {
        $this->component = $debugComponent;
        $this->debug = $debugComponent->debug->rootInstance;
        /*
            Debug error handler may have been registered first -> reregister
        */
        $this->debug->errorHandler->unregister();
        $this->debug->errorHandler->register();

        $this->addIgnorePath($this->debug->getCfg('yii.pathsIgnoreError'));
    }

    /**
     * Add path to ignore
     *
     * @param string|array $path Path(s) to ignore
     *
     * @return void
     */
    public function addIgnorePath($path)
    {
        \array_map(function ($path) {
            $path = \preg_replace_callback('/:([\w\.]+):/', static function (array $matches) {
                return Yii::getPathOfAlias($matches[1]);
            }, $path);
            $path = \preg_replace('#' . DIRECTORY_SEPARATOR . '+#', DIRECTORY_SEPARATOR, $path);
            $this->pathsIgnoreError[] = $path;
        }, (array) $path);
        $this->pathsIgnoreError = \array_unique($this->pathsIgnoreError);
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptions()
    {
        return array(
            Debug::EVENT_CONFIG => 'onDebugConfig',
            Debug::EVENT_OUTPUT => ['onDebugOutput', 1],
            ErrorHandler::EVENT_ERROR => [
                ['onErrorLow', -1],
                ['onErrorHigh', 1],
            ],
        );
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onDebugConfig(Event $event)
    {
        if ($event['isTarget'] && isset($event['debug']['yii']['pathsIgnoreError'])) {
            $this->addIgnorePath($event['debug']['yii']['pathsIgnoreError']);
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
        if ($error['isFirstOccur'] === false) {
            $error->stopPropagation();
            return;
        }
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
            // Yii's exception handler calls `restore_error_handler()`
            //   make sure it restores to our error handler
            \set_error_handler([$this->debug->errorHandler, 'handleError']);
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
        $ignorableCats = [Error::CAT_DEPRECATED, Error::CAT_NOTICE, Error::CAT_STRICT, Error::CAT_WARNING];
        if (\in_array($error['category'], $ignorableCats, true) === false) {
            return false;
        }
        /*
            "Ignore" minor internal framework errors
        */
        foreach ($this->pathsIgnoreError as $pathIgnore) {
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
            $error['isSuppressed'] = true;
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
        foreach ($eventManager->getSubscribers(EventManager::EVENT_PHP_SHUTDOWN) as $subscriberInfo) {
            $callable = $subscriberInfo['callable'];
            $eventManager->unsubscribe(EventManager::EVENT_PHP_SHUTDOWN, $callable);
            if (\is_array($callable) && $callable[0] === $this->debug->errorHandler) {
                break;
            }
        }
        $eventManager->publish(EventManager::EVENT_PHP_SHUTDOWN);
    }
}
