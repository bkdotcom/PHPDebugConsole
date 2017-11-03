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
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetInstance()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testHandler()
    {
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
    public function testRegisterOnErrorFunction()
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

    /**
     * Test
     *
     * @return void
     */
    public function testUnregisterOnErrorFunction()
    {
    }
}
