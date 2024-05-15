<?php

namespace bdk\Test\Debug\Type;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Abstraction\AbstractString
 * @covers \bdk\Debug\Dump\Base
 * @covers \bdk\Debug\Dump\Base\Value
 * @covers \bdk\Debug\Dump\Html
 * @covers \bdk\Debug\Dump\Html\HtmlString
 * @covers \bdk\Debug\Dump\Html\HtmlStringBinary
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\Text\Value
 * @covers \bdk\Debug\Dump\TextAnsi\Value
 *
 * @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
 */
class StringTest extends DebugTestFramework
{
    public static function setUpBeforeClass(): void
    {
        $debug = Debug::getInstance();
        $htmlString = $debug->getDump('html')->valDumper->string;
        \bdk\Debug\Utility\Reflection::propSet($htmlString, 'lazy', array());
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

        $binary = \base64_decode('j/v9wNrF5i1abMXFW/4vVw==', true);

        $tests = array(
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
                    'entry' => static function (LogEntry $logEntry) {
                        // assert args are unchanged
                        self::assertSame(
                            "\xef\xbb\xbfPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \x07 (a control char).",
                            $logEntry['args'][0]
                        );
                    },
                    'firephp' => 'X-Wf-1-1-1-19: %d|[{"Type":"LOG"},"\\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \\\x07 (a control char)."]|',
                    'html' => '<li class="m_log"><span class="no-quotes t_string"><a class="unicode" href="https://symbl.cc/en/FEFF" target="unicode" title="U-FEFF: BOM / Zero Width No-Break Space">\u{feff}</a>Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and <span class="char-control" title="\x07: BEL (bell)">␇</span> (a control char).</span></li>',
                    'script' => 'console.log("\\\u{feff}Pesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \\\x07 (a control char).");',
                    'streamAnsi' => "\e[38;5;208m\\u{feff}\e[0mPesky <abbr title=\"Byte-Order-Mark\">BOM</abbr> and \e[38;5;208m\\x07\e[0m (a control char).",
                    'text' => '\u{feff}Pesky <abbr title="Byte-Order-Mark">BOM</abbr> and \x07 (a control char).',
                ),
            ),

            'highlight' => array(
                'log',
                array(
                    "\tcontrol chars: \x07 \x1F \x7F\r\n",
                    "\teasy-to-miss \xD1\x81haracters such as \xc2\xa0(nbsp), \xE2\x80\x89(thsp), &amp; \xE2\x80\x8B(zwsp)",
                ),
                array(
                    'chromeLogger' => array(
                        array(
                            "\tcontrol chars: \\x07 \\x1f \\x7f\r\n",
                            "\teasy-to-miss \\u{0441}haracters such as \\u{00a0}(nbsp), \\u{2009}(thsp), &amp; \\u{200b}(zwsp)",
                        ),
                        null,
                        '',
                    ),
                    'entry' => static function (LogEntry $logEntry) {
                        // assert args are unchanged
                        self::assertSame(array(
                            "\tcontrol chars: \x07 \x1F \x7F\r\n",
                            "\teasy-to-miss \xD1\x81haracters such as \xc2\xa0(nbsp), \xE2\x80\x89(thsp), &amp; \xE2\x80\x8B(zwsp)",
                        ), $logEntry['args']);
                    },
                    'firephp' => 'X-Wf-1-1-1-5: 165|[{"Label":"\tcontrol chars: \\\x07 \\\x1f \\\x7f\r\n","Type":"LOG"},"\teasy-to-miss \\\u{0441}haracters such as \\\u{00a0}(nbsp), \\\u{2009}(thsp), &amp; \\\u{200b}(zwsp)"]|',
                    'html' => '<li class="m_log"><span class="no-quotes t_string">' . "\t" . 'control chars: <span class="char-control" title="\x07: BEL (bell)">␇</span> <span class="char-control" title="\x1f: US (unit separator)">␟</span> <span class="char-control" title="\x7f: DEL">␡</span>' . "\r\n"
                        . '</span> = <span class="t_string"><span class="ws_t">' . "\t" . '</span>easy-to-miss <a class="unicode" href="https://symbl.cc/en/0441" target="unicode" title="U-0441: CYRILLIC SMALL LETTER ES">' . "\xD1\x81" . '</a>haracters such as <a class="unicode" href="https://symbl.cc/en/00A0" target="unicode" title="U-00A0: NBSP">\u{00a0}</a>(nbsp), <a class="unicode" href="https://symbl.cc/en/2009" target="unicode" title="U-2009: Thin Space">\u{2009}</a>(thsp), &amp;amp; <a class="unicode" href="https://symbl.cc/en/200B" target="unicode" title="U-200B: Zero Width Space">\u{200b}</a>(zwsp)'
                        . '</span></li>',
                    'script' => 'console.log("\tcontrol chars: \\\x07 \\\x1f \\\x7f\r\n","\teasy-to-miss \\\u{0441}haracters such as \\\u{00a0}(nbsp), \\\u{2009}(thsp), &amp; \\\u{200b}(zwsp)");',
                    'streamAnsi' => "control chars: \e[38;5;208m\\x07\e[0m \e[38;5;208m\\x1f\e[0m \e[38;5;208m\\x7f\e[0m\r\n"
                        . "\e[38;5;245m=\e[0m \e[38;5;250m\"\e[0m	easy-to-miss \e[38;5;208m\\u{0441}\e[0mharacters such as \e[38;5;208m\\u{00a0}\e[0m(nbsp), \e[38;5;208m\\u{2009}\e[0m(thsp), &amp; \e[38;5;208m\\u{200b}\e[0m(zwsp)\e[38;5;250m\"\e[0m",
                    'text' => 'control chars: \x07 \x1f \x7f' . "\r\n"
                        . '= "' . "\t" . 'easy-to-miss \\u{0441}haracters such as \u{00a0}(nbsp), \u{2009}(thsp), &amp; \u{200b}(zwsp)"',
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
                    'entry' => static function (LogEntry $logEntry) {
                        self::assertSame(2205, $logEntry['args'][1]['strlen']);
                        // maxLen is 430, but we're cutting in middle of multi-byte char
                        self::assertSame(427, $logEntry['args'][1]['strlenValue']);
                        self::assertSame($logEntry['args'][1]['strlenValue'], \strlen($logEntry['args'][1]['value']));
                    },
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
                        . "\e[30;48;5;41m[1778 more bytes (not logged)]\e[0m"
                        . "\e[38;5;250m\"\e[0m",
                    'text' => 'long string = "' . $longStringExpect . '[1778 more bytes (not logged)]"',
                ),
            ),

            'containsBinary' => array(
                'log',
                array(
                    '<b>Brad</b>:' . "\n" . 'w𝔞s ' . "\x80" . 'hеre',
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        self::assertInstanceOf('\bdk\Debug\Abstraction\Abstraction', $logEntry['args'][0]);
                        self::assertSame(Type::TYPE_STRING, $logEntry['args'][0]['type']);
                        self::assertSame(Type::TYPE_STRING_BINARY, $logEntry['args'][0]['typeMore']);
                    },
                    'html' => '<li class="m_log">&lt;b&gt;Brad&lt;/b&gt;:
                        w<a class="unicode" href="https://symbl.cc/en/1D51E" target="unicode" title="U-1D51E: MATHEMATICAL FRAKTUR SMALL A">𝔞</a>s <span class="binary">\x80</span>h<a class="unicode" href="https://symbl.cc/en/0435" target="unicode" title="U-0435: CYRILLIC SMALL LETTER IE">е</a>re</li>',
                    'script' => 'console.log("<b>Brad</b>:\nw\\\u{1d51e}s \\\x80h\\\u{0435}re");',
                    'streamAnsi' => "<b>Brad</b>:
                        w\e[38;5;208m\\u{1d51e}\e[0ms \e[30;48;5;250m80\e[0mh\e[38;5;208m\\u{0435}\e[0mre",
                    'text' => '<b>Brad</b>:
                        w\u{1d51e}s \x80h\u{0435}re',
                ),
            ),

            'binary' => array(
                'log',
                array(
                    $binary,
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'percentBinary' => 62.5,
                                'strlen' => 16,
                                'strlenValue' => 16,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_BINARY,
                                'value' => \trim(\chunk_split(\bin2hex($binary), 2, ' ')),
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="m_log"><span class="t_keyword">string</span><span class="text-muted">(binary)</span>
                        <ul class="list-unstyled value-container" data-type="string" data-type-more="binary">
                        <li>size = <span class="t_int">16</span></li>
                        <li class="t_string"><span class="binary">8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57</span></li>
                        </ul></li>',
                    'streamAnsi' => "\e[30;48;5;250m" . \trim(\chunk_split(\bin2hex($binary), 2, ' ')) . "\e[0m",
                    'text' => '8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57',
                ),
            ),

            'binary.brief' => array(
                'group',
                array(
                    $binary,
                ),
                array(
                    'entry' => array(
                        'method' => 'group',
                        'args' => array(
                            array(
                                'brief' => true,
                                'debug' => Abstracter::ABSTRACTION,
                                'percentBinary' => 62.5,
                                'strlen' => 16,
                                'strlenValue' => 16,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_BINARY,
                                'value' => \trim(\chunk_split(\bin2hex($binary), 2, ' ')),
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="binary">8f fb fd c0 da c5 e6 2d 5a 6c c5 c5 5b fe 2f 57</span></span></div>
                        <ul class="group-body">',
                    'streamAnsi' => "▸ \e[30;48;5;250m" . \trim(\chunk_split(\bin2hex($binary), 2, ' ')) . "\e[0m",
                    'text' => '▸ ' . \trim(\chunk_split(\bin2hex($binary), 2, ' ')),
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
                                'brief' => true,
                                'contentType' => 'image/png',
                                'debug' => Abstracter::ABSTRACTION,
                                'percentBinary' => 0,
                                'strlen' => \filesize(TEST_DIR . '/assets/logo.png'),
                                'strlenValue' => 0,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_BINARY,
                                'value' => '',
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_keyword">string</span><span class="text-muted">(image/png)</span><span class="t_punct colon">:</span> 7.95 kB</span></div>
                        <ul class="group-body">',
                    'streamAnsi' => "▸ \e[38;5;45mstring\e[0m\e[38;5;250m(image/png)\e[0m: 7.95 kB",
                    'text' => '▸ string(image/png): 7.95 kB',
                ),
            ),

            'dblEncode' => array(
                'log',
                array(
                    '\u0000 / foo \\ bar',  // both are single backslash
                ),
                array(
                    'script' => 'console.log(' . \json_encode('\u0000 / foo \ bar', JSON_UNESCAPED_SLASHES). ');',
                    'text' => '\u0000 / foo \ bar',
                ),
            ),
        );
        // $tests = \array_intersect_key($tests, \array_flip(['binary.brief']));
        return $tests;
    }
}
