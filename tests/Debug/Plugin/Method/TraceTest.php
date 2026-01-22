<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug::trace() method
 *
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\Helper
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\Table
 * @covers \bdk\Debug\Plugin\Method\Trace
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class TraceTest extends DebugTestFramework
{
    /**
     * Test
     *
     * @return void
     */
    public function testTrace()
    {
        eval('$this->debug->trace();');
        $values = array(
            'file0' => __FILE__,
            'line0' => __LINE__ - 3,
            'function1' => __CLASS__ . '->' . __FUNCTION__,
        );

        $this->testMethod(
            array(
                'dataPath' => 'log/0',
            ),
            array(),
            array(
                'entry' => static function (LogEntry $logEntry) use ($values) {
                    $traceValues = $logEntry['args'][0]->getValues();
                    // \bdk\Debug::varDump('traceValues', \print_r($traceValues, true));

                    // row[0] has attribs and children
                    self::assertSame(['data-file', 'data-line'], \array_keys($traceValues['rows'][0]['attribs']));
                    self::assertSame('eval()\'d code', $traceValues['rows'][0]['children'][1]);
                    self::assertSame(1, $traceValues['rows'][0]['children'][2]);
                    self::assertSame(Abstracter::UNDEFINED, $traceValues['rows'][0]['children'][3]);

                    // file
                    self::assertInstanceOf('bdk\Debug\Abstraction\Abstraction', $traceValues['rows'][1][1]);
                    // self::assertSame(\bdk\Debug\Abstraction\Type::TYPE_STRING_FILEPATH, $traceArray['rows'][1][1]['typeMore']);
                    self::assertSame($values['file0'], (string) $traceValues['rows'][1][1]);

                    // line
                    self::assertIsInt($traceValues['rows'][1][2]);
                    self::assertSame($values['line0'], $traceValues['rows'][1][2]);

                    // function
                    self::assertEquals(new Abstraction(Type::TYPE_IDENTIFIER, array(
                        'value' => 'eval',
                        'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    )), $traceValues['rows'][1][3]);
                    self::assertEquals(new Abstraction(Type::TYPE_IDENTIFIER, array(
                        'value' => $values['function1'],
                        'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    )), $traceValues['rows'][2][3]);

                    self::assertSame('trace', $traceValues['caption']);
                    self::assertFalse($traceValues['meta']['inclContext']);
                    self::assertFalse($traceValues['meta']['sortable']);
                },
                'chromeLogger' => static function ($outputArray, LogEntry $logEntry) {
                    $tableValues = $logEntry['args'][0]->getValues();
                    $table = new \bdk\Table\Table($tableValues);
                    $tableAsArray = \bdk\Table\Utility::asArray($table);
                    foreach ($tableAsArray as $iRow => $row) {
                        foreach ($row as $k => $v) {
                            if ($v instanceof Abstraction) {
                                $tableAsArray[$iRow][$k] = (string) $v;
                            }
                        }
                    }
                    // \bdk\Debug::varDump('tableAsArray', \print_r($tableAsArray, true));
                    // \bdk\Debug::varDump('outputArray', \print_r($outputArray[0][0], true));
                    self::assertSame($tableAsArray, $outputArray[0][0]);
                    self::assertSame(null, $outputArray[1]);
                    self::assertSame('table', $outputArray[2]);
                },
                'firephp' => static function ($output, LogEntry $logEntry) {
                    $traceData = $logEntry['args'][0]->getValues();
                    $traceTable = new \bdk\Table\Table($traceData);
                    $traceRows = $traceTable->getRows();
                    \preg_match('#\|(.+)\|#', $output, $matches);
                    $output = \json_decode($matches[1], true);
                    list($logEntryMeta, $logEntryTable) = $output;
                    self::assertSame(array(
                        'Label' => 'trace',
                        'Type' => 'TABLE',
                    ), $logEntryMeta);
                    self::assertSame(array(
                        '',
                        'file',
                        'line',
                        'function',
                    ), $logEntryTable[0]);
                    $count = \count($logEntryTable);
                    for ($i = 1; $i < $count; $i++) {
                        $iRow = $i - 1;
                        $cells = $traceRows[$iRow]->getChildren();
                        $valuesExpect = array(
                            $iRow,
                            (string) $cells[1]->getValue(), // file
                            $cells[2]->getValue(),          // line
                            $cells[3]->getValue() !== Abstracter::UNDEFINED
                                ? (string) $cells[3]->getValue()
                                : null,
                        );
                        // \bdk\Debug::varDump('traceExpect', $valuesExpect);
                        // \bdk\Debug::varDump('logEntryTable[' . $i . ']', $logEntryTable[$i]);
                        self::assertSame($valuesExpect, $logEntryTable[$i]);
                    }
                },
                'html' => function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    self::assertStringContainsString('<caption>trace</caption>' . "\n"
                        . '<thead>' . "\n"
                        . '<tr>' . "\n"
                        . '<th class="t_string" scope="col"></th>' . "\n"
                        . '<th class="t_string" scope="col">file</th>' . "\n"
                        . '<th class="t_string" scope="col">line</th>' . "\n"
                        . '<th class="t_string" scope="col">function</th>' . "\n"
                        . '</tr>' . "\n"
                        . '</thead>', self::normalizeString($output));
                    $matches = array();
                    \preg_match_all('#<tr[^>]*>'
                        . '<th[^>]*>(.*?)</th>'
                        . '<t[hd][^>]*>(.*?)</t[hd]>'
                        . '<t[hd][^>]*>(.*?)</t[hd]>'
                        . '<t[hd]([^>]*)>(.*?)</t[hd]>'
                        . '</tr>#s', $output, $matches, PREG_SET_ORDER);
                    // \bdk\Debug::varDump('html', $output);
                    // \bdk\Debug::varDump('matches', $matches);
                    // \bdk\Debug::varDump('trace', $trace);
                    // \bdk\Debug::varDump('meta', $logEntry['meta']);
                    $count = \count($matches);
                    for ($i = 1; $i < $count; $i++) {
                        // build expected values
                        $valuesExpect = \array_merge(
                            [(string) ($i - 1)],
                            \array_values($trace[$i - 1])
                        );

                        $valuesExpect[1] = $this->debug->getDump('html')->valDumper->dump($valuesExpect[1], array('tagName' => null));
                        $valuesExpect[2] = $valuesExpect[2] === null ? 'null' : (string) $valuesExpect[2];
                        $valuesExpect[3] = $this->debug->getDump('html')->valDumper->dump($valuesExpect[3], array('tagName' => null));

                        // build actual values
                        $valuesActual = $matches[$i];
                        \array_shift($valuesActual);
                        unset($valuesActual[3]); // toss attribs
                        $valuesActual = \array_values($valuesActual);

                        // \bdk\Debug::varDump('valuesExpect', $i, $valuesExpect);
                        // \bdk\Debug::varDump('valuesActual', $i, $valuesActual);

                        self::assertSame($valuesExpect, $valuesActual);
                    }
                },
                'script' => static function ($output, LogEntry $logEntry) {
                    $tableValues = $logEntry['args'][0]->getValues();
                    $table = new \bdk\Table\Table($tableValues);
                    $tableAsArray = \bdk\Table\Utility::asArray($table, array('undefinedAs' => \bdk\Table\Factory::VAL_UNDEFINED));
                    foreach ($tableAsArray as $iRow => $row) {
                        foreach ($row as $k => $v) {
                            if ($v instanceof Abstraction) {
                                $tableAsArray[$iRow][$k] = (string) $v;
                            }
                        }
                    }
                    $expect = \str_replace(\json_encode(Abstracter::UNDEFINED), 'undefined', \json_encode($tableAsArray, JSON_UNESCAPED_SLASHES));

                    \preg_match('#console.table\((.+)\);#', $output, $matches);
                    // \bdk\Debug::varDump('expect', $expect);
                    // \bdk\Debug::varDump('actual', $matches[1]);
                    self::assertSame($expect, $matches[1]);
                },
                'streamAnsi' => static function ($output, LogEntry $logEntry) {
                    $tableValues = $logEntry['args'][0]->getValues();
                    $table = new \bdk\Table\Table($tableValues);
                    $tableAsArray = \bdk\Table\Utility::asArray($table);
                    $expect = "\e[1mtrace\n-----\e[0m\n" . $logEntry->getSubject()->getDump('textAnsi')->valDumper->dump($tableAsArray);
                    // \bdk\Debug::varDump('expect', $expect);
                    // \bdk\Debug::varDump('output', $output);
                    // echo $output;
                    self::assertSame($expect, \trim($output));
                },
                'text' => function ($output, LogEntry $logEntry) {
                    // @todo this isn't a very good test
                    $tableValues = $logEntry['args'][0]->getValues();
                    $table = new \bdk\Table\Table($tableValues);
                    $tableAsArray = \bdk\Table\Utility::asArray($table);
                    self::assertNotEmpty($tableAsArray);
                    $expect = "trace\n-----\n" . $this->debug->getDump('text')->valDumper->dump($tableAsArray);
                    // \bdk\Debug::varDump('expect', $expect);
                    // \bdk\Debug::varDump('output', $output);
                    self::assertSame($expect, \trim($output));
                },
                // 'wamp' => @todo
            )
        );
    }

    public function testTraceProvided()
    {
        $frames = array(
            array(
                'file' => '/path/to/file.php',
                'line' => 42,
                'function' => 'Foo::bar',
            ),
            array(
                'file' => '/fakepath/to/otherfile.php',
                'line' => 69,
                'function' => 'dingus',
            ),
        );
        $tableMetaExpect = array(
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
                    'attribs' => array(
                        'class' => ['no-quotes'],
                    ),
                    'key' => 'file',
                ),
                array('key' => 'line'),
                array('key' => 'function'),
            ),
            'haveObjectRow' => false,
            'inclArgs' => false,
            'inclContext' => false,
            'sortable' => false,
        );
        $this->testMethod(
            'trace',
            [$this->debug->meta('trace', $frames)],
            array(
                'entry' => static function (LogEntry $logEntry) use ($frames, $tableMetaExpect) {
                    self::assertSame('trace', $logEntry['method']);
                    self::assertSame('trace', $logEntry['args'][0]['caption']);
                    $i = 0;
                    $tableDataExpect = \array_map(static function (array $frame) use (&$i) {
                        return \array_merge([$i++], \array_values($frame));
                    }, $frames);
                    $tableDataExpect[1][1] = \str_replace('/fakepath', '/fakepathNew', $tableDataExpect[1][1]);
                    $tableDataActual = \array_map(static function ($row) {
                        $row[0] = $row[0];
                        $row[1] = (string) $row[1]; // filepath abstraction
                        $row[3] = (string) $row[3]; // method abstraction
                        return $row;
                    }, $logEntry['args'][0]['rows']);
                    // \bdk\Debug::varDump('tableDataExpect', $tableDataExpect);
                    // \bdk\Debug::varDump('tableDataActual', $tableDataActual);
                    self::assertSame($tableDataExpect, $tableDataActual);
                    // \bdk\Debug::varDump('tableMetaExpect', $tableMetaExpect);
                    // \bdk\Debug::varDump('tableMetaActual', $logEntry['args'][0]['meta']);
                    self::assertEquals($tableMetaExpect, $logEntry['args'][0]['meta']);
                },
                'wamp' => array(
                    'trace',
                    [
                        /*
                        \array_map(static function ($frame) {
                            $row = \array_values($frame);
                            $row[0] = \str_replace('/fakepath', '/fakepathNew', $row[0]);
                            $fileAbsValues = (new Abstraction(Type::TYPE_STRING, array(
                                'typeMore' => Type::TYPE_STRING_FILEPATH,
                                'docRoot' => false,
                                'baseName' => \basename($row[0]),
                                'pathCommon' => '',
                                'pathRel' => \dirname($row[0]) . '/',
                            )))->jsonSerialize();
                            \ksort($fileAbsValues);
                            $funcAbsValues = (new Abstraction(Type::TYPE_IDENTIFIER, array(
                                'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                                'value' => $row[2],
                            )))->jsonSerialize();
                            \ksort($funcAbsValues);
                            $row[0] = $fileAbsValues;
                            $row[2] = $funcAbsValues;
                            return $row;
                        }, $frames),
                        */
                        array(
                            'caption' => 'trace',
                            'debug' => Abstracter::ABSTRACTION,
                            'header' => ['', 'file', 'line', 'function'],
                            'meta' => $tableMetaExpect,
                            'rows' => [
                                [
                                    0,
                                    array(
                                        'baseName' => 'file.php',
                                        'debug' => Abstracter::ABSTRACTION,
                                        'docRoot' => false,
                                        'pathCommon' => '',
                                        'pathRel' => '/path/to/',
                                        'type' => 'string',
                                        'typeMore' => 'filepath',
                                        'value' => null,
                                    ),
                                    42,
                                    array(
                                        'debug' => Abstracter::ABSTRACTION,
                                        'type' => 'identifier',
                                        'typeMore' => 'method',
                                        'value' => 'Foo::bar',
                                    ),
                                ],
                                [
                                    1,
                                    array(
                                        'baseName' => 'otherfile.php',
                                        'debug' => Abstracter::ABSTRACTION,
                                        'docRoot' => false,
                                        'pathCommon' => '',
                                        'pathRel' => '/fakepathNew/to/',
                                        'type' => 'string',
                                        'typeMore' => 'filepath',
                                        'value' => null,
                                    ),
                                    69,
                                    array(
                                        'debug' => Abstracter::ABSTRACTION,
                                        'type' => 'identifier',
                                        'typeMore' => 'method',
                                        'value' => 'dingus',
                                    ),
                                ],
                            ],
                            'type' => 'table',
                            'value' => null,
                        ),
                    ],
                    array(
                        'inclInternal' => false,
                        'limit' => 0,
                    ),
                ),
            )
        );
    }

    public function testTraceWithContext()
    {
        $this->testMethod(
            'trace',
            array(
                true,   // inclContext
                $this->debug->meta('cfg', 'objectsExclude', array('*')), // don't inspect any objects
            ),
            array(
                'entry' => static function (LogEntry $logEntry) {
                    // $tableInfo = $logEntry->getMeta('tableInfo');
                    $tableArray = $logEntry['args'][0]->getValues();
                    // \bdk\Debug::varDump('traceArray', \print_r($tableArray, true));
                    self::assertSame('trace', $tableArray['caption']);
                    self::assertTrue($tableArray['meta']['inclContext']);
                    self::assertFalse($tableArray['meta']['sortable']);

                    self::assertIsArray($tableArray['rows'][0]['meta']['args']);
                    self::assertIsArray($tableArray['rows'][0]['meta']['context']);
                },
                'html' => static function ($output, LogEntry $logEntry) {
                    $expectStartsWith = <<<'EOD'
<li class="m_trace">
<table class="table-bordered trace-context">
<caption>trace</caption>
<thead>
<tr>
<th class="t_string" scope="col"></th>
<th class="t_string" scope="col">file</th>
<th class="t_string" scope="col">line</th>
<th class="t_string" scope="col">function</th>
</tr>
</thead>
<tbody>
<tr class="expanded" data-toggle="next">
EOD;
                    // \bdk\Debug::varDump('output', $output);
                    self::assertStringContainsString($expectStartsWith, self::normalizeString($output));
                    $expectMatch = '%a<tr class="context" style="display:table-row;">' . "\n"
                        . '<td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-line-offset="%d" data-start="%d"><code class="language-php">%a';
                    // \bdk\Debug::varDump('expectMatch', $expectMatch);
                    // \bdk\Debug::varDump('output', $output);
                    self::assertStringMatchesFormatNormalized($expectMatch, $output);
                    $expectContains = '</code></pre><hr />Arguments = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>';
                    self::assertStringContainsString($expectContains, self::normalizeString($output));
                },
            )
        );
    }

    public function testCollectFalse()
    {
        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'trace',
            array(),
            array(
                'notLogged' => true,
                'return' => $this->debug,
                'wamp' => false,
            )
        );
    }

    public function testTraceChars()
    {
        $this->testMethod(
            'trace',
            [
                [
                    array(
                        'file' => '/var/w𝕨w/site/file.php',
                        'line' => 123,
                        'function' => 'func',
                    ),
                    array(
                        'file' => '/var/w𝕨w/site/𝓋endor/𝒻ile.php',
                        'line' => 123,
                        'function' => 'func',
                    ),
                ],
            ],
            array(
                'html' => static function ($html) {
                    $expect = '<li class="m_trace">
                        <table class="table-bordered">
                        <caption>trace</caption>
                        <thead>
                            <tr>
                                <th class="t_string" scope="col"></th>
                                <th class="t_string" scope="col">file</th>
                                <th class="t_string" scope="col">line</th>
                                <th class="t_string" scope="col">function</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th class="t_int t_key" scope="row">0</th>
                                <td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-basename">file.php</span></td>
                                <td class="t_int">123</td>
                                <td class="t_identifier" data-type-more="method"><span class="t_name">func</span></td>
                            </tr>
                            <tr>
                                <th class="t_int t_key" scope="row">1</th>
                                <td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-path-rel"><span class="unicode" data-code-point="1D4CB" title="U-1D4CB: MATHEMATICAL SCRIPT SMALL V">𝓋</span>endor/</span><span class="file-basename"><span class="unicode" data-code-point="1D4BB" title="U-1D4BB: MATHEMATICAL SCRIPT SMALL F">𝒻</span>ile.php</span></td>
                                <td class="t_int">123</td>
                                <td class="t_identifier" data-type-more="method"><span class="t_name">func</span></td>
                            </tr>
                        </tbody>
                        </table>
                        </li>';
                    self::assertStringMatchesFormatNormalized($expect, $html);
                },
            )
        );
    }

    public function testTraceNoFunction()
    {
        $this->testMethod(
            'trace',
            [
                [
                    array(
                        'file' => '/var/w𝕨w/site/file.php',
                        'line' => 123,
                        'function' => 'func',
                    ),
                    array(
                        'file' => '/var/w𝕨w/site/𝓋endor/𝒻ile.php',
                        'line' => 123,
                        'function' => 'func',
                    ),
                ],
                \bdk\Debug::meta('columns', ['file', 'line']),
            ],
            array(
                'html' => static function ($html) {
                    $expect = '<li class="m_trace">
                        <table class="table-bordered">
                        <caption>trace</caption>
                        <thead>
                            <tr>
                                <th class="t_string" scope="col"></th>
                                <th class="t_string" scope="col">file</th>
                                <th class="t_string" scope="col">line</th>
                            </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <th class="t_int t_key" scope="row">0</th>
                            <td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-basename">file.php</span></td>
                            <td class="t_int">123</td>
                        </tr>
                        <tr>
                            <th class="t_int t_key" scope="row">1</th>
                            <td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-path-rel"><span class="unicode" data-code-point="1D4CB" title="U-1D4CB: MATHEMATICAL SCRIPT SMALL V">𝓋</span>endor/</span><span class="file-basename"><span class="unicode" data-code-point="1D4BB" title="U-1D4BB: MATHEMATICAL SCRIPT SMALL F">𝒻</span>ile.php</span></td>
                            <td class="t_int">123</td>
                        </tr>
                        </tbody>
                        </table>
                        </li>';
                    self::assertStringMatchesFormatNormalized($expect, $html);
                },
            )
        );
    }

    // this test needs improvement
    public function testInclInternal()
    {
        $this->testMethod(
            'trace',
            [
                \bdk\Debug::meta('inclInternal'),
            ],
            array(
                'entry' => static function (LogEntry $logEntry) {
                    // bdk\AbstractDebug for php 5.x
                    // bdk\Debug for php 7.x +
                    self::assertStringMatchesFormat('bdk\%ADebug->__call(\'trace\')', (string) $logEntry['args'][0]['rows'][0][3]);
                },
            )
        );
    }
}
