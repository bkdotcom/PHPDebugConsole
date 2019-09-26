<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeObjectTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        // val, html, text, script

        $text = <<<'EOD'
(object) bdk\DebugTest\Test
  Properties:
    ✨ This object has a __get() method
    (public) debug = (object) bdk\Debug (not inspected)
    (public) instance = (object) bdk\DebugTest\Test *RECURSION*
    (public) propPublic = "redefined in Test (public)"
    (public) propStatic = "I'm Static"
    (public) someArray = array(
        [int] => 123
        [numeric] => "123"
        [string] => "cheese"
        [bool] => true
        [obj] => null
    )
    (public) toString = "abracadabra"
    (protected ✨ magic-read) magicReadProp = "not null"
    (protected) propProtected = "defined only in TestBase (protected)"
    (private excluded) propNoDebug
    (private) propPrivate = "redefined in Test (private) (alternate value via __debugInfo)"
    (🔒 private) testBasePrivate = "defined in TestBase (private)"
    (✨ magic excluded) magicProp
    (debug) debugValue = "This property is debug only"
  Methods:
    public: 8
    protected: 1
    private: 1
    magic: 2
EOD;

        $text2 = <<<'EOD'
(object) bdk\DebugTest\Test2
  Properties:
    ✨ This object has a __get() method
    (protected ✨ magic-read) magicReadProp = "not null"
    (✨ magic) magicProp = undefined
  Methods:
    public: 3
    magic: 1
EOD;

        return array(
            array(
                'log',
                array(
                    new \bdk\DebugTest\Test(),
                ),
                array(
                    'html' => function ($str) {
                        $this->assertStringStartsWith(
                            '<div class="m_log"><span class="t_object" data-accessible="public">'
                            .'<span class="t_string t_stringified" title="__toString()">abracadabra</span>'."\n"
                            .'<span class="t_classname" title="Test"><span class="namespace">bdk\DebugTest\</span>Test</span>',
                            $str
                        );
                        $this->assertSelectCount('dl.object-inner', 1, $str);

                        // extends
                        $this->assertContains('<dt>extends</dt>'."\n".'<dd class="extends">bdk\DebugTest\TestBase</dd>', $str);

                        // implements
                        if (defined('HHVM_VERSION')) {
                            $this->assertContains(implode("\n", array(
                                '<dt>implements</dt>',
                                '<dd class="interface">Stringish</dd>',
                                '<dd class="interface">XHPChild</dd>',
                            )), $str);
                        } else {
                            $this->assertNotContains('<dt>implements</dt>', $str);
                        }

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
                            '<dd class="magic info">This object has a <code>__get</code> method</dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">debug</span> <span class="t_operator">=</span> <span class="t_object" data-accessible="public"><span class="t_classname"><span class="namespace">bdk\</span>Debug</span>',
                            '<span class="excluded">(not inspected)</span></span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">instance</span> <span class="t_operator">=</span> <span class="t_object" data-accessible="private"><span class="t_classname"><span class="namespace">bdk\DebugTest\</span>Test</span> <span class="t_recursion">*RECURSION*</span></span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name" title="Public Property.">propPublic</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (public)</span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="property-name">propStatic</span> <span class="t_operator">=</span> <span class="t_string">I\'m Static</span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">someArray</span> <span class="t_operator">=</span> <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>',
                            '<span class="array-inner">',
                            "\t".'<span class="key-value"><span class="t_key">int</span> <span class="t_operator">=&gt;</span> <span class="t_int">123</span></span>',
                            "\t".'<span class="key-value"><span class="t_key">numeric</span> <span class="t_operator">=&gt;</span> <span class="numeric t_string">123</span></span>',
                            "\t".'<span class="key-value"><span class="t_key">string</span> <span class="t_operator">=&gt;</span> <span class="t_string">cheese</span></span>',
                            "\t".'<span class="key-value"><span class="t_key">bool</span> <span class="t_operator">=&gt;</span> <span class="t_bool true">true</span></span>',
                            "\t".'<span class="key-value"><span class="t_key">obj</span> <span class="t_operator">=&gt;</span> <span class="t_null">null</span></span>',
                            '</span><span class="t_punct">)</span></span></dd>',
                            '<dd class="property public"><span class="t_modifier_public">public</span> <span class="property-name">toString</span> <span class="t_operator">=</span> <span class="t_string">abracadabra</span></dd>',
                            '<dd class="property protected magic-read"><span class="t_modifier_protected">protected</span> <span class="t_modifier_magic-read">magic-read</span> <span class="t_type">bool</span> <span class="property-name" title="Read Only!">magicReadProp</span> <span class="t_operator">=</span> <span class="t_string">not null</span></dd>',
                            '<dd class="property protected"><span class="t_modifier_protected">protected</span> <span class="property-name">propProtected</span> <span class="t_operator">=</span> <span class="t_string">defined only in TestBase (protected)</span></dd>',
                            '<dd class="excluded property private"><span class="t_modifier_private">private</span> <span class="property-name">propNoDebug</span> <span class="t_operator">=</span> <span class="t_string">not included in __debugInfo</span></dd>',
                            '<dd class="debuginfo-value property private"><span class="t_modifier_private">private</span> <span class="t_type">string</span> <span class="property-name" title="Private Property.">propPrivate</span> <span class="t_operator">=</span> <span class="t_string">redefined in Test (private) (alternate value via __debugInfo)</span></dd>',
                            '<dd class="private-ancestor property private"><span class="t_modifier_private">private</span> (<i>bdk\DebugTest\TestBase</i>) <span class="property-name">testBasePrivate</span> <span class="t_operator">=</span> <span class="t_string">defined in TestBase (private)</span></dd>',
                            '<dd class="excluded property magic"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="property-name" title="I\'m avail via __get()">magicProp</span></dd>',
                            '<dd class="debuginfo-value property"><span class="t_modifier_debug">debug</span> <span class="property-name">debugValue</span> <span class="t_operator">=</span> <span class="t_string">This property is debug only</span></dd>',
                            '<dt class="methods">methods</dt>'
                        )), $str);

                        // methods
                        $this->assertContains(implode("\n", array(
                            '<dt class="methods">methods</dt>',
                            '<dd class="magic info">This object has a <code>__call</code> method</dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="method-name" title="Constructor">__construct</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$toString</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">abracadabra</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">mixed</span> <span class="method-name" title="call magic method">__call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="Method being called">$name</span></span>, <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="Arguments passed">$args</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">array</span> <span class="method-name" title="magic method">__debugInfo</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">mixed</span> <span class="method-name" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we\'re getting">$key</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">string</span> <span class="method-name" title="toString magic method">__toString</span><span class="t_punct">(</span><span class="t_punct">)</span><br /><span class="t_string">abracadabra</span></dd>',
                            '<dd class="deprecated method public"><span class="t_modifier_public">public</span> <span class="t_type">void</span> <span class="method-name" title="This method is public">methodPublic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">SomeClass</span> <span class="t_parameter-name" title="first param',
                                'two-line description!">$param1</span></span>, <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="third param">$param2</span> <span class="t_operator">=</span> <span class="t_parameter-default t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="method-name">testBasePublic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="method-name">testBaseStatic</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>',
                            '<dd class="method protected"><span class="t_modifier_protected">protected</span> <span class="t_type">void</span> <span class="method-name" title="This method is protected">methodProtected</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="first param">$param1</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method private"><span class="t_modifier_private">private</span> <span class="t_type">void</span> <span class="method-name" title="This method is private">methodPrivate</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">SomeClass</span> <span class="t_parameter-name" title="first param (passed by ref)">&amp;$param1</span></span>, <span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name" title="second param (passed by ref)">&amp;$param2</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="magic method"><span class="t_modifier_magic">magic</span> <span class="t_type">void</span> <span class="method-name" title="I\'m a magic method">presto</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span>, <span class="parameter"><span class="t_type">int</span> <span class="t_parameter-name">$int</span> <span class="t_operator">=</span> <span class="t_parameter-default t_int">1</span></span>, <span class="parameter"><span class="t_parameter-name">$bool</span> <span class="t_operator">=</span> <span class="t_parameter-default t_bool true">true</span></span>, <span class="parameter"><span class="t_parameter-name">$null</span> <span class="t_operator">=</span> <span class="t_parameter-default t_null">null</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="magic method static"><span class="t_modifier_magic">magic</span> <span class="t_modifier_static">static</span> <span class="t_type">void</span> <span class="method-name" title="I\'m a static magic method">prestoStatic</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name">$noDefault</span></span>, <span class="parameter"><span class="t_parameter-name">$arr</span> <span class="t_operator">=</span> <span class="t_parameter-default t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span></span>, <span class="parameter"><span class="t_parameter-name">$opts</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">array(\'a\'=&gt;\'ay\',\'b\'=&gt;\'bee\')</span></span><span class="t_punct">)</span></dd>',
                            '<dt class="phpDoc">',
                        )), $str);

                        // phpdoc
                        $this->assertContains(implode("\n", array(
                            '<dt class="phpDoc">phpDoc</dt>',
                            '<dd class="phpdoc phpdoc-link"><span class="phpdoc-tag">link</span><span class="t_operator">:</span> <a href="http://www.bradkent.com/php/debug" target="_blank">PHPDebugConsole Homepage</a></dd>',
                            '</dl>',
                        )), $str);
                    },
                    'text' => $text,
                    'script' => 'console.log({"___class_name":"bdk\\\DebugTest\\\Test","(public) debug":"(object) bdk\\\Debug (not inspected)","(public) instance":"(object) bdk\\\DebugTest\\\Test *RECURSION*","(public) propPublic":"redefined in Test (public)","(public) propStatic":"I\'m Static","(public) someArray":{"int":123,"numeric":"123","string":"cheese","bool":true,"obj":null},"(public) toString":"abracadabra","(protected \u2728 magic-read) magicReadProp":"not null","(protected) propProtected":"defined only in TestBase (protected)","(private excluded) propNoDebug":"not included in __debugInfo","(private) propPrivate":"redefined in Test (private) (alternate value via __debugInfo)","(\ud83d\udd12 private) testBasePrivate":"defined in TestBase (private)","(\u2728 magic excluded) magicProp":undefined,"(debug) debugValue":"This property is debug only"});'
                )
            ),
            array(
                'log',
                array(
                    new \bdk\DebugTest\Test('This is the song that never ends.  Yes, it goes on and on my friend.  Some people started singing it not knowing what it was.  And they\'ll never stop singing it forever just because.  This is the song that never ends...'),
                ),
                array(
                    'html' => function ($str) {
                        $this->assertContains('<span class="t_string t_string_trunc t_stringified" title="__toString()">This is the song that never ends.  Yes, it goes on and on my friend.  Some people started singing it&hellip; <i>(119 more chars)</i></span>', $str);
                    }
                ),
            ),
            array(
                'log',
                array(
                    new \bdk\DebugTest\Test2(),
                ),
                array(
                    'html' => function ($str) {
                        // properties
                        $this->assertContains(implode("\n", array(
                            '<dt class="properties">properties</dt>',
                            '<dd class="magic info">This object has a <code>__get</code> method</dd>',
                            '<dd class="property protected magic-read"><span class="t_modifier_protected">protected</span> <span class="t_modifier_magic-read">magic-read</span> <span class="t_type">bool</span> <span class="property-name" title="Read Only!">magicReadProp</span> <span class="t_operator">=</span> <span class="t_string">not null</span></dd>',
                            '<dd class="property magic"><span class="t_modifier_magic">magic</span> <span class="t_type">bool</span> <span class="property-name" title="I\'m avail via __get()">magicProp</span></dd>',
                        )), $str);

                        // methods
                        $constName = defined('HHVM_VERSION')
                            ? '\\bdk\\DebugTest\\Test2Base::WORD'
                            : 'self::WORD';
                        $this->assertContains(implode("\n", array(
                            '<dt class="methods">methods</dt>',
                            '<dd class="magic info">This object has a <code>__call</code> method</dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">mixed</span> <span class="method-name" title="magic method">__call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="Method being called">$name</span></span>, <span class="parameter"><span class="t_type">array</span> <span class="t_parameter-name" title="Arguments passed">$args</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">mixed</span> <span class="method-name" title="get magic method">__get</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="what we\'re getting">$key</span></span><span class="t_punct">)</span></dd>',
                            version_compare(PHP_VERSION, '5.4.6', '>=')
                                ? '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">void</span> <span class="method-name" title="Test constant as default value">constDefault</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="only php &gt;= 5.4.6 can get the name of the constant used">$param</span> <span class="t_operator">=</span> <span class="t_parameter-default t_const" title="value: &quot;bird&quot;">'.$constName.'</span></span><span class="t_punct">)</span></dd>'
                                : '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">void</span> <span class="method-name" title="Test constant as default value">constDefault</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">string</span> <span class="t_parameter-name" title="only php &gt;= 5.4.6 can get the name of the constant used">$param</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">bird</span></span><span class="t_punct">)</span></dd>',
                            '<dd class="magic method"><span class="t_modifier_magic">magic</span> <span class="method-name" title="test constant as param">methConstTest</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$mode</span> <span class="t_operator">=</span> <span class="t_parameter-default t_const" title="value: &quot;bird&quot;">self::WORD</span></span><span class="t_punct">)</span></dd>',
                            '</dl>',
                        )), $str);
                    },
                    'text' => $text2,
                    'script' => 'console.log({"___class_name":"bdk\\\DebugTest\\\Test2","(protected \u2728 magic-read) magicReadProp":"not null","(\u2728 magic) magicProp":undefined});',
                ),
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
            defined('HHVM_VERSION')
                ? array('Stringish','XHPChild') // hhvm-3.25 has XHPChild
                : array(),
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
                'desc' => null,
            ),
            $abs['phpDoc']
        );
        $this->assertTrue($abs['viaDebugInfo']);

        //    Properties
        // $this->assertArrayNotHasKey('propNoDebug', $abs['properties']);
        $this->assertTrue($abs['properties']['propNoDebug']['isExcluded']);
        $this->assertTrue($abs['properties']['debug']['value']['isExcluded']);
        $this->assertTrue($abs['properties']['instance']['value']['isRecursion']);
        $this->assertArraySubset(
            array(
                'visibility' => 'public',
                'value' => 'redefined in Test (public)',
                'valueFrom' => 'value',
                'overrides' => 'bdk\DebugTest\TestBase',
                'originallyDeclared' => 'bdk\DebugTest\TestBase',
            ),
            $abs['properties']['propPublic']
        );
        $this->assertArraySubset(
            array(
                'visibility' => 'public',
                // 'value' => 'This property is debug only',
                'valueFrom' => 'value',
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
                'valueFrom' => 'value',
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
                'valueFrom' => 'debugInfo',
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
                'valueFrom' => 'value',
            ),
            $abs['properties']['testBasePrivate']
        );
        $this->assertArraySubset(
            array(
                'value' => 'This property is debug only',
                'valueFrom' => 'debugInfo',
            ),
            $abs['properties']['debugValue']
        );

        //    Methods
        $this->assertArrayNotHasKey('testBasePrivate', $abs['methods']);
        $this->assertTrue($abs['methods']['methodPublic']['isDeprecated']);
    }

    public function testVariadic()
    {
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            return;
        }
        $testVar = new \bdk\DebugTest\TestVariadic();
        $abs = $this->debug->abstracter->getAbstraction($testVar);
        $this->assertSame('...$moreParams', $abs['methods']['methodVariadic']['params']['moreParams']['name']);
    }

    public function testVariadicByReference()
    {
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            return;
        }
        if (defined('HHVM_VERSION')) {
            return;
        }
        $testVarByRef = new \bdk\DebugTest\TestVariadicByReference();
        $abs = $this->debug->abstracter->getAbstraction($testVarByRef);
        $this->assertSame('&...$moreParams', $abs['methods']['methodVariadicByReference']['params']['moreParams']['name']);
    }

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetAbstraction()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetMethods()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetParams()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testParamTypeHint()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testGetProperties()
    {
    }
    */

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testParseDocComment()
    {
    }
    */

    /**
     * test handling __debugInfo magic method
     *
     * @return void
     */
    public function testDebugInfo()
    {
        $test = new \bdk\DebugTest\Test();
        $this->debug->log('test', $test);
        $abstraction = $this->debug->getData('log/0/1/1');
        $props = $abstraction['properties'];
        $this->assertArrayNotHasKey('propHidden', $props, 'propHidden shouldn\'t be debugged');
        // debugValue
        $this->assertSame('This property is debug only', $props['debugValue']['value']);
        $this->assertEquals('debug', $props['debugValue']['visibility']);
        // propPrivate
        $this->assertStringEndsWith('(alternate value via __debugInfo)', $props['propPrivate']['value']);
        $this->assertSame('debugInfo', $props['propPrivate']['valueFrom']);
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
        $abstraction = $this->debug->getData('log/0/1/1');
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
        $abstraction = $this->debug->getData('log/0/1/1');
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
        $abstraction = $this->debug->getData('log/0/1/1');
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
        $abstraction = $this->debug->getData('log/0/1/1');
        $this->assertEquals(
            true,
            $abstraction['properties']['ob']['value']['properties']['oa']['value']['isRecursion'],
            'Did not find expected recursion'
        );
        $this->debug->output();
    }
}
