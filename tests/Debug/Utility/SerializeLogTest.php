<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Utility\SerializeLog;
use bdk\HttpMessage\ServerRequestExtended as ServerRequest;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Test\Debug\DebugTestFramework;
use bdk\Test\Debug\Fixture\TestObj;

/**
 * Test SerializeLog
 *
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\Object\Abstraction
 * @covers \bdk\Debug\Utility\SerializeLog
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class SerializeLogTest extends DebugTestFramework
{
    use AssertionTrait;

    public function testSerializeUnserialize()
    {
        $debug = new Debug(array(
            'collect' => true,
            'logResponse' => false,
            'serviceProvider' => array(
                'serverRequest' => new ServerRequest(
                    'GET',
                    null,
                    array(
                        'DOCUMENT_ROOT' => TEST_DIR . '/../tmp',
                        'REQUEST_METHOD' => 'GET',
                        'REQUEST_TIME_FLOAT' => \microtime(true),
                        'SERVER_ADMIN' => 'testAdmin@test.com',
                    )
                ),
            ),
        ));
        $debug->alert('some alert');
        $debug->groupSummary();
        $debug->log('in summary');
        $debug->log(new TestObj());
        $debug->groupEnd();
        $debug->info('this is a test');
        $serialized = SerializeLog::serialize($debug);
        $unserialized = SerializeLog::unserialize($serialized);
        self::assertIsArray($unserialized);
        $channelNameRoot = $debug->getCfg('channelName', Debug::CONFIG_DEBUG);
        $expect = array(
            'alerts' => $this->helper->deObjectifyData($debug->data->get('alerts'), false),
            'classDefinitions' => $this->helper->deObjectifyData($debug->data->get('classDefinitions')),
            'config' => array(
                'channelIcon' => $debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                'channelKey' => $debug->getCfg('channelKey', Debug::CONFIG_DEBUG),
                'channelName' => $channelNameRoot,
                'channels' => \array_map(static function (Debug $channel) use ($channelNameRoot) {
                    $channelName = $channel->getCfg('channelName', Debug::CONFIG_DEBUG);
                    return array(
                        'channelIcon' => $channel->getCfg('channelIcon', Debug::CONFIG_DEBUG),
                        'channelShow' => $channel->getCfg('channelShow', Debug::CONFIG_DEBUG),
                        'channelSort' => $channel->getCfg('channelSort', Debug::CONFIG_DEBUG),
                        'nested' => \strpos($channelName, $channelNameRoot . '.') === 0,
                    );
                }, $debug->getChannels(true, true)),
                'logRuntime' => $debug->getCfg('logRuntime'),
            ),
            'log' => $this->helper->deObjectifyData($debug->data->get('log'), false, true),
            'logSummary' => $this->helper->deObjectifyData($debug->data->get('logSummary'), false, true),
            'runtime' => $debug->data->get('runtime'),
            'requestId' => $debug->data->get('requestId'),
            'version' => Debug::VERSION,
        );
        $actual = $this->helper->deObjectifyData($unserialized);

        self::assertSame(array(
            "\x00default\x00",
            'bdk\\Test\\Debug\\Fixture\\TestObj',
            'stdClass',
        ), \array_keys($unserialized['classDefinitions']));

        self::assertEquals($expect, $actual);
        $debug = SerializeLog::import($unserialized);
        $objAbs = $debug->data->get('logSummary.0.1.args.0');
        self::assertInstanceOf('bdk\\Debug\\Abstraction\\Abstraction', $objAbs);
        self::assertSame(
            $debug->data->get(array('classDefinitions', 'bdk\\Test\\Debug\\Fixture\\TestObj')),
            \bdk\Debug\Utility\Reflection::propGet($objAbs, 'inherited')
        );
        $serialized = SerializeLog::serialize($debug);
        $unserialized = SerializeLog::unserialize($serialized);
        self::assertEquals(
            $expect,
            $this->helper->deObjectifyData($unserialized)
        );
    }

    /**
     * Simply test that importing and outputting log serialized with older versions of PHPDebugConsole does not throw an error.
     *
     * @doesNotPerformAssertions
     *
     * @dataProvider BackwardsCompatibilityDataProvider
     */
    public function testBackwardsImportCompatibility($dataFilepath)
    {
        $serialized = \file_get_contents($dataFilepath);
        $data = SerializeLog::unserialize($serialized);
        $debug = SerializeLog::import($data);
        $debug->setCfg(array(
            'output' => true,
            'route' => 'html',
        ));
        $debug->output();
    }

    public static function BackwardsCompatibilityDataProvider()
    {
        $dataDir = TEST_DIR . '/Debug/data/serialized/';
        $dataFiles = \glob($dataDir . '*.txt');
        $tests = array();
        foreach ($dataFiles as $filepath) {
            $basename = \basename($filepath, '.txt');
            $tests[$basename] = array(
                $filepath,
            );
        }
        return $tests;
    }

    public function testSerialieNotBase64()
    {
        $this->debug->alert('some alert');
        $this->debug->groupSummary();
        $this->debug->log('in summary');
        $this->debug->groupEnd();
        $serialized = SerializeLog::serialize($this->debug);
        $strStart = 'START DEBUG';
        $strEnd = 'END DEBUG';
        $regex = '/' . $strStart . '[\r\n]+(.+)[\r\n]+' . $strEnd . '/s';
        if (\preg_match($regex, $serialized, $matches)) {
            $serialized = $matches[1];
        }
        $serialized = \base64_decode($serialized);
        $unserialized = SerializeLog::unserialize($serialized);
        self::assertFalse($unserialized);
    }

    public function testUnserializeLogLegacy()
    {
        $serialized = <<<'EOD'
START DEBUG
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
END DEBUG
EOD;
        $unserialized = SerializeLog::unserialize($serialized);
        $expectUnserialized = array(
            'config' => array(
                'channels' => array(),
                'channelName' => 'general',
            ),
            'version' => '2.3',
            'alerts' => array(
                array(
                    'alert',
                    array('Alerty'),
                    array(
                        'class' => 'danger',
                        'dismissible' => false,
                    ),
                ),
            ),
            'log' => array(
                array(
                    'log',
                    array('hello world'),
                    array(),
                ),
                array(
                    'log',
                    array(
                        'some obj',
                        array(
                            'className' => 'stdClass',
                            'collectMethods' => true,
                            'constants' => array(),
                            'debug' => Abstracter::ABSTRACTION,
                            'debugMethod' => 'log',
                            'definition' => array(
                                'fileName' => false,
                                'startLine' => false,
                                'extensionName' => 'Core',
                            ),
                            'extends' => array(),
                            'implements' => array(),
                            'isExcluded' => false,
                            'isRecursion' => false,
                            'methods' => array(),
                            'phpDoc' => array(
                                'summary' => null,
                                'desc' => null,
                            ),
                            'properties' => array(
                                'foo' => array(
                                    'desc' => null,
                                    'inheritedFrom' => null,
                                    'isExcluded' => false,
                                    'isStatic' => false,
                                    'originallyDeclared' => null,
                                    'overrides' => null,
                                    'forceShow' => false,
                                    'type' => null,
                                    'value' => 'bar',
                                    'valueFrom' => 'value',
                                    'visibility' => 'public',
                                    // import merges in default values
                                    // 'attributes' => array(),
                                    // 'debugInfoExcluded' => false,
                                    // 'isPromoted' => false,
                                    // 'isReadOnly' => false,
                                ),
                            ),
                            'scopeClass' => 'bdk\Debug',
                            'stringified' => null,
                            'type' => Type::TYPE_OBJECT,
                            'traverseValues' => array(),
                            'viaDebugInfo' => false,
                        ),
                    ),
                    array(),
                ),
            ),
            'logSummary' => array(
                array(
                    array(
                        'group',
                        array('environment'),
                        array(
                            'hideIfEmpty' => true,
                            'level' => 'info',
                        ),
                    ),
                    array(
                        'log',
                        array(
                            'PHP Version',
                            '8.1.0',
                        ),
                        array(),
                    ),
                    array(
                        'log',
                        array(
                            'ini location',
                            '/usr/local/etc/php/8.1/php.ini',
                        ),
                        array(),
                    ),
                    array(
                        'log',
                        array(
                            'memory_limit',
                            '1 GB',
                        ),
                        array(),
                    ),
                    array(
                        'log',
                        array(
                            'session.cache_limiter',
                            'nocache',
                        ),
                        array(),
                    ),
                    array(
                        'log',
                        array(
                            'session_save_path',
                            '/var/tmp/',
                        ),
                        array(),
                    ),
                    array(
                        'warn',
                        array(
                            'PHP\'s %cerror_reporting%c is set to `%cE_ALL | E_STRICT & ~E_DEPRECATED%c` rather than `%cE_ALL | E_STRICT%c`' . "\n"
                                . 'PHPDebugConsole is disregarding %cerror_reporting%c value (this is configurable)',
                            'font-family:monospace; opacity:0.8;',
                            'font-family:inherit; white-space:pre-wrap;',
                            'font-family:monospace; opacity:0.8;',
                            'font-family:inherit; white-space:pre-wrap;',
                            'font-family:monospace; opacity:0.8;',
                            'font-family:inherit; white-space:pre-wrap;',
                            'font-family:monospace; opacity:0.8;',
                            'font-family:inherit; white-space:pre-wrap;',
                        ),
                        array(
                            'file' => null,
                            'line' => null,
                        ),
                    ),
                    array(
                        'log',
                        array(
                            '$_SERVER',
                            array(
                                'REMOTE_ADDR' => '127.0.0.1',
                                'REQUEST_TIME' => '2022-03-21 03:19:25 UTC',
                                'REQUEST_URI' => '/common/vendor/bdk/PHPDebugConsole/examples/ver23.php',
                                'SERVER_ADDR' => '127.0.0.1',
                                'SERVER_NAME' => '127.0.0.1',
                            ),
                        ),
                        array(),
                    ),
                    array(
                        'log',
                        array(
                            'request headers',
                            array(
                                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                                'Accept-Encoding' => 'gzip, deflate, br',
                                'Accept-Language' => 'en-US,en;q=0.9',
                                'Cache-Control' => 'max-age=0',
                                'Connection' => 'keep-alive',
                                'Cookie' => 'undefined=undefined; SESSIONID=hp5ln6mia3bjrgkjpsn8usta8b;',
                                'Host' => '127.0.0.1',
                                'Sec-Fetch-Dest' => 'document',
                                'Sec-Fetch-Mode' => 'navigate',
                                'Sec-Fetch-Site' => 'none',
                                'Sec-Fetch-User' => '?1',
                                'Upgrade-Insecure-Requests' => '1',
                                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.83 Safari/537.36',
                                'dnt' => '1',
                                'sec-ch-ua' => '" Not A;Brand";v="99", "Chromium";v="99", "Google Chrome";v="99"',
                                'sec-ch-ua-mobile' => '?0',
                                'sec-ch-ua-platform' => '"macOS"',
                                'sec-gpc' => '1',
                            ),
                        ),
                        array(),
                    ),
                    array(
                        'log',
                        array(
                            '$_COOKIE',
                            array(
                                'SESSIONID' => 'hp5ln6mia3bjrgkjpsn8usta8b',
                                'undefined' => 'undefined',
                            ),
                        ),
                        array(),
                    ),
                    array(
                        'groupEnd',
                        array(),
                        array(),
                    ),
                ),
            ),
            'runtime' => array(),
            'config' => array(
                'channels' => array(),
                'channelName' => 'general',
            ),
        );
        self::assertSame($expectUnserialized, $unserialized);

        $debug = SerializeLog::import($unserialized);

        /*
            Now test that imported log serializes as expected
        */

        $expect = $expectUnserialized;

        unset($expect['log'][1][1][1]['collectMethods']);
        // unset($expect['logSummary'][0][6][2]); // remove empty meta  (null meta no longer stored as of 3.4)
        // serialized did not include these values
        $expect['log'][1][1][1] = \array_merge(
            array(
                'attributes' => array(),
                'cases' => array(),
                'cfgFlags' => 29360127,
                'interfacesCollapse' => array(),
                'isAbstract' => false,
                'isAnonymous' => false,
                'isFinal' => false,
                'isInterface' => false,
                'isLazy' => false,
                'isMaxDepth' => false,
                'isReadOnly' => false,
                'isTrait' => false,
                'methodsWithStaticVars' => array(),
                'sectionOrder' => array(
                    'attributes',
                    'extends',
                    'implements',
                    'constants',
                    'cases',
                    'properties',
                    'methods',
                    'phpDoc',
                ),
                'sort' => 'inheritance visibility name',
                'keys' => array(),
                'typeMore' => null,
            ),
            $expect['log'][1][1][1]
        );
        // serialized did not include these values
        $expect['log'][1][1][1]['properties']['foo'] = \array_merge(
            array(
                'attributes' => array(),
                'declaredLast' => null,
                'declaredPrev' => null,
                'declaredOrig' => null,
                'debugInfoExcluded' => false,
                'hooks' => array(),
                'isDeprecated' => false,
                'isFinal' => false,
                'isPromoted' => false,
                'isReadOnly' => false,
                'isVirtual' => false,
                'phpDoc' => array(
                    'desc' => '',
                    'summary' => '',
                ),
            ),
            $expect['log'][1][1][1]['properties']['foo']
        );

        $serialized = SerializeLog::serialize($debug);
        $unserialized = SerializeLog::unserialize($serialized);

        $keysCompare = array('alerts', 'log', 'logSummary');
        foreach ($expect['log'] as $i => $logEntryArray) {
            if (empty($logEntryArray[2])) {
                unset($expect['log'][$i][2]);
            }
        }
        foreach ($expect['logSummary'][0] as $i => $logEntryArray) {
            if (empty($logEntryArray[2])) {
                unset($expect['logSummary'][0][$i][2]);
            }
        }

        $expect = \array_intersect_key($expect, \array_flip($keysCompare));
        \ksort($expect['log'][1][1][1]);
        \ksort($expect['log'][1][1][1]['properties']['foo']);
        $actual = \array_intersect_key(
            $this->helper->deObjectifyData($unserialized, true, false, true),
            \array_flip($keysCompare)
        );

        // \bdk\Debug::varDump('expect', print_r($expect, true));
        // \bdk\Debug::varDump('actual', print_r($actual, true));
        self::assertEquals($expect, $actual);
    }
}
