<?php

namespace bdk\Test\Debug;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;

/**
 * PHPUnit tests for Debug Methods
 *
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Substitution
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\TextAnsi
 */
class SubstitutionTest extends DebugTestFramework
{
    public function testTypesBasic()
    {
        $this->doTestSubstitution(
            'log',
            array(
                '%s %s %s %s %s %s',
                'plain ol string',
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
                    . 'plain ol string'
                    . ' <span class="t_keyword">array</span><span class="t_punct">(</span>1<span class="t_punct">)</span>'
                    . ' <span class="t_keyword">array</span><span class="t_punct">(</span>0<span class="t_punct">)</span>'
                    . ' <span class="t_null">null</span>'
                    . ' <span class="t_bool" data-type-more="true">true</span>'
                    . ' <span class="t_bool" data-type-more="false">false</span>'
                    . '</span></li>',
                'script' => 'console.log({{args}});',
                'text' => 'plain ol string array(1) array(0) null true false',
                // 'wamp' => // @todo
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
                    return $foo . $bar;
                },
                $datetime,
            ),
            array(
                'chromeLogger' => array(
                    array(
                        '%s %s %s',
                        'callable: ' . __CLASS__ . '::' . __FUNCTION__,
                        'Closure',
                        $datetime->format(\DateTime::ISO8601),
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},['
                    . \json_encode('callable: ' . __CLASS__ . '::' . __FUNCTION__) . ','
                    . '"Closure",'
                    . '"' . $datetime->format(\DateTime::ISO8601) . '"'
                    . ']]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">'
                    . '<span class="t_callable"><span class="t_type">callable</span> <span class="classname"><span class="namespace">' . __NAMESPACE__ . '\</span>SubstitutionTest</span><span class="t_operator">::</span><span class="t_identifier">' . __FUNCTION__ . '</span></span>'
                    . ' <span class="classname">Closure</span>'
                    . ' ' . $datetime->format(\DateTime::ISO8601)
                    . '</span></li>',
                'script' => 'console.log("%%s %%s %%s",' . \json_encode('callable: ' . __CLASS__ . '::' . __FUNCTION__) . ',"Closure","' . $datetime->format(\DateTime::ISO8601) . '");',
                'text' => 'callable: ' . __CLASS__ . '::' . __FUNCTION__ . ' Closure ' . $datetime->format(\DateTime::ISO8601),
                // 'wamp' => @todo
            )
        );
    }

    public function testTypesOther()
    {
        $binary = \base64_decode('j/v9wNrF5i1abMXFW/4vVw==');
        $binaryStr = \trim(\chunk_split(\bin2hex($binary), 2, ' '));
        $time = \time();
        $timeStr = \gmdate(self::DATETIME_FORMAT, $time);
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
                    'method' => 'log',
                    'args' => array(
                        '%s %s %s %s %s',
                        123.45,
                        42,
                        array(
                            'debug' => Abstracter::ABSTRACTION,
                            'type' => Abstracter::TYPE_INT,
                            'typeMore' => Abstracter::TYPE_TIMESTAMP,
                            'value' => $time,
                        ),
                        '<i>boring</i>',
                        array(
                            'brief' => false,
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => 16,
                            'type' => Abstracter::TYPE_STRING,
                            'typeMore' => Abstracter::TYPE_STRING_BINARY,
                            'value' => $binary,
                        ),
                    ),
                    'meta' => array(),
                ),
                'chromeLogger' => array(
                    array(
                        '%s %s %s %s %s',
                        123.45,
                        42,
                        $time . ' (' . $timeStr . ')',
                        '<i>boring</i>',
                        $binaryStr, // binary
                    ),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-%d: %d|[{{meta}},[123.45,42,"' . $time . ' (' . $timeStr . ')","<i>boring</i>","' . $binaryStr . '"]]|',
                'html' => '<li class="m_log"><span class="no-quotes t_string">'
                    . '<span class="t_float">123.45</span>'
                    . ' <span class="t_int">42</span>'
                    . ' <span class="timestamp value-container" title="' . $timeStr . '"><span class="t_int" data-type-more="timestamp">' . $time . '</span></span>'
                    . ' &lt;i&gt;boring&lt;/i&gt;'
                    . ' ' . $binaryStr
                    . '</span></li>',
                'script' => 'console.log("%%s %%s %%s %%s %%s",123.45,42,"' . $time . ' (' . $timeStr . ')","<i>boring</i>","' . $binaryStr . '");',
                'text' => '123.45 42 📅 ' . $time . ' (' . $timeStr . ') <i>boring</i> ' . $binaryStr,
                // 'wamp' => @todo
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
                $this->debug->meta('sanitize', false),
            ),
            array(
                'entry' => array(
                    'method' => 'log',
                    'args' => '{{args}}',
                    'meta' => array(
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
                // 'wamp' => @todo
            )
        );
    }

    public function testMarkupSanitize()
    {
        $args = array(
            "\xef\xbb\xbf" . '%c%s%c <b>boldy</b> %s',
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
                    'method' => 'log',
                    'args' => '{{args}}',
                    'meta' => array(),
                ),
                'chromeLogger' => array(
                    // array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')),
                    '{{args}}',
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log">'
                    . '<span class="no-quotes t_string">'
                        . '<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>'
                        . '<span style="color:red;">sub 1</span>'
                        . '<span> &lt;b&gt;boldy&lt;/b&gt; &lt;b&gt;sub bold&lt;/b&gt;</span>'
                    . '</span> = <span class="t_string">extra</span>'
                    . '</li>',
                'script' => 'console.log('
                    . \trim(\json_encode(\array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), JSON_UNESCAPED_SLASHES), '[]')
                . ');',
                'text' => '\\u{feff}sub 1 <b>boldy</b> <b>sub bold</b> = "extra"',
                // 'wamp' => @todo,
            )
        );
    }

    public function testMarkupSanitizeFalse()
    {
        $args = array(
            "\xef\xbb\xbf" . '%c%s%c <b>boldy</b> %s',
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
                    'method' => 'log',
                    'args' => '{{args}}',
                    'meta' => array(
                        'sanitize' => false,
                    ),
                ),
                'chromeLogger' => array(
                    \array_slice(\array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log">'
                    . '<span class="no-quotes t_string">'
                        . '<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>'
                        . '<span style="color:red;">sub 1</span>'
                        . '<span> <b>boldy</b> <b>sub bold</b></span>'
                    . '</span> = <span class="t_string">extra</span>'
                    . '</li>',
                'script' => 'console.log('
                    . \trim(\json_encode(\array_slice(\array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1), JSON_UNESCAPED_SLASHES), '[]')
                . ');',
                'text' => '\\u{feff}sub 1 <b>boldy</b> <b>sub bold</b> = "extra"',
                // 'wamp' => @todo,
            )
        );
    }

    public function testMarkupSanitizeFirstFalse()
    {
        $args = array(
            "\xef\xbb\xbf" . '%c%s%c <b>boldy</b> %s',
            'color:red;',
            'sub 1',
            '',
            '<b>sub bold</b>',
            'extra',
            $this->debug->meta('sanitizeFirst', false),
        );

        $this->doTestSubstitution(
            'log',
            $args,
            array(
                'entry' => array(
                    'method' => 'log',
                    'args' => '{{args}}',
                    'meta' => array(
                        'sanitizeFirst' => false,
                    ),
                ),
                'chromeLogger' => array(
                    \array_slice(\array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1),
                    null,
                    '',
                ),
                'firephp' => 'X-Wf-1-1-1-19: %d|[{{meta}},{{args}}]|',
                'html' => '<li class="m_log">'
                    . '<span class="no-quotes t_string">'
                        . '<a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>'
                        . '<span style="color:red;">sub 1</span>'
                        . '<span> <b>boldy</b> &lt;b&gt;sub bold&lt;/b&gt;</span>'
                    . '</span> = <span class="t_string">extra</span>'
                    . '</li>',
                'script' => 'console.log('
                    . \trim(\json_encode(\array_slice(\array_replace($args, array('\\u{feff}%c%s%c <b>boldy</b> %s')), 0, -1), JSON_UNESCAPED_SLASHES), '[]')
                . ');',
                'text' => '\\u{feff}sub 1 <b>boldy</b> <b>sub bold</b> = "extra"',
                // 'wamp' => @todo,
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
        $methods = array(
            'alert',
            'assert',
            'error',
            'info',
            'log',
            'warn',
        );
        foreach ($methods as $method) {
            if ($method === 'assert') {
                \array_unshift($args, false);
                \array_unshift($argsSansMeta, false);
            }
            foreach ($tests as $name => $test) {
                $foundArgs = false;
                if (\is_array($test)) {
                    foreach ($test as $i => $val) {
                        if ($val !== '{{args}}') {
                            continue;
                        }
                        $foundArgs = true;
                        $test[$i] = $argsSansMeta;
                    }
                    $tests[$name] = $test;
                }
                if ($name === 'chromeLogger') {
                    if (!$foundArgs && $method === 'assert') {
                        \array_unshift($test[0], false);
                    }
                    $test[0] = \array_map(function ($val) {
                        return $this->debug->getDump('base')->valDumper->dump($val);
                    }, $test[0]);
                    $test[1] = \in_array($method, array('error','warn'), true)
                        ? $this->file . ': ' . $this->line
                        : null;
                    $test[2] = \in_array($method, array('alert','log'), true)
                        ? ''
                        : $method;
                } elseif ($name === 'entry' && \is_array($test)) {
                    $test['method'] = $method;
                    if ($method === 'alert') {
                        $test['meta']['dismissible'] = false;
                        $test['meta']['level'] = 'error';
                    } elseif ($method === 'assert') {
                        if ($foundArgs) {
                            // the first arg (false) is not stored
                            \array_shift($test['args']);
                        }
                    } elseif (\in_array($method, array('error','warn'))) {
                        $test['meta']['detectFiles'] = true;
                        $test['meta']['file'] = $this->file;
                        $test['meta']['line'] = $this->line;
                        $test['meta']['uncollapse'] = true;
                    }
                } elseif ($name === 'firephp') {
                    $i = $method === 'assert'
                        ? 1
                        : 0;
                    $label = $argsSansMeta[$i];
                    $label = $this->debug->getDump('base')->valDumper->dump($label);
                    $label = \strtr($label, $replace);
                    // $label = strtr($label, array('\\u', 'foo'));
                    // $test = str_replace('{{label}}', $label, $test);
                    $firephpMethods = array(
                        'alert' => 'ERROR',
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
                    if (\in_array($method, array('error','warn'))) {
                        $firemeta['File'] = $this->file;
                        $firemeta['Line'] = $this->line;
                    }
                    \ksort($firemeta);
                    $test = \str_replace('{{meta}}', \json_encode($firemeta, JSON_UNESCAPED_SLASHES), $test);
                } elseif ($name === 'html') {
                    $attribs = array(
                        'class' => array('m_' . $method),
                    );
                    if ($method === 'alert') {
                        $attribs['class'][] = 'alert-error';
                        $attribs['role'] = 'alert';
                        // $test = \str_replace(array('<li','</li'), array('<div', '</div'), $test);
                        $test = \str_replace(
                            ' = <span class="t_string">extra</span>',
                            '',
                            $test
                        );
                        $test = \str_replace(
                            '<li class="m_log"><span class="no-quotes t_string">',
                            '<div' . $this->debug->html->buildAttribString($attribs) . '>',
                            $test
                        );
                        $test = \str_replace(
                            '</span></li>',
                            '</div>',
                            $test
                        );
                    } elseif (\in_array($method, array('error','warn'))) {
                        $attribs['data-detect-files'] = true;
                        $attribs['data-file'] = $this->file;
                        $attribs['data-line'] = $this->line;
                    }
                    $test = \str_replace(' class="m_log"', $this->debug->html->buildAttribString($attribs), $test);
                } elseif ($name === 'script') {
                    $consoleMethod = $method;
                    if ($method === 'alert') {
                        $consoleMethod = 'log';
                    }
                    $test = \str_replace('console.log', 'console.' . $consoleMethod, $test);
                    $fileLine = $this->file . ': line ' . $this->line;
                    if (\in_array($method, array('error','warn'))) {
                        $argsSansMeta[] = $fileLine;
                        if (\strpos($test, '{{args}}') === false) {
                            $test = \str_replace(');', ',"' . $fileLine . '");', $test);
                        }
                    } else {
                        $test = \str_replace(',"' . $fileLine . '"', '', $test);
                    }
                    if ($method === 'assert' && \strpos($test, '{{args}}') === false) {
                        $test = \str_replace('console.assert(', 'console.assert(false,', $test);
                    }
                } elseif ($name === 'text') {
                    $prefixes = array(
                        'alert' => '',
                        'assert' => '≠ ',
                        'error' => '⦻ ',
                        'info' => 'ℹ ',
                        'log' => '',
                        'warn' => '⚠ ',
                    );
                    $test = $prefixes[$method] . $test;
                    if ($method === 'alert') {
                        $test = \str_replace(' = "extra"', '', $test);
                        $test = '》[Alert ⦻ error] ' . $test . '《';
                    }
                }
                if (\is_string($test) && \strpos($test, '{{args}}') !== false) {
                    $i = $method === 'assert'
                        ? 2
                        : 1;
                    $argStr = $name === 'firephp'
                        ? \json_encode(\array_slice($argsSansMeta, $i), JSON_UNESCAPED_SLASHES)
                        : \trim(\json_encode($argsSansMeta, JSON_UNESCAPED_SLASHES), '[]');
                    $argStr = \strtr($argStr, $replace);
                    $test = \str_replace('{{args}}', $argStr, $test);
                }
                $tests[$name] = $test;
            }
            $this->testMethod($method, $args, $tests);
            if ($method === 'assert') {
                \array_shift($args);
                \array_shift($argsSansMeta);
            }
            $tests = $testsBack;
            $argsSansMeta = $argsSansMetaBack;
        }
    }
}
