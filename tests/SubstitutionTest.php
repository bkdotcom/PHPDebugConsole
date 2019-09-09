<?php

use bdk\Debug;
use bdk\Debug\LogEntry;

/**
 * PHPUnit tests for Debug Methods
 */
class SubstitutionTest extends DebugTestFramework
{

    public function testTypesBasic()
    {
        $this->doTestSubstitution(
            'log',
            array(
                '%s %s %s %s %s',
                array(0),
                array(),
                null,
                true,
                false,
            ),
            array(
                'chromeLogger' => array(
                    '{{args}}',
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">'
                    .'<span class="t_keyword">array</span><span class="t_punct">(</span>1<span class="t_punct">)</span>'
                    .' <span class="t_keyword">array</span><span class="t_punct">(</span>0<span class="t_punct">)</span>'
                    .' <span class="t_null">null</span>'
                    .' <span class="t_bool true">true</span>'
                    .' <span class="false t_bool">false</span>'
                    .'</span></li>',
                'script' => 'console.log({{args}});',
                'text' => 'array(1) array(0) null true false',
            )
        );
    }

    public function testTypesMore()
    {
        $datetime = new \DateTime();
        $this->doTestSubstitution(
            'log',
            array(
                '%s %s %s',
                array($this, __FUNCTION__), // callable
                function ($foo, $bar) {
                    return $foo.$bar;
                },
                $datetime,
            ),
            array(
                'chromeLogger' => array(
                    array(
                        '%s %s %s',
                        'callable: '.__CLASS__.'::'.__FUNCTION__,
                        'Closure',
                        $datetime->format(\DateTime::ISO8601),
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},['
                    .'"callable: '.__CLASS__.'::'.__FUNCTION__.'",'
                    .'"Closure",'
                    .'"'.$datetime->format(\DateTime::ISO8601).'"'
                    .']]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">'
                    .'<span class="t_callable"><span class="t_type">callable</span> <span class="classname">'.__CLASS__.'</span><span class="t_operator">::</span><span class="t_identifier">'.__FUNCTION__.'</span></span>'
                    .' <span class="classname">Closure</span>'
                    .' '.$datetime->format(\DateTime::ISO8601)
                    .'</span></li>',
                'script' => 'console.log("%%s %%s %%s","callable: '.__CLASS__.'::'.__FUNCTION__.'","Closure","'.$datetime->format(\DateTime::ISO8601).'");',
                'text' => 'callable: '.__CLASS__.'::'.__FUNCTION__.' Closure '.$datetime->format(\DateTime::ISO8601),
            )
        );
    }

    public function testTypesOther()
    {
        $binary = base64_decode('j/v9wNrF5i1abMXFW/4vVw==');
        $binaryStr = \trim(\chunk_split(\bin2hex($binary), 2, ' '));
        $time = time();
        $timeStr = date('Y-m-d H:i:s', $time);
        $this->doTestSubstitution(
            'log',
            array(
                '%s %s %s %s %s',
                123.45,
                42,
                $time,
                '<i>boring</i>',
                $binary, // binary
            ),
            array(
                'entry' => array(
                    'log',
                    '{{args}}',
                    array(),
                ),
                'chromeLogger' => array(
                    array(
                        '%s %s %s %s %s',
                        123.45,
                        42,
                        $time.' ('.$timeStr.')',
                        '<i>boring</i>',
                        $binaryStr, // binary
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-%d: %d|[{{meta}},[123.45,42,"'.$time.' ('.$timeStr.')","<i>boring</i>","'.$binaryStr.'"]]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">'
                    .'<span class="t_float">123.45</span>'
                    .' <span class="t_int">42</span>'
                    .' <span class="t_int timestamp" title="'.$timeStr.'">'.$time.'</span>'
                    .' &lt;i&gt;boring&lt;/i&gt;'
                    .' <span class="binary">'.$binaryStr.'</span>'
                    .'</span></li>',
                'script' => 'console.log("%%s %%s %%s %%s %%s",123.45,42,"'.$time.' ('.$timeStr.')","<i>boring</i>","'.$binaryStr.'");',
                'text' => '123.45 42 📅 '.$time.' ('.$timeStr.') <i>boring</i> '.$binaryStr,
            )
        );
    }

    public function testMarkup()
    {
        $location = 'http://localhost/?foo=bar&jim=slim';
        $this->doTestSubstitution(
            'log',
            array(
                '%cLocation:%c <a href="%s">%s</a>',
                'font-weight:bold;',
                '',
                $location,
                $location,
                'extra',
                Debug::_meta('sanitize', false),
            ),
            array(
                'entry' => array(
                    'log',
                    '{{args}}',
                    array(
                        'sanitize' => false,
                    ),
                ),
                'chromeLogger' => array(
                    '{{args}}',
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string"><span style="font-weight:bold;">Location:</span><span> <a href="http://localhost/?foo=bar&jim=slim">http://localhost/?foo=bar&jim=slim</a></span></span> = <span class="t_string">extra</span></li>',
                'script' => 'console.log({{args}});',
                'text' => 'Location: <a href="http://localhost/?foo=bar&jim=slim">http://localhost/?foo=bar&jim=slim</a> = "extra"',
            )
        );
    }

    public function testMarkupSanitize()
    {
        $args = array(
            "\xef\xbb\xbf".'%c%s%c <b>boldy</b> %s',
            'color:red;',
            'sub 1',
            '',
            '<b>sub bold</b>',
            'extra',
        );

        $this->doTestSubstitution(
            'log',
            $args,
            array(
                'entry' => array(
                    'log',
                    '{{args}}',
                    array(),
                ),
                'chromeLogger' => array(
                    // array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')),
                    '{{args}}',
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log">'
                    .'<span class="no-quotes t_string">'
                        .'<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>'
                        .'<span style="color:red;">sub 1</span>'
                        .'<span> &lt;b&gt;boldy&lt;/b&gt; &lt;b&gt;sub bold&lt;/b&gt;</span>'
                    .'</span> = <span class="t_string">extra</span>'
                    .'</li>',
                'script' => 'console.log('
                    .trim(json_encode(array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), JSON_UNESCAPED_SLASHES), '[]')
                .');',
                'text' => '\\u{feff}sub 1 <b>boldy</b> <b>sub bold</b> = "extra"',
            )
        );
    }

    public function testMarkupSanitizeFalse()
    {
        $args = array(
            "\xef\xbb\xbf".'%c%s%c <b>boldy</b> %s',
            'color:red;',
            'sub 1',
            '',
            '<b>sub bold</b>',
            'extra',
            $this->debug->meta('sanitize', false)
        );

        $this->doTestSubstitution(
            'log',
            $args,
            array(
                'entry' => array(
                    'log',
                    '{{args}}',
                    array(
                        'sanitize' => false,
                    ),
                ),
                'chromeLogger' => array(
                    array_slice(array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log">'
                    .'<span class="no-quotes t_string">'
                        .'<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>'
                        .'<span style="color:red;">sub 1</span>'
                        .'<span> <b>boldy</b> <b>sub bold</b></span>'
                    .'</span> = <span class="t_string">extra</span>'
                    .'</li>',
                'script' => 'console.log('
                    .trim(json_encode(array_slice(array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1), JSON_UNESCAPED_SLASHES), '[]')
                .');',
                'text' => '\\u{feff}sub 1 <b>boldy</b> <b>sub bold</b> = "extra"',
            )
        );
    }

    public function testMarkupSanitizeFirstFalse()
    {
        $args = array(
            "\xef\xbb\xbf".'%c%s%c <b>boldy</b> %s',
            'color:red;',
            'sub 1',
            '',
            '<b>sub bold</b>',
            'extra',
            $this->debug->meta('sanitizeFirst', false)
        );

        $this->doTestSubstitution(
            'log',
            $args,
            array(
                'entry' => array(
                    'log',
                    '{{args}}',
                    array(
                        'sanitizeFirst' => false,
                    ),
                ),
                'chromeLogger' => array(
                    array_slice(array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log">'
                    .'<span class="no-quotes t_string">'
                        .'<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>'
                        .'<span style="color:red;">sub 1</span>'
                        .'<span> <b>boldy</b> &lt;b&gt;sub bold&lt;/b&gt;</span>'
                    .'</span> = <span class="t_string">extra</span>'
                    .'</li>',
                'script' => 'console.log('
                    .trim(json_encode(array_slice(array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1), JSON_UNESCAPED_SLASHES), '[]')
                .');',
                'text' => '\\u{feff}sub 1 <b>boldy</b> <b>sub bold</b> = "extra"',
            )
        );
    }

    public function doTestSubstitution($method, $args, $tests)
    {
        $argsSansMeta = array();
        foreach ($args as $arg) {
            $isMeta = \is_array($arg) && isset($arg['debug']) && $arg['debug'] === Debug::META;
            if (!$isMeta) {
                $argsSansMeta[] = $arg;
            }
        }
        $testsBack = $tests;
        $argsSansMetaBack = $argsSansMeta;
        $replace = array(
            '%c' => '%%c',
            '%s' => '%%s',
        );
        foreach (array('error','info','log','warn') as $method) {
            // $this->stderr('method', $method);
            if ($method == 'assert') {
                array_unshift($args, false);
                array_unshift($argsSansMeta, false);
            }
            foreach ($tests as $name => $test) {
                if (is_array($test)) {
                    $foundArgs = false;
                    foreach ($test as $i => $val) {
                        if ($val === '{{args}}') {
                            $foundArgs = true;
                            $test[$i] = $argsSansMeta;
                            if ($name == 'entry' && $method == 'assert') {
                                array_shift($test[$i]);
                            }
                        }
                    }
                    if ($name == 'chromeLogger') {
                        if (!$foundArgs && $method == 'assert') {
                            array_unshift($test[0], false);
                        }
                        $test[0] = array_map(function ($val) {
                            return $this->debug->dumpBase->dump($val);
                        }, $test[0]);
                        $test[1] = in_array($method, array('error','warn'))
                            ? $this->file.': '.$this->line
                            : null;
                        $test[2] = $method == 'log'
                            ? ''
                            : $method;
                    } elseif ($name == 'entry') {
                        $test[0] = $method;
                        if (in_array($method, array('error','warn'))) {
                            $test[2]['detectFiles'] = true;
                            $test[2]['file'] = $this->file;
                            $test[2]['line'] = $this->line;
                        }
                    }
                    $tests[$name] = $test;
                } else {
                    if ($name == 'firephp') {
                        $i = $method == 'assert'
                            ? 1
                            : 0;
                        $label = $argsSansMeta[$i];
                        $label = $this->debug->dumpBase->dump($label);
                        $label = strtr($label, $replace);
                        // $label = strtr($label, array('\\u', 'foo'));
                        // $test = str_replace('{{label}}', $label, $test);
                        $firephpMethods = array(
                            'assert' => 'LOG',
                            'log' => 'LOG',
                            'info' => 'INFO',
                            'warn' => 'WARN',
                            'error' => 'ERROR',
                        );
                        $firemeta = array(
                            'Label' => $label,
                            'Type' => $firephpMethods[$method],
                        );
                        if (in_array($method, array('error','warn'))) {
                            $firemeta['File'] = $this->file;
                            $firemeta['Line'] = $this->line;
                        }
                        ksort($firemeta);
                        $test = str_replace('{{meta}}', json_encode($firemeta, JSON_UNESCAPED_SLASHES), $test);
                    } elseif ($name == 'html') {
                        $attribs = array(
                            'class' => 'm_'.$method,
                        );
                        if (in_array($method, array('error','warn'))) {
                            $attribs['data-detect-files'] = true;
                            $attribs['data-file'] = $this->file;
                            $attribs['data-line'] = $this->line;
                        }
                        $test = str_replace(' class="m_log"', \bdk\Debug\Utilities::buildAttribString($attribs), $test);
                    } elseif ($name == 'script') {
                        $test = str_replace('console.log', 'console.'.$method, $test);
                        $fileLine = $this->file.': line '.$this->line;
                        if (in_array($method, array('error','warn'))) {
                            $argsSansMeta[] = $fileLine;
                            if (strpos($test, '{{args}}') === false) {
                                $test = str_replace(');', ',"'.$fileLine.'");', $test);
                            }
                        } else {
                            $test = str_replace(',"'.$fileLine.'"', '', $test);
                        }
                        if ($method == 'assert' && strpos($test, '{{args}}') === false) {
                            $test = str_replace('console.assert(', 'console.assert(false,', $test);
                        }
                    } elseif ($name == 'text') {
                        $prefixes = array(
                            'assert' => '≠ ',
                            'log' => '',
                            'error' => '⦻ ',
                            'info' => 'ℹ ',
                            'warn' => '⚠ ',
                        );
                        $test = $prefixes[$method].$test;
                    }
                    if (strpos($test, '{{args}}') !== false) {
                        $i = $method == 'assert'
                            ? 2
                            : 1;
                        $argStr = $name == 'firephp'
                            ? json_encode(array_slice($argsSansMeta, $i), JSON_UNESCAPED_SLASHES)
                            : trim(json_encode($argsSansMeta, JSON_UNESCAPED_SLASHES), '[]');
                        $argStr = strtr($argStr, $replace);
                        $test = str_replace('{{args}}', $argStr, $test);
                    }
                    $tests[$name] = $test;
                }
                // $this->stderr('test', $name, $test);
            }
            $this->testMethod($method, $args, $tests);
            if ($method == 'assert') {
                array_shift($args);
                array_shift($argsSansMeta);
            }
            $tests = $testsBack;
            $argsSansMeta = $argsSansMetaBack;
        }
    }
}
