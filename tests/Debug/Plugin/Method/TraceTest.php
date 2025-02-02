<?php

namespace bdk\Test\Debug\Plugin\Method;

use bdk\Debug\Abstraction\Abstracter;
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

                    self::assertSame('eval()\'d code', $trace[0]['file']);
                    self::assertSame(1, $trace[0]['line']);
                    self::assertSame(Abstracter::UNDEFINED, $trace[0]['function']);

                    self::assertSame($values['file0'], $trace[1]['file']);
                    self::assertSame($values['line0'], $trace[1]['line']);
                    self::assertSame('eval', $trace[1]['function']);
                    self::assertIsInt($trace[1]['line']);

                    self::assertSame($values['function1'], $trace[2]['function']);

                    self::assertSame('trace', $logEntry->getMeta('caption'));
                    self::assertTrue($logEntry->getMeta('detectFiles'));
                    self::assertNull($logEntry->getMeta('inclContext'));
                    self::assertFalse($logEntry->getMeta('sortable'));
                },
                'chromeLogger' => static function ($outputArray, LogEntry $logEntry) {
                    $traceExpect = \array_map(static function ($row) {
                        foreach ($row as $k => $v) {
                            if ($v === Abstracter::UNDEFINED) {
                                unset($row[$k]);
                            }
                        }
                        return $row;
                    }, $logEntry['args'][0]);
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
                            $trace[$tracei]['file'],
                            $trace[$tracei]['line'],
                            isset($trace[$tracei]['function']) && $trace[$tracei]['function'] !== Abstracter::UNDEFINED
                                ? $trace[$tracei]['function']
                                : null,
                        );
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
                    $filePaths = \array_column($trace, 'file');
                    $filePaths = \array_slice($filePaths, 1); // remove eval()'d code frame
                    $commonPrefix = $this->debug->stringUtil->commonPrefix($filePaths);
                    for ($i = 1; $i < $count; $i++) {
                        // build expected values
                        $valuesExpect = \array_merge(
                            array((string) ($i - 1)),
                            \array_values($trace[$i - 1])
                        );
                        $valuesExpect[1] = $valuesExpect[1] === null ? 'null' : $this->debug->getDump('html')->helper->markupFilePath($valuesExpect[1], $commonPrefix); // file
                        $valuesExpect[2] = $valuesExpect[2] === null ? 'null' : (string) $valuesExpect[2];
                        $valuesExpect[3] = $this->debug->getDump('html')->valDumper->markupIdentifier($valuesExpect[3], 'function', 'span', array(), true);

                        // build actual values
                        $valuesActual = $matches[$i];
                        \array_shift($valuesActual);
                        $attribs = $valuesActual[3];
                        unset($valuesActual[3]);
                        $valuesActual = \array_values($valuesActual);
                        if (\strpos($attribs, 't_undefined') !== false) {
                            $valuesActual[3] = $this->debug->getDump('html')->valDumper->markupIdentifier(Abstracter::UNDEFINED, 'function', 'span', array(), true);
                        }

                        // \bdk\Debug::varDump('valuesExpect', $i, $valuesExpect);
                        // \bdk\Debug::varDump('valuesActual', $i, $valuesActual);

                        self::assertSame($valuesExpect, $valuesActual);
                    }
                },
                'script' => static function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    \preg_match('#console.table\((.+)\);#', $output, $matches);
                    self::assertSame(
                        \str_replace(\json_encode(Abstracter::UNDEFINED), 'undefined', \json_encode($trace, JSON_UNESCAPED_SLASHES)),
                        $matches[1]
                    );
                },
                'text' => function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    $traceExpect = \array_map(static function ($row) {
                        foreach ($row as $k => $v) {
                            if ($v === Abstracter::UNDEFINED) {
                                unset($row[$k]);
                            }
                        }
                        return $row;
                    }, $trace);
                    self::assertNotEmpty($traceExpect);
                    $expect = 'trace = ' . $this->debug->getDump('text')->valDumper->dump($traceExpect);
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
            'detectFiles' => true,
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
                'commonRowInfo' => array(
                    'commonFilePrefix' => '/path/to/file.php',
                ),
            ),
        );
        $this->testMethod(
            'trace',
            array($this->debug->meta('trace', $frames)),
            array(
                'entry' => array(
                    'method' => 'trace',
                    'args' => array($frames),
                    'meta' => $metaExpect,
                ),
                'wamp' => array(
                    'trace',
                    array(
                        array(
                            array(
                                'file' => '/path/to/file.php',
                                'line' => 42,
                                'function' => 'Foo::bar',
                                '__debug_key_order__' => array(
                                    'file',
                                    'line',
                                    'function',
                                ),
                            ),
                        ),
                    ),
                    \array_merge(array(
                        'foundFiles' => array(),
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
                'entry' => function (LogEntry $logEntry) {
                    $tableInfo = $logEntry->getMeta('tableInfo');
                    self::assertSame('trace', $logEntry->getMeta('caption'));
                    self::assertTrue($logEntry->getMeta('detectFiles'));
                    self::assertTrue($logEntry->getMeta('inclContext'));
                    self::assertFalse($logEntry->getMeta('sortable'));

                    self::assertIsArray($tableInfo['rows'][0]['args']);
                    self::assertIsArray($tableInfo['rows'][0]['context']);
                },
                'html' => function ($output, LogEntry $logEntry) {
                    $expectStartsWith = <<<'EOD'
<li class="m_trace" data-detect-files="true">
<table class="table-bordered trace-context">
<caption>trace</caption>
<thead>
<tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th><th scope="col">function</th></tr>
</thead>
<tbody>
<tr class="expanded" data-toggle="next">
EOD;
                    self::assertStringContainsString($expectStartsWith, $output);
                    $expectMatch = '%a<tr class="context" style="display:table-row;"><td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-start="%d"><code class="language-php">%a';
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
                    $expect = '<li class="m_trace" data-detect-files="true">
                        <table class="table-bordered">
                        <caption>trace</caption>
                        <thead>
                            <tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th><th scope="col">function</th></tr>
                        </thead>
                        <tbody>
                            <tr><th class="t_int t_key text-right" scope="row">0</th><td class="no-quotes t_string"><span class="file-basepath">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-basename">file.php</span></td><td class="t_int">123</td><td class="no-quotes t_identifier t_string"><span class="t_name">func</span></td></tr>
                            <tr><th class="t_int t_key text-right" scope="row">1</th><td class="no-quotes t_string"><span class="file-basepath">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-relpath"><span class="unicode" data-code-point="1D4CB" title="U-1D4CB: MATHEMATICAL SCRIPT SMALL V">𝓋</span>endor/</span><span class="file-basename"><span class="unicode" data-code-point="1D4BB" title="U-1D4BB: MATHEMATICAL SCRIPT SMALL F">𝒻</span>ile.php</span></td><td class="t_int">123</td><td class="no-quotes t_identifier t_string"><span class="t_name">func</span></td></tr>
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
                    $expect = '<li class="m_trace" data-detect-files="true">
                        <table class="table-bordered">
                        <caption>trace</caption>
                        <thead>
                            <tr><th>&nbsp;</th><th scope="col">file</th><th scope="col">line</th></tr>
                        </thead>
                        <tbody>
                        <tr><th class="t_int t_key text-right" scope="row">0</th><td class="no-quotes t_string"><span class="file-basepath">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-basename">file.php</span></td><td class="t_int">123</td></tr>
                        <tr><th class="t_int t_key text-right" scope="row">1</th><td class="no-quotes t_string"><span class="file-basepath">/var/w<span class="unicode" data-code-point="1D568" title="U-1D568: MATHEMATICAL DOUBLE-STRUCK SMALL W">𝕨</span>w/site/</span><span class="file-relpath"><span class="unicode" data-code-point="1D4CB" title="U-1D4CB: MATHEMATICAL SCRIPT SMALL V">𝓋</span>endor/</span><span class="file-basename"><span class="unicode" data-code-point="1D4BB" title="U-1D4BB: MATHEMATICAL SCRIPT SMALL F">𝒻</span>ile.php</span></td><td class="t_int">123</td></tr>
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
                    self::assertStringMatchesFormat('bdk\%ADebug->__call(\'trace\')', $logEntry['args'][0][0]['function']);
                },
            )
        );
    }
}
