<?php
/**
 * Run with --process-isolation option
 */

/**
 * PHPUnit tests for Debug class
 */
class ErrorHandlerTest extends DebugTestFramework
{

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
     * Test
     *
     * @return void
     */
    public function testHandler()
    {
        parent::$allowError = true;

        $file = __FILE__;
        $line = __LINE__;
        $vars = array('foo'=>'bar');
        $return = $this->debug->errorHandler->handleError(E_WARNING, 'test warning', $file, $line, $vars);
        $this->assertTrue($return);
        $lastError = $this->debug->errorHandler->get('lastError');
        $this->assertArraySubset(array(
            'type'      => E_WARNING,                    // int
            'typeStr'   => 'Warning',   // friendly string version of 'type'
            'category'  => 'warning',
            'message'   => 'test warning',
            'file'      => $file,
            'line'      => $line,
            'vars'      => $vars,
            'backtrace' => array(), // only for fatal type errors, and only if xdebug is enabled
            'exception' => null,  // non-null if error is uncaught-exception
            // 'hash'      => null,
            'isFirstOccur'  => true,
            'isSuppressed'  => false,
            'logError'      => false,
        ), $lastError);
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
    public function testShutdownFunction()
    {
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
