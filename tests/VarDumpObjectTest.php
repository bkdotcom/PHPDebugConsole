<?php
/**
 * Run with --process-isolation option
 */

/**
 * PHPUnit tests for Debug class
 */
class VarDumpObjectTest extends PHPUnit_Framework_DOMTestCase
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
    public function testGetMethods()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetParams()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testParamTypeHint()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testGetProperties()
    {
    }

    /**
     * Test
     *
     * @return void
     */
    public function testParseDocComment()
    {
    }

    /**
     * v 1.0 = fatal error
     *
     * @return void
     */
    public function testRecursiveObjectProp1()
    {
        $test = new \bdk\DebugTest\Test();
        $test->prop = array();
        $test->prop[] = &$test->prop;
        $this->debug->log('test', $test);
        $abstraction = $this->debug->dataGet('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['properties']['prop']['value']['values'][0]['isRecursion'],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        $select = '.m_log
            > .t_object > .object-inner
            > .property
            > .t_array .array-inner > .key-value
            > .t_array
            > .t_recursion';
        $this->assertSelectCount($select, 1, $output);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveObjectProp2()
    {
        $test = new \bdk\DebugTest\Test();
        $test->prop = &$test;
        $this->debug->log('test', $test);
        /*
        $output = $this->debug->output();
        $xml = new DomDocument;
        $xml->loadXML($output);
        $select = '.m_log
            > .t_object > .object-inner
            > .t_array > .array-inner > .key-value
            > .t_object > .t_recursion';
        $this->assertSelectCount($select, 1, $xml);
        */
        $abstraction = $this->debug->dataGet('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['properties']['prop']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testRecursiveObjectProp3()
    {
        $test = new \bdk\DebugTest\Test();
        $test->prop = array( &$test );
        $this->debug->log('test', $test);
        /*
        $output = $this->debug->output();
        $xml = new DomDocument;
        $xml->loadXML($output);
        $select = '.m_log
            > .t_object > .object-inner
            > .t_array > .array-inner > .key-value
            > .t_array > .array-inner > .key-value
            > .t_object > .t_recursion';
        $this->assertSelectCount($select, 1, $xml);
        */
        $abstraction = $this->debug->dataGet('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['properties']['prop']['value']['values'][0]['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }

    /**
     * Test
     *
     * @return void
     */
    public function testCrossRefObjects()
    {
        $test_oa = new \bdk\DebugTest\Test();
        $test_ob = new \bdk\DebugTest\Test();
        $test_oa->prop = 'this is object a';
        $test_ob->prop = 'this is object b';
        $test_oa->ob = $test_ob;
        $test_ob->oa = $test_oa;
        $this->debug->log('test_oa', $test_oa);
        /*
        $output = $this->debug->output();
        $xml = new DomDocument;
        $xml->loadXML($output);
        $select = '.m_log
            > .t_object > .object-inner
            > .t_array > .array-inner > .t_key_value
            > .t_object > .object-inner
            > .t_array > .array-inner > .t_key_value
            > .t_object > .t_recursion';
        $this->assertSelectCount($select, 1, $xml);
        */
        $abstraction = $this->debug->dataGet('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['properties']['ob']['value']['properties']['oa']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }


}
