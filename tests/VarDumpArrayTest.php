<?php
/**
 * Run with --process-isolation option
 */

/**
 * PHPUnit tests for Debug class
 */
class VarDumpArrayTest extends PHPUnit_Framework_TestCase
{

    /**
     * setUp is executed before each test
     *
     * @return void
     */
    public function setUp()
    {
        $this->debug = new \bdk\Debug\Debug(array(
            'collect' => true,
            'output' => true,
            'outputCss' => false,
            'outputScript' => false,
            'outputAs' => 'html',
        ));
    }

    /**
     * tearDown is executed after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->debug->set('output', false);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testDump()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetAbstraction()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testDereferenceArray()
    {
        $test_val = 'success';
        $test_a = array(
            'ref' => &$test_val,
        );
        $this->debug->log('test_a', $test_a);
        $test_val = 'fail';
        $output = $this->debug->output();
        $this->assertContains('success', $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveArray()
    {
        $array = array();
        $array[] = &$array;
        $this->debug->log('array', $array);
        $abstraction = $this->debug->dataGet('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['values'][0]['isRecursion'],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $test_a = array( 'foo' => 'bar' );
        $test_a['val'] = &$test_a;
        $this->debug->log('test_a', $test_a);
        $output = $this->debug->output();
        $this->assertContains('t_recursion', $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveArray2()
    {
        /*
            $test_a is a circular reference
            $test_b references $test_a
        */
        $test_a = array();
        $test_a[] = &$test_a;
        $this->debug->log('test_a', $test_a);
        $test_b = array('foo', &$test_a, 'bar');
        $this->debug->log('test_b', $test_b);
        $output = $this->debug->output();
        $this->assertSelectCount('.t_recursion', 2, $output, 'Does not contain two recursion types');
    }

    /**
     * v 1.0 = fatal error
     *
     * @return void
     */
    public function testDereferenceObject()
    {
        $test_val = 'success A';
        $test_o = new \bdk\DebugTest\Test();
        $test_o->prop = &$test_val;
        $this->debug->log('test_o', $test_o);
        $test_val = 'success B';
        $this->debug->log('test_o', $test_o);
        $test_val = 'fail';
        $output = $this->debug->output();
        $this->assertContains('success A', $output);
        $this->assertContains('success B', $output);
        $this->assertNotContains('fail', $output);
        $this->assertSame('fail', $test_o->prop);   // prop should be 'fail' at this point
    }
}
