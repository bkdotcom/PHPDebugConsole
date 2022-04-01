<?php

namespace bdk\Test\Debug\Route;

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
        $cfg = $this->helper->getPrivateProp($this->debug->pluginRedaction, 'cfg');
        $this->assertSame(array(
            'password',
            'x-api-key',
        ), \array_keys($cfg['redactKeys']));
    }

    public function providerTestMethod()
    {
        $base64snip = \base64_encode(
            \json_encode(array(
                'poop' => '💩',
                'int' => 42,
                'password' => 'secret',
            ))
        );
        return array(
            'base64' => array(
                'log',
                array(
                    $base64snip,
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => function (LogEntry $logEntry) use ($base64snip) {
                        $jsonExpect = '{"method":"log","args":[{"strlen":null,"typeMore":"base64","value":"' . $base64snip . '","valueDecoded":{"strlen":null,"typeMore":"json","valueDecoded":{"poop":"\ud83d\udca9","int":42,"password":"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588"},"value":"{\n    \"poop\": \"\\\\ud83d\\\\udca9\",\n    \"int\": 42,\n    \"password\": \"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\"\n}","type":"string","attribs":{"class":["highlight","language-json"]},"addQuotes":false,"contentType":"application\/json","prettified":true,"prettifiedTag":true,"visualWhiteSpace":false,"debug":"\u0000debug\u0000"},"type":"string","debug":"\u0000debug\u0000"}],"meta":{"redact":true}}';
                        $jsonified = \json_encode($logEntry);
                        $this->assertSame($jsonExpect, $jsonified);
                    },
                ),
            ),
            'object' => array(
                'log',
                array(
                    // (object) array('ding' => 'dong'),
                    new \bdk\Test\Debug\Fixture\Test(\http_build_query(array(
                        'foo' => 'bar',
                        'password' => 'secret',
                        'ding' => 'dong',
                    ))),
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => function (LogEntry $logEntry) {
                        $logEntry = $this->helper->logEntryToArray($logEntry);
                        $obj = $logEntry['args'][0];
                        $this->assertSame(null, $obj['stringified']);
                        $this->assertSame('foo=bar&password=█████████&ding=dong', $obj['methods']['__toString']['returnValue']);
                        // $this->helper->stderr($logEntry);
                        // $jsonExpect = '{"method":"log","args":[{"strlen":null,"typeMore":"base64","value":"' . $base64snip . '","valueDecoded":{"strlen":null,"typeMore":"json","valueDecoded":{"poop":"\ud83d\udca9","int":42,"password":"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588"},"value":"{\n    \"poop\": \"\\\\ud83d\\\\udca9\",\n    \"int\": 42,\n    \"password\": \"\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\u2588\"\n}","type":"string","attribs":{"class":["highlight","language-json"]},"addQuotes":false,"contentType":"application\/json","prettified":true,"prettifiedTag":true,"visualWhiteSpace":false,"debug":"\u0000debug\u0000"},"type":"string","debug":"\u0000debug\u0000"}],"meta":{"redact":true}}';
                        // $jsonified = \json_encode($logEntry);
                        // $this->assertSame($jsonExpect, $jsonified);
                    },
                ),
            ),
        );
    }
}
