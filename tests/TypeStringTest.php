<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeStringTest extends DebugTestFramework
{

    public function dumpProvider()
    {
        $ts = time();
        // val, html, text, script
        return array(
            array('a "string"'."\r\n\tline 2",
                '<span class="t_string">a &quot;string&quot;<span class="ws_r"></span><span class="ws_n"></span>'."\n"
                    .'<span class="ws_t">'."\t".'</span>line 2</span>',
                '"a "string"'."\r\n\t".'line 2"',
                'a "string"'."\r\n\t".'line 2'
            ),
            array("\xef\xbb\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \x07 (a control char).",
                // '<span class="t_string"><span class="binary">\xef\xbb\xbf</span>Pesky &lt;abbr title=&quot;Byte-Order-Mark&quot;&gt;BOM&lt;/abbr&gt; and <span class="binary">\x07</span> (a control char).</span>',
                '<span class="t_string"><a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>Pesky &lt;abbr title=&quot;Byte-Order-Mark&quot;&gt;BOM&lt;/abbr&gt; and <span class="binary"><span class="c1-control" title="BEL: \x07">␇</span></span> (a control char).</span>',
                '"\u{feff}Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and \x07 (a control char)."',
                '\u{feff}Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and \x07 (a control char).'),
            array('10', '<span class="t_string numeric">10</span>', '"10"', '10'),
            array('10.10', '<span class="t_string numeric">10.10</span>', '"10.10"', '10.10'),
            array((string) $ts,
                '<span class="t_string numeric timestamp" title="'.date('Y-m-d H:i:s', $ts).'">'.$ts.'</span>',
                '📅 "'.$ts.'" ('.date('Y-m-d H:i:s').')',
                $ts.' ('.date('Y-m-d H:i:s').')'
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
    /*
    public function testDump($val, $html, $text, $script)
    {
        $dump = $this->debug->output->outputHtml->dump($val);
        $this->assertSame($html, $dump);
        $dump = $this->debug->output->outputText->dump($val);
        $this->assertSame($text, $dump);
        $dump = $this->debug->output->outputScript->dump($val);
        $this->assertSame($script, $dump);
    }
    */
}
