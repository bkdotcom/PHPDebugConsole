<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeStringTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        $ts = time();
        // val, html, text, script
        return array(
            array(
                'log',
                array(
                    'string', 'a "string"'."\r\n\tline 2",
                ),
                array(
                    'html' => '<div class="m_log"><span class="t_string no-pseudo">string</span> = <span class="t_string">a &quot;string&quot;<span class="ws_r"></span><span class="ws_n"></span>'."\n"
                        .'<span class="ws_t">'."\t".'</span>line 2</span></div>',
                    'text' => "string = \"a \"string\"\r\n\tline 2\"",
                    'script' => 'console.log("string","a \"string\"\r\n\tline 2");',
                ),
            ),
            array(
                'log',
                array(
                    "\xef\xbb\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \x07 (a control char).",
                ),
                array(
                    'html' => '<div class="m_log"><span class="t_string no-pseudo"><a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and <span class="binary"><span class="c1-control" title="BEL: \x07">␇</span></span> (a control char).</span></div>',
                    'text' => "\xef\xbb\xbfPesky BOM and \x07 (a control char).",
                    'script' => 'console.log("\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM<\/abbr> and \\\x07 (a control char).");',
                ),
            ),
            array(
                'log',
                array('numeric string', '10'),
                array(
                    'html' => '<div class="m_log"><span class="t_string no-pseudo">numeric string</span> = <span class="t_string numeric">10</span></div>',
                    'text' => 'numeric string = "10"',
                    'script' => 'console.log("numeric string","10");',
                ),
            ),
            array(
                'log',
                array('numeric string', '10.10'),
                array(
                    'html' => '<div class="m_log"><span class="t_string no-pseudo">numeric string</span> = <span class="t_string numeric">10.10</span></div>',
                    'text' => 'numeric string = "10.10"',
                    'script' => 'console.log("numeric string","10.10");',
                ),
            ),
            array(
                'log',
                array('timestamp', (string) $ts),
                array(
                    // 'html' => '<div class="m_log"><span class="t_string numeric timestamp" title="'.date('Y-m-d H:i:s', $ts).'">'.$ts.'</span></div>',
                    'html' => '<div class="m_log"><span class="t_string no-pseudo">timestamp</span> = <span class="t_string numeric timestamp" title="'.date('Y-m-d H:i:s', $ts).'">'.$ts.'</span></div>',
                    'text' => 'timestamp = 📅 "'.$ts.'" ('.date('Y-m-d H:i:s').')',
                    'script' => 'console.log("timestamp","'.$ts.' ('.date('Y-m-d H:i:s').')");',
                ),
            ),
        );
    }
}
