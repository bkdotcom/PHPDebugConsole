<?php

namespace bdk\Test\Debug\Utility;

use bdk\Backtrace;
use bdk\Debug\Utility\FindExit;
use bdk\Test\PolyFill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test FindExit utility
 *
 * @covers \bdk\Debug\Utility\FindExit
 */
class FindExitTest extends TestCase
{
    use AssertionTrait;

    public function testFind()
    {
        if (Backtrace::isXdebugFuncStackAvail() === false) {
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
        if (Backtrace::isXdebugFuncStackAvail() === false) {
            $this->markTestSkipped('xdebug_get_function_stack() required');
        }
        $findExit = new FindExit();
        $this->assertNull($findExit->find());
    }

    public function testFindClosure()
    {
        if (Backtrace::isXdebugFuncStackAvail() === false) {
            $this->markTestSkipped('xdebug_get_function_stack() required');
        }
        $closure = function () {
            $findExit = new FindExit();
            $info = $findExit->find();
            if (false) {
                exit;
            }
            return $info;
        };
        $lineBegin = __LINE__ - 8;
        $lineEnd = __LINE__ - 2;
        $info = $closure();
        // var_dump($info);
        $this->assertArraySubset(array(
            'class' => 'bdk\\Test\\Debug\\Utility\\FindExitTest',
            'file' => __FILE__,
            'found' => 'exit',
            'function' => __NAMESPACE__ . '\\{closure:' . __FILE__ . ':' . $lineBegin . '-' . $lineEnd . '}',
        ), $info);
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
