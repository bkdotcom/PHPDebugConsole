<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\SerializeLog;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Test\Debug\DebugTestFramework;

/**
 * Test SerializeLog
 *
 * @covers \bdk\Debug\Abstraction\AbstractObject
 * @covers \bdk\Debug\Abstraction\Object\Abstraction
 * @covers \bdk\Debug\Utility\SerializeLog
 * @covers \bdk\Debug\Utility\UnserializeLog
 * @covers \bdk\Debug\Utility\UnserializeLogBackwards
 *
 * @phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
 */
class SerializeLogTest extends DebugTestFramework
{
    use AssertionTrait;

    /**
     * @dataProvider importProvider
     */
    public function testImport($version, $dataFilepath)
    {
        $serialized = \file_get_contents($dataFilepath);
        $data = SerializeLog::unserialize($serialized);

        if ($version === '2.3') {
            $data['runtime']['memoryLimit'] = '2G';
        }

        $debug = SerializeLog::import($data);

        // now "export" what we just imported and compare to expected

        $serialized = SerializeLog::serialize($debug);
        $data = SerializeLog::unserialize($serialized);

        $dataExpect = include TEST_DIR . '/Debug/data/serialized/expected.php';

        if ($version === '3.1') {
            unset($data['classDefinitions']['Simple']['cfgFlags']);
        }
        if ($version === '3.0' || $version === '2.3') {
            unset($data['classDefinitions']['Simple']['cfgFlags']);
            $nullify = [
                'methods.__toString.declaredLast',
                'methods.__toString.declaredOrig',
                'methods.foo.declaredLast',
                'methods.foo.declaredOrig',
                'properties.offLimits.declaredLast',
                'properties.offLimits.declaredOrig',
            ];
            foreach ($nullify as $path) {
                \bdk\Debug\Utility\ArrayUtil::pathSet($dataExpect['classDefinitions']['Simple'], $path, null);
            }
        }
        if ($version === '2.3') {
            unset($dataExpect['classDefinitions']['Simple']['implements']);
            $dataExpect['log'][1][1][0]['object']['scopeClass'] = 'bdk\Debug';
            \ksort($dataExpect['log'][1][1][0]['object']);
            $dataExpect['log'][1][1][0]['string (timestamp)'] = (string) \strtotime('2026-01-01 12:34:56 UTC');
        }

        // \bdk\Debug::varDump('expect', $dataExpect);
        // \bdk\Debug::varDump('actual', $this->helper->deObjectifyData($data, false));

        self::assertSame($dataExpect, $this->helper->deObjectifyData($data, false));
    }

    public static function importProvider()
    {
        $dataDir = TEST_DIR . '/Debug/data/serialized/';
        $dataFiles = \glob($dataDir . '*.txt');
        $tests = array();
        foreach ($dataFiles as $filepath) {
            $basename = \basename($filepath, '.txt');
            $version = \preg_replace('/[^\d]+$/', '', $basename);
            $tests[$basename] = array(
                $version,
                $filepath,
            );
        }
        if (PHP_VERSION_ID < 70400) {
            // objects serialized with __serialize can not be unserialized in php < 7.4
            //  v2.3 did not use __serialize, so can still test that
            return \array_intersect_key($tests, \array_flip(['2.3']));
        }

        /*
        return \array_intersect_key($tests, \array_flip([
            '2.3',
            '3.0',
            '3.1',
            '3.2',
            '3.3',
            '3.4',
            '3.5',
            '3.6',
        ]));
        */
        return $tests;
    }

    public function testSerializeNotBase64()
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
        $serialized = \base64_decode($serialized, true);
        $unserialized = SerializeLog::unserialize($serialized);
        self::assertFalse($unserialized);
    }
}
