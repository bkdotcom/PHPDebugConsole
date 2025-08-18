<?php

namespace bdk\Test\ErrorHandler;

use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\PubSub\Manager as EventManager;
use PHPUnit\Framework\TestCase;

/**
 *
 */
abstract class AbstractTestCase extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    public static $allowError = false;
    public $errorHandler = null;
    protected $emailInfo = array();

    protected $caughtException;
    protected $onErrorEvent;
    protected $onErrorUpdate = array();

    protected static $subscribersBackup = array();

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp(): void
    {
        self::$allowError = false;
        $this->onErrorUpdate = array();

        $this->errorHandler = ErrorHandler::getInstance();
        if (!$this->errorHandler) {
            $eventManager = new EventManager();
            $this->errorHandler = new ErrorHandler($eventManager);
        }

        $this->errorHandler->eventManager->subscribe(ErrorHandler::EVENT_ERROR, array($this, 'onError'));

        $this->errorHandler->setCfg(array(
            'enableStats' => true,
            'emailer' => array(
                'emailTo' => 'test@email.com', // need an email address to email to!
                'emailFrom' => 'php@test.com',
                'emailFunc' => array($this, 'emailMock'),
            ),
            'onEUserError' => 'continue',
            'errorThrow' => 0,
        ));
        $this->errorHandler->register();
        $this->errorHandler->setData('errors', array());
        $this->errorHandler->setData('errorCaller', array());
        $this->errorHandler->setData('lastErrors', array());
    }

    public static function setUpBeforeClass(): void
    {
        if (\class_exists('bdk\\Debug')) {
            \bdk\Debug::getInstance()->setCfg(array(
                'collect' => false,
                'output' => false,
            ));
            self::$subscribersBackup = ErrorHandler::getInstance()->eventManager->getSubscribers(ErrorHandler::EVENT_ERROR);
            foreach (self::$subscribersBackup as $subscriberInfo) {
                ErrorHandler::getInstance()->eventManager->unsubscribe(ErrorHandler::EVENT_ERROR, $subscriberInfo['callable']);
            }
        }
    }

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearTown(): void
    {
        $this->onErrorEvent = null;
        $this->onErrorUpdate = array();
        $this->errorHandler->eventManager->unsubscribe(ErrorHandler::EVENT_ERROR, array($this, 'onError'));
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$subscribersBackup as $subscriberInfo) {
            ErrorHandler::getInstance()->eventManager->subscribe(ErrorHandler::EVENT_ERROR, $subscriberInfo['callable'], $subscriberInfo['priority']);
        }
    }

    public function onError(Error $error)
    {
        foreach ($this->onErrorUpdate as $k => $v) {
            if ($k === 'stopPropagation') {
                if ($v) {
                    $error->stopPropagation();
                }
                continue;
            }
            $error->setValue($k, $v);
        }
        $this->onErrorEvent = $error;

        if (\array_key_exists('continueToPrevHandler', $this->onErrorUpdate)) {
            // continueToPrevHandler explicitly set
            return;
        }
        if (self::$allowError) {
            $error['continueToPrevHandler'] = false;
            return;
        }
        $error['continueToPrevHandler'] = true;
        $error['throw'] = true;
    }

    public function emailMock($to, $subject, $body, $addHeadersStr)
    {
        $this->emailInfo = array(
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'addHeadersStr' => $addHeadersStr,
        );
    }

    protected function randoErrorVals($asList = false, $vals = array())
    {
        $vals = \array_merge(array(
            'type' => E_USER_ERROR,
            'message' => 'Some error ' . \uniqid('', true),
            'file' => __FILE__,
            'line' => __LINE__,
            'vars' => array('foo' => 'bar'),
        ), $vals);
        return $asList
            ? \array_values($vals)
            : $vals;
    }

    protected function raiseError($vals = array(), $suppress = false)
    {
        self::$allowError = true;
        $this->caughtException = null;
        $errorReportingWas = $suppress
            ? \error_reporting(0)
            : \error_reporting();
        $vals = \array_merge(array(
            'type' => E_NOTICE,
            'message' => 'default error message',
            'file' => '/path/to/file.php',
            'line' => 42,
            'vars' => array(),
        ), $vals);
        $addVals = \array_diff_key($vals, \array_flip(array('type', 'message', 'file', 'line', 'vars')));
        if ($addVals) {
            $this->onErrorUpdate = \array_merge($this->onErrorUpdate, $addVals);
        }
        try {
            $return = $this->errorHandler->handleError($vals['type'], $vals['message'], $vals['file'], $vals['line'], $vals['vars']);
        } catch (\Exception $e) {
            $error = $this->errorHandler->getLastError(true);
            $return = $error['continueToNormal'];
            $this->caughtException = $e;
        }
        \error_reporting($errorReportingWas);
        $error = $this->errorHandler->getLastError(true);
        if ($error) {
            $error['return'] = $return;
        }
        return $error;
    }
}
