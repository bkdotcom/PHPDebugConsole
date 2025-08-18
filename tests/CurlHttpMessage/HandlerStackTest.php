<?php

namespace bdk\Test\CurlHttpMessage;

use bdk\CurlHttpMessage\HandlerStack;
use bdk\Test\CurlHttpMessage\AbstractTestCase;

/**
 * @covers \bdk\CurlHttpMessage\HandlerStack
 */
class HandlerStackTest extends AbstractTestCase
{
    public function testSetsHandlerInConstructor()
    {
        $callable = static function () {};
        $stack = new HandlerStack($callable);
        self::assertSame($callable, self::propGet($stack, 'handler'));
    }

    public function testCanSetDifferentHandlerAfterConstruction()
    {
        $callable = static function () {};
        $stack = new HandlerStack();
        $stack->setHandler($callable);
        self::assertSame($callable, self::propGet($stack, 'handler'));
    }

    public function testAssertHandlerCallable()
    {
        $this->expectException($this->classes['InvalidArgumentException']);
        new HandlerStack('bogus');
    }

    public function testPushInOrder()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->push($meths[2]);
        $stack->push($meths[3]);
        $stack->push($meths[4]);
        self::assertSame('Hello - testabc', $stack('test'));
        self::assertSame(
            [['a', 'test'], ['b', 'testa'], ['c', 'testab']],
            $meths[0]
        );
    }

    public function testUseCachedComposedStack()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->push($meths[2]);
        $stack->push($meths[3]);
        $stack->push($meths[4]);
        self::assertSame('Hello - testabc', $stack('test'));
        self::assertSame('Hello - testabc', $stack('test'));
    }

    public function testUnshiftsInReverseOrder()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->unshift($meths[2]);
        $stack->unshift($meths[3]);
        $stack->unshift($meths[4]);
        self::assertSame('Hello - testcba', $stack('test'));
        self::assertSame(
            [['c', 'test'], ['b', 'testc'], ['a', 'testcb']],
            $meths[0]
        );
    }

    public function testAssertNameValid()
    {
        $stack = new HandlerStack();
        $this->expectException($this->classes['InvalidArgumentException']);
        $stack->push(static function (callable $next) {
            return static function ($val) use ($next) {
                return $next($val . 'a');
            };
        }, 123);
    }

    public function testAssertNameUnique()
    {
        $stack = new HandlerStack();
        $stack->push(static function (callable $next) {
            return static function ($val) use ($next) {
                return $next($val . 'a');
            };
        }, 'foo');
        $this->expectException($this->classes['RuntimeException']);
        $stack->push(static function (callable $next) {
            return static function ($val) use ($next) {
                return $next($val . 'a');
            };
        }, 'foo');
    }

    public function testCanRemoveMiddlewareByName()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->push($meths[2], 'foo');
        $stack->push($meths[3], 'bar');
        $stack->push($meths[4], 'baz');
        $stack->remove('bar');
        self::assertSame('Hello - testac', $stack('test'));
    }

    public function testCanRemoveMiddlewareByInstance()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->push($meths[2]);
        $stack->push($meths[2]);
        $stack->push($meths[3]);
        $stack->push($meths[4]);
        $stack->push($meths[2]);
        $stack->remove($meths[3]);
        self::assertSame('Hello - testaaca', $stack('test'));
    }

    public function testRemoveThrowInvalidArgumentException()
    {
        $this->expectException($this->classes['InvalidArgumentException']);
        $stack = new HandlerStack();
        $stack->remove(null);
    }

    public function testCanAddBeforeByName()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->push($meths[2], 'foo');
        $stack->before('foo', $meths[3], 'baz');
        $stack->before('baz', $meths[4], 'bar');
        $stack->before('baz', $meths[4], 'qux');
        self::assertSame('Hello - testccba', $stack('test'));
    }

    public function testEnsuresHandlerExistsByName()
    {
        $this->expectException($this->classes['RuntimeException']);

        $builder = new HandlerStack();
        $builder->before('foo', static function () {
        });
    }

    public function testCanAddAfterByName()
    {
        $meths = $this->getFunctions();
        $stack = new HandlerStack();
        $stack->setHandler($meths[1]);
        $stack->push($meths[2], 'a');
        $stack->push($meths[3], 'b');
        $stack->after('a', $meths[4], 'c');
        $stack->after('b', $meths[4], 'd');
        self::assertSame('Hello - testacbc', $stack('test'));
    }

    private function getFunctions()
    {
        $calls = [];

        return array(
            &$calls,
            static function ($val) {
                return 'Hello - ' . $val;
            },
            static function (callable $next) use (&$calls) {
                return static function ($val) use ($next, &$calls) {
                    $calls[] = ['a', $val];
                    return $next($val . 'a');
                };
            },
            static function (callable $next) use (&$calls) {
                return static function ($val) use ($next, &$calls) {
                    $calls[] = ['b', $val];
                    return $next($val . 'b');
                };
            },
            static function (callable $next) use (&$calls) {
                return static function ($val) use ($next, &$calls) {
                    $calls[] = ['c', $val];
                    return $next($val . 'c');
                };
            },
        );
    }
}
