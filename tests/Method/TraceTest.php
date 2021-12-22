<?php

namespace bdk\DebugTests\Method;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug trace method
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
        $this->debug->trace();
        $values = array(
            'file0' => __FILE__,
            'line0' => __LINE__ - 3,
            'function1' => __CLASS__ . '->' . __FUNCTION__,
        );

        $this->testMethod(
            array(
                'dataPath' => 'log/0'
            ),
            array(),
            array(
                'entry' => function (LogEntry $logEntry) use ($values) {
                    $trace = $logEntry['args'][0];
                    $this->assertSame($values['file0'], $trace[0]['file']);
                    $this->assertSame($values['line0'], $trace[0]['line']);
                    $this->assertIsInt($trace[0]['line']);
                    $this->assertSame(Abstracter::UNDEFINED, $trace[0]['function']);
                    $this->assertSame($values['function1'], $trace[1]['function']);

                    $this->assertSame('trace', $logEntry->getMeta('caption'));
                    $this->assertTrue($logEntry->getMeta('detectFiles'));
                    $this->assertNull($logEntry->getMeta('inclContext'));
                    $this->assertFalse($logEntry->getMeta('sortable'));
                },
                'chromeLogger' => function ($outputArray, LogEntry $logEntry) {
                    $traceExpect = \array_map(function ($row) {
                        foreach ($row as $k => $v) {
                            if ($v === Abstracter::UNDEFINED) {
                                unset($row[$k]);
                            }
                        }
                        return $row;
                    }, $logEntry['args'][0]);
                    $this->assertSame($traceExpect, $outputArray[0][0]);
                    $this->assertSame(null, $outputArray[1]);
                    $this->assertSame('table', $outputArray[2]);
                },
                'firephp' => function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    \preg_match('#\|(.+)\|#', $output, $matches);
                    $output = \json_decode($matches[1], true);
                    list($logEntryMeta, $logEntryTable) = $output;
                    $this->assertSame(array(
                        'Label' => 'trace',
                        'Type' => 'TABLE',
                    ), $logEntryMeta);
                    $this->assertSame(array(
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
                        $this->assertSame($valuesExpect, $logEntryTable[$i]);
                    }
                },
                'html' => function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    $this->assertStringContainsString('<caption>trace</caption>' . "\n"
                        . '<thead>' . "\n"
                        . '<tr><th>&nbsp;</th><th>file</th><th scope="col">line</th><th scope="col">function</th></tr>' . "\n"
                        . '</thead>', $output);
                    $matches = array();
                    \preg_match_all('#<tr>'
                        . '<th.*?>(.*?)</th>'
                        . '<td.*?>(.*?)</td>'
                        . '<td.*?>(.*?)</td>'
                        . '<td.*?>(.*?)</td>'
                        . '</tr>#is', $output, $matches, PREG_SET_ORDER);
                    $count = \count($matches);
                    for ($i = 1; $i < $count; $i++) {
                        $valuesExpect = \array_merge(
                            array((string) $i),
                            \array_values($trace[$i])
                        );
                        $valuesExpect[1] = \is_null($valuesExpect[1]) ? 'null' : $valuesExpect[1];
                        $valuesExpect[2] = \is_null($valuesExpect[2]) ? 'null' : (string) $valuesExpect[2];

                        $function = $valuesExpect[3];
                        $regex = '/^(.+)(::|->)(.+)$/';
                        $valuesExpect[3] = \preg_match($regex, $function) || \strpos($function, '{closure}')
                            ? $this->debug->getDump('html')->valDumper->markupIdentifier($function, 'span', array(), true)
                            : '<span class="t_identifier">' . \htmlspecialchars($function) . '</span>';
                        $valuesActual = $matches[$i];
                        \array_shift($valuesActual);
                        echo $i . ': ' . print_r($valuesActual, true) . "\n";
                        $this->assertSame($valuesExpect, $valuesActual);
                    }
                },
                'script' => function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    \preg_match('#console.table\((.+)\);#', $output, $matches);
                    $this->assertSame(
                        \str_replace(\json_encode(Abstracter::UNDEFINED), 'undefined', \json_encode($trace, JSON_UNESCAPED_SLASHES)),
                        $matches[1]
                    );
                },
                'text' => function ($output, LogEntry $logEntry) {
                    $trace = $logEntry['args'][0];
                    $traceExpect = \array_map(function ($row) {
                        foreach ($row as $k => $v) {
                            if ($v === Abstracter::UNDEFINED) {
                                unset($row[$k]);
                            }
                        }
                        return $row;
                    }, $trace);
                    $this->assertNotEmpty($traceExpect);
                    $expect = 'trace = ' . $this->debug->getDump('text')->valDumper->dump($traceExpect);
                    $this->assertSame($expect, \trim($output));
                },
                // 'wamp' => @todo
            )
        );

        $this->debug->setCfg('collect', false);
        $this->testMethod(
            'trace',
            array('log message'),
            array(
                'notLogged' => true,
                'wamp' => false,
            )
        );
    }

    public function testTraceWithContext()
    {
        $this->testMethod(
            'trace',
            array(
                true,   // inclTrace
                $this->debug->meta('cfg', 'objectsExclude', array('*')), // don't inspect any objects
            ),
            array(
                'entry' => function (LogEntry $logEntry) {
                    $tableInfo = $logEntry->getMeta('tableInfo');
                    $this->assertSame('trace', $logEntry->getMeta('caption'));
                    $this->assertTrue($logEntry->getMeta('detectFiles'));
                    $this->assertTrue($logEntry->getMeta('inclContext'));
                    $this->assertFalse($logEntry->getMeta('sortable'));

                    $this->assertIsArray($tableInfo['rows'][0]['args']);
                    $this->assertIsArray($tableInfo['rows'][0]['context']);
                },
                'html' => function ($output, LogEntry $logEntry) {
                    $expectStartsWith = <<<'EOD'
<li class="m_trace" data-detect-files="true">
<table class="table-bordered trace-context">
<caption>trace</caption>
<thead>
<tr><th>&nbsp;</th><th>file</th><th scope="col">line</th><th scope="col">function</th></tr>
</thead>
<tbody>
<tr class="expanded" data-toggle="next">
EOD;
                    $this->assertStringContainsString($expectStartsWith, $output);
                    $expectMatch = '%a<tr class="context" style="display:table-row;"><td colspan="4"><pre class="highlight line-numbers" data-line="%d" data-start="%d"><code class="language-php">%a';
                    $this->assertStringMatchesFormat($expectMatch, $output);
                    $expectContains = '</code></pre><hr />Arguments = <span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>';
                    $this->assertStringContainsString($expectContains, $output);
                },
            )
        );
    }
}
