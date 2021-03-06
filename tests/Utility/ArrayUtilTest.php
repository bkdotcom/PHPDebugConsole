<?php

namespace bdk\DebugTests\Utility;

use bdk\Debug\Utility\ArrayUtil;
use bdk\DebugTests\DebugTestFramework;

/**
 * PHPUnit tests for Debug class
 */
class ArrayUtilTest extends DebugTestFramework
{

    public function testArrayIsList()
    {
        $this->assertFalse(ArrayUtil::isList('string'));
        $this->assertTrue(ArrayUtil::isList(array()));     // empty array = "list"
        $this->assertFalse(ArrayUtil::isList(array(3 => 'foo',2 => 'bar',1 => 'baz',0 => 'nope')));
        $this->assertTrue(ArrayUtil::isList(array(0 => 'nope',1 => 'baz',2 => 'bar',3 => 'foo')));
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
        $arrayOut = ArrayUtil::mergeDeep($array1, $array2, $array3);
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
        $this->assertSame(true, ArrayUtil::pathGet($array, 'surfaces.bed.comfy'));
        $this->assertSame(false, ArrayUtil::pathGet($array, 'surfaces.rock.comfy'));
        $this->assertSame(null, ArrayUtil::pathGet($array, 'surfaces.bed.comfy.foo'));
        $this->assertSame(null, ArrayUtil::pathGet($array, 'surfaces.bed.comfy.0'));
        $this->assertSame(array('comfy' => true), ArrayUtil::pathGet($array, 'surfaces.bed'));
        $this->assertSame(null, ArrayUtil::pathGet($array, 'surfaces.bed.foo'));
        $this->assertSame(2, ArrayUtil::pathGet($array, 'surfaces.__count__'));
        $this->assertSame(false, ArrayUtil::pathGet($array, 'surfaces.__end__.comfy'));
        $this->assertSame(true, ArrayUtil::pathGet($array, 'surfaces.__reset__.comfy'));
        $this->assertSame(null, ArrayUtil::pathGet($array, 'surfaces.sofa.comfy'));
        $this->assertSame(false, ArrayUtil::pathGet($array, array('surfaces','__end__','comfy')));
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

        ArrayUtil::pathSet($array, 'surfaces.__end__.hard', true);
        ArrayUtil::pathSet($array, 'surfaces.__reset__.hard', false);
        ArrayUtil::pathSet($array, 'surfaces.__end__.__push__', 'pushed');
        ArrayUtil::pathSet($array, 'surfaces.__push__.itchy', true);

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
}
