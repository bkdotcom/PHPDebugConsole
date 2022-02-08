<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\ArrayUtil;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 */
class ArrayUtilTest extends TestCase
{

    /**
     * @dataProvider providerSpliceAssoc
     */
    public function testSpliceAssoc($array, $key, $length, $replace, $expectReturn, $expectArray)
    {
        $return = ArrayUtil::spliceAssoc($array, $key, $length, $replace);
        $this->assertSame($expectReturn, $return, 'return not as expected');
        $this->assertSame($expectArray, $array, 'updated array not as expected');
    }

    public function providerSpliceAssoc()
    {
        return array(
            'twoArgs' => array(
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'bar',
                null, // everything to end removed
                null,
                [
                    'bar' => 'bar',
                    'baz' => 'baz',
                ],
                [
                    'foo' => 'foo',
                    0 => 'zero',
                ],
            ),
            'remove' => array(
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'bar',
                1,
                null,
                array(
                    'bar' => 'bar',
                ),
                array('foo' => 'foo', 'zero', 'baz' => 'baz'),
            ),
            'insert' => array(
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'bar',
                0,
                [
                    'new' => 'value',
                ],
                [],
                [
                    'foo' => 'foo',
                    0 => 'zero',
                    'new' => 'value',
                    'bar' => 'bar',
                    'baz' => 'baz',
                ],
            ),
            'removeInsert' => array(
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'bar',
                1,  // 1 value removed
                'string',
                [
                    'bar' => 'bar',
                ],
                [
                    'foo' => 'foo',
                    0 => 'zero',
                    1 => 'string',
                    'baz' => 'baz',
                ],
            ),
            'negLength' => array(
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'bar',
                -1,  // offset to end - 1 removed
                array('bip', 'ding' => 'dong', 'foo' => 'new foo'),
                [
                    'bar' => 'bar',
                ],
                [
                    'foo' => 'new foo',
                    0 => 'zero',
                    1 => 'bip',
                    'ding' => 'dong',
                    'baz' => 'baz',
                ],
            ),
            'bigNegLength' => array(
                // negative length goes beyond offset... don't remove anything
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'bar',
                -2,  // offset to end - 2 removed..  which will be zero
                array('bip', 'ding' => 'dong', 'foo' => 'new foo'),
                [],
                [
                    'foo' => 'new foo',
                    0 => 'zero',
                    1 => 'bip',
                    'ding' => 'dong',
                    'bar' => 'bar',
                    'baz' => 'baz',
                ],
            ),
            'keyNotFound' => array(
                array('foo' => 'foo', 'zero', 'bar' => 'bar', 'baz' => 'baz'),
                'notFound',
                2,  //  0 will be removed because key not found
                array('bip', 'ding' => 'dong'),
                [],
                [
                    'foo' => 'foo',
                    0 => 'zero',
                    'bar' => 'bar',
                    'baz' => 'baz',
                    1 => 'bip',
                    'ding' => 'dong',
                ],
            ),
        );
    }

    public function testCopy()
    {
        $foo = 'foo';
        $bar = 'bar';
        $array = array(
            'foo' => &$foo,
            'baz' => array(
                'bar' => &$bar,
            ),
        );
        $copy = ArrayUtil::copy($array);
        $foo = 'foo2';
        $bar = 'bar2';
        $this->assertSame(array(
            'foo' => 'foo',
            'baz' => array(
                'bar' => 'bar',   // no longer reference
            ),
        ), $copy);
        $copy = ArrayUtil::copy($array, false);
        $foo = 'foo3';
        $bar = 'bar3';
        $this->assertSame(array(
            'foo' => 'foo2',
            'baz' => array(
                'bar' => 'bar3',  // is still a reference
            ),
        ), $copy);
    }

    public function testIsList()
    {
        $this->assertFalse(ArrayUtil::isList('string'));
        $this->assertTrue(ArrayUtil::isList(array()));     // empty array = "list"
        $this->assertFalse(ArrayUtil::isList(array(3 => 'foo',2 => 'bar',1 => 'baz',0 => 'nope')));
        $this->assertTrue(ArrayUtil::isList(array(0 => 'nope',1 => 'baz',2 => 'bar',3 => 'foo')));
    }

    public function testMapRecursive()
    {
        $array = array(
            'foo' => 1,
            'bar' => null,
            'baz' => array(
                'ding' => 'bar',
                'bar' => true,
            ),
        );
        $expect = array(
            'foo' => 'foo',
            'bar' => 'foo',
            'baz' => array(
                'ding' => 'foo',
                'bar' => 'foo',
            ),
        );
        $array = ArrayUtil::mapRecursive(function ($val) {
            return 'foo';
        }, $array);
        $this->assertSame($expect, $array);
    }

    /**
     * Test
     *
     * @return void
     */
    public function testMergeDeep()
    {
        $array1 = array(
            'planes' => 'array1 val',
            'trains' => array('electric','diesel',),
            'callable' => array($this, 'testIsList'),
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
    public function testPathGet()
    {
        $array = array(
            'surfaces' => array(
                'bed' => array(
                    'comfy' => true,
                ),
                'rock' => array(
                    'comfy' => false,
                ),
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
        $this->assertSame(false, ArrayUtil::pathGet($array, '__reset__/__pop__/comfy'));
        $this->assertSame(array(
            'surfaces' => array(
                'bed' => array(
                    'comfy' => true,
                ),
            ),
        ), $array);
        // Test numeric keys
        $array2 = array(
            array('foo'),
            array('bar'),
            array('baz'),
        );
        $this->assertSame('foo', ArrayUtil::pathGet($array2, '0.0'));
        $this->assertSame('bar', ArrayUtil::pathGet($array2, '1.0'));
    }

    public function testPathSet()
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

        ArrayUtil::pathSet($array, 'surfaces.__reset__.comfy', '__unset__');
        ArrayUtil::pathSet($array, 'surfaces.__end__.hard', true);
        ArrayUtil::pathSet($array, 'surfaces.__reset__.hard', false);
        ArrayUtil::pathSet($array, 'surfaces.__end__.__push__', 'pushed');
        ArrayUtil::pathSet($array, 'surfaces.__push__.itchy', true);

        $this->assertSame(array(
            'surfaces' => array(
                'bed' => array(
                    // 'comfy' => true,  // we unset comfy
                    'hard' => false,  // we set to false
                ),
                'rock' => array(
                    'comfy' => false,
                    'hard' => true,  // we set to true
                    0 => 'pushed',  // we pushed
                ),
                0 => array(         // we created
                    'itchy' => true,
                ),
            ),
        ), $array);
    }
}