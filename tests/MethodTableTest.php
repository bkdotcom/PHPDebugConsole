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
        if (!$this->refMethods) {
            foreach (array('html','text','script') as $outputAs) {
                $obj = $this->debug->output->{$outputAs};
                $reflectionMethod = new \reflectionMethod(get_class($obj), 'doProcessLogEntry');
                $reflectionMethod->setAccessible(true);
                $this->refMethods[$outputAs] = $reflectionMethod;
            }
        }
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
                array('a','b','c'),
                array(),
                '<table class="m_table table-bordered sortable">
                <thead><tr><th>&nbsp;</th><th>value</th></tr>
                </thead>
                <tbody>
                <tr><th class="t_key t_int" scope="row">0</th><td class="t_string">a</td></tr>
                <tr><th class="t_key t_int" scope="row">1</th><td class="t_string">b</td></tr>
                <tr><th class="t_key t_int" scope="row">2</th><td class="t_string">c</td></tr>
                </tbody>
                </table>',
                'array(
                    [0] => array(
                        [] => "a"
                    )
                    [1] => array(
                        [] => "b"
                    )
                    [2] => array(
                        [] => "c"
                    )
                )',
                'console.table([{"":"a"},{"":"b"},{"":"c"}]);',
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
                <thead><tr><th>&nbsp;</th><th>&nbsp;</th><th>Naughty</th><th scope="col">age</th><th scope="col">extracol</th><th scope="col">name</th><th scope="col">sex</th></tr>
                </thead>
                <tbody>
                <tr><th class="t_key t_int" scope="row">4</th><td class="t_classname">stdClass</td><td class="t_bool false">false</td><td class="t_string numeric">12</td><td class="t_undefined"></td><td class="t_string">Bob</td><td class="t_string">M</td></tr>
                <tr><th class="t_key t_int" scope="row">2</th><td class="t_classname">stdClass</td><td class="t_bool true">true</td><td class="t_string numeric">10</td><td class="t_string">yes</td><td class="t_string">Sally</td><td class="t_string">F</td></tr>
                </tbody>
                </table>',
                'array -o- objects = array(
                    [4] => array(
                        [___class_name] => "stdClass"
                        [Naughty] => false
                        [age] => "12"
                        [name] => "Bob"
                        [sex] => "M"
                    )
                    [2] => array(
                        [___class_name] => "stdClass"
                        [Naughty] => true
                        [age] => "10"
                        [extracol] => "yes"
                        [name] => "Sally"
                        [sex] => "F"
                    )
                )',
                'console.table({"4":{"___class_name":"stdClass","Naughty":false,"age":"12","name":"Bob","sex":"M"},"2":{"___class_name":"stdClass","Naughty":true,"age":"10","extracol":"yes","name":"Sally","sex":"F"}});',
            ),
            // @todo resource & callable
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
        foreach ($dumps as $outputAs => $dumpExpect) {
            $obj = $this->debug->output->{$outputAs};
            $output = $this->refMethods[$outputAs]->invoke($obj, $logEntry[0], $logEntry[1], $logEntry[2]);
            $output = trim($output);
            if (is_callable($dumpExpect)) {
                $dumpExpect($output);
            } elseif (is_array($dumpExpect) && isset($dumpExpect['contains'])) {
                $this->assertContains($dumpExpect['contains'], $output, $outputAs.' doesn\'t contain');
            } else {
                $output = preg_replace("#^\s+#m", '', $output);
                $dumpExpect = preg_replace('#^\s+#m', '', $dumpExpect);
                $this->assertSame($dumpExpect, $output, $outputAs.' not same');
            }
        }
    }
}
