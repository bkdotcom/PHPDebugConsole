<?php

/**
 * PHPUnit tests for Debug class
 */
class TypeStringTest extends DebugTestFramework
{

    public function providerTestMethod()
    {
        $ts = time();
        return array(
            array(
                'log',
                array(
                    'string', 'a "string"'."\r\n\tline 2",
                ),
                array(
                    'chromeLogger' => '[["string","a \"string\"\r\n\tline 2"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">string</span> = <span class="t_string">a &quot;string&quot;<span class="ws_r"></span><span class="ws_n"></span>'."\n"
                        .'<span class="ws_t">'."\t".'</span>line 2</span></li>',
                    'text' => "string = \"a \"string\"\r\n\tline 2\"",
                    'script' => 'console.log("string","a \"string\"\r\n\tline 2");',
                ),
            ),
            array(
                'log',
                array(
                    "\xef\xbb\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \x07 (a control char).",
                    \bdk\Debug::_meta('sanitize', false),
                ),
                array(
                    'chromeLogger' => '[["\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM<\/abbr> and \\\x07 (a control char)."],null,""]',
                    'firephp' => 'X-Wf-1-1-1-19: %d|[{"Type":"LOG"},"\\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \\\x07 (a control char)."]|',
                    'html' => '<li class="m_log"><span class="no-quotes t_string"><a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and <span class="binary"><span class="c1-control" title="BEL: \x07">␇</span></span> (a control char).</span></li>',
                    'text' => '\u{feff}Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and \x07 (a control char).',
                    'script' => 'console.log("\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \\\x07 (a control char).");',
                ),
            ),
            array(
                'log',
                array('numeric string', '10'),
                array(
                    'chromeLogger' => '[["numeric string","10"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">numeric string</span> = <span class="numeric t_string">10</span></li>',
                    'text' => 'numeric string = "10"',
                    'script' => 'console.log("numeric string","10");',
                ),
            ),
            array(
                'log',
                array('numeric string', '10.10'),
                array(
                    'chromeLogger' => '[["numeric string","10.10"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">numeric string</span> = <span class="numeric t_string">10.10</span></li>',
                    'text' => 'numeric string = "10.10"',
                    'script' => 'console.log("numeric string","10.10");',
                ),
            ),
            array(
                'log',
                array('timestamp', (string) $ts),
                array(
                    'chromeLogger' => '[["timestamp","'.$ts.' ('.date('Y-m-d H:i:s').')"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">timestamp</span> = <span class="numeric t_string timestamp" title="'.date('Y-m-d H:i:s', $ts).'">'.$ts.'</span></li>',
                    'text' => 'timestamp = 📅 "'.$ts.'" ('.date('Y-m-d H:i:s').')',
                    'script' => 'console.log("timestamp","'.$ts.' ('.date('Y-m-d H:i:s').')");',
                ),
            ),
        );
    }
}
