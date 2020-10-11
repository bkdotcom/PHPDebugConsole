<?php

namespace bdk\DebugTests\Utility;

use bdk\Debug\Psr7lite\ServerRequest;
use bdk\Debug\Utility;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class UtilityTest extends DebugTestFramework
{

    public function testArrayIsList()
    {
        $this->assertFalse(Utility::arrayIsList('string'));
        $this->assertTrue(Utility::arrayIsList(array()));     // empty array = "list"
        $this->assertFalse(Utility::arrayIsList(array(3 => 'foo',2 => 'bar',1 => 'baz',0 => 'nope')));
        $this->assertTrue(Utility::arrayIsList(array(0 => 'nope',1 => 'baz',2 => 'bar',3 => 'foo')));
    }

    /**
     * Test
     *
     * @return void
     */
    public function testArrayMergeDeep()
    {
        $array1 = array(
            'planes' => 'array1 val',
            'trains' => array('electric','diesel',),
            'automobiles' => array(
                'hatchback' => array(),
                'sedan' => array('family','luxury'),
                'suv' => array('boxy','good'),
            ),
            1 => array('foo'),
        );
        $array2 = array(
            'boats' => array('speed','house'),
            'trains' => array('steam',),
            'planes' => 'array2 val',
            'automobiles' => array(
                'hatchback' => 'array2 val',
                'suv' => 'array2 val',
            ),
            1 => array('bar'),
        );
        $arrayExpect = array(
            'planes' => 'array2 val',
            'trains' => array('electric','diesel','steam',),
            'automobiles' => array(
                'hatchback' => 'array2 val',
                'sedan' => array('family','luxury'),
                'suv' => 'array2 val',
            ),
            1 => array('foo','bar'),
            'boats' => array('speed','house'),
        );
        $array3 = Utility::arrayMergeDeep($array1, $array2);
        $this->assertSame($arrayExpect, $array3);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testArrayPathGet()
    {
        $array = array(
            'surfaces' => array(
                'bed' => array(
                    'comfy' => true,
                ),
                'rock' => array(
                    'comfy' => false,
                )
            ),
        );
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.bed.comfy'), true);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.rock.comfy'), false);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.bed.comfy.foo'), null);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.bed.comfy.0'), null);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.bed'), array('comfy' => true));
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.bed.foo'), null);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.__count__'), 2);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.__end__.comfy'), false);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.__reset__.comfy'), true);
        $this->assertSame(Utility::arrayPathGet($array, 'surfaces.sofa.comfy'), null);
        $this->assertSame(Utility::arrayPathGet($array, array('surfaces','__end__','comfy')), false);
    }

    public function testGetBytes()
    {
        $this->assertSame('1 kB', Utility::getBytes('1kb'));
        $this->assertSame('1 kB', Utility::getBytes('1024'));
        $this->assertSame('1 kB', Utility::getBytes(1024));
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test from cli
     */
    public function testGetEmittedHeader()
    {
        $this->assertSame(array(), Utility::getEmittedHeader());
    }

    public function testGetIncludedFiles()
    {
        $filesA = get_included_files();
        $filesB = Utility::getIncludedFiles();
        sort($filesA);
        sort($filesB);
        $this->assertArraySubset($filesA, $filesB);
    }

    public function testGetInterface()
    {
        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                'REQUEST_METHOD' => 'GET',
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('http', Utility::getInterface());

        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'REQUEST_METHOD' => 'GET',
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('http ajax', Utility::getInterface());

        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET', null, array(
                'PATH' => '.',
            )),
        ));
        $this->clearServerParamCache();
        $this->assertSame('cli', Utility::getInterface());

        $this->debug->setCfg('services', array(
            'request' => new ServerRequest('GET'),
        ));
        $this->clearServerParamCache();
        $this->assertSame('cli cron', Utility::getInterface());
    }

    /**
     * Test
     *
     * @return void
     */
    public function testIsBase64Encoded()
    {
        $base64Str = base64_encode(chunk_split(str_repeat('zippity do dah', 50)));
        $this->assertTrue(Utility::isBase64Encoded($base64Str));
        $this->assertFalse(Utility::isBase64Encoded('I\'m just a bill.'));
    }

    public function testIsFile()
    {
        $this->assertTrue(Utility::isFile(__FILE__));
        // is_file() expects parameter 1 to be a valid path, string given
        $this->assertFalse(Utility::isFile("\0foo.txt"));
        $this->assertFalse(Utility::isFile(__DIR__ . '/' . "\0foo.txt"));
    }

    /**
     * Test
     *
     * @return void
     *
     * @todo better test
     */
    public function testMemoryLimit()
    {
        $this->assertNotNull(Utility::memoryLimit());
    }

    public function testRequestId()
    {
        $this->assertStringMatchesFormat('%x', Utility::requestId());
    }

    /**
     * Test
     *
     * @return array of serialized logs
     */
    /*
    public function serializeLogProvider()
    {
        $log = array(
            array('log', 'What rolls down stairs'),
            array('info', 'alone or in pairs'),
            array('warn', 'rolls over your neighbor\'s dog?'),
        );
        $serialized = Utility::serializeLog($log);
        return array(
            array($serialized, $log)
        );
    }
    */

    /**
     * Test
     *
     * @param string $serialized   string provided by serializeLogProvider dataProvider
     * @param array  $unserialized the unserialized array
     *
     * @return void
     *
     * @dataProvider serializeLogProvider
     */
    /*
    public function testUnserializeLog($serialized, $unserialized)
    {
        $log = Utility::unserializeLog($serialized, $this->debug);
        $this->assertSame($unserialized, $log);
    }
    */
}
