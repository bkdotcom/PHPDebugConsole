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
                    $trace = $logEntry['args'][0];

                    self::assertSame('eval()\'d code', $trace[0][0]);
                    self::assertSame(1, $trace[0][1]);
                    self::assertSame(Abstracter::UNDEFINED, $trace[0][2]);

                    // file
                    self::assertInstanceOf('bdk\Debug\Abstraction\Abstraction', $trace[1][0]);
                    self::assertSame(\bdk\Debug\Abstraction\Type::TYPE_STRING_FILEPATH, $trace[1][0]->getValue('typeMore'));
                    self::assertSame($values['file0'], (string) $trace[1][0]);

                    // line
                    self::assertIsInt($trace[1][1]);
                    self::assertSame($values['line0'], $trace[1][1]);

                    // function
                    self::assertEquals(new Abstraction(Type::TYPE_IDENTIFIER, array(
                        'value' => 'eval',
                        'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    )), $trace[1][2]);
                    self::assertEquals(new Abstraction(Type::TYPE_IDENTIFIER, array(
                        'value' => $values['function1'],
                        'typeMore' => Type::TYPE_IDENTIFIER_METHOD,
                    )), $trace[2][2]);

                    self::assertSame('trace', $logEntry->getMeta('caption'));
                    self::assertNull($logEntry->getMeta('inclContext'));
                    self::assertFalse($logEntry->getMeta('sortable'));
                },
                'chromeLogger' => static function ($outputArray, LogEntry $logEntry) {
                    $cols = ['file', 'line', 'function'];
                    $traceExpect = \array_map(static function ($row) use ($cols) {
                        $row = \array_combine($cols, $row);
                        foreach ($row as $k => $v) {
                            if ($v === Abstracter::UNDEFINED) {
                                unset($row[$k]);
                            } elseif ($v instanceof Abstraction) {
                                $row[$k] = (string) $v;
                            }
                        }
                        return $row;
                    }, $logEntry['args'][0]);
                    // \bdk\Debug::varDump('traceExpect', $traceExpect);
                    // \bdk\Debug::varDump('outputArray', $outputArray[0][0]);
                    self::assertSame($traceExpect, $outputArray[0][0]);
                    self::assertSame(null, $outputArray[1]);
                    self::assertSame('table', $outputArray[2]);
                },
                'firephp' => static function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
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
                        $tracei = $i - 1;
                        $valuesExpect = array(
                            $tracei,
                            (string) $trace[$tracei][0], // file
                            $trace[$tracei][1],
                            isset($trace[$tracei][2]) && $trace[$tracei][2] !== Abstracter::UNDEFINED
                                ? (string) $trace[$tracei][2]
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
                        . '<tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th><th scope="col">function</th></tr>' . "\n"
                        . '</thead>', $output);
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
                    $traceExpect = \array_map(static function (array $frame) {
                        $row = \array_combine(['file', 'line', 'function'], $frame);
                        $row['file'] = (string) $row['file'];
                        $row['function'] = (string) $row['function'];
                        return $row;
                    }, $logEntry['args'][0]);
                    \preg_match('#console.table\((.+)\);#', $output, $matches);
                    $expect = \str_replace(\json_encode(Abstracter::UNDEFINED), 'undefined', \json_encode($traceExpect, JSON_UNESCAPED_SLASHES));
                    // \bdk\Debug::varDump('expect', $expect);
                    // \bdk\Debug::varDump('actual', $matches[1]);
                    self::assertSame($expect, $matches[1]);
                },
                'text' => function ($output, LogEntry $logEntry) {
                    $trace = \array_map(static function (array $frame) {
                        return \array_combine(['file', 'line', 'function'], $frame);
                    }, $logEntry['args'][0]);
                    $traceExpect = \array_map(function ($row) {
                        foreach ($row as $k => $v) {
                            if ($v === Abstracter::UNDEFINED) {
                                unset($row[$k]);
                            } elseif ($v instanceof Abstraction) {
                                $row[$k] = $this->debug->getDump('text')->valDumper->dump($v, array('addQuotes' => false));
                            }
                        }
                        return $row;
                    }, $trace);
                    self::assertNotEmpty($traceExpect);
                    // @todo this isn't a very good test
                    $expect = 'trace = ' . $this->debug->getDump('text')->valDumper->dump($traceExpect);
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
        );
        $metaExpect = array(
            'caption' => 'trace',
            'inclArgs' => false,
            'inclInternal' => false,
            'limit' => 0,
            'sortable' => false,
            'tableInfo' => array(
                'class' => null,
                'columns' => array(
                    array('key' => 'file'),
                    array('key' => 'line'),
                    array('key' => 'function'),
                ),
                'haveObjRow' => false,
                'indexLabel' => null,
                'rows' => array(),
                'summary' => '',
            ),
        );
        $this->testMethod(
            'trace',
            [$this->debug->meta('trace', $frames)],
            array(
                'entry' => static function (LogEntry $logEntry) use ($frames, $metaExpect) {
                    self::assertSame('trace', $logEntry['method']);
                    $tableDataExpect = \array_map(static function (array $frame) {
                        return \array_values($frame);
                    }, $frames);
                    $tableDataActual = \array_map(static function ($row) {
                        $row[0] = (string) $row[0];
                        $row[2] = (string) $row[2];
                        return $row;
                    }, $logEntry['args'][0]);
                    self::assertSame($tableDataExpect, $tableDataActual);
                    self::assertSame($metaExpect, $logEntry['meta']);
                },
                'wamp' => array(
                    'trace',
                    [
                        \array_map(static function ($frame) {
                            $row = \array_values($frame);
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
                    ],
                    \array_merge(array(
                        // 'foundFiles' => array(),
                    ), $metaExpect),
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
                    $tableInfo = $logEntry->getMeta('tableInfo');
                    self::assertSame('trace', $logEntry->getMeta('caption'));
                    self::assertTrue($logEntry->getMeta('inclContext'));
                    self::assertFalse($logEntry->getMeta('sortable'));

                    self::assertIsArray($tableInfo['rows'][0]['args']);
                    self::assertIsArray($tableInfo['rows'][0]['context']);
                },
                'html' => static function ($output, LogEntry $logEntry) {
                    $expectStartsWith = <<<'EOD'
<li class="m_trace">
<table class="table-bordered trace-context">
<caption>trace</caption>
<thead>
<tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th><th scope="col">function</th></tr>
</thead>
<tbody>
<tr class="expanded" data-toggle="next">
EOD;
                    self::assertStringContainsString($expectStartsWith, $output);
                    $expectMatch = '%a<tr class="context" style="display:table-row;"><td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-line-offset="%d" data-start="%d"><code class="language-php">%a';
                    self::assertStringMatchesFormat($expectMatch, $output);
                    $expectContains = '</code></pre><hr />Arguments = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>';
                    self::assertStringContainsString($expectContains, $output);
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
                            <tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th><th scope="col">function</th></tr>
                        </thead>
                        <tbody>
                            <tr><th class="t_int t_key text-right" scope="row">0</th><td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-basename">file.php</span></td><td class="t_int">123</td><td class="t_identifier" data-type-more="method"><span class="t_name">func</span></td></tr>
                            <tr><th class="t_int t_key text-right" scope="row">1</th><td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-path-rel"><span class="unicode" data-code-point="1D4CB" title="U-1D4CB: MATHEMATICAL SCRIPT SMALL V">𝓋</span>endor/</span><span class="file-basename"><span class="unicode" data-code-point="1D4BB" title="U-1D4BB: MATHEMATICAL SCRIPT SMALL F">𝒻</span>ile.php</span></td><td class="t_int">123</td><td class="t_identifier" data-type-more="method"><span class="t_name">func</span></td></tr>
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
                            <tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">0</th><td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-basename">file.php</span></td><td class="t_int">123</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">1</th><td class="no-quotes t_string" data-type-more="filepath"><span class="file-path-common">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-path-rel"><span class="unicode" data-code-point="1D4CB" title="U-1D4CB: MATHEMATICAL SCRIPT SMALL V">𝓋</span>endor/</span><span class="file-basename"><span class="unicode" data-code-point="1D4BB" title="U-1D4BB: MATHEMATICAL SCRIPT SMALL F">𝒻</span>ile.php</span></td><td class="t_int">123</td></tr>
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
                \bdk\Debug::meta('inclInternal')
            ],
            array(
                'entry' => static function (LogEntry $logEntry) {
                    // bdk\AbstractDebug for php 5.x
                    // bdk\Debug for php 7.x +
                    self::assertStringMatchesFormat('bdk\%ADebug->__call(\'trace\')', (string) $logEntry['args'][0][0][2]);
                },
            )
        );
    }
}
