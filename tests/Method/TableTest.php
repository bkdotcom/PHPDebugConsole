<?php

namespace bdk\DebugTests\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\DebugTests\DebugTestFramework;
use ReflectionMethod;

/**
 * PHPUnit tests for Debug class
 */
class TableTest extends DebugTestFramework
{

    /**
     * Test
     *
     * @return void
     */
    public function testTableColKeys()
    {
        $colKeysMeth = new ReflectionMethod('bdk\\Debug\\Method\\Table', 'colKeys');
        $colKeysMeth->setAccessible(true);
        $array = array(
            array('col1' => '', 'col2' => '', 'col4' => ''),
            array('col1' => '', 'col2' => '', 'col3' => ''),
            array('col1' => '', 'col2' => '', 'col3' => ''),
        );
        $colKeys = $colKeysMeth->invoke(null, $array);
        $this->assertSame(array('col1','col2','col3','col4'), $colKeys);
        $array = array(
            array('a','b','c'),
            array('d','e','f','g'),
            array('h','i'),
        );
        $colKeys = $colKeysMeth->invoke(null, $array);
        $this->assertSame(array(0,1,2,3), $colKeys);
    }

    /**
     * @return array
     */
    public function providerTestMethod()
    {
        $rowsA = array(
            // note different order of keys / not all rows have all cols
            4 => array('name' => 'Bob', 'age' => '12', 'sex' => 'M', 'Naughty' => false),
            2 => array('Naughty' => true, 'name' => 'Sally', 'extracol' => 'yes', 'sex' => 'F', 'age' => '10'),
        );
        $rowsAProcessed = array(
            4 => array(
                'name' => 'Bob',
                'age' => '12',
                'sex' => 'M',
                'Naughty' => false,
                'extracol' => Abstracter::UNDEFINED,
            ),
            2 => array(
                'name' => 'Sally',
                'age' => '10',
                'sex' => 'F',
                'Naughty' => true,
                'extracol' => 'yes',
            ),
        );

        // not all date2 values of same type
        $rowsB = array(
            array(
                'date' => new \DateTime('1955-11-05'),
                'date2' => 'not a datetime',
            ),
            array(
                'date' => new \DateTime('1985-10-26'),
                'date2' => new \DateTime('2015-10-21'),
            )
        );
        $rowsAHtml = <<<'EOD'
<li class="m_table">
<table class="sortable table-bordered">
<caption>table caption</caption>
<thead>
<tr><th>&nbsp;</th><th>name</th><th scope="col">age</th><th scope="col">sex</th><th scope="col">Naughty</th><th scope="col">extracol</th></tr>
</thead>
<tbody>
<tr><th class="t_int t_key text-right" scope="row">4</th><td class="t_string">Bob</td><td class="numeric t_string">12</td><td class="t_string">M</td><td class="false t_bool">false</td><td class="t_undefined"></td></tr>
<tr><th class="t_int t_key text-right" scope="row">2</th><td class="t_string">Sally</td><td class="numeric t_string">10</td><td class="t_string">F</td><td class="t_bool true">true</td><td class="t_string">yes</td></tr>
</tbody>
</table>
</li>
EOD;
        $rowsAText = <<<'EOD'
table caption = array(
    [4] => array(
        [name] => "Bob"
        [age] => "12"
        [sex] => "M"
        [Naughty] => false
    )
    [2] => array(
        [name] => "Sally"
        [age] => "10"
        [sex] => "F"
        [Naughty] => true
        [extracol] => "yes"
    )
)
EOD;
        $rowsAScript = 'console.table({"4":{"name":"Bob","age":"12","sex":"M","Naughty":false,"extracol":undefined},"2":{"name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});';
        $rowsAFirephp = 'X-Wf-1-1-1-3: %d|[{"Label":"table caption","Type":"TABLE"},[["","name","age","sex","Naughty","extracol"],[4,"Bob","12","M",false,null],[2,"Sally","10","F",true,"yes"]]]|';

        $dateTimePubMethods = 3;
        if (\version_compare(PHP_VERSION, '7.1', '>=')) {
            $dateTimePubMethods = 5;
        } elseif (\version_compare(PHP_VERSION, '7.0', '>=')) {
            $dateTimePubMethods = 4;
        }

        $vals = array(
            'datetime' => new \DateTime('2233-03-22'),
            'resource' => \fopen(__FILE__, 'r'),
            'callable' => array($this, __FUNCTION__),
            'closure' => function ($foo) {
                echo $foo;
            },
        );
        $abstracter = \bdk\Debug::getInstance()->abstracter;
        foreach ($vals as $k => $raw) {
            $vals[$k] = array(
                'raw' => $raw,
                'crated' => $abstracter->crate($raw, 'table')->jsonSerialize(),
            );
        }

        return array(
            // 0 null
            array(
                'table',
                array(null),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(null),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_null">null</span></li>',
                    'text' => 'null',
                    'script' => 'console.log(null);',
                    'firephp' => 'X-Wf-1-1-1-1: 21|[{"Type":"LOG"},null]|',
                ),
            ),
            // 1 empty array
            array(
                'table',
                array('arg1', array()),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array('arg1', array()),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log">'
                        . '<span class="no-quotes t_string">arg1</span> = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">()</span></span>'
                        . '</li>',
                    'text' => 'arg1 = array()',
                    'script' => 'console.log("arg1",[]);',
                    'firephp' => 'X-Wf-1-1-1-2: %d|[{"Label":"arg1","Type":"LOG"},[]]|',
                ),
            ),
            // 2 not table material
            array(
                'table',
                array('arg1'),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array('arg1'),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log">'
                        . '<span class="no-quotes t_string">arg1</span>'
                        . '</li>',
                    'text' => 'arg1',
                    'script' => 'console.log("arg1");',
                    'firephp' => 'X-Wf-1-1-1-2: %d|[{"Type":"LOG"},"arg1"]|',
                ),
            ),
            // 3 not table material with label
            array(
                'table',
                array('arg1', 'arg2'),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array('arg1', 'arg2'),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log">'
                        . '<span class="no-quotes t_string">arg1</span> = <span class="t_string">arg2</span>'
                        . '</li>',
                    'text' => 'arg1 = "arg2"',
                    'script' => 'console.log("arg1","arg2");',
                    'firephp' => 'X-Wf-1-1-1-2: %d|[{"Label":"arg1","Type":"LOG"},"arg2"]|',
                ),
            ),
            // 4 superfluous args
            array(
                'table',
                array('arg1', 'arg2 is not logged', $rowsA, 'arg4 is not logged'),
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => array($rowsAProcessed),
                        'meta' => array(
                            'caption' => 'arg1',
                            'sortable' => true,
                            'tableInfo' => array(
                                'class' => null,
                                'columns' => array(
                                    array('key' => 'name'),
                                    array('key' => 'age'),
                                    array('key' => 'sex'),
                                    array('key' => 'Naughty'),
                                    array('key' => 'extracol'),
                                ),
                                'haveObjRow' => false,
                                'rows' => array(),
                                'summary' => null,
                            ),
                        ),
                    ),
                    'html' => \str_replace('table caption', 'arg1', $rowsAHtml),
                    'text' => \str_replace('table caption', 'arg1', $rowsAText),
                    'script' => $rowsAScript,
                    'firephp' => \str_replace('table caption', 'arg1', $rowsAFirephp),
                ),
            ),
            // 5 rowsA
            array(
                'table',
                array('table caption', $rowsA),
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => array($rowsAProcessed),
                        'meta' => array(
                            'caption' => 'table caption',
                            'sortable' => true,
                            'tableInfo' => array(
                                'class' => null,
                                'columns' => array(
                                    array('key' => 'name'),
                                    array('key' => 'age'),
                                    array('key' => 'sex'),
                                    array('key' => 'Naughty'),
                                    array('key' => 'extracol'),
                                ),
                                'haveObjRow' => false,
                                'rows' => array(),
                                'summary' => null,
                            )
                        ),
                    ),
                    'html' => $rowsAHtml,
                    'text' => $rowsAText,
                    'script' => $rowsAScript,
                    'firephp' => $rowsAFirephp,
                ),
            ),
            // 6 rowsA - specify columns
            array(
                'table',
                array($rowsA, 'table caption', array('name','extracol')),
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => array(
                            array(
                                4 => array('name' => 'Bob', 'extracol' => Abstracter::UNDEFINED),
                                2 => array('name' => 'Sally', 'extracol' => 'yes'),
                            ),
                        ),
                        'meta' => array(
                            'caption' => 'table caption',
                            'sortable' => true,
                            'tableInfo' => array(
                                'class' => null,
                                'columns' => array(
                                    array('key' => 'name'),
                                    array('key' => 'extracol'),
                                ),
                                'haveObjRow' => false,
                                'rows' => array(),
                                'summary' => null,
                            )
                        ),
                    ),
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>table caption</caption>
                        <thead>
                        <tr><th>&nbsp;</th><th>name</th><th scope="col">extracol</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">4</th><td class="t_string">Bob</td><td class="t_undefined"></td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">2</th><td class="t_string">Sally</td><td class="t_string">yes</td></tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'table caption = array(
                            [4] => array(
                                [name] => "Bob"
                            )
                            [2] => array(
                                [name] => "Sally"
                                [extracol] => "yes"
                            )
                        )',
                    'script' => 'console.table({"4":{"name":"Bob","extracol":undefined},"2":{"name":"Sally","extracol":"yes"}});',
                    'firephp' => 'X-Wf-1-1-1-20: %d|[{"Label":"table caption","Type":"TABLE"},[["","name","extracol"],[4,"Bob",null],[2,"Sally","yes"]]]|',
                ),
            ),
            // 7 flat
            array(
                'table',
                array(
                    'flat',
                    array(
                        'a',
                        $vals['datetime']['raw'],
                        $vals['resource']['raw'],
                        $vals['callable']['raw'],
                        $vals['closure']['raw'],
                    ),
                ),
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => array(
                            array(
                                array('value' => 'a'),
                                array('value' => $vals['datetime']['crated']['stringified']),
                                array('value' => $vals['resource']['crated']),
                                array('value' => $vals['callable']['crated']),
                                array('value' => $vals['closure']['crated']),
                            ),
                        ),
                        'meta' => array(
                            'caption' => 'flat',
                            'sortable' => true,
                            'tableInfo' => array(
                                'class' => null,
                                'columns' => array(
                                    array('key' => 'value'),
                                ),
                                'haveObjRow' => false,
                                'rows' => array(
                                    array('isScalar' => true),
                                    array(
                                        // 'class' => 'DateTime',
                                        'isScalar' => true
                                    ),
                                    array('isScalar' => true),
                                    array('isScalar' => true),
                                    array('isScalar' => true),
                                ),
                                'summary' => null,
                            ),
                        ),
                    ),
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>flat</caption>
                        <thead>
                        <tr><th>&nbsp;</th><th>value</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">0</th><td class="t_string">a</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">1</th><td class="t_string">2233-03-22T00:00:00%i</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">2</th><td class="t_resource">Resource id #%d: stream</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">3</th><td class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">bdk\DebugTests\Method\</span>TableTest</span><span class="t_operator">::</span><span class="t_identifier">providerTestMethod</span></td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">4</th><td class="t_object" data-accessible="public"><span class="classname">Closure</span>
                            <dl class="object-inner">
                            <dt class="properties">properties</dt>
                            <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">string</span> <span class="t_identifier">file</span> <span class="t_operator">=</span> <span class="t_string">' . __FILE__ . '</span></dd>
                            <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">int</span> <span class="t_identifier">line</span> <span class="t_operator">=</span> <span class="t_int">%i</span></dd>
                            <dt class="methods">methods</dt>' . "\n"
                            . (PHP_VERSION_ID >= 80000
                                ? '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">__invoke</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_type"><span class="classname">Closure</span></span> <span class="t_identifier">bind</span><span class="t_punct">(</span><span class="parameter"><span class="t_type"><span class="classname">Closure</span></span> <span class="t_parameter-name">$closure</span></span>, <span class="parameter"><span class="t_type">object</span> <span class="t_parameter-name">$newThis</span></span>, <span class="parameter"><span class="t_type">object</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">null</span> <span class="t_parameter-name">$newScope</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">static</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type"><span class="classname">Closure</span></span> <span class="t_identifier">bindTo</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">object</span> <span class="t_parameter-name">$newThis</span></span>, <span class="parameter"><span class="t_type">object</span><span class="t_punct">|</span><span class="t_type">string</span><span class="t_punct">|</span><span class="t_type">null</span> <span class="t_parameter-name">$newScope</span> <span class="t_operator">=</span> <span class="t_parameter-default t_string">static</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_type">mixed</span> <span class="t_identifier">call</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">object</span> <span class="t_parameter-name">$newThis</span></span>, <span class="parameter"><span class="t_type">mixed</span> <span class="t_parameter-name">...$args</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_type"><span class="classname">Closure</span></span> <span class="t_identifier">fromCallable</span><span class="t_punct">(</span><span class="parameter"><span class="t_type">callable</span> <span class="t_parameter-name">$callback</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method private"><span class="t_modifier_private">private</span> <span class="t_identifier">__construct</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>'
                                : '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">__invoke</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">bind</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$closure</span></span>, <span class="parameter"><span class="t_parameter-name">$newthis</span></span>, <span class="parameter"><span class="t_parameter-name">$newscope</span></span><span class="t_punct">)</span></dd>
                                    <dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">bindTo</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$newthis</span></span>, <span class="parameter"><span class="t_parameter-name">$newscope</span></span><span class="t_punct">)</span></dd>
                                    ' . (\version_compare(PHP_VERSION, '7.0', '>=') ? '<dd class="method public"><span class="t_modifier_public">public</span> <span class="t_identifier">call</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$newthis</span></span>, <span class="parameter"><span class="t_parameter-name">...$parameters</span></span><span class="t_punct">)</span></dd>' . "\n" : '')
                                    . (\version_compare(PHP_VERSION, '7.1', '>=') ? '<dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="t_identifier">fromCallable</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$callable</span></span><span class="t_punct">)</span></dd>' . "\n" : '')
                                    . '<dd class="method private"><span class="t_modifier_private">private</span> <span class="t_identifier">__construct</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>'
                            ) . '
                            </dl>
                        </td></tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'flat = array(
                        [0] => "a"
                        [1] => "2233-03-22T00:00:00%i"
                        [2] => Resource id #%d: stream
                        [3] => callable: bdk\DebugTests\Method\TableTest::providerTestMethod
                        [4] => Closure
                            Properties:
                                (debug) file = "' . __FILE__ . '"
                                (debug) line = %i
                            Methods:
                                public: ' . $dateTimePubMethods . '
                                private: 1
                    )',
                    'script' => 'console.table(["a","2233-03-22T00:00:00%i","Resource id #%d: stream",' . \json_encode('callable: ' . __CLASS__ . '::providerTestMethod') . ',{"___class_name":"Closure","(debug) file":"' . __FILE__ . '","(debug) line":%i}]);',
                    'firephp' => 'X-Wf-1-1-1-4: %d|[{"Label":"flat","Type":"TABLE"},[["","value"],[0,"a"],[1,"2233-03-22T00:00:00%i"],[2,"Resource id #%d: stream"],[3,' . \json_encode('callable: ' . __CLASS__ . '::providerTestMethod') . '],[4,{"___class_name":"Closure","(debug) file":"' . __FILE__ . '","(debug) line":%i}]]]|',
                ),
            ),
            // 8 traversavle
            array(
                'table',
                array(
                    'traversable',
                    new \bdk\DebugTests\Fixture\TestTraversable($rowsA),
                ),
                array(
                    'html' => \str_replace('table caption', 'traversable (<span class="classname" title="I implement Traversable!"><span class="namespace">bdk\DebugTests\Fixture\</span>TestTraversable</span>)', $rowsAHtml),
                    'text' => \str_replace('table caption', 'traversable', $rowsAText),
                    'script' => $rowsAScript,
                    'firephp' => 'X-Wf-1-1-1-5: 149|[{"Label":"traversable","Type":"TABLE"},[["","name","age","sex","Naughty","extracol"],[4,"Bob","12","M",false,null],[2,"Sally","10","F",true,"yes"]]]|',
                ),
            ),
            // 9 traversable -o- traversables
            array(
                'table',
                array(
                    'traversable -o- traversables',
                    new \bdk\DebugTests\Fixture\TestTraversable(array(
                        4 => new \bdk\DebugTests\Fixture\TestTraversable($rowsA[4]),
                        2 => new \bdk\DebugTests\Fixture\TestTraversable($rowsA[2]),
                    )),
                ),
                array(
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>traversable -o- traversables (<span class="classname" title="I implement Traversable!"><span class="namespace">bdk\DebugTests\Fixture\</span>TestTraversable</span>)</caption>
                        <thead>
                        <tr><th>&nbsp;</th><th>&nbsp;</th><th>name</th><th scope="col">age</th><th scope="col">sex</th><th scope="col">Naughty</th><th scope="col">extracol</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">4</th><td class="classname" title="I implement Traversable!"><span class="namespace">bdk\DebugTests\Fixture\</span>TestTraversable</td><td class="t_string">Bob</td><td class="numeric t_string">12</td><td class="t_string">M</td><td class="false t_bool">false</td><td class="t_undefined"></td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">2</th><td class="classname" title="I implement Traversable!"><span class="namespace">bdk\DebugTests\Fixture\</span>TestTraversable</td><td class="t_string">Sally</td><td class="numeric t_string">10</td><td class="t_string">F</td><td class="t_bool true">true</td><td class="t_string">yes</td></tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'traversable -o- traversables = array(
                            [4] => array(
                                [___class_name] => "bdk\DebugTests\Fixture\TestTraversable"
                                [name] => "Bob"
                                [age] => "12"
                                [sex] => "M"
                                [Naughty] => false
                            )
                            [2] => array(
                                [___class_name] => "bdk\DebugTests\Fixture\TestTraversable"
                                [name] => "Sally"
                                [age] => "10"
                                [sex] => "F"
                                [Naughty] => true
                                [extracol] => "yes"
                            )
                        )',
                    'script' => 'console.table({"4":{"___class_name":"bdk\\\DebugTests\\\Fixture\\\TestTraversable","name":"Bob","age":"12","sex":"M","Naughty":false,"extracol":undefined},"2":{"___class_name":"bdk\\\DebugTests\\\Fixture\\\TestTraversable","name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});',
                    'firephp' => 'X-Wf-1-1-1-6: 270|[{"Label":"traversable -o- traversables","Type":"TABLE"},['
                        . '["","___class_name","name","age","sex","Naughty","extracol"],'
                        . '[4,"bdk\\\DebugTests\\\Fixture\\\TestTraversable","Bob","12","M",false,null],'
                        . '[2,"bdk\\\DebugTests\\\Fixture\\\TestTraversable","Sally","10","F",true,"yes"]]]|',
                ),
            ),
            // 10 array -o- objects
            array(
                'table',
                array(
                    'array -o- objects',
                    array(
                        4 => (object) $rowsA[4],
                        2 => (object) $rowsA[2],
                    ),
                ),
                array(
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>array -o- objects</caption>
                        <thead>
                        <tr><th>&nbsp;</th><th>&nbsp;</th><th>age</th><th scope="col">extracol</th><th scope="col">name</th><th scope="col">Naughty</th><th scope="col">sex</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">4</th><td class="classname">stdClass</td><td class="numeric t_string">12</td><td class="t_undefined"></td><td class="t_string">Bob</td><td class="false t_bool">false</td><td class="t_string">M</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">2</th><td class="classname">stdClass</td><td class="numeric t_string">10</td><td class="t_string">yes</td><td class="t_string">Sally</td><td class="t_bool true">true</td><td class="t_string">F</td></tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'array -o- objects = array(
                        [4] => array(
                            [___class_name] => "stdClass"
                            [age] => "12"
                            [name] => "Bob"
                            [Naughty] => false
                            [sex] => "M"
                            )
                        [2] => array(
                            [___class_name] => "stdClass"
                            [age] => "10"
                            [extracol] => "yes"
                            [name] => "Sally"
                            [Naughty] => true
                            [sex] => "F"
                        )
                    )',
                    'script' => 'console.table({"4":{"___class_name":"stdClass","age":"12","extracol":undefined,"name":"Bob","Naughty":false,"sex":"M"},"2":{"___class_name":"stdClass","age":"10","extracol":"yes","name":"Sally","Naughty":true,"sex":"F"}});',
                    'firephp' => 'X-Wf-1-1-1-7: 193|[{"Label":"array -o- objects","Type":"TABLE"},['
                        . '["","___class_name","age","extracol","name","Naughty","sex"],'
                        . '[4,"stdClass","12",null,"Bob",false,"M"],'
                        . '[2,"stdClass","10","yes","Sally",true,"F"]]]|',
                ),
            ),
            // 11 rowsB (not all col values of same type)
            array(
                'table',
                array(
                    'not all col values of same type',
                    $rowsB,
                ),
                array(
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>not all col values of same type</caption>
                        <thead>
                        <tr><th>&nbsp;</th><th>date <span class="classname">DateTime</span></th><th scope="col">date2</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">0</th><td class="t_string">1955-11-05T00:00:00%i</td><td class="t_string">not a datetime</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">1</th><td class="t_string">1985-10-26T00:00:00%i</td><td class="t_string">2015-10-21T00:00:00%i</td></tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'not all col values of same type = array(
                        [0] => array(
                            [date] => "1955-11-05T00:00:00%i"
                            [date2] => "not a datetime"
                        )
                        [1] => array(
                            [date] => "1985-10-26T00:00:00%i"
                            [date2] => "2015-10-21T00:00:00%i"
                        )
                    )',
                    'script' => 'console.table([{"date":"1955-11-05T00:00:00%i","date2":"not a datetime"},{"date":"1985-10-26T00:00:00%i","date2":"2015-10-21T00:00:00%i"}]);',
                    'firephp' => 'X-Wf-1-1-1-8: 188|[{"Label":"not all col values of same type","Type":"TABLE"},[["","date","date2"],[0,"1955-11-05T00:00:00%i","not a datetime"],[1,"1985-10-26T00:00:00%i","2015-10-21T00:00:00%i"]]]|',
                ),
            ),
        );
    }
}
