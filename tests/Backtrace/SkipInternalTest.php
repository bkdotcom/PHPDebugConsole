<?php

namespace bdk\Test\Backtrace;

use bdk\Backtrace;
use bdk\Backtrace\Normalizer;
use bdk\Backtrace\SkipInternal;
use bdk\PhpUnitPolyfill\AssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Backtrace\SkipInternal
 */
class SkipInternalTest extends TestCase
{
    use AssertionTrait;

    protected $internalClassesBackup = array();

    public function setUp(): void
    {
        $internalClassesRef = new \ReflectionProperty('bdk\\Backtrace\\SkipInternal', 'internalClasses');
        $internalClassesRef->setAccessible(true);
        $this->internalClassesBackup = $internalClassesRef->getValue();
        $internalClassesRef->setValue(null, array(
            'classes' => array(),
            'levelCurrent' => null,
            'levels' => array(),
            'regex' => null,
        ));
        SkipInternal::addInternalClass('bdk\\Backtrace');
        SkipInternal::addInternalClass('ReflectionMethod');
    }

    public function tearDown(): void
    {
        $internalClassesRef = new \ReflectionProperty('bdk\\Backtrace\\SkipInternal', 'internalClasses');
        $internalClassesRef->setAccessible(true);
        $internalClassesRef->setValue(null, $this->internalClassesBackup);
    }

    public function testAddInternalClass()
    {
        SkipInternal::addInternalClass('foo\\bar');

        $internalClassesRef = new \ReflectionProperty('bdk\\Backtrace\\SkipInternal', 'internalClasses');
        $internalClassesRef->setAccessible(true);
        $internalClasses = $internalClassesRef->getValue();
        $this->assertSame(0, $internalClasses['classes']['foo\\bar']);
        Backtrace::addInternalClass(array(
            'foo\\bar',
            'ding\\dong',
        ), 1);
        $internalClasses = $internalClassesRef->getValue();
        $this->assertSame(1, $internalClasses['classes']['foo\\bar']);
        $this->assertSame(1, $internalClasses['classes']['ding\\dong']);
        $e = null;
        try {
            SkipInternal::addInternalClass('foo\\bar', false);
        } catch (\InvalidArgumentException $e) {
            // meh
        }
        $this->assertInstanceOf('InvalidArgumentException', $e);
        $this->assertSame('level must be an integer. boolean provided.', $e->getMessage());
    }

    public function testRemoveInternalFrames()
    {
        SkipInternal::addInternalClass('bdk\\Test\\Backtrace\\Fixture\\SkipMe');

        $line = __LINE__ + 2;
        $closure = static function ($php) {
            eval($php);
        };
        $closure('
            $thing = new \bdk\Test\Backtrace\Fixture\SkipMe\Thing();
            $thing->a();
        ');
        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);
        $trace = SkipInternal::removeInternalFrames($trace);

        self::assertSame(array(
            'args' => array(),
            'evalLine' => 3,
            'file' => __FILE__,
            'function' => 'bdk\Test\Backtrace\Fixture\SkipMe\Thing->a',
            'line' => $line,
        ), \array_diff_key($trace[0], \array_flip(['object'])));
        self::assertInstanceOf('bdk\Test\Backtrace\Fixture\SkipMe\Thing', $trace[0]['object']);
    }

    public function testRemoveInternalFramesSubclass()
    {
        SkipInternal::addInternalClass('bdk\\Test\\Backtrace\\Fixture\\SkipMe\\Thing');

        $line = __LINE__ + 2;
        $closure = static function ($php) {
            eval($php);
        };
        $closure('
            $thing = new \bdk\Test\Backtrace\Fixture\Thing2();
            $thing->a();
        ');
        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);
        $trace = SkipInternal::removeInternalFrames($trace);

        self::assertSame(array(
            'args' => array(),
            'evalLine' => 3,
            'file' => __FILE__,
            'function' => 'bdk\Test\Backtrace\Fixture\Thing2->a',
            'line' => $line,
        ), \array_diff_key($trace[0], \array_flip(['object'])));
        self::assertInstanceOf('bdk\Test\Backtrace\Fixture\Thing2', $trace[0]['object']);
    }

    public function testRemoveInternalFramesAllInternal()
    {
        SkipInternal::addInternalClass('PHPUnit', 1);
        SkipInternal::addInternalClass('bdk\\Test\\Backtrace');

        $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace = Normalizer::normalize($trace);

        // github actions last frame looks like the below
        $trace[] = array(
            'args' => array(
                '/home/runner/work/Backtrace/Backtrace/vendor/phpunit/phpunit/phpunit',
            ),
            'evalLine' => null,
            'file' => '/home/runner/work/Backtrace/Backtrace/vendor/bin/phpunit',
            'function' => 'include',
            'line' => 122,
            'object' => null,
        );

        $trace = SkipInternal::removeInternalFrames($trace);

        self::assertSame(__CLASS__ . '->' . __FUNCTION__, $trace[0]['function']);
    }

    public function testIsSkippableMagic()
    {
        $magic = new \bdk\Test\Backtrace\Fixture\Magic();
        $magic->test();

        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);
        $trace = SkipInternal::removeInternalFrames($trace, 5);

        self::assertSame('bdk\Test\Backtrace\Fixture\Magic->__call', $trace[0]['function']);
    }

    public function testIsSkippableInvoke()
    {
        $magic = new \bdk\Test\Backtrace\Fixture\Magic();
        $refMethod = new \ReflectionMethod($magic, 'secret');
        $refMethod->setAccessible(true);
        $refMethod->invoke($magic);

        $trace = $GLOBALS['debug_backtrace'];
        $trace = Normalizer::normalize($trace);
        $trace = SkipInternal::removeInternalFrames($trace);

        self::assertSame('bdk\Test\Backtrace\Fixture\Magic->secret', $trace[0]['function']);
    }

    protected static function dumpTrace($label, $trace, $limit = 0)
    {
        if ($limit > 0) {
            $trace = \array_slice($trace, 0, $limit);
        }
        \bdk\Debug::varDump($label, \array_map(function ($frame) {
            \ksort($frame);
            unset($frame['args']);
            unset($frame['object']);
            return $frame;
        }, $trace));
    }
}
