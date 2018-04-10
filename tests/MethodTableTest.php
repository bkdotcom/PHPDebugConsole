<?php

use bdk\Debug\Table;

/**
 * PHPUnit tests for Debug class
 */
class MethodTableTest extends DebugTestFramework
{

    private $refMethods;

    public function setUp()
    {
        parent::setUp();
        /*
        if (!$this->refMethods) {
            foreach (array('html','text','script') as $outputAs) {
                $obj = $this->debug->output->{$outputAs};
                $reflectionMethod = new \reflectionMethod(get_class($obj), 'processLogEntry');
                $reflectionMethod->setAccessible(true);
                $this->refMethods[$outputAs] = $reflectionMethod;
            }
        }
        */
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTableColKeys()
    {
        $array = array(
            array('col1'=>'', 'col2'=>'', 'col4'=>''),
            array('col1'=>'', 'col2'=>'', 'col3'=>''),
            array('col1'=>'', 'col2'=>'', 'col3'=>''),
        );
        $colKeys = Table::colKeys($array);
        $this->assertSame(array('col1','col2','col3','col4'), $colKeys);
        $array = array(
            array('a','b','c'),
            array('d','e','f','g'),
            array('h','i'),
        );
        $colKeys = Table::colKeys($array);
        $this->assertSame(array(0,1,2,3), $colKeys);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTableLog()
    {
        $list = array(
            // note different order of keys / not all rows have all cols
            array('name'=>'Bob', 'age'=>'12', 'sex'=>'M', 'Naughty'=>false),
            array('Naughty'=>true, 'name'=>'Sally', 'extracol' => 'yes', 'sex'=>'F', 'age'=>'10'),
        );
        $this->debug->table($list);
        $this->debug->table('arg1', array());
        $this->debug->table('arg1', 'arg2 is not logged', $list, 'arg4 is not logged');
        $this->debug->table($list, 'arg2', array('arg3 is array'), 'arg4 is not logged');
        $this->debug->table('arg1', 'arg2');
        $this->debug->table('flat', array('a','b','c'));
        // test stored args
        $this->assertSame(array(
            'table',
            array($list),
            array('caption'=>null, 'columns'=>array()),
        ), $this->debug->getData('log/0'));
        $this->assertSame(array(
            'log',
            array('arg1', array()),
            array(),
        ), $this->debug->getData('log/1'));
        $this->assertSame(array(
            'table',
            array($list),
            array(
                'caption'=>'arg1',
                'columns'=>array(),
            ),
        ), $this->debug->getData('log/2'));
        $this->assertSame(array(
            'table',
            array($list),
            array(
                'caption'=>'arg2',
                'columns'=>array('arg3 is array'),
            ),
        ), $this->debug->getData('log/3'));
        $this->assertSame(array('log', array('arg1', 'arg2'), array()), $this->debug->getData('log/4'));
        $this->assertSame(array(
            'table',
            array(
                array('a', 'b', 'c'),
            ),
            array(
                'caption'=>'flat',
                'columns'=>array(),
            ),
        ), $this->debug->getData('log/5'));
    }

    /**
     * Override me
     *
     * @return array
     */
    public function dumpProvider()
    {
        $rowsA = array(
            // note different order of keys / not all rows have all cols
            4 => array('name'=>'Bob', 'age'=>'12', 'sex'=>'M', 'Naughty'=>false),
            2 => array('Naughty'=>true, 'name'=>'Sally', 'extracol' => 'yes', 'sex'=>'F', 'age'=>'10'),
        );
        $rowsB = array(
            array(
                'date' => new DateTime('1955-11-05'),
                'date2' => 'not a datetime',
            ),
            array(
                'date' => new DateTime('1985-10-26'),
                'date2' => new DateTime('2015-10-21'),
            )
        );
        $rowsAHtml = <<<'EOD'
<table class="m_table table-bordered sortable">
<caption>table caption</caption>
<thead><tr><th>&nbsp;</th><th>name</th><th scope="col">age</th><th scope="col">sex</th><th scope="col">Naughty</th><th scope="col">extracol</th></tr>
</thead>
<tbody>
<tr><th class="t_key t_int" scope="row">4</th><td class="t_string">Bob</td><td class="t_string numeric">12</td><td class="t_string">M</td><td class="t_bool false">false</td><td class="t_undefined"></td></tr>
<tr><th class="t_key t_int" scope="row">2</th><td class="t_string">Sally</td><td class="t_string numeric">10</td><td class="t_string">F</td><td class="t_bool true">true</td><td class="t_string">yes</td></tr>
</tbody>
</table>
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
        return array(
            // val, html, text, script
            array(
                null,
                array(),
                '<div class="m_log"><span class="t_null">null</span></div>',
                'null',
                'console.log(null);',
            ),
            array(
                'blah',
                array(),
                '<div class="m_log"><span class="t_string no-pseudo">blah</span></div>',
                'blah',
                'console.log("blah");',
            ),
            array(
                $rowsA,
                array('caption' => 'table caption'),
                $rowsAHtml,
                $rowsAText,
                'console.table({"4":{"name":"Bob","age":"12","sex":"M","Naughty":false},"2":{"name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});',
            ),
            array(
                array(
                    'a',
                    new DateTime('2233-03-22'),
                    fopen(__FILE__, 'r'),
                    array($this, __FUNCTION__),
                    function ($foo) {
                    },
                ),
                array('caption' => 'flat'),
                '<table class="m_table table-bordered sortable">
                <caption>flat</caption>
                <thead><tr><th>&nbsp;</th><th>value</th></tr>
                </thead>
                <tbody>
                <tr><th class="t_key t_int" scope="row">0</th><td class="t_string">a</td></tr>
                <tr><th class="t_key t_int" scope="row">1</th><td class="t_string">2233-03-22T00:00:00%i</td></tr>
                <tr><th class="t_key t_int" scope="row">2</th><td class="t_resource">Resource id #%d: stream</td></tr>
                <tr><th class="t_key t_int" scope="row">3</th><td class="t_callable"><span class="t_type">callable</span> <span class="t_classname">MethodTableTest</span><span class="t_operator">::</span><span class="method-name">dumpProvider</span></td></tr>
                <tr><th class="t_key t_int" scope="row">4</th><td class="t_object" data-accessible="public"><span class="t_classname">Closure</span>
                <dl class="object-inner">
                <dt class="properties">no properties</dt>
                <dt class="methods">methods</dt>
                <dd class="method public"><span class="t_modifier_public">public</span> <span class="method-name">__invoke</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$foo</span></span><span class="t_punct">)</span></dd>
                <dd class="method public static"><span class="t_modifier_public">public</span> <span class="t_modifier_static">static</span> <span class="method-name">bind</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$closure</span></span>, <span class="parameter"><span class="t_parameter-name">$newthis</span></span>, <span class="parameter"><span class="t_parameter-name">$newscope</span></span><span class="t_punct">)</span></dd>
                <dd class="method public"><span class="t_modifier_public">public</span> <span class="method-name">bindTo</span><span class="t_punct">(</span><span class="parameter"><span class="t_parameter-name">$newthis</span></span>, <span class="parameter"><span class="t_parameter-name">$newscope</span></span><span class="t_punct">)</span></dd>
                <dd class="method private"><span class="t_modifier_private">private</span> <span class="method-name">__construct</span><span class="t_punct">(</span><span class="t_punct">)</span></dd>
                </dl>
                </td></tr>
                </tbody>
                </table>',
                'flat = array(
                    [0] => "a"
                    [1] => "2233-03-22T00:00:00%i"
                    [2] => Resource id #%d: stream
                    [3] => callable: MethodTableTest::dumpProvider
                    [4] => (object) Closure
                        Properties: none!
                        Methods:
                            public: 3
                            private: 1
                )',
                'console.table(["a","2233-03-22T00:00:00%i","Resource id #%d: stream","callable: MethodTableTest::dumpProvider",{"___class_name":"Closure"}]);',
            ),
            array(
                new \bdk\DebugTest\TestTraversable($rowsA),
                array('caption' => 'traversable'),
                str_replace('table caption', 'traversable', $rowsAHtml),
                str_replace('table caption', 'traversable', $rowsAText),
                'console.table({"4":{"name":"Bob","age":"12","sex":"M","Naughty":false},"2":{"name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});',
            ),
            array(
                new \bdk\DebugTest\TestTraversable(array(
                    4 => new \bdk\DebugTest\TestTraversable($rowsA[4]),
                    2 => new \bdk\DebugTest\TestTraversable($rowsA[2]),
                )),
                array('caption' => 'traversable -o- traversables'),
                '<table class="m_table table-bordered sortable">
                <caption>traversable -o- traversables</caption>
                <thead><tr><th>&nbsp;</th><th>&nbsp;</th><th>name</th><th scope="col">age</th><th scope="col">sex</th><th scope="col">Naughty</th><th scope="col">extracol</th></tr>
                </thead>
                <tbody>
                <tr><th class="t_key t_int" scope="row">4</th><td class="t_classname" title="I implement Traversable!"><span class="namespace">bdk\DebugTest\</span>TestTraversable</td><td class="t_string">Bob</td><td class="t_string numeric">12</td><td class="t_string">M</td><td class="t_bool false">false</td><td class="t_undefined"></td></tr>
                <tr><th class="t_key t_int" scope="row">2</th><td class="t_classname" title="I implement Traversable!"><span class="namespace">bdk\DebugTest\</span>TestTraversable</td><td class="t_string">Sally</td><td class="t_string numeric">10</td><td class="t_string">F</td><td class="t_bool true">true</td><td class="t_string">yes</td></tr>
                </tbody>
                </table>',
                'traversable -o- traversables = array(
                    [4] => array(
                        [___class_name] => "bdk\DebugTest\TestTraversable"
                        [name] => "Bob"
                        [age] => "12"
                        [sex] => "M"
                        [Naughty] => false
                    )
                    [2] => array(
                        [___class_name] => "bdk\DebugTest\TestTraversable"
                        [name] => "Sally"
                        [age] => "10"
                        [sex] => "F"
                        [Naughty] => true
                        [extracol] => "yes"
                    )
                )',
                'console.table({"4":{"___class_name":"bdk\\\DebugTest\\\TestTraversable","name":"Bob","age":"12","sex":"M","Naughty":false},"2":{"___class_name":"bdk\\\DebugTest\\\TestTraversable","name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});',
            ),
            array(
                array(
                    4 => (object) $rowsA[4],
                    2 => (object) $rowsA[2],
                ),
                array('caption' => 'array -o- objects'),
                '<table class="m_table table-bordered sortable">
                <caption>array -o- objects</caption>
                <thead><tr><th>&nbsp;</th><th>&nbsp;</th><th>age</th><th scope="col">extracol</th><th scope="col">name</th><th scope="col">Naughty</th><th scope="col">sex</th></tr>
                </thead>
                <tbody>
                <tr><th class="t_key t_int" scope="row">4</th><td class="t_classname">stdClass</td><td class="t_string numeric">12</td><td class="t_undefined"></td><td class="t_string">Bob</td><td class="t_bool false">false</td><td class="t_string">M</td></tr>
                <tr><th class="t_key t_int" scope="row">2</th><td class="t_classname">stdClass</td><td class="t_string numeric">10</td><td class="t_string">yes</td><td class="t_string">Sally</td><td class="t_bool true">true</td><td class="t_string">F</td></tr>
                </tbody>
                </table>',
                'array -o- objects = array(
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
                'console.table({"4":{"___class_name":"stdClass","age":"12","name":"Bob","Naughty":false,"sex":"M"},"2":{"___class_name":"stdClass","age":"10","extracol":"yes","name":"Sally","Naughty":true,"sex":"F"}});',
            ),
            array(
                $rowsB,
                array('caption' => 'not all col values of same type'),
                '<table class="m_table table-bordered sortable">
                <caption>not all col values of same type</caption>
                <thead><tr><th>&nbsp;</th><th>date <span class="t_classname">DateTime</span></th><th scope="col">date2</th></tr>
                </thead>
                <tbody>
                <tr><th class="t_key t_int" scope="row">0</th><td class="t_string">1955-11-05T00:00:00%i</td><td class="t_string">not a datetime</td></tr>
                <tr><th class="t_key t_int" scope="row">1</th><td class="t_string">1985-10-26T00:00:00%i</td><td class="t_string">2015-10-21T00:00:00%i</td></tr>
                </tbody>
                </table>',
                'not all col values of same type = array(
                    [0] => array(
                        [date] => "1955-11-05T00:00:00%i"
                        [date2] => "not a datetime"
                    )
                    [1] => array(
                        [date] => "1985-10-26T00:00:00%i"
                        [date2] => "2015-10-21T00:00:00%i"
                    )
                )',
                'console.table([{"date":"1955-11-05T00:00:00%i","date2":"not a datetime"},{"date":"1985-10-26T00:00:00%i","date2":"2015-10-21T00:00:00%i"}]);',
            ),
        );
    }

    /**
     * Test
     *
     * @dataProvider dumpProvider
     *
     * @return void
     */
    public function testDump($val, $meta, $html, $text, $script)
    {
        $dumps = array(
            'html' => $html,
            'text' => $text,
            'script' => $script,
        );
        $this->debug->table($val, $this->debug->meta($meta));
        $logEntry = $this->debug->getData('log/0');
        foreach ($dumps as $outputAs => $outputExpect) {
            // $obj = $this->debug->output->{$outputAs};
            // $output = $this->refMethods[$outputAs]->invoke($obj, $logEntry[0], $logEntry[1], $logEntry[2]);
            $output = $this->debug->output->{$outputAs}->processLogEntry($logEntry[0], $logEntry[1], $logEntry[2]);
            $output = trim($output);
            if (is_callable($outputExpect)) {
                $outputExpect($output);
            } elseif (is_array($outputExpect) && isset($outputExpect['contains'])) {
                $this->assertContains($outputExpect['contains'], $output, $outputAs.' doesn\'t contain');
            } else {
                $output = preg_replace("#^\s+#m", '', $output);
                $outputExpect = preg_replace('#^\s+#m', '', $outputExpect);
                $this->assertStringMatchesFormat($outputExpect, $output, $outputAs.' not same');
            }
        }
    }
}
