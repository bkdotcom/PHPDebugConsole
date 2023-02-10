<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\ArrayUtil;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\ArrayUtil
 */
class ArrayUtilTest extends TestCase
{
    /**
     * @dataProvider spliceAssocProvider
     */
    public function testSpliceAssoc($array, $key, $length, $replace, $expectReturn, $expectArray)
    {
        $return = ArrayUtil::spliceAssoc($array, $key, $length, $replace);
        self::assertSame($expectReturn, $return, 'return not as expected');
        self::assertSame($expectArray, $array, 'updated array not as expected');
    }

    public static function mergeDeepProvider()
    {
        return array(
            0 => array(
                array(
                    'planes' => 'array1 val',
                    'trains' => array('electric','diesel',),
                    'callable' => array(__CLASS__, 'testIsList'),
                    'automobiles' => array(
                        'hatchback' => array(),
                        'sedan' => array('family','luxury'),
                        'suv' => array('boxy','good'),
                    ),
                    1 => array('bar'),
                    'typeMismatch' => 'not array'
                ),
                array(
                    'boats' => array('speed','house'),
                    'callable' => array(__CLASS__, __FUNCTION__),
                    'trains' => array('steam',),
                    'planes' => 'array2 val',
                    'automobiles' => array(
                        'hatchback' => 'array2 val',
                        'suv' => 'array2 val',
                    ),
                    1 => array('foo','bar','baz'),
                    'typeMismatch' => array('array'),
                ),
                array(
                    'trains' => array('maglev'),
                ),
                array(
                    'planes' => 'array2 val',
                    'trains' => array('electric','diesel','steam','maglev'),
                    'callable' => array(__CLASS__, __FUNCTION__), // callable was replaced vs appending
                    'automobiles' => array(
                        'hatchback' => 'array2 val',
                        'sedan' => array('family','luxury'),
                        'suv' => 'array2 val',
                    ),
                    1 => array('bar','foo','baz'),
                    'typeMismatch' => array('array'),
                    'boats' => array('speed','house'),
                ),
            ),
            /*
            1 => array(
                array('orange', 'banana', 'apple', 'raspberry'),
                array('pineapple', 4 => 'cherry'),
                array('grape'),
                ArrayUtil::MERGE_INT_KEY_REPLACE,
                array(
                    'grape', 'banana', 'apple', 'raspberry', 'cherry',
                )
            )
            */
        );
    }

    public static function spliceAssocProvider()
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
        self::assertSame(array(
            'foo' => 'foo',
            'baz' => array(
                'bar' => 'bar',   // no longer reference
            ),
        ), $copy);
        $copy = ArrayUtil::copy($array, false);
        $foo = 'foo3';
        $bar = 'bar3';
        self::assertSame(array(
            'foo' => 'foo2',
            'baz' => array(
                'bar' => 'bar3',  // is still a reference
            ),
        ), $copy);
    }

    public function testIsList()
    {
        self::assertFalse(ArrayUtil::isList('string'));
        self::assertTrue(ArrayUtil::isList(array()));     // empty array = "list"
        self::assertFalse(ArrayUtil::isList(array(3 => 'foo',2 => 'bar',1 => 'baz',0 => 'nope')));
        self::assertTrue(ArrayUtil::isList(array(0 => 'nope',1 => 'baz',2 => 'bar',3 => 'foo')));
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
        self::assertSame($expect, $array);
    }

    /**
     * @dataProvider mergeDeepProvider
     */
    public function testMergeDeep($argsAndExpect)
    {
        $argsAndExpect = \func_get_args();
        $expect = \array_pop($argsAndExpect);
        self::assertSame(
            $expect,
            \call_user_func_array('bdk\Debug\Utility\ArrayUtil::mergeDeep', $argsAndExpect)
        );
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
        self::assertSame(true, ArrayUtil::pathGet($array, 'surfaces.bed.comfy'));
        self::assertSame(false, ArrayUtil::pathGet($array, 'surfaces.rock.comfy'));
        self::assertSame(null, ArrayUtil::pathGet($array, 'surfaces.bed.comfy.foo'));
        self::assertSame(null, ArrayUtil::pathGet($array, 'surfaces.bed.comfy.0'));
        self::assertSame(array('comfy' => true), ArrayUtil::pathGet($array, 'surfaces.bed'));
        self::assertSame(null, ArrayUtil::pathGet($array, 'surfaces.bed.foo'));
        self::assertSame(2, ArrayUtil::pathGet($array, 'surfaces.__count__'));
        self::assertSame(false, ArrayUtil::pathGet($array, 'surfaces.__end__.comfy'));
        self::assertSame(true, ArrayUtil::pathGet($array, 'surfaces.__reset__.comfy'));
        self::assertSame(null, ArrayUtil::pathGet($array, 'surfaces.sofa.comfy'));
        self::assertSame(false, ArrayUtil::pathGet($array, array('surfaces','__end__','comfy')));
        self::assertSame(false, ArrayUtil::pathGet($array, '__reset__/__pop__/comfy'));
        self::assertSame(array(
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
        self::assertSame('foo', ArrayUtil::pathGet($array2, '0.0'));
        self::assertSame('bar', ArrayUtil::pathGet($array2, '1.0'));
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

        self::assertSame(array(
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

    public function testSearchRecursive()
    {
        $array = array(
            'toys' => array(
                'nerf' => array(
                    'ball',
                    'gun',
                ),
                'car' => array(
                    'hotwheel',
                    'matchbox',
                    'rc'
                )
            ),
            'games' => array(
                'connect4',
                'monopoly',
            ),
            'yoyos' => array(
                'Duncan',
                'yomega',
            )
        );
        self::assertSame(false, ArrayUtil::searchRecursive('notfound', $array));
        self::assertSame(false, ArrayUtil::searchRecursive('car', $array));
        self::assertSame(array('toys','car'), ArrayUtil::searchRecursive('car', $array, true));
        self::assertSame(array('toys','car',2), ArrayUtil::searchRecursive('rc', $array));
    }

    public function testSortWithOrder()
    {
        $array = array(
            'a' => 'foo',
            'c' => '10',
            'b' => '9',
            'd' => 'derp',
        );
        // sort by value
        ArrayUtil::sortWithOrder($array, array('foo','derp'));
        self::assertSame(array(
            'a' => 'foo',
            'd' => 'derp',
            'b' => '9',
            'c' => '10',
        ), $array);

        // sort by key
        ArrayUtil::sortWithOrder($array, array('b'), 'key');
        self::assertSame(array(
            'b' => '9',
            'a' => 'foo',
            'c' => '10',
            'd' => 'derp',
        ), $array);

        // sort by value
        ArrayUtil::sortWithOrder($array);
        self::assertSame(array(
            'b' => '9',
            'c' => '10',
            'd' => 'derp',
            'a' => 'foo',
        ), $array);

        // sort by value
        ArrayUtil::sortWithOrder($array, null, 'key');
        self::assertSame(array(
            'a' => 'foo',
            'b' => '9',
            'c' => '10',
            'd' => 'derp',
        ), $array);
    }
}
