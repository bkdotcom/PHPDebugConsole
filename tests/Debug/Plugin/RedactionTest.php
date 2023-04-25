<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Redaction
 *
 * @covers \bdk\Debug\Plugin\Redaction
 */
class RedactionTest extends DebugTestFramework
{
    public function testConfig()
    {
        $this->debug->setCfg(array(
            'redactKeys' => array(          // case-insensitive
                'password',
                'x-api-key',
            ),
        ));
        $cfg = $this->helper->getProp($this->debug->pluginRedaction, 'cfg');
        self::assertSame(array(
            'password',
            'x-api-key',
        ), \array_keys($cfg['redactKeys']));
    }

    public static function providerTestMethod()
    {
        $base64snip = \base64_encode(
            \json_encode(array(
                'poop' => '💩',
                'int' => 42,
                'password' => 'secret',
            ))
        );
        return array(
            'url' => array(
                'log',
                array(
                    'https://joe:secret@test.com/?zig=zag&password=snusnu&foo=bar',
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            'https://joe:█████████@test.com/?zig=zag&password=█████████&foo=bar'
                        ),
                        'meta' => array(
                            'redact' => true,
                        )
                    ),
                )
            ),
            'base64' => array(
                'log',
                array(
                    $base64snip,
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) use ($base64snip) {
                        $jsonExpect = '{"method":"log","args":[{"brief":false,"strlen":null,"type":"string","typeMore":"base64","value":"' . $base64snip . '","valueDecoded":{"addQuotes":false,"attribs":{"class":["highlight","language-json"]},"brief":false,"contentType":"application\/json","prettified":true,"prettifiedTag":true,"strlen":null,"type":"string","typeMore":"json","value":"{\n    \"poop\": \"\\\\ud83d\\\\udca9\",\n    \"int\": 42,\n    \"password\": \"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\"\n}","valueDecoded":{"poop":"\ud83d\udca9","int":42,"password":"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588"},"visualWhiteSpace":false,"debug":"\u0000debug\u0000"},"debug":"\u0000debug\u0000"}],"meta":{"redact":true}}';
                        $jsonified = \json_encode($logEntry);
                        self::assertSame($jsonExpect, $jsonified);
                    },
                ),
            ),
            'object' => array(
                'log',
                array(
                    // (object) array('ding' => 'dong'),
                    new \bdk\Test\Debug\Fixture\TestObj(\http_build_query(array(
                        'foo' => 'bar',
                        'password' => 'secret',
                        'ding' => 'dong',
                    ))),
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => static function (LogEntry $logEntry) {
                        $logEntry = \bdk\Test\Debug\Helper::logEntryToArray($logEntry);
                        $obj = $logEntry['args'][0];
                        self::assertSame(null, $obj['stringified']);
                        self::assertSame('foo=bar&password=█████████&ding=dong', $obj['methods']['__toString']['returnValue']);
                    },
                ),
            ),
        );
    }
}
