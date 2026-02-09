<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\LogEntry;
use bdk\Debug\Utility\ArrayUtil;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Abstraction\Abstracter
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\Object\Constants
 * @covers \bdk\Debug\Abstraction\Object\Properties
 * @covers \bdk\Debug\Abstraction\Object\Methods
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\HtmlStringBinary
 * @covers \bdk\Debug\Dump\Html\Table
 * @covers \bdk\Debug\Dump\Html\Value
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Plugin\Method\Table
 * @covers \bdk\Debug\Route\Firephp
 * @covers \bdk\Debug\Route\Script
 * @covers \bdk\Debug\ServiceProvider
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class TableTest extends DebugTestFramework
{
    protected static $captionStyle = 'font-size:1.20em; font-weight:bold;';

    /**
     * Test
     *
     * @return void
     */
    /*
    public function testColKeys()
    {
        $table = new Table(null, array(), $this->debug);
        $colKeysMeth = new ReflectionMethod($table, 'colKeys');
        if (PHP_VERSION_ID < 80100) {
            $colKeysMeth->setAccessible(true);
        }
        $array = array(
            array('col1' => '', 'col2' => '', 'col4' => ''),
            array('col1' => '', 'col2' => '', 'col3' => ''),
            array('col1' => '', 'col2' => '', 'col3' => ''),
        );
        $colKeys = $colKeysMeth->invoke($table, $array);
        self::assertSame(array('col1', 'col2', 'col3', 'col4'), $colKeys);
        $array = array(
            array('a','b','c'),
            array('d','e','f','g'),
            array('h','i'),
        );
        $colKeys = $colKeysMeth->invoke($table, $array);
        self::assertSame(array(0, 1, 2, 3), $colKeys);
    }
    */

    /**
     * @return array
     */
    public static function providerTestMethod()
    {
        $rowsA = array(
            // note different order of keys / not all rows have all cols
            4 => array('name' => 'Bob', 'age' => '12', 'sex' => 'M', 'Naughty' => false),
            2 => array('Naughty' => true, 'name' => 'Sally', 'extracol' => 'yes', 'sex' => 'F', 'age' => '10'),
        );
        $tableAProperties = array(
            'caption' => 'arg1',
            'debug' => Abstracter::ABSTRACTION,
            'header' => [
                '',
                'name',
                'age',
                'sex',
                'Naughty',
                'extracol',
            ],
            'meta' => array(
                'class' => null,
                'columns' => array(
                    array(
                        'attribs' => array(
                            'class' => ['t_key'],
                            'scope' => 'row',
                        ),
                        'key' => \bdk\Table\Factory::KEY_INDEX,
                        'tagName' => 'th',
                    ),
                    array(
                        'key' => 'name',
                    ),
                    array(
                        'key' => 'age',
                    ),
                    array(
                        'key' => 'sex',
                    ),
                    array(
                        'key' => 'Naughty',
                    ),
                    array(
                        'key' => 'extracol',
                    ),
                ),
                'haveObjectRow' => false,
                'sortable' => true,
            ),
            'rows' => [
                [
                    4,
                    'Bob',
                    '12',
                    'M',
                    false,
                    Abstracter::UNDEFINED,
                ],
                [
                    2,
                    'Sally',
                    '10',
                    'F',
                    true,
                    'yes',
                ],
            ],
            'type' => 'table',
            'value' => null,
        );

        // not all date2 values of same type
        $rowsB = array(
            array(
                'dɑte' => new \DateTime('1955-11-05'),
                'date2' => 'not a datetime',
            ),
            array(
                'dɑte' => new \DateTime('1985-10-26'),
                'date2' => new \DateTime('2015-10-21'),
            ),
        );
        $rowsAHtml = <<<'EOD'
<li class="m_table">
<table class="sortable table-bordered">
<caption>table caption</caption>
<thead>
<tr>
<th class="t_string" scope="col"></th>
<th class="t_string" scope="col">name</th>
<th class="t_string" scope="col">age</th>
<th class="t_string" scope="col">sex</th>
<th class="t_string" scope="col">Naughty</th>
<th class="t_string" scope="col">extracol</th>
</tr>
</thead>
<tbody>
<tr>
<th class="t_int t_key" scope="row">4</th>
<td class="t_string">Bob</td>
<td class="t_string" data-type-more="numeric">12</td>
<td class="t_string">M</td>
<td class="t_bool text-center" data-type-more="false"></td>
<td class="t_undefined"></td>
</tr>
<tr>
<th class="t_int t_key" scope="row">2</th>
<td class="t_string">Sally</td>
<td class="t_string" data-type-more="numeric">10</td>
<td class="t_string">F</td>
<td class="t_bool text-center" data-type-more="true"><i class="fa fa-check"></i></td>
<td class="t_string">yes</td>
</tr>
</tbody>
</table>
</li>
EOD;
        $rowsAText = <<<'EOD'
array(
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

        /*
        $dateTimePubMethods = 3;
        if (\version_compare(PHP_VERSION, '7.1', '>=')) {
            $dateTimePubMethods = 5;
        } elseif (\version_compare(PHP_VERSION, '7.0', '>=')) {
            $dateTimePubMethods = 4;
        }
        */

        $vals = array(
            'datetime' => new \DateTime('2233-03-22'),
            'resource' => \fopen(__FILE__, 'r'),
            'callable' => array(__CLASS__, __FUNCTION__),
            'closure' => static function ($foo) {
                echo $foo;
            },
        );
        $abstracter = Debug::getInstance()->abstracter;
        foreach ($vals as $k => $raw) {
            $crated = $abstracter->crate($raw, 'table');
            if ($crated instanceof Abstraction) {
                $crated = \bdk\Test\Debug\Helper::deObjectifyData($crated);
                if ($crated['type'] === 'object') {
                    $crated['scopeClass'] = __CLASS__;
                }
            }
            $vals[$k] = array(
                'raw' => $raw,
                'crated' => $crated,
            );
        }

        $tests = array(
            'noArgs' => array(
                'table',
                [],
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => ['No arguments passed to table()'],
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="no-quotes t_string">No arguments passed to table()</span></li>',
                    'text' => 'No arguments passed to table()',
                    'script' => 'console.log("No arguments passed to table()");',
                    'firephp' => 'X-Wf-1-1-1-1: %d|[{"Type":"LOG"},"No arguments passed to table()"]|',
                ),
            ),

            'null' => array(
                'table',
                [null],
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => [null],
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_null">null</span></li>',
                    'text' => 'null',
                    'script' => 'console.log(null);',
                    'firephp' => 'X-Wf-1-1-1-1: 21|[{"Type":"LOG"},null]|',
                ),
            ),

            'emptyArray' => array(
                'table',
                ['arg1', array()],
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => ['arg1', array()],
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

            'string' => array(
                'table',
                ['arg1'],
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => ['arg1'],
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

            'notTabularWithLabel' => array(
                'table',
                array('arg1', 'arg2'),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => ['arg1', 'arg2'],
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

            'superfluousArgs' => array(
                'table',
                ['arg1', 'arg2 is not logged', $rowsA, 'arg4 is not logged', Debug::meta('columnMeta', array(
                    'Naughty' => array(
                        'attribs' => array('class' => ['text-center']),
                        'falseAs' => '',
                        'trueAs' => '<i class="fa fa-check"></i>',
                    ),
                ))],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [ArrayUtil::mergeDeep($tableAProperties, array(
                            'meta' => array(
                                'columns' => array(
                                    4 => array(
                                        'attribs' => array('class' => ['text-center']),
                                        'falseAs' => '',
                                        'trueAs' => '<i class="fa fa-check"></i>',
                                    ),
                                ),
                            ),
                        ))],
                        'meta' => array(),
                    ),
                    'html' => \str_replace('table caption', 'arg1', $rowsAHtml),
                    'text' => "arg1\n----\n" . $rowsAText,
                    'script' => 'console.log("%%carg1", "' . self::$captionStyle . '")' . "\n"
                        . $rowsAScript,
                    'firephp' => \str_replace('table caption', 'arg1', $rowsAFirephp),
                ),
            ),

            'maxDepthCfg' => array(
                'table',
                [
                    $rowsA,
                    'table caption',
                    Debug::meta('cfg', 'maxDepth', 1),
                ],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [\array_merge($tableAProperties, array(
                            'caption' => 'table caption',
                        ))],
                        'meta' => array(),
                    ),
                ),
            ),

            // rowsA
            array(
                'table',
                [
                    'table caption',
                    $rowsA,
                    Debug::meta('columnMeta', array(
                        'Naughty' => array(
                            'attribs' => array('class' => ['text-center']),
                            'falseAs' => '',
                            'trueAs' => '<i class="fa fa-check"></i>',
                        ),
                    )),
                ],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [ArrayUtil::mergeDeep($tableAProperties, array(
                            'caption' => 'table caption',
                            'meta' => array(
                                'columns' => array(
                                    4 => array(
                                        'attribs' => array('class' => ['text-center']),
                                        'falseAs' => '',
                                        'trueAs' => '<i class="fa fa-check"></i>',
                                    ),
                                ),
                            ),
                        ))],
                        'meta' => array(),
                    ),
                    'html' => $rowsAHtml,
                    'text' => "table caption\n-------------\n" . $rowsAText,
                    'script' => 'console.log("%%ctable caption", "' . self::$captionStyle . '")' . "\n"
                        . $rowsAScript,

                    'streamAnsi' => static function ($output, LogEntry $logEntry) {
                        $tableValues = $logEntry['args'][0]->getValues();
                        $table = new \bdk\Table\Table($tableValues);
                        $tableAsArray = \bdk\Table\Utility::asArray($table);
                        $expect = "\e[1mtable caption\n-------------\e[0m\n" . $logEntry->getSubject()->getDump('textAnsi')->valDumper->dump($tableAsArray);
                        // \bdk\Debug::varDump('expect', $expect);
                        // \bdk\Debug::varDump('output', $output);
                        // echo $output;
                        self::assertSame($expect, \trim($output));
                    },
                    'firephp' => $rowsAFirephp,
                ),
            ),

            // 6 rowsA - specify columns
            array(
                'table',
                [$rowsA, 'table caption', ['name', 'extracol']],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [
                            array(
                                'caption' => 'table caption',
                                'debug' => Abstracter::ABSTRACTION,
                                'header' => ['', 'name', 'extracol'],
                                'meta' => array(
                                    'class' => null,
                                    'columns' => array(
                                        array(
                                            'attribs' => array(
                                                'class' => ['t_key'],
                                                'scope' => 'row',
                                            ),
                                            'key' => \bdk\Table\Factory::KEY_INDEX,
                                            'tagName' => 'th',
                                        ),
                                        array(
                                            'key' => 'name',
                                        ),
                                        array(
                                            'key' => 'extracol',
                                        ),
                                    ),
                                    'haveObjectRow' => false,
                                    'sortable' => true,
                                ),
                                'rows' => array(
                                    [
                                        4,
                                        'Bob',
                                        Abstracter::UNDEFINED,
                                    ],
                                    [
                                        2,
                                        'Sally',
                                        'yes',
                                    ],
                                ),
                                'type' => 'table',
                                'value' => null,
                            ),
                        ],
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>table caption</caption>
                        <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">name</th>
                            <th class="t_string" scope="col">extracol</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">4</th>
                            <td class="t_string">Bob</td>
                            <td class="t_undefined"></td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">2</th>
                            <td class="t_string">Sally</td>
                            <td class="t_string">yes</td>
                        </tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'table caption
                        -------------
                        array(
                            [4] => array(
                                [name] => "Bob"
                            )
                            [2] => array(
                                [name] => "Sally"
                                [extracol] => "yes"
                            )
                        )',
                    'script' => 'console.log("%%ctable caption", "' . self::$captionStyle . '")' . "\n"
                        . 'console.table({"4":{"name":"Bob","extracol":undefined},"2":{"name":"Sally","extracol":"yes"}});',
                    'firephp' => 'X-Wf-1-1-1-20: %d|[{"Label":"table caption","Type":"TABLE"},[["","name","extracol"],[4,"Bob",null],[2,"Sally","yes"]]]|',
                ),
            ),

            'flat' => array(
                'table',
                [
                    'flat',
                    array(
                        'a',
                        42,
                        true,
                        false,
                        null,
                    ),
                ],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [
                            array(
                                'caption' => 'flat',
                                'debug' => Abstracter::ABSTRACTION,
                                'header' => array('', 'value'),
                                'meta' => array(
                                    'class' => null,
                                    'columns' => array(
                                        array(
                                            'attribs' => array(
                                                'class' => ['t_key'],
                                                'scope' => 'row',
                                            ),
                                            'key' => \bdk\Table\Factory::KEY_INDEX,
                                            'tagName' => 'th',
                                        ),
                                        array(
                                            'key' => \bdk\Table\Factory::KEY_SCALAR,
                                        ),
                                    ),
                                    'haveObjectRow' => false,
                                    'sortable' => true,
                                ),
                                'rows' => [
                                    [
                                        0,
                                        'a',
                                    ],
                                    [
                                        1,
                                        42,
                                    ],
                                    [
                                        2,
                                        true,
                                    ],
                                    [
                                        3,
                                        false,
                                    ],
                                    [
                                        4,
                                        null,
                                    ],
                                ],
                                'type' => 'table',
                                'value' => null,
                            ),
                        ],
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>flat</caption>
                        <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">value</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">0</th>
                            <td class="t_string">a</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">1</th>
                            <td class="t_int">42</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">2</th>
                            <td class="t_bool" data-type-more="true">true</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">3</th>
                            <td class="t_bool" data-type-more="false">false</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">4</th>
                            <td class="t_null">null</td>
                        </tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'flat
                        ----
                        array(
                            [0] => "a"
                            [1] => 42
                            [2] => true
                            [3] => false
                            [4] => null
                        )',
                    'script' => 'console.log("%%cflat", "' . self::$captionStyle . '")' . "\n"
                        . 'console.table(['
                            . '"a",'
                            . '42,'
                            . 'true,'
                            . 'false,'
                            . 'null'
                            . ']);',
                    'firephp' => 'X-Wf-1-1-1-4: %d|[{"Label":"flat","Type":"TABLE"},['
                        . '["","value"],'
                        . '[0,"a"],'
                        . '[1,42],'
                        . '[2,true],'
                        . '[3,false],'
                        . '[4,null]'
                    . ']]|',


                ),
            ),

            'flatWithClass' => array(
                'table',
                [
                    'flat',
                    array(
                        'a',
                        $vals['datetime']['raw'],
                        $vals['resource']['raw'],
                        $vals['callable']['raw'],
                        $vals['closure']['raw'],
                    ),
                ],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [
                            array(
                                'caption' => 'flat',
                                'debug' => Abstracter::ABSTRACTION,
                                'header' => array('', '', 'value'),
                                'meta' => array(
                                    'class' => null,
                                    'columns' => array(
                                        array(
                                            'attribs' => array(
                                                'class' => ['t_key'],
                                                'scope' => 'row',
                                            ),
                                            'key' => \bdk\Table\Factory::KEY_INDEX,
                                            'tagName' => 'th',
                                        ),
                                        array(
                                            'key' => '___class_name',
                                        ),
                                        array(
                                            'key' => \bdk\Table\Factory::KEY_SCALAR,
                                        ),
                                    ),
                                    'haveObjectRow' => true,
                                    'sortable' => true,
                                ),
                                'rows' => [
                                    [
                                        0,
                                        Abstracter::UNDEFINED,
                                        'a',
                                    ],
                                    [
                                        1,
                                        'DateTime',
                                        $vals['datetime']['crated']['stringified'],
                                    ],
                                    [
                                        2,
                                        Abstracter::UNDEFINED,
                                        $vals['resource']['crated'],
                                    ],
                                    [
                                        3,
                                        Abstracter::UNDEFINED,
                                        $vals['callable']['crated'],
                                    ],
                                    [
                                        4,
                                        'Closure',
                                        $vals['closure']['crated'],
                                    ],
                                ],
                                'type' => 'table',
                                'value' => null,
                            ),
                        ],
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>flat</caption>
                        <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">value</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">0</th>
                            <td class="t_undefined"></td>
                            <td class="t_string">a</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">1</th>
                            <td class="classname">DateTime</td>
                            <td class="t_string">2233-03-22T00:00:00%i:00</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">2</th>
                            <td class="t_undefined"></td>
                            <td class="t_resource">Resource id #%d: stream</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">3</th>
                            <td class="t_undefined"></td>
                            <td><span class="t_type">callable</span> <span class="t_identifier" data-type-more="callable"><span class="classname"><span class="namespace">bdk\Test\Debug\Plugin\Method\</span>TableTest</span><span class="t_operator">::</span><span class="t_name">providerTestMethod</span></span></td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">4</th>
                            <td class="classname">Closure</td>
                            <td class="groupByInheritance t_object" data-accessible="public"><span class="t_identifier" data-type-more="className"><span class="classname">Closure</span></span>
                                <dl class="object-inner">
                                    <dt class="modifiers">modifiers</dt>
                                    <dd class="t_modifier_final">final</dd>
                                    <dt class="properties">properties</dt>
                                    <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">string</span> <span class="no-quotes t_identifier t_string">file</span> <span class="t_operator">=</span> <span class="t_string" data-type-more="filepath"><span class="file-path-rel">' . \dirname(__FILE__) . '/</span><span class="file-basename">' . \basename(__FILE__) . '</span></span></dd>
                                    <dd class="debug-value property"><span class="t_modifier_debug">debug</span> <span class="t_type">int</span> <span class="no-quotes t_identifier t_string">line</span> <span class="t_operator">=</span> <span class="t_int">%i</span></dd>
                                    <dt class="methods">methods</dt>
                                    %A
                                </dl>
                            </td>
                        </tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'flat
                        ----
                        array(
                            [0] => "a"
                            [1] => array(
                                [___class_name] => "DateTime"
                                [value] => "2233-03-22T00:00:00%i:00"
                            )
                            [2] => Resource id #%d: stream
                            [3] => callable: bdk\Test\Debug\Plugin\Method\TableTest::providerTestMethod
                            [4] => array(
                                [___class_name] => "Closure"
                                [value] => Closure
                                    properties:
                                        (debug) file = "' . __FILE__ . '"
                                        (debug) line = %i
                                    methods:
                                        public: ' . (PHP_VERSION_ID >= 80500 ? '6' : '5') . '
                                        private: 1
                            )
                        )',
                    'script' => 'console.log("%%cflat", "' . self::$captionStyle . '")' . "\n"
                        . 'console.table(['
                            . '{"___class_name":undefined,"value":"a"},'
                            . '{"___class_name":"DateTime","value":"' . $vals['datetime']['crated']['stringified'] . '"},'
                            . '{"___class_name":undefined,"value":"Resource id #%d: stream"},'
                            . '{"___class_name":undefined,"value":' . \json_encode('callable: ' . __CLASS__ . '::providerTestMethod') . '},'
                            . '{"___class_name":"Closure","value":{"___class_name":"Closure","(debug) file":"' . __FILE__ . '","(debug) line":%i}}'
                            . ']);',
                    'firephp' => 'X-Wf-1-1-1-4: %d|[{"Label":"flat","Type":"TABLE"},['
                        . '["","","value"],'
                        . '[0,null,"a"],'
                        . '[1,"DateTime","2233-03-22T00:00:00%i:00"],'
                        . '[2,null,"Resource id #%d: stream"],'
                        . '[3,null,' . \json_encode('callable: ' . __CLASS__ . '::providerTestMethod') . '],'
                        . '[4,"Closure",{"___class_name":"Closure","(debug) file":"' . __FILE__ . '","(debug) line":%i}]'
                    . ']]|',
                ),
            ),

            'traversable' => array(
                'table',
                [
                    'traversable',
                    new \bdk\Test\Debug\Fixture\TestTraversable($rowsA),
                    Debug::meta('columnMeta', array(
                        'Naughty' => array(
                            'attribs' => array('class' => ['text-center']),
                            'falseAs' => '',
                            'trueAs' => '<i class="fa fa-check"></i>',
                        ),
                    )),
                ],
                array(
                    // title="I implement Traversable!"
                    'html' => \str_replace('table caption', 'traversable (<span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestTraversable</span>)', $rowsAHtml),
                    'text' => "traversable\n-----------\n" . $rowsAText,
                    'script' => 'console.log("%%ctraversable", "' . self::$captionStyle . '")' . "\n"
                        . $rowsAScript,
                    'firephp' => 'X-Wf-1-1-1-5: 149|[{"Label":"traversable","Type":"TABLE"},[["","name","age","sex","Naughty","extracol"],[4,"Bob","12","M",false,null],[2,"Sally","10","F",true,"yes"]]]|',
                ),
            ),

            'traversableOfTraversable' => array(
                'table',
                [
                    'traversable -o- traversables',
                    new \bdk\Test\Debug\Fixture\TestTraversable(array(
                        4 => new \bdk\Test\Debug\Fixture\TestTraversable($rowsA[4]),
                        2 => new \bdk\Test\Debug\Fixture\TestTraversable($rowsA[2]),
                    )),
                ],
                array(
                    // title="I implement Traversable!"
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>traversable -o- traversables (<span class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestTraversable</span>)</caption>
                        <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">name</th>
                            <th class="t_string" scope="col">age</th>
                            <th class="t_string" scope="col">sex</th>
                            <th class="t_string" scope="col">Naughty</th>
                            <th class="t_string" scope="col">extracol</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">4</th>
                            <td class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestTraversable</td>
                            <td class="t_string">Bob</td>
                            <td class="t_string" data-type-more="numeric">12</td>
                            <td class="t_string">M</td>
                            <td class="t_bool" data-type-more="false">false</td>
                            <td class="t_undefined"></td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">2</th>
                            <td class="classname"><span class="namespace">bdk\Test\Debug\Fixture\</span>TestTraversable</td>
                            <td class="t_string">Sally</td>
                            <td class="t_string" data-type-more="numeric">10</td>
                            <td class="t_string">F</td>
                            <td class="t_bool" data-type-more="true">true</td>
                            <td class="t_string">yes</td>
                        </tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'traversable -o- traversables
                        ----------------------------
                        array(
                            [4] => array(
                                [___class_name] => "bdk\Test\Debug\Fixture\TestTraversable"
                                [name] => "Bob"
                                [age] => "12"
                                [sex] => "M"
                                [Naughty] => false
                            )
                            [2] => array(
                                [___class_name] => "bdk\Test\Debug\Fixture\TestTraversable"
                                [name] => "Sally"
                                [age] => "10"
                                [sex] => "F"
                                [Naughty] => true
                                [extracol] => "yes"
                            )
                        )',
                    'script' => 'console.log("%%ctraversable -o- traversables", "' . self::$captionStyle . '")' . "\n"
                        . 'console.table({"4":{"___class_name":"bdk\\\Test\\\Debug\\\Fixture\\\TestTraversable","name":"Bob","age":"12","sex":"M","Naughty":false,"extracol":undefined},"2":{"___class_name":"bdk\\\Test\\\Debug\\\Fixture\\\TestTraversable","name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});',
                    'firephp' => 'X-Wf-1-1-1-6: 259|[{"Label":"traversable -o- traversables","Type":"TABLE"},['
                        . '["","","name","age","sex","Naughty","extracol"],'
                        . '[4,"bdk\\\Test\\\Debug\\\Fixture\\\TestTraversable","Bob","12","M",false,null],'
                        . '[2,"bdk\\\Test\\\Debug\\\Fixture\\\TestTraversable","Sally","10","F",true,"yes"]]]|',
                ),
            ),

            'arrayOfObjects' => array(
                'table',
                [
                    'array -o- objects',
                    array(
                        4 => (object) $rowsA[4],
                        2 => (object) $rowsA[2],
                    ),
                ],
                array(
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>array -o- objects</caption>
                        <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">name</th>
                            <th class="t_string" scope="col">age</th>
                            <th class="t_string" scope="col">sex</th>
                            <th class="t_string" scope="col">Naughty</th>
                            <th class="t_string" scope="col">extracol</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">4</th>
                            <td class="classname">stdClass</td>
                            <td class="t_string">Bob</td>
                            <td class="t_string" data-type-more="numeric">12</td>
                            <td class="t_string">M</td>
                            <td class="t_bool" data-type-more="false">false</td>
                            <td class="t_undefined"></td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">2</th>
                            <td class="classname">stdClass</td>
                            <td class="t_string">Sally</td>
                            <td class="t_string" data-type-more="numeric">10</td>
                            <td class="t_string">F</td>
                            <td class="t_bool" data-type-more="true">true</td>
                            <td class="t_string">yes</td>
                        </tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'array -o- objects
                        -----------------
                        array(
                        [4] => array(
                            [___class_name] => "stdClass"
                            [name] => "Bob"
                            [age] => "12"
                            [sex] => "M"
                            [Naughty] => false
                            )
                        [2] => array(
                            [___class_name] => "stdClass"
                            [name] => "Sally"
                            [age] => "10"
                            [sex] => "F"
                            [Naughty] => true
                            [extracol] => "yes"
                        )
                    )',
                    'script' => 'console.log("%%carray -o- objects", "' . self::$captionStyle . '")' . "\n"
                        . 'console.table({"4":{"___class_name":"stdClass","name":"Bob","age":"12","sex":"M","Naughty":false,"extracol":undefined},"2":{"___class_name":"stdClass","name":"Sally","age":"10","sex":"F","Naughty":true,"extracol":"yes"}});',
                    'firephp' => 'X-Wf-1-1-1-7: 180|[{"Label":"array -o- objects","Type":"TABLE"},['
                        . '["","","name","age","sex","Naughty","extracol"],'
                        . '[4,"stdClass","Bob","12","M",false,null],'
                        . '[2,"stdClass","Sally","10","F",true,"yes"]]]|',
                ),
            ),

            'object' => array(
                'table',
                [
                    'object -o- objects',
                    (object) array(
                        4 => (object) $rowsA[4], // Bob
                        2 => (object) $rowsA[2], // Sally
                    ),
                    Debug::meta('columnMeta', array(
                        'Naughty' => array(
                            'attribs' => array('class' => ['text-center']),
                            'falseAs' => '',
                            'trueAs' => '<i class="fa fa-check"></i>',
                        ),
                    )),
                ],
                array(
                    'html' => \preg_replace(
                        array(
                            '/<caption>.*?<\/caption>/',
                            '/(<th.*scope="row">\w+<\/th>)/',
                            '/(<th class="t_string" scope="col"><\/th>)/',
                        ),
                        array(
                            '<caption>object -o- objects (<span class="classname">stdClass</span>)</caption>',
                            '$1' . "\n" . '<td class="classname">stdClass</td>',
                            '$1' . "\n" . '$1',
                        ),
                        $rowsAHtml
                    ),
                ),
            ),

            'differentTypes' => array(
                'table',
                [
                    'not all col values of same type',
                    $rowsB,
                ],
                array(
                    'html' => '<li class="m_table">
                        <table class="sortable table-bordered">
                        <caption>not all col values of same type</caption>
                        <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">d<span class="unicode" data-code-point="0251" title="U-0251: LATIN SMALL LETTER ALPHA">ɑ</span>te <span class="classname">DateTime</span></th>
                            <th class="t_string" scope="col">date2</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">0</th>
                            <td class="t_string">1955-11-05T00:00:00%i:00</td>
                            <td class="t_string">not a datetime</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">1</th>
                            <td class="t_string">1985-10-26T00:00:00%i:00</td>
                            <td class="t_string">2015-10-21T00:00:00%i:00</td>
                            </tr>
                        </tbody>
                        </table>
                        </li>',
                    'text' => 'not all col values of same type
                        -------------------------------
                        array(
                        [0] => array(
                            [d\u{0251}te] => "1955-11-05T00:00:00%i:00"
                            [date2] => "not a datetime"
                        )
                        [1] => array(
                            [d\u{0251}te] => "1985-10-26T00:00:00%i:00"
                            [date2] => "2015-10-21T00:00:00%i:00"
                        )
                    )',
                    'script' => 'console.log("%%cnot all col values of same type", "' . self::$captionStyle . '")' . "\n"
                        . 'console.table([{"d\\\u{0251}te":"1955-11-05T00:00:00%i:00","date2":"not a datetime"},{"d\\\u{0251}te":"1985-10-26T00:00:00%i:00","date2":"2015-10-21T00:00:00%i:00"}]);',
                    'firephp' => 'X-Wf-1-1-1-8: 199|[{"Label":"not all col values of same type","Type":"TABLE"},[["","d\\\u{0251}te","date2"],[0,"1955-11-05T00:00:00%i:00","not a datetime"],[1,"1985-10-26T00:00:00%i:00","2015-10-21T00:00:00%i:00"]]]|',
                ),
            ),

            'inclContext' => array(
                'table',
                [
                    'table caption',
                    $rowsA,
                    Debug::meta('inclContext'), // irrelevant for table.. only applies for trace
                    Debug::meta('columnMeta', array(
                        'Naughty' => array(
                            'attribs' => array('class' => ['text-center']),
                            'falseAs' => '',
                            'trueAs' => '<i class="fa fa-check"></i>',
                        ),
                    )),
                ],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [
                            ArrayUtil::mergeDeep($tableAProperties, array(
                                'caption' => 'table caption',
                                'meta' => array(
                                    'columns' => array(
                                        4 => array(
                                            'attribs' => array('class' => ['text-center']),
                                            'falseAs' => '',
                                            'trueAs' => '<i class="fa fa-check"></i>',
                                        ),
                                    ),
                                ),
                            ))
                        ],
                        'meta' => array(
                            'inclContext' => true,
                        ),
                    ),
                    // 'html' => \str_replace('table-bordered', 'table-bordered trace-context', $rowsAHtml),
                    'html' => $rowsAHtml,
                    'text' => "table caption\n-------------\n" . $rowsAText,
                    'script' => 'console.log("%%ctable caption", "' . self::$captionStyle . '")' . "\n"
                        . $rowsAScript,
                    'firephp' => $rowsAFirephp,
                ),
            ),

            'totalRow' => array(
                'table',
                [
                    'caption',
                    $rowsA,
                    Debug::meta('totalCols', ['age', 'noSuchVal']),
                ],
                array(
                    'entry' => array(
                        'method' => 'table',
                        'args' => [
                            ArrayUtil::mergeDeep($tableAProperties, array(
                                'caption' => 'caption',
                                'footer' => [
                                    array(
                                        'html' => '',
                                        'value' => null,
                                    ),
                                    array(
                                        'html' => '',
                                        'value' => null,
                                    ),
                                    22,
                                    array(
                                        'html' => '',
                                        'value' => null,
                                    ),
                                    array(
                                        'html' => '',
                                        'value' => null,
                                    ),
                                    array(
                                        'html' => '',
                                        'value' => null,
                                    ),
                                ],
                            )),
                        ],
                        'meta' => array(),
                    ),
                    'html' => '%A<tfoot>
                            <tr>
                                <td></td>
                                <td></td>
                                <td class="t_int">22</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            </tfoot>
                            </table>%A',
                    'text' => 'caption
                        -------
                        array(
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
                    )',
                ),
            ),
        );

        // return \array_intersect_key($tests, \array_flip(['traversableOfTraversable']));
        return $tests;
    }

    public function testSpecialValues()
    {
        $binary = \base64_decode('j/v9wNrF5i1abMXFW/4vVw==', true);
        $binaryStr = \trim(\chunk_split(\bin2hex($binary), 2, ' '));

        $json = \json_encode(array(
            'poop' => '💩',
            'int' => 42,
            'password' => 'secret',
        ));

        // timestamp value-container
        $time = \time();
        // test prettify
        $xml = Debug::getInstance()->prettify('<?xml version="1.0" encoding="UTF-8" standalone="no"?><fart></fart>', 'application/xml');

        $this->testMethod(
            'table',
            [
                'foo',
                [
                    $binary,
                    $json,
                    $time,
                    $xml,
                ],
            ],
            array(
                'html' => '<li class="m_table">
                    <table class="sortable table-bordered">
                    <caption>foo</caption>
                    <thead>
                        <tr>
                            <th class="t_string" scope="col"></th>
                            <th class="t_string" scope="col">value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">0</th>
                            <td><span class="t_keyword">string</span><span class="text-muted">(binary)</span>
                                <ul class="list-unstyled value-container" data-type="string" data-type-more="binary">
                                    <li>size = <span class="t_int">16</span></li>
                                    <li class="t_string"><span class="binary">' . $binaryStr . '</span></li>
                                </ul></td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">1</th>
                            <td class="string-encoded tabs-container" data-type-more="json">
                            <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">parsed</a></nav>
                            <div class="tab-1 tab-pane" role="tabpanel"><span class="value-container" data-type="string"><span class="prettified">(prettified)</span> <span class="highlight language-json no-quotes t_string">{
                                &quot;poop&quot;: &quot;\ud83d\udca9&quot;,
                                &quot;int&quot;: 42,
                                &quot;password&quot;: &quot;secret&quot;
                            }</span></span></div>
                            <div class="active tab-2 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                                <ul class="array-inner list-unstyled">
                                    <li><span class="t_key">poop</span><span class="t_operator">=&gt;</span><span class="t_string">💩</span></li>
                                    <li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">42</span></li>
                                    <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">secret</span></li>
                                </ul><span class="t_punct">)</span></span></div>
                            </td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">2</th>
                            <td class="timestamp value-container" title="' . \gmdate(self::DATETIME_FORMAT, $time) . '"><span class="t_int" data-type-more="timestamp">' . $time . '</span></td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">3</th>
                            <td class="value-container" data-type="string"><span class="prettified">(prettified)</span> <span class="highlight language-xml no-quotes t_string">&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot; standalone=&quot;no&quot;?&gt;
                        &lt;fart/&gt;</span></td>
                        </tr>
                    </tbody>
                    </table>
                    </li>',
                'text' => 'foo
                    ---
                    array(
                    [0] => "8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57"
                    [1] => {
                        "poop": "\ud83d\udca9",
                        "int": 42,
                        "password": "secret"
                    }
                    [2] => 📅 ' . $time . ' (' . \gmdate(self::DATETIME_FORMAT, $time) . ')
                    [3] => <?xml version="1.0" encoding="UTF-8" standalone="no"?>
                    <fart/>
                )',
                'script' => 'console.log("%%cfoo", "' . self::$captionStyle . '")' . "\n"
                    .'console.table(['
                        . '"8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57",'
                        . '"{\n    \"poop\": \"\\\ud83d\\\udca9\",\n    \"int\": 42,\n    \"password\": \"secret\"\n}",'
                        . '"' . $time . ' (' . \gmdate(self::DATETIME_FORMAT, $time) . ')",'
                        . '"<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\n<fart/>"'
                        . ']);',
                'firephp' => 'X-Wf-1-1-1-4: %d|[{"Label":"foo","Type":"TABLE"},['
                        . '["","value"],'
                        . '[0,"8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57"],'
                        . '[1,"{\n    \"poop\": \"\\\ud83d\\\udca9\",\n    \"int\": 42,\n    \"password\": \"secret\"\n}"],'
                        . '[2,"' . $time . ' (' . \gmdate(self::DATETIME_FORMAT, $time) . ')"],'
                        . '[3,"<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\n<fart/>"]'
                    . ']]|',
            )
        );
    }

    public function testCollectFalse()
    {
        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'table',
            [],
            array(
                'notLogged' => true,
                'return' => $this->debug,
            )
        );
    }
}
