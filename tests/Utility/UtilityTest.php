<?php

namespace bdk\DebugTests\Utility;

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
            'callable' => array($this, 'testArrayIsList'),
            'automobiles' => array(
                'hatchback' => array(),
                'sedan' => array('family','luxury'),
                'suv' => array('boxy','good'),
            ),
            1 => array('foo'),
        );
        $array2 = array(
            'boats' => array('speed','house'),
            'callable' => array($this, __FUNCTION__),
            'trains' => array('steam',),
            'planes' => 'array2 val',
            'automobiles' => array(
                'hatchback' => 'array2 val',
                'suv' => 'array2 val',
            ),
            1 => array('bar'),
        );
        $array3 = array(
            'trains' => array('maglev'),
        );
        $arrayExpect = array(
            'planes' => 'array2 val',
            'trains' => array('electric','diesel','steam','maglev'),
            'callable' => array($this, __FUNCTION__), // callable was replaced vs appending
            'automobiles' => array(
                'hatchback' => 'array2 val',
                'sedan' => array('family','luxury'),
                'suv' => 'array2 val',
            ),
            1 => array('foo','bar'),
            'boats' => array('speed','house'),
        );
        $arrayOut = Utility::arrayMergeDeep($array1, $array2, $array3);
        $this->assertSame($arrayExpect, $arrayOut);
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
        $this->assertSame(true, Utility::arrayPathGet($array, 'surfaces.bed.comfy'));
        $this->assertSame(false, Utility::arrayPathGet($array, 'surfaces.rock.comfy'));
        $this->assertSame(null, Utility::arrayPathGet($array, 'surfaces.bed.comfy.foo'));
        $this->assertSame(null, Utility::arrayPathGet($array, 'surfaces.bed.comfy.0'));
        $this->assertSame(array('comfy' => true), Utility::arrayPathGet($array, 'surfaces.bed'));
        $this->assertSame(null, Utility::arrayPathGet($array, 'surfaces.bed.foo'));
        $this->assertSame(2, Utility::arrayPathGet($array, 'surfaces.__count__'));
        $this->assertSame(false, Utility::arrayPathGet($array, 'surfaces.__end__.comfy'));
        $this->assertSame(true, Utility::arrayPathGet($array, 'surfaces.__reset__.comfy'));
        $this->assertSame(null, Utility::arrayPathGet($array, 'surfaces.sofa.comfy'));
        $this->assertSame(false, Utility::arrayPathGet($array, array('surfaces','__end__','comfy')));
    }

    public function testArrayPathSet()
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

        Utility::arrayPathSet($array, 'surfaces.__end__.hard', true);
        Utility::arrayPathSet($array, 'surfaces.__reset__.hard', false);
        Utility::arrayPathSet($array, 'surfaces.__end__.__push__', 'pushed');
        Utility::arrayPathSet($array, 'surfaces.__push__.itchy', true);

        $this->assertSame(array(
            'surfaces' => array(
                'bed' => array(
                    'comfy' => true,
                    'hard' => false,
                ),
                'rock' => array(
                    'comfy' => false,
                    'hard' => true,
                    0 => 'pushed',
                ),
                0 => array(
                    'itchy' => true,
                ),
            ),
        ), $array);
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
        $filesA = \get_included_files();
        $filesB = Utility::getIncludedFiles();
        \sort($filesA);
        \sort($filesB);
        $this->assertArraySubset($filesA, $filesB);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testIsBase64Encoded()
    {
        $base64Str = \base64_encode(\chunk_split(\str_repeat('zippity do dah', 50)));
        $this->assertTrue(Utility::isBase64Encoded($base64Str));

        $this->assertFalse(Utility::isBase64Encoded('I\'m just a bill.'));
        $this->assertFalse(Utility::isBase64Encoded('onRenderComplete'));
        $this->assertFalse(Utility::isBase64Encoded('/Users/jblow/not/base64/'));
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
