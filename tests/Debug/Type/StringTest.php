<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Abstraction\AbstractString
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\BaseValue
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\HtmlStringEncoded
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\TextAnsiValue
 * @covers \bdk\Debug\Dump\TextValue
 */
class StringTest extends DebugTestFramework
{
    public static function setUpBeforeClass(): void
    {
        $debug = Debug::getInstance();
        $htmlString = $debug->getDump('html')->valDumper->string;
        \bdk\Test\Debug\Helper::setProp($htmlString, 'lazy', array());
    }

    public static function providerTestMethod()
    {
        $ts = \time();
        $longString = <<<'EOD'
They see me mowin' my front lawn
I know they're all thinkin' I'm so
White and nerdy

Think I'm just too white and nerdy
Think I'm just too white and nerdy
Can't you see I'm white and nerdy?
Look at me, I'm white and nerdy

I wanna roll with the gangstas
But so far they all think I'm too
White and nerdy

Think I'm just too white and nerdy
Think I'm just too white and nerdy
I'm just too white and nerdy
Really white and nerdy
💩
First in my class here at M-I-T
Got skills, I'm a champion at D and D
M.C. Escher, that's my favorite M.C.
Keep you're forty, I'll just have an Earl Grey tea
My rims never spin, to the contrary
You'll find that they're quite stationary
All of my action figures are cherry
Stephen Hawking's in my library

My MySpace page is all totally pimped out
Got people beggin' for my top eight spaces
Yo, I know pi to a thousand places
Ain't got no grills, but I still wear braces
I order all of my sandwiches with mayonnaise
I'm a wiz' at Minesweeper, and I play for days
Once you've see my sweet moves, you're gonna stay amazed
My fingers movin' so fast, I'll set the place ablaze

There's no killer app I haven't run (run)
At Pascal, well, I'm number one (one)
Do vector calculus just for fun
I ain't got a gat, but I got a soldering gun (What?)
Happy Days is my favorite theme song
I could sure kick your butt in a game of ping pong
I'll ace any trivia quiz you bring on
I'm fluent in JavaScript as well as Klingon

Here's the part I sing on

You see me roll on my Segway
I know in my heart they think I'm
White and nerdy

Think I'm just too white and nerdy
Think I'm just too white and nerdy
Can't you see I'm white and nerdy?
Look at me, I'm white and nerdy

I'd like to roll with the gangstas
Although it's apparent I'm too
White and nerdy

Think I'm just too white and nerdy
Think I'm just too white and nerdy
I'm just too white and nerdy
How'd I get so white and nerdy?

I been browsin', inspectin' X-Men comics
You know I collect 'em
The pens in my pocket, I must protect them
My ergonomic keyboard never leaves me bored
Shoppin' online for deals on some writable media
I edit Wikipedia
I memorized Holy Grail really well
I can recite it right now and have you R-O-T-F-L-O-L
EOD;

        $longStringExpect = <<<'EOD'
They see me mowin' my front lawn
I know they're all thinkin' I'm so
White and nerdy

Think I'm just too white and nerdy
Think I'm just too white and nerdy
Can't you see I'm white and nerdy?
Look at me, I'm white and nerdy

I wanna roll with the gangstas
But so far they all think I'm too
White and nerdy

Think I'm just too white and nerdy
Think I'm just too white and nerdy
I'm just too white and nerdy
Really white and nerdy

EOD;

        $base64snip = \substr(
            \base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')),
            0,
            156
        );

        $array = array(
            'poop' => '💩',
            'int' => 42,
            'password' => 'secret',
        );
        $base64snip2 = \base64_encode(
            \json_encode($array)
        );
        $base64snip3 = \base64_encode(
            \serialize($array)
        );
        return array(
            'basic' => array(
                'log',
                array(
                    'string', 'a "string"' . "\r\n\tline 2",
                ),
                array(
                    'chromeLogger' => '[["string","a \"string\"\r\n\tline 2"],null,""]',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">string</span> = <span class="t_string">a &quot;string&quot;<span class="ws_r"></span><span class="ws_n"></span>' . "\n"
                        . '<span class="ws_t">' . "\t" . '</span>line 2</span></li>',
                    'script' => 'console.log("string","a \"string\"\r\n\tline 2");',
                    'text' => "string = \"a \"string\"\r\n\tline 2\"",
                ),
            ),

            'whitespace' => array(
                'log',
                array(
                    "\xef\xbb\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \x07 (a control char).",
                    Debug::meta('sanitize', false),
                ),
                array(
                    'chromeLogger' => '[["\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM<\/abbr> and \\\x07 (a control char)."],null,""]',
                    'firephp' => 'X-Wf-1-1-1-19: %d|[{"Type":"LOG"},"\\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \\\x07 (a control char)."]|',
                    'html' => '<li class="m_log"><span class="no-quotes t_string"><a class="unicode" href="https://unicode-table.com/en/feff" target="unicode-table" title="BOM / Zero Width No-Break Space: \xef \xbb \xbf">\ufeff</a>Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and <span class="binary"><span class="c1-control" title="BEL (bell): \x07">␇</span></span> (a control char).</span></li>',
                    'script' => 'console.log("\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \\\x07 (a control char).");',
                    'text' => '\u{feff}Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and \x07 (a control char).',
                ),
            ),

            'nonPrintable' => array(
                'log',
                array(
                    "\tcontrol chars: \x07 \x1F \x7F\n",
                    "\teasy-to-miss characters such as \xc2\xa0(nbsp), \xE2\x80\x89(thsp), &amp; \xE2\x80\x8B(zwsp)",
                ),
                array(
                    'chromeLogger' => array(
                        array(
                            "\tcontrol chars: \\x07 \\x1f \\x7f\n",
                            "\teasy-to-miss characters such as \\u{00a0}(nbsp), \\u{2009}(thsp), &amp; \\u{200b}(zwsp)",
                        ),
                        null,
                        '',
                    ),
                    'firephp' => 'X-Wf-1-1-1-5: 155|[{"Label":"\tcontrol chars: \\\x07 \\\x1f \\\x7f\n","Type":"LOG"},"\teasy-to-miss characters such as \\\u{00a0}(nbsp), \\\u{2009}(thsp), &amp; \\\u{200b}(zwsp)"]|',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">' . "\t" . 'control chars: <span class="binary"><span class="c1-control" title="BEL (bell): \x07">␇</span></span> <span class="binary"><span class="c1-control" title="US (unit seperator): \x1f">␟</span></span> <span class="binary"><span class="c1-control" title="DEL: \x7f">␡</span></span>' . "\n"
                        . '</span> = <span class="t_string"><span class="ws_t">' . "\t" . '</span>easy-to-miss characters such as <a class="unicode" href="https://unicode-table.com/en/00a0" target="unicode-table" title="NBSP: \xc2 \xa0">\u00a0</a>(nbsp), <a class="unicode" href="https://unicode-table.com/en/2009" target="unicode-table" title="Thin Space: \xe2 \x80 \x89">\u2009</a>(thsp), &amp;amp; <a class="unicode" href="https://unicode-table.com/en/200b" target="unicode-table" title="Zero Width Space: \xe2 \x80 \x8b">\u200b</a>(zwsp)'
                        . '</span></li>',
                    'script' => 'console.log("\tcontrol chars: \\\x07 \\\x1f \\\x7f\n","\teasy-to-miss characters such as \\\u{00a0}(nbsp), \\\u{2009}(thsp), &amp; \\\u{200b}(zwsp)");',
                    'text' => 'control chars: \x07 \x1f \x7f' . "\n"
                        . '= "' . "\t" . 'easy-to-miss characters such as \u{00a0}(nbsp), \u{2009}(thsp), &amp; \u{200b}(zwsp)"',
                ),
            ),

            'numeric.int' => array(
                'log',
                array('numeric string', '10'),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            'numeric string',
                            '10',
                        ),
                        'meta' => array(),
                    ),
                    'chromeLogger' => array(
                        array('numeric string', '10'),
                        null,
                        '',
                    ),
                    'html' => '<li class="m_log"><span class="no-quotes t_string">numeric string</span> = <span class="t_string" data-type-more="numeric">10</span></li>',
                    'script' => 'console.log("numeric string","10");',
                    'text' => 'numeric string = "10"',
                    'wamp' => array(
                        'log',
                        array(
                            'numeric string',
                            '10',
                        ),
                        array(
                            'format' => 'raw',
                        ),
                    ),
                ),
            ),

            'numeric.float' => array(
                'log',
                array('numeric string', '10.10'),
                array(
                    'chromeLogger' => array(
                        array('numeric string', '10.10'),
                        null,
                        '',
                    ),
                    'html' => '<li class="m_log"><span class="no-quotes t_string">numeric string</span> = <span class="t_string" data-type-more="numeric">10.10</span></li>',
                    'script' => 'console.log("numeric string","10.10");',
                    'text' => 'numeric string = "10.10"',
                ),
            ),

            'long' => array(
                'log',
                array('long string', $longString, Debug::meta('cfg', 'stringMaxLen', 430)), // cut in middle of multi-byte char
                array(
                    'chromeLogger' => array(
                        array(
                            'long string',
                            $longStringExpect . '[1778 more bytes (not logged)]',
                        ),
                        null,
                        '',
                    ),
                    'html' => \str_replace(
                        '\'',
                        PHP_VERSION_ID >= 80100 ? '&#039;' : '\'',
                        '<li class="m_log">'
                        . '<span class="no-quotes t_string">long string</span> = '
                        . '<span class="t_string" data-type-more="maxLen">'
                            . \str_replace("\n", '<span class="ws_n"></span>' . "\n", $longStringExpect)
                            . '<span class="maxlen">&hellip; 1778 more bytes (not logged)</span>'
                        . '</span></li>'
                    ),
                    'script' => 'console.log("long string",' . \json_encode($longStringExpect . '[1778 more bytes (not logged)]') . ');',
                    'streamAnsi' => "long string \e[38;5;245m=\e[0m \e[38;5;250m\"\e[0m"
                        . $longStringExpect
                        . "\e[38;5;250m\"\e[0m"
                        . "\e[30;48;5;41m[1778 more bytes (not logged)]\e[0m",
                    'text' => 'long string = "' . $longStringExpect . '"[1778 more bytes (not logged)]',
                ),
            ),

            'base64' => array(
                'log',
                array(
                    \base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) use ($base64snip) {
                        $jsonExpect = '{"method":"log","args":[{"brief":false,"strlen":10852,"type":"string","typeMore":"base64","value":' . \json_encode($base64snip) . ',"valueDecoded":{"brief":false,"contentType":"%s","strlen":%d,"type":"string","typeMore":"binary","value":"","debug":"\u0000debug\u0000"},"debug":"\u0000debug\u0000"}],"meta":[]}';
                        $jsonified = \json_encode($logEntry);
                        self::assertStringMatchesFormat($jsonExpect, $jsonified);
                    },
                    'chromeLogger' => array(
                        array(
                            $base64snip . '[10696 more bytes (not logged)]',
                        ),
                        null,
                        '',
                    ),
                    'html' => '<li class="m_log"><span class="string-encoded tabs-container" data-type-more="base64">' . "\n"
                        . '<nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">base64</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">decoded</a></nav>' . "\n"
                        . '<div class="tab-1 tab-pane" role="tabpanel"><span class="no-quotes t_string">' . $base64snip . '</span><span class="maxlen">&hellip; 10696 more bytes (not logged)</span></div>' . "\n"
                        . '<div class="active tab-2 tab-pane" role="tabpanel"><span class="t_keyword">string</span><span class="text-muted">(binary)</span>' . "\n"
                        . '<ul class="list-unstyled value-container" data-type="string" data-type-more="binary">' . "\n"
                        . '<li>mime type = <span class="content-type t_string">%s</span></li>' . "\n"
                        . '<li>size = <span class="t_int">%d</span></li>' . "\n"
                        . '<li>Binary data not collected</li>' . "\n"
                        . '</ul></div>' . "\n"
                        . '</span></li>',
                    'script' => 'console.log("' . $base64snip . '[10696 more bytes (not logged)]");',
                    'text' => $base64snip . '[10696 more bytes (not logged)]',
                ),
            ),

            'base64.brief' => array(
                'group',
                array(
                    \base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                    },
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_keyword">string</span><span class="text-muted">(base64)</span><span class="t_punct colon">:</span> <span class="t_string" data-type-more="base64"><span class="no-quotes t_string">'
                            . \substr(\base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')), 0, 156)
                            . '</span><span class="maxlen">&hellip; 10696 more bytes (not logged)</span></span></span></div>
                        <ul class="group-body">',
                ),
            ),

            'base64.json.redact' => array(
                'log',
                array(
                    $base64snip2,
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) use ($base64snip2) {
                        $jsonExpect = '{"method":"log","args":[{"brief":false,"strlen":null,"type":"string","typeMore":"base64","value":"' . $base64snip2 . '","valueDecoded":{"addQuotes":false,"attribs":{"class":["highlight","language-json"]},"brief":false,"contentType":"application\/json","prettified":true,"prettifiedTag":true,"strlen":null,"type":"string","typeMore":"json","value":"{\n    \"poop\": \"\\\\ud83d\\\\udca9\",\n    \"int\": 42,\n    \"password\": \"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\"\n}","valueDecoded":{"poop":"\ud83d\udca9","int":42,"password":"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588"},"visualWhiteSpace":false,"debug":"\u0000debug\u0000"},"debug":"\u0000debug\u0000"}],"meta":{"redact":true}}';
                        $jsonified = \json_encode($logEntry);
                        self::assertSame($jsonExpect, $jsonified);
                    },
                    'chromeLogger' => array(
                        array(
                            $base64snip2,
                        ),
                        null,
                        '',
                    ),
                    'html' => '<li class="m_log"><span class="string-encoded tabs-container" data-type-more="base64">
                        <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">base64</a><a class="nav-link" data-target=".tab-2" data-toggle="tab" role="tab">json</a><a class="active nav-link" data-target=".tab-3" data-toggle="tab" role="tab">decoded</a></nav>
                        <div class="tab-1 tab-pane" role="tabpanel"><span class="no-quotes t_string">' . $base64snip2 . '</span></div>
                        <div class="tab-2 tab-pane" role="tabpanel"><span class="value-container" data-type="string"><span class="prettified">(prettified)</span> <span class="highlight language-json no-quotes t_string">{
                            &quot;poop&quot;: &quot;\ud83d\udca9&quot;,
                            &quot;int&quot;: 42,
                            &quot;password&quot;: &quot;█████████&quot;
                        }</span></span></div>
                        <div class="active tab-3 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">poop</span><span class="t_operator">=&gt;</span><span class="t_string">💩</span></li>
                                <li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">42</span></li>
                                <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">█████████</span></li>
                            </ul><span class="t_punct">)</span></span></div>
                        </span></li>',
                    'script' => 'console.log("' . $base64snip2 . '");',
                    'text' => $base64snip2,
                ),
            ),

            'base64.serialized.redact' => array(
                'log',
                array(
                    $base64snip3,
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) use ($base64snip3) {
                        $jsonExpect = '{"method":"log","args":[{"brief":false,"strlen":null,"type":"string","typeMore":"base64","value":"' . $base64snip3 . '","valueDecoded":{"brief":false,"strlen":null,"type":"string","typeMore":"serialized","value":"a:3:{s:4:\"poop\";s:4:\"\ud83d\udca9\";s:3:\"int\";i:42;s:8:\"password\";s:6:\"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\";}","valueDecoded":{"poop":"\ud83d\udca9","int":42,"password":"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588"},"debug":"\u0000debug\u0000"},"debug":"\u0000debug\u0000"}],"meta":{"redact":true}}';
                        $jsonified = \json_encode($logEntry);
                        self::assertSame($jsonExpect, $jsonified);
                    },
                    'chromeLogger' => array(
                        array(
                            $base64snip3,
                        ),
                        null,
                        '',
                    ),
                    'html' => '<li class="m_log"><span class="string-encoded tabs-container" data-type-more="base64">
                        <nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">base64</a><a class="nav-link" data-target=".tab-2" data-toggle="tab" role="tab">serialized</a><a class="active nav-link" data-target=".tab-3" data-toggle="tab" role="tab">unserialized</a></nav>
                        <div class="tab-1 tab-pane" role="tabpanel"><span class="no-quotes t_string">' . $base64snip3 . '</span></div>
                        <div class="tab-2 tab-pane" role="tabpanel"><span class="no-quotes t_string">a:3:{s:4:&quot;poop&quot;;s:4:&quot;💩&quot;;s:3:&quot;int&quot;;i:42;s:8:&quot;password&quot;;s:6:&quot;█████████&quot;;}</span></div>
                        <div class="active tab-3 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">poop</span><span class="t_operator">=&gt;</span><span class="t_string">💩</span></li>
                                <li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">42</span></li>
                                <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">█████████</span></li>
                            </ul><span class="t_punct">)</span></span></div>
                        </span></li>',
                    'script' => 'console.log("' . $base64snip3 . '");',
                    'text' => $base64snip3,
                ),
            ),

            'binary' => array(
                'log',
                array(
                    \base64_decode('j/v9wNrF5i1abMXFW/4vVw==', true),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => false,
                                'strlen' => 16,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_BINARY,
                                'value' => \base64_decode('j/v9wNrF5i1abMXFW/4vVw==', true),
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_keyword">string</span><span class="text-muted">(binary)</span>
                        <ul class="list-unstyled value-container" data-type="string" data-type-more="binary">
                        <li>size = <span class="t_int">16</span></li>
                        <li class="t_string"><span class="binary">8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57</span></li>
                        </ul></li>',
                ),
            ),

            'binary.brief' => array(
                'group',
                array(
                    \base64_decode('j/v9wNrF5i1abMXFW/4vVw==', true),
                ),
                array(
                    'entry' => array(
                        'method' => 'group',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => true,
                                'strlen' => 16,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_BINARY,
                                'value' => \base64_decode('j/v9wNrF5i1abMXFW/4vVw==', true),
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="binary">8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57</span></span></div>
                        <ul class="group-body">',
                ),
            ),

            'binary.brief.contentType' => array(
                'group',
                array(
                    \file_get_contents(TEST_DIR . '/assets/logo.png'),
                ),
                array(
                    'entry' => array(
                        'method' => 'group',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => true,
                                'strlen' => \filesize(TEST_DIR . '/assets/logo.png'),
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_BINARY,
                                'contentType' => 'image/png',
                                'value' => '',
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_keyword">string</span><span class="text-muted">(image/png)</span><span class="t_punct colon">:</span> 7.95 kB</span></div>
                        <ul class="group-body">',
                ),
            ),

            'json.long' => array(
                'log',
                array(
                    \file_get_contents(TEST_DIR . '/../composer.json'),
                    Debug::_meta('cfg', 'stringMaxLen', array('json' => array(0 => 123, 5000 => 5000))),
                ),
                array(
                    'entry' => static function (LogEntry $entry) {
                        $json = \file_get_contents(TEST_DIR . '/../composer.json');
                        $jsonPrettified = Debug::getInstance()->stringUtil->prettyJson($json);
                        // $this->helper->stderr('jsonPrettified', $entry['args'][0]);
                        self::assertSame(\strlen($jsonPrettified), $entry['args'][0]['strlen']);
                        self::assertSame(\substr($jsonPrettified, 0, 123), $entry['args'][0]['value']);
                    },
                    'html' => static function ($html) {
                        $json = \file_get_contents(TEST_DIR . '/../composer.json');
                        $jsonPrettified = Debug::getInstance()->stringUtil->prettyJson($json);
                        $diff = \strlen($jsonPrettified) - 123;
                        self::assertStringContainsString('<span class="maxlen">&hellip; ' . $diff . ' more bytes (not logged)</span></span></span></div>', $html);
                    },
                    'text' => static function ($text) {
                        $json = \file_get_contents(TEST_DIR . '/../composer.json');
                        $jsonPrettified = Debug::getInstance()->stringUtil->prettyJson($json);
                        $diff = \strlen($jsonPrettified) - 123;
                        self::assertStringContainsString('[' . $diff . ' more bytes (not logged)]', $text);
                    },
                ),
            ),

            'json.brief' => array(
                'group',
                array(
                    \json_encode(array(
                        'poop' => '💩',
                        'int' => 42,
                        'password' => 'secret',
                    )),
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => array(
                        'method' => 'group',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => true,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_JSON,
                                'value' => '{"poop":"\ud83d\udca9","int":42,"password":"█████████"}',
                                'valueDecoded' => null,
                            ),
                        ),
                        'meta' => array(
                            'redact' => true,
                        ),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_string" data-type-more="json"><span class="no-quotes t_string">{&quot;poop&quot;:&quot;\ud83d\udca9&quot;,&quot;int&quot;:42,&quot;password&quot;:&quot;█████████&quot;}</span></span></span></div>
                        <ul class="group-body">',
                ),
            ),

            'dblEncode' => array(
                'log',
                array(
                    '\u0000 / foo \\ bar',
                ),
                array(
                    'script' => 'console.log(' . \json_encode('\u0000 / foo \\ bar', JSON_UNESCAPED_SLASHES) . ');',
                ),
            ),

            'serialized' => array(
                'log',
                array(
                    \serialize(array('foo' => 'bar')),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => false,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_SERIALIZED,
                                'value' => 'a:1:{s:3:"foo";s:3:"bar";}',
                                'valueDecoded' => array(
                                    'foo' => 'bar',
                                ),
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="string-encoded tabs-container" data-type-more="serialized">' . "\n"
                        . '<nav role="tablist"><a class="nav-link" data-target=".tab-1" data-toggle="tab" role="tab">serialized</a><a class="active nav-link" data-target=".tab-2" data-toggle="tab" role="tab">unserialized</a></nav>' . "\n"
                        . '<div class="tab-1 tab-pane" role="tabpanel"><span class="no-quotes t_string">a:1:{s:3:&quot;foo&quot;;s:3:&quot;bar&quot;;}</span></div>' . "\n"
                        . '<div class="active tab-2 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>' . "\n"
                        . '<ul class="array-inner list-unstyled">' . "\n"
                        . '<li><span class="t_key">foo</span><span class="t_operator">=&gt;</span><span class="t_string">bar</span></li>' . "\n"
                        . '</ul><span class="t_punct">)</span></span></div>' . "\n"
                        . '</span></li>',
                ),
            ),
            'serialized.brief' => array(
                'group',
                array(
                    \serialize(array('foo' => 'bar')),
                ),
                array(
                    'entry' => array(
                        'method' => 'group',
                        'args' => array(
                            array(
                                'debug' => Abstracter::ABSTRACTION,
                                'brief' => true,
                                'strlen' => null,
                                'type' => Abstracter::TYPE_STRING,
                                'typeMore' => Abstracter::TYPE_STRING_SERIALIZED,
                                'value' => 'a:1:{s:3:"foo";s:3:"bar";}',
                                'valueDecoded' => null,
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_string" data-type-more="serialized"><span class="no-quotes t_string">a:1:{s:3:&quot;foo&quot;;s:3:&quot;bar&quot;;}</span></span></span></div>
                        <ul class="group-body">',
                ),
            ),
        );
    }
}
