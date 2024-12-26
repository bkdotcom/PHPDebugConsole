<?php

namespace bdk\Test\Debug\Utility;

use bdk\Backtrace\Xdebug;
use bdk\Debug\Utility\FindExit;
use bdk\PhpUnitPolyfill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test FindExit utility
 *
 * @covers \bdk\Debug\Plugin\InternalEvents
 * @covers \bdk\Debug\Utility\FindExit
 */
class FindExitTest extends TestCase
{
    use AssertionTrait;

    public function testFind()
    {
        if (Xdebug::isXdebugFuncStackAvail() === false) {
            $this->markTestSkipped('xdebug_get_function_stack() required');
        }
        $info = \call_user_func(array($this, 'findExit'));
        $this->assertArraySubset(array(
            'class' => 'bdk\\Test\\Debug\\Utility\\FindExitTest',
            'file' => __FILE__,
            'found' => 'exit',
            'function' => 'findExit',
            // 'line' => 30,
        ), $info);
    }

    public function testFindNotFound()
    {
        if (Xdebug::isXdebugFuncStackAvail() === false) {
            $this->markTestSkipped('xdebug_get_function_stack() required');
        }
        $findExit = new FindExit();
        $this->assertNull($findExit->find());
    }

    public function testFindClosure()
    {
        if (Xdebug::isXdebugFuncStackAvail() === false) {
            $this->markTestSkipped('xdebug_get_function_stack() required');
        }
        $xdebugVer = \phpversion('xdebug');
        if (\version_compare($xdebugVer, '3.0.0', '<')) {
            $this->markTestSkipped('xdebug_3.0 required to determine closure source');
        }

        $closure = function () {
            $findExit = new FindExit();
            $info = $findExit->find();
            if (false) {
                exit;
            }
            return $info ?: array();
        };
        $lineBegin = __LINE__ - 8;
        $lineEnd = __LINE__ - 2;
        $info = $closure();
        $xdebugVer = \phpversion('xdebug');
        $expect = array(
            'class' => 'bdk\\Test\\Debug\\Utility\\FindExitTest',
            'file' => __FILE__,
            'found' => 'exit',
            'function' => (\version_compare($xdebugVer, '3.4.0.alpha', '>=') && PHP_VERSION_ID >= 80400 ? '' : __NAMESPACE__ . '\\')
                 . '{closure:' . __FILE__ . ':' . $lineBegin . '-' . $lineEnd . '}',
        );
        // \bdk\Debug::varDump('expect', $expect);
        // \bdk\Debug::varDump('actual', $info);
        $this->assertArraySubset($expect, $info);
    }

    private function findExit()
    {
        $findExit = new FindExit();
        $info = $findExit->find();
        if (false) {
            exit;
        }
        return $info;
    }
}
