<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeObjectTest extends DebugTestFramework
{

    public function dumpProvider()
    {
        // @todo
        // val, html, text, script
        return array(
            array(null, '<span class="t_null">null</span>', 'null', null),
        );
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


    /**
     * Test
     *
     * @return void
     */
    public function testAbstraction()
    {
        // mostly tested via logTest, infoTest, warnTest, errorTest....
        // test object inheritance
        $test = new \bdk\DebugTest\Test();
        $abs = $this->debug->abstracter->getAbstraction($test);

        $this->assertSame('object', $abs['type']);
        $this->assertSame('bdk\DebugTest\Test', $abs['className']);
        $this->assertSame(
            array('bdk\DebugTest\TestBase'),
            $abs['extends']
        );
        $this->assertSame(
            array(),
            $abs['implements']
        );
        $this->assertSame(
            array(
                'INHERITED' => 'defined in TestBase',
                'MY_CONSTANT' => 'redefined in Test',
            ),
            $abs['constants']
        );
        $this->assertArraySubset(
            array(
                'summary' => 'Test',
                'description' => null,
            ),
            $abs['phpDoc']
        );
        $this->assertTrue($abs['viaDebugInfo']);

        /*
            Properties
        */
        $this->assertArrayNotHasKey('propNoDebug', $abs['properties']);
        $this->assertTrue($abs['properties']['debug']['value']['excluded']);
        $this->assertTrue($abs['properties']['instance']['value']['isRecursion']);
        $this->assertArraySubset(
            array(
                'visibility' => 'public',
                'value' => 'redefined in Test (public)',
                'viaDebugInfo' => false,
                'overrides' => 'bdk\DebugTest\TestBase',
                'originallyDeclared' => 'bdk\DebugTest\TestBase',
            ),
            $abs['properties']['propPublic']
        );
        $this->assertArraySubset(
            array(
                'visibility' => 'public',
                // 'value' => 'This property is debug only',
                'viaDebugInfo' => false,
            ),
            $abs['properties']['someArray']
        );
        $this->assertArraySubset(
            array(
                'visibility' => 'protected',
                'value' => 'redefined in Test (protected)',
                'inheritedFrom' => null,
                'overrides' => 'bdk\DebugTest\TestBase',
                'originallyDeclared' => 'bdk\DebugTest\TestBase',
                'viaDebugInfo' => false,
            ),
            $abs['properties']['propProtected']
        );
        $this->assertArraySubset(
            array(
                'visibility' => 'private',
                'value' => 'redefined in Test (private) (alternate value via __debugInfo)',
                'inheritedFrom' => null,
                'overrides' => 'bdk\DebugTest\TestBase',
                'originallyDeclared' => 'bdk\DebugTest\TestBase',
                'viaDebugInfo' => true,
            ),
            $abs['properties']['propPrivate']
        );
        $this->assertArraySubset(
            array(
                'visibility' => 'private',
                'value' => 'defined in TestBase (private)',
                'inheritedFrom' => 'bdk\DebugTest\TestBase',
                'overrides' => null,
                'originallyDeclared' => null,
                'viaDebugInfo' => false,
            ),
            $abs['properties']['testBasePrivate']
        );
        $this->assertArraySubset(
            array(
                'value' => 'This property is debug only',
                'viaDebugInfo' => true,
            ),
            $abs['properties']['debugValue']
        );

        /*
            Methods
        */
        $this->assertArrayNotHasKey('testBasePrivate', $abs['methods']);
        $this->assertTrue($abs['methods']['methodPublic']['isDeprecated']);
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
     * test handling __debugInfo magic method
     *
     * @return void
     */
    public function testDebugInfo()
    {
        $test = new \bdk\DebugTest\Test();
        $this->debug->log('test', $test);
        $abstraction = $this->debug->getData('log/0/2');
        $props = $abstraction['properties'];
        $this->assertArrayNotHasKey('propHidden', $props, 'propHidden shouldn\'t be debugged');
        // debugValue
        $this->assertSame('This property is debug only', $props['debugValue']['value']);
        $this->assertEquals('debug', $props['debugValue']['visibility']);
        // propPrivate
        $this->assertStringEndsWith('(alternate value via __debugInfo)', $props['propPrivate']['value']);
        $this->assertSame(true, $props['propPrivate']['viaDebugInfo']);
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
        $abstraction = $this->debug->getData('log/0/2');
        $this->assertEquals(
            \bdk\Debug\Abstracter::RECURSION,
            $abstraction['properties']['prop']['value'][0],
            'Did not find expected recursion'
        );
        $output = $this->debug->output();
        // $this->output('output', $output);
        $select = '.m_log
            > .t_object > .object-inner
            > .property
            > .t_array .array-inner > .key-value'
            // > .t_array
            .'> .t_recursion';
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
        $abstraction = $this->debug->getData('log/0/2');
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
        $abstraction = $this->debug->getData('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['properties']['prop']['value'][0]['isRecursion'],
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
        $abstraction = $this->debug->getData('log/0/2');
        $this->assertEquals(
            true,
            $abstraction['properties']['ob']['value']['properties']['oa']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }
}
