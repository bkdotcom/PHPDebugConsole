<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeObjectTest extends DebugTestFramework
{

    public function dumpProvider()
    {
        // val, html, text, script

        $text = <<<'EOD'
(object) bdk\DebugTest\Test
  Properties:
    (public) debug = (object) bdk\Debug (not inspected)
    (public) instance = (object) bdk\DebugTest\Test *RECURSION*
    (public) propPublic = "redefined in Test (public)"
    (public) someArray = Array(
        [int] => 123
        [numeric] => "123"
        [string] => "cheese"
        [bool] => true
        [obj] => null
    )
    (protected) propProtected = "defined only in TestBase (protected)"
    (private) propPrivate = "redefined in Test (private) (alternate value via __debugInfo)"
    (🔒 private) testBasePrivate = "defined in TestBase (private)"
    (debug) debugValue = "This property is debug only"
  Methods:
    public: 7
    protected: 1
    private: 1
EOD;
        $script = array(
            '___class_name' => 'bdk\DebugTest\Test',
            '(public) debug' => '(object) bdk\Debug (not inspected)',
            '(public) instance' => '(object) bdk\DebugTest\Test *RECURSION*',
            '(public) propPublic' => 'redefined in Test (public)',
            '(public) someArray' => array(
                'int' => 123,
                'numeric' => '123',
                'string' => 'cheese',
                'bool' => true,
                'obj' => null,
            ),
            '(protected) propProtected' => 'defined only in TestBase (protected)',
            '(private) propPrivate' => 'redefined in Test (private) (alternate value via __debugInfo)',
            '(🔒 private) testBasePrivate' => 'defined in TestBase (private)',
            '(debug) debugValue' => 'This property is debug only',
        );
        return array(
            array(
                new \bdk\DebugTest\Test(),
                function ($str) {
                    $this->assertStringStartsWith(
                        '<span class="t_object" data-accessible="public">'
                        .'<span class="t_string t_stringified" title="__toString()">abracadabra</span>'."\n"
                        .'<span class="t_classname" title="Test"><span class="namespace">bdk\DebugTest\</span>Test</span>',
                        $str
                    );
                    $this->assertSelectCount('dl.object-inner', 1, $str);

                    // extends
                    $this->assertContains('<dt>extends</dt>'."\n".'<dd class="extends">bdk\DebugTest\TestBase</dd>', $str);

                    // implements
                    $this->assertNotContains('<dt>implements</dt>', $str);

                    // constants
                    $this->assertContains(
                        '<dt class="constants">constants</dt>'."\n"
                        .'<dd class="constant"><span class="constant-name">INHERITED</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase</span></dd>'."\n"
                        .'<dd class="constant"><span class="constant-name">MY_CONSTANT</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test</span></dd>',
                        $str
                    );

                    // properties
                    $this->assertContains(implode("\n", array(
                        '<dt class="properties">properties <span class="text-muted">(via __debugInfo)</span></dt>',
                        '<dd class="magic-method info">This object has a <code>__get()</code> method</dd>',
                        '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">debug</span> <span class="t_operator">=</span> <span class="t_object" data-accessible="public"><span class="t_classname"><span class="namespace">bdk\</span>Debug</span> <span class="excluded">(not inspected)</span></span></dd>',
                        '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">instance</span> <span class="t_operator">=</span> <span class="t_object" data-accessible="private"><span class="t_classname"><span class="namespace">bdk\DebugTest\</span>Test</span> <span class="t_recursion">*RECURSION*</span></span></dd>',
                        '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name" title="Public Property.">propPublic</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (public)</span></dd>',
                        '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">someArray</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">Array</span><span class="t_punct">(</span>',
                        '<span class="array-inner">',
                        "\t".'<span class="key-value"><span class="t_key">int</span> <span class="t_operator">=&gt;</span> <span class="t_int">123</span></span>',
                        "\t".'<span class="key-value"><span class="t_key">numeric</span> <span class="t_operator">=&gt;</span> <span class="t_string numeric">123</span></span>',
                        "\t".'<span class="key-value"><span class="t_key">string</span> <span class="t_operator">=&gt;</span> <span class="t_string">cheese</span></span>',
                        "\t".'<span class="key-value"><span class="t_key">bool</span> <span class="t_operator">=&gt;</span> <span class="t_bool true">true</span></span>',
                        "\t".'<span class="key-value"><span class="t_key">obj</span> <span class="t_operator">=&gt;</span> <span class="t_null">null</span></span>',
                        '</span><span class="t_punct">)</span></span></dd>',
                        '<dd class="property protected"><span class="t_modifier_protected">protected</span> <span class="property-name">propProtected</span> <span class="t_operator">=</span> <span class="t_string">defined only in TestBase (protected)</span></dd>',
                        '<dd class="property private debug-value"><span class="t_modifier_private">private</span> <span class="t_type">string</span> <span class="property-name" title="Private Property.: ">propPrivate</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (private) (alternate value via __debugInfo)</span></dd>',
                        '<dd class="property private private-ancestor"><span class="t_modifier_private">private</span> (<i>bdk\DebugTest\TestBase</i>) <span class="property-name">testBasePrivate</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase (private)</span></dd>',
                        '<dd class="property debug-value"><span class="t_modifier_debug">debug</span> <span class="property-name">debugValue</span> <span class="t_operator">=</span> <span class="t_string">This property is debug only</span></dd>',
                    )), $str);

                    // methods
                    $this->assertContains(implode("\n", array(
                        '<dt class="methods">methods</dt>',
                        '<dd class="method public"><span class="t_modifier_public">public</span> <span class="method-name" title="Constructor">__construct</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                        '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">array</span> <span class="method-name" title="magic method">__debugInfo</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                        '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">mixed</span> <span class="method-name" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we\'re getting">$key</span></span><span class="t_punct">)</span></dd>',
                        '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="method-name" title="toString magic method">__toString</span><span class="t_punct">(</span><span class="t_punct">)</span><br /><span class="indent"><span class="t_string">abracadabra</span></span></dd>',
                        '<dd class="method public deprecated"><span class="t_modifier_public">public</span> <span class="t_type">void</span> <span class="method-name" title="This method is public">methodPublic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">SomeClass</span> <span class="t_parameter-name" title="first param">$param1</span></span>, <span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="second param'."\n".'two-line description!">$param2</span> <span class="t_operator">=</span> <span class="t_parameter-default"><span class="t_string">Constant value</span></span></span>, <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="third param">$param3</span> <span class="t_operator">=</span> <span class="t_parameter-default"><span class="t_array"><span class="t_keyword">Array</span><span class="t_punct">()</span></span></span></span><span class="t_punct">)</span></dd>',
                        // '<dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_type" title="Nothing is returned">void</span> <span class="method-name" title="This is a static method">methodStatic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                        '<dd class="method public"><span class="t_modifier_public">public</span> <span class="method-name">testBasePublic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                        '<dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="method-name">testBaseStatic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                        '<dd class="method protected"><span class="t_modifier_protected">protected</span> <span class="t_type">void</span> <span class="method-name" title="This method is protected">methodProtected</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="first param">$param1</span></span><span class="t_punct">)</span></dd>',
                        '<dd class="method private"><span class="t_modifier_private">private</span> <span class="t_type">void</span> <span class="method-name" title="This method is private">methodPrivate</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">SomeClass</span> <span class="t_parameter-name" title="first param (passed by ref)">&amp;$param1</span></span>, <span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="second param (passed by ref)">&amp;$param2</span></span><span class="t_punct">)</span></dd>',
                    )), $str);
                },
                $text,
                $script
            ),
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
        $test_o->propPublic = &$test_val;
        $this->debug->log('test_o', $test_o);
        $test_val = 'success B';
        $this->debug->log('test_o', $test_o);
        $test_val = 'fail';
        $output = $this->debug->output();
        $this->assertContains('success A', $output);
        $this->assertContains('success B', $output);
        $this->assertNotContains('fail', $output);
        $this->assertSame('fail', $test_o->propPublic);   // prop should be 'fail' at this point
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
        $this->assertTrue($abs['properties']['debug']['value']['isExcluded']);
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
                'value' => 'defined only in TestBase (protected)',
                'inheritedFrom' => 'bdk\DebugTest\TestBase',
                'overrides' => null,
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
        $test->propPublic = &$test;
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
            $abstraction['properties']['propPublic']['value']['isRecursion'],
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
