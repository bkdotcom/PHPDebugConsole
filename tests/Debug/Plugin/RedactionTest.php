<?php

namespace bdk\Test\Debug\Plugin;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\LogEntry;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test Redaction
 *
 * @covers \bdk\Debug\Plugin\Redaction
 */
class RedactionTest extends DebugTestFramework
{
    /**
     * @doesNotPerformAssertions
     */
    public function testBootstrap()
    {
        $this->debug->removePlugin($this->debug->getPlugin('redaction'));
        $this->debug->addPlugin(new \bdk\Debug\Plugin\Redaction(), 'redaction');
    }

    public function testConfig()
    {
        $this->debug->setCfg(array(
            'redactKeys' => array(          // case-insensitive
                'password',
                'x-api-key',
            ),
            'redactStrings' => array(
                'secret1',
                'secret2' => 'foo',
            ),
        ));
        $cfg = $this->debug->getPlugin('redaction')->getCfg();
        self::assertSame(array(
            'password',
            'x-api-key',
        ), \array_keys($cfg['redactKeys']));
    }

    public function testAddSearchReplace()
    {
        $redaction = $this->debug->getPlugin('redaction');
        $redactStringsBak = $redaction->getCfg('redactStrings');
        $redaction->addSearchReplace('search1', 'replace1');
        $redaction->addSearchReplace('search2');
        self::assertSame(array(
            'secret1' => null,
            'secret2' => 'foo',
            'search1' => 'replace1',
            'search2' => null,
        ), $redaction->getCfg('redactStrings'));
        $redaction->setCfg('redactStrings', $redactStringsBak);
    }

    public function testRedactHeaders()
    {
        $headerBlock = \implode("\r\n", array(
            'GET /foo/bar HTTP/1.1',
            'Authorization: Digest username="Mufasa", realm="testrealm@host.com", nonce="dcd98b7102dd2f0e8b11d0f600bfb0c093", uri="/dir/index.html", qop=auth, nc=00000001, cnonce="0a4f113b", response="6629fae49393a05397450978507c4ef1", opaque="5ccc069c403ebaf9f0171e9517f40e41"',
            'Authorization: Unknown auth scheme',
            'Invalid:',
            'Invalid2',
            'Content-Type: text/html',
        ));
        self::assertSame(implode("\r\n", array(
            'GET /foo/bar HTTP/1.1',
            'Authorization: Digest username="Mufasa", realm="testrealm@host.com", nonce="dcd98b7102dd2f0e8b11d0f600bfb0c093", uri="/dir/index.html", qop=auth, nc=00000001, cnonce="0a4f113b", response="█████████", opaque="5ccc069c403ebaf9f0171e9517f40e41"',
            'Authorization: Unknown █████████',
            'Invalid:',
            'Invalid2:',
            'Content-Type: text/html',
        )), $this->debug->redactHeaders($headerBlock));
    }

    public static function providerTestMethod()
    {
        $base64snip = \base64_encode(
            \json_encode(array(
                'poop' => '💩',
                'int' => 42,
                'password' => 'secret',
                'string' => 'Never tell anyone secret1 or secret2.',
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
                        ),
                    ),
                ),
            ),
            'base64' => array(
                'log',
                array(
                    $base64snip,
                    Debug::meta('redact'),
                ),
                array(
                    'entry' => array(
                        'method' => 'log',
                        'args' => array(
                            array(
                                'brief' => false,
                                // 'strlen' => 136,
                                // 'strlenValue' => 136,
                                'type' => 'string',
                                'typeMore' => 'base64',
                                'value' => $base64snip,
                                'valueDecoded' => array(
                                    'attribs' => array(
                                        'class' => array('highlight','language-json', 'no-quotes'),
                                    ),
                                    'brief' => false,
                                    'contentType' => 'application/json',
                                    'prettified' => true,
                                    'prettifiedTag' => true,
                                    // 'strlen' => 126,
                                    // 'strlenValue' => 126,
                                    'type' => 'string',
                                    'typeMore' => 'json',
                                    'value' => \str_replace('redactBlock', '█████████', \json_encode(array(
                                        'poop' => '💩',
                                        'int' => 42,
                                        'password' => 'redactBlock',
                                        'string' => 'Never tell anyone redactBlock or foo.',
                                    ), JSON_PRETTY_PRINT)),
                                    'valueDecoded' =>  array(
                                        'poop' => '💩',
                                        'int' => 42,
                                        'password' => '█████████',
                                        'string' => 'Never tell anyone █████████ or foo.',
                                    ),
                                    'debug' => Abstracter::ABSTRACTION,
                                ),
                                'debug' => Abstracter::ABSTRACTION,
                            ),
                        ),
                        'meta' => array(
                            'redact' => true,
                        ),
                    ),
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
                        // self::assertSame(null, $obj['stringified']);
                        self::assertArrayNotHasKey('stringified', $obj);
                        self::assertSame('foo=bar&password=█████████&ding=dong', $obj['methods']['__toString']['returnValue']);
                    },
                ),
            ),
        );
    }
}
