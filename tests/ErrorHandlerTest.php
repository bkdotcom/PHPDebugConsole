<?php
/**
 * Run with --process-isolation option
 */

/**
 * PHPUnit tests for Debug class
 */
class ErrorHandlerTest extends DebugTestFramework
{

    private $onErrorEvent;

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->onErrorEvent = null;
        $this->debug->eventManager->unsubscribe('errorHandler.error', array($this, 'onError'));
        parent::tearDown();
    }


    public function onError(\bdk\PubSub\Event $event)
    {
        $this->onErrorEvent = $event;
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGet()
    {
        $this->assertSame(null, $this->debug->errorHandler->get('lastError'));
        $this->assertSame(array(), $this->debug->errorHandler->get('errors'));
        $this->assertSame(array(
            'errorCaller'   => array(),
            'errors'        => array(),
            'lastError'     => null,
        ), $this->debug->errorHandler->get('data'));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetInstance()
    {
        $instance = \bdk\Debug\ErrorHandler::getInstance();
        $this->assertInstanceOf('\\bdk\\Debug\\ErrorHandler', $instance);
        $this->assertSame($this->debug->errorHandler, $instance);
    }

    /**
     * Test fatal error handling as well as can be tested...
     *
     * @return void
     */
    public function testFatal()
    {
        self::$allowError = true;
        $this->debug->setCfg('output', false);
        $error = array(
            'type' => E_ERROR,
            'file' => __FILE__,
            'line' => __LINE__,
            'message' => 'This is a bogus fatal error',
        );
        $errorValues = array(
            'type'      => $error['type'],                    // int
            'typeStr'   => 'Fatal Error',       // friendly string version of 'type'
            'category'  => 'fatal',
            'message'   => $error['message'],
            'file'      => $error['file'],
            'line'      => $error['line'],
            'vars'      => array(),
            // 'backtrace' => array(), // only if xdebug is enabled
            'exception' => null,  // non-null if error is uncaught-exception
            // 'hash'      => null,
            'isFirstOccur'  => true,
            'isSuppressed'  => false,
            'logError'      => false,   // set to false via DebugTestFramework error subscriber
        );
        $this->debug->eventManager->subscribe('errorHandler.error', array($this, 'onError'));
        $callLine = __LINE__ + 1;
        $this->debug->eventManager->publish('php.shutdown', null, array('error'=>$error));
        $lastError = $this->debug->errorHandler->get('lastError');
        $this->assertArraySubset($errorValues, $lastError);
        // test subscriber
        $this->assertInstanceOf('bdk\\PubSub\\Event', $this->onErrorEvent);
        $this->assertSame($this->debug->errorHandler, $this->onErrorEvent->getSubject());
        $this->assertArraySubset($errorValues, $this->onErrorEvent->getValues());
        if (extension_loaded('xdebug')) {
            $backtrace = $this->onErrorEvent['backtrace'];
            $this->assertSame(array(
                'file' => $error['file'],
                'line' => $error['line'],
            ), $backtrace[0]);
            $this->assertSame(array(
                'file' => $error['file'],
                'line' => $callLine,
                'function' => 'bdk\PubSub\Manager->publish',
            ), $backtrace[1]);
            $this->assertSame(__CLASS__.'->'.__FUNCTION__, $backtrace[2]['function']);
        }
    }

    /**
     * Test
     *
     * @return void
     */
    public function testHandler()
    {
        parent::$allowError = true;
        $error = array(
            'type' => E_WARNING,
            'file' => __FILE__,
            'line' => __LINE__,
            'vars' => array('foo'=>'bar'),
            'message' => 'test warmomg',
        );
        $errorValues = array(
            'type'      => E_WARNING,                    // int
            'typeStr'   => 'Warning',   // friendly string version of 'type'
            'category'  => 'warning',
            'message'   => $error['message'],
            'file'      => $error['file'],
            'line'      => $error['line'],
            'vars'      => $error['vars'],
            'backtrace' => array(), // only for fatal type errors, and only if xdebug is enabled
            'exception' => null,  // non-null if error is uncaught-exception
            // 'hash'      => null,
            'isFirstOccur'  => true,
            'isSuppressed'  => false,
            'logError'      => false,   // set to false via DebugTestFramework error subscriber
        );

        $this->debug->eventManager->subscribe('errorHandler.error', array($this, 'onError'));
        $return = $this->debug->errorHandler->handleError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
            $error['vars']
        );
        $this->assertTrue($return);
        $lastError = $this->debug->errorHandler->get('lastError');
        $this->assertArraySubset($errorValues, $lastError);
        // test subscriber
        $this->assertInstanceOf('bdk\\PubSub\\Event', $this->onErrorEvent);
        $this->assertSame($this->debug->errorHandler, $this->onErrorEvent->getSubject());
        $this->assertArraySubset($errorValues, $this->onErrorEvent->getValues());
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRegister()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSet()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testSetErrorCaller()
    {
        $this->setErrorCallerHelper();
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __LINE__ - 4,
        ), $errorCaller);

        // this will use maximum debug_backtrace depth
        call_user_func(array($this, 'setErrorCallerHelper'));
        $errorCaller = $this->debug->errorHandler->get('errorCaller');
        $this->assertSame(array(
            'file' => __FILE__,
            'line' => __LINE__ - 4,
        ), $errorCaller);
    }

    private function setErrorCallerHelper()
    {
        $this->debug->errorHandler->setErrorCaller();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testUnregister()
    {
    }
}
