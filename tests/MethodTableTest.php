<?php

/**
 * PHPUnit tests for Debug class
 */
class MethodTableTest extends DebugTestFramework
{

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
        $this->debug->table('arg1', 'arg2', $list, 'arg4');
        $this->debug->table($list, 'arg2', array('arg3 is array'), 'arg4');
        $this->debug->table('arg1', 'arg2');
        // test stored args
        $this->assertSame(array('table', $list, null, array()), $this->debug->getData('log/0'));
        $this->assertSame(array('log', 'arg1', array()), $this->debug->getData('log/1'));
        $this->assertSame(array('table', $list, 'arg1', array()), $this->debug->getData('log/2'));
        $this->assertSame(array('table', $list, 'arg2', array('arg3 is array')), $this->debug->getData('log/3'));
        $this->assertSame(array('log', 'arg1', 'arg2'), $this->debug->getData('log/4'));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testTableDump()
    {
        $list = array(
            // note different order of keys / not all rows have all cols
            array('name'=>'Bob', 'age'=>'12', 'sex'=>'M', 'Naughty'=>false),
            array('Naughty'=>true, 'name'=>'Sally', 'extracol' => 'yes', 'sex'=>'F', 'age'=>'10'),
        );
        $html = $this->debug->output->outputHtml->buildTable($list, 'table caption');
        $expect = <<<'EOD'
<table>
<caption>table caption</caption>
<thead><tr><th>&nbsp;</th><th>name</th><th scope="col">age</th><th scope="col">sex</th><th scope="col">Naughty</th><th scope="col">extracol</th></tr>
</thead>
<tbody>
<tr><th class="t_key t_int" scope="row">0</th><td class="t_string">Bob</td><td class="t_string numeric">12</td><td class="t_string">M</td><td class="t_bool false">false</td><td class="t_undefined"></td></tr>
<tr><th class="t_key t_int" scope="row">1</th><td class="t_string">Sally</td><td class="t_string numeric">10</td><td class="t_string">F</td><td class="t_bool true">true</td><td class="t_string">yes</td></tr>
</tbody>
</table>
EOD;
        // $this->output('table', $html);
        $this->assertSame($expect, $html);
        $html = $this->debug->output->outputHtml->buildTable(array(), 'caption');
        $this->assertSame('<div class="m_log">caption = <span class="t_array"><span class="t_keyword">Array</span><span class="t_punct">()</span></span></div>', $html);
        $html = $this->debug->output->outputHtml->buildTable('blah', 'caption');
        $this->assertSame('<div class="m_log">caption = <span class="t_string">blah</span></div>', $html);
    }
}
