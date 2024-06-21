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
 * @covers \bdk\Debug\Dump\Html\HtmlStringEncoded
 * @covers \bdk\Debug\Dump\Text
 * @covers \bdk\Debug\Dump\Text\Value
 * @covers \bdk\Debug\Dump\TextAnsi\Value
 *
 * @phpcs:ignore SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys
 */
class StringEncodedTest extends DebugTestFramework
{

    public static function providerTestMethod()
    {
        $base64snip = \substr(
            \base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')),
            0,
            156
        );

        $array = array(
            'pоop' => '💩',
            'int' => 42,
            'string' => "strıngy\nstring",
            'password' => 'secret',
        );
        $base64snip2 = \base64_encode(
            \json_encode($array)
        );
        $base64snip3 = \base64_encode(
            \serialize($array)
        );

        $tests = array(
            'base64' => array(
                'log',
                array(
                    \base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) use ($base64snip) {
                        $jsonExpect = '{"method":"log","args":[{"brief":false,"strlen":10852,"strlenValue":156,"type":"string","typeMore":"base64","value":' . \json_encode($base64snip) . ',"valueDecoded":{"brief":false,"contentType":"%s","percentBinary":%f,"strlen":%d,"strlenValue":0,"type":"string","typeMore":"binary","value":"","debug":"\u0000debug\u0000"},"debug":"\u0000debug\u0000"}],"meta":[]}';
                        $jsonActual = \json_encode($logEntry);
                        // echo 'expect = ' . $jsonExpect . "\n";
                        // echo 'actual = ' . $jsonActual . "\n";
                        self::assertStringMatchesFormat($jsonExpect, $jsonActual);
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
                        $actual = \bdk\Test\Debug\Helper::logEntryToArray($logEntry)['args'][0];
                        self::assertInstanceOf('bdk\\Debug\\Abstraction\\Abstraction', $logEntry['args'][0]);
                        self::assertSame(array(
                            'brief' => true,
                            'debug' => Abstracter::ABSTRACTION,
                            'strlen' => 10852,
                            'strlenValue' => 128,
                            'type' => 'string',
                            'typeMore' => Type::TYPE_STRING_BASE64,
                            'value' => \substr(\base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')), 0, 128),
                            'valueDecoded' => array(
                                'brief' => true,
                                'contentType' => 'image/png',
                                'debug' => Abstracter::ABSTRACTION,
                                'percentBinary' => $actual['valueDecoded']['percentBinary'],
                                'strlen' => 8138,
                                'strlenValue' => 0,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_BINARY,
                                'value' => '',
                            ),
                        ), $actual);
                        self::assertGreaterThan(0, $actual['valueDecoded']['percentBinary']);
                    },
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label"><span class="t_keyword">string</span><span class="text-muted">(base64⇢image/png)</span><span class="t_punct colon">:</span> <span class="t_string" data-type-more="base64"><span class="no-quotes t_string">'
                            . \substr(\base64_encode(\file_get_contents(TEST_DIR . '/assets/logo.png')), 0, 128)
                            . '</span><span class="maxlen">&hellip; 10724 more bytes (not logged)</span></span></span></div>
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
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_BASE64,
                                'value' => $base64snip2,
                                'valueDecoded' => array(
                                    'attribs' => array(
                                        'class' => ['highlight', 'language-json', 'no-quotes'],
                                    ),
                                    'brief' => false,
                                    'contentType' => \bdk\HttpMessage\Utility\ContentType::JSON,
                                    'debug' => Abstracter::ABSTRACTION,
                                    'prettified' => true,
                                    'prettifiedTag' => true,
                                    'type' => Type::TYPE_STRING,
                                    'typeMore' => Type::TYPE_STRING_JSON,
                                    'value' => \str_replace('[redacted]', '█████████', \json_encode(array(
                                        'pоop' => '💩',
                                        'int' => 42,
                                        'string' => "strıngy\nstring",
                                        'password' => '[redacted]',
                                    ), JSON_PRETTY_PRINT)),
                                    'valueDecoded' => array(
                                        'pоop' => '💩',
                                        'int' => 42,
                                        'string' => "strıngy\nstring",
                                        'password' => '█████████',
                                    ),
                                ),
                            ),
                        ),
                        'meta' => array(
                            'redact' => true,
                        ),
                    ),
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
                            &quot;p\u043eop&quot;: &quot;\ud83d\udca9&quot;,
                            &quot;int&quot;: 42,
                            &quot;string&quot;: &quot;str\u0131ngy\nstring&quot;,
                            &quot;password&quot;: &quot;█████████&quot;
                        }</span></span></div>
                        <div class="active tab-3 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">p<span class="unicode" data-code-point="043E" title="U-043E: CYRILLIC SMALL LETTER O">о</span>op</span><span class="t_operator">=&gt;</span><span class="t_string">💩</span></li>
                                <li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">42</span></li>
                                <li><span class="t_key">string</span><span class="t_operator">=&gt;</span><span class="t_string">str<span class="unicode" data-code-point="0131" title="U-0131: LATIN SMALL LETTER DOTLESS I">ı</span>ngy
                                string</span></li>
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
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'brief' => false,
                                'debug' => Abstracter::ABSTRACTION,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_BASE64,
                                'value' => $base64snip3,
                                'valueDecoded' => array(
                                    'brief' => false,
                                    'debug' => Abstracter::ABSTRACTION,
                                    'type' => Type::TYPE_STRING,
                                    'typeMore' => Type::TYPE_STRING_SERIALIZED,
                                    'value' => 'a:4:{s:5:"pоop";s:4:"💩";s:3:"int";i:42;s:6:"string";s:15:"strıngy' . "\n" . 'string";s:8:"password";s:6:"█████████";}',
                                    'valueDecoded' => array(
                                        'pоop' => '💩',
                                        'int' => 42,
                                        'string' => "strıngy\nstring",
                                        'password' => '█████████',
                                    ),
                                ),
                            ),
                        ),
                        'meta' => array(
                            'redact' => true,
                        ),
                    ),
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
                        <div class="tab-2 tab-pane" role="tabpanel"><span class="no-quotes t_string">a:4:{s:5:&quot;p<span class="unicode" data-code-point="043E" title="U-043E: CYRILLIC SMALL LETTER O">о</span>op&quot;;s:4:&quot;💩&quot;;s:3:&quot;int&quot;;i:42;s:6:&quot;string&quot;;s:15:&quot;str<span class="unicode" data-code-point="0131" title="U-0131: LATIN SMALL LETTER DOTLESS I">ı</span>ngy
                            string&quot;;s:8:&quot;password&quot;;s:6:&quot;█████████&quot;;}</span></div>
                        <div class="active tab-3 tab-pane" role="tabpanel"><span class="t_array"><span class="t_keyword">array</span><span class="t_punct">(</span>
                            <ul class="array-inner list-unstyled">
                                <li><span class="t_key">p<span class="unicode" data-code-point="043E" title="U-043E: CYRILLIC SMALL LETTER O">о</span>op</span><span class="t_operator">=&gt;</span><span class="t_string">💩</span></li>
                                <li><span class="t_key">int</span><span class="t_operator">=&gt;</span><span class="t_int">42</span></li>
                                <li><span class="t_key">string</span><span class="t_operator">=&gt;</span><span class="t_string">str<span class="unicode" data-code-point="0131" title="U-0131: LATIN SMALL LETTER DOTLESS I">ı</span>ngy
                                    string</span></li>
                                <li><span class="t_key">password</span><span class="t_operator">=&gt;</span><span class="t_string">█████████</span></li>
                            </ul><span class="t_punct">)</span></span></div>
                        </span></li>',
                    'script' => 'console.log("' . $base64snip3 . '");',
                    'text' => $base64snip3,
                ),
            ),

            'json.long' => array(
                'log',
                array(
                    \file_get_contents(TEST_DIR . '/../composer.json'),
                    Debug::meta('cfg', 'stringMaxLen', array('json' => array(0 => 123, 5000 => 5000))),
                ),
                array(
                    'entry' => static function (LogEntry $entry) {
                        $json = \file_get_contents(TEST_DIR . '/../composer.json');
                        $jsonPrettified = Debug::getInstance()->stringUtil->prettyJson($json);
                        self::assertSame(\strlen($jsonPrettified), $entry['args'][0]['strlen']);
                        $expect = \substr($jsonPrettified, 0, 123);
                        self::assertSame($expect, $entry['args'][0]['value']);
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
                                // 'strlen' => 52,
                                // 'strlenValue' => 52,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_JSON,
                                'value' => '{"poop":"\ud83d\udca9","int":42,"password":"█████████"}',
                                'valueDecoded' => null,
                            ),
                        ),
                        'meta' => array(
                            'redact' => true,
                        ),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label">'
                        . '<span class="t_keyword">string</span><span class="text-muted">(json)</span><span class="t_punct colon">:</span> '
                        . '<span class="t_string" data-type-more="json"><span class="no-quotes t_string">{&quot;poop&quot;:&quot;\ud83d\udca9&quot;,&quot;int&quot;:42,&quot;password&quot;:&quot;█████████&quot;}</span></span>'
                        . '</span></div>
                        <ul class="group-body">',
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
                                // 'strlen' => 26,
                                // 'strlenValue' => 26,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_SERIALIZED,
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
                                // 'strlen' => 26,
                                // 'strlenValue' => 26,
                                'type' => Type::TYPE_STRING,
                                'typeMore' => Type::TYPE_STRING_SERIALIZED,
                                'value' => 'a:1:{s:3:"foo";s:3:"bar";}',
                                'valueDecoded' => null,
                            ),
                        ),
                        'meta' => array(),
                    ),
                    'html' => '<li class="expanded m_group">
                        <div class="group-header"><span class="font-weight-bold group-label">'
                        . '<span class="t_keyword">string</span><span class="text-muted">(serialized)</span><span class="t_punct colon">:</span> '
                        . '<span class="t_string" data-type-more="serialized"><span class="no-quotes t_string">a:1:{s:3:&quot;foo&quot;;s:3:&quot;bar&quot;;}</span></span>'
                        . '</span></div>
                        <ul class="group-body">',
                ),
            ),
        );
        return $tests;
    }
}
