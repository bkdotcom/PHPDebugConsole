<?php

namespace bdk\Test\Debug;

use bdk\Debug\Autoloader;

/**
 * @covers \bdk\Debug\Autoloader
 */
class AutoloaderTest extends DebugTestFramework
{
    protected static $autoloader;

    public static function setUpBeforeClass(): void
    {
        static::$autoloader = new Autoloader();
    }

    public function testRegister()
    {
        $countPre = \count(\spl_autoload_functions());
        static::$autoloader->register();
        $this->assertSame($countPre + 1, \count(\spl_autoload_functions()));
    }

    public function testUnregsiter()
    {
        $countPre = \count(\spl_autoload_functions());
        static::$autoloader->unregister();
        $this->assertSame($countPre - 1, \count(\spl_autoload_functions()));
    }

    public function testAutoloadClassMap()
    {
        $classMap = $this->helper->getProp(static::$autoloader, 'classMap');
        $autoloadMethod = new \ReflectionMethod(static::$autoloader, 'autoload');
        $autoloadMethod->setAccessible(true);
        foreach (\array_keys($classMap) as $class) {
            if (\class_exists($class, false)) {
                continue;
            }
            $autoloadMethod->invoke(static::$autoloader, $class);
            $this->assertTrue(\class_exists($class, false));
        }
    }

    public function testAutloadPsr4()
    {
        $classes = array(
            'bdk\\Backtrace\\SkipInternal',
            'bdk\\ErrorHandler\\Error',
        );
        $autoloadMethod = new \ReflectionMethod(static::$autoloader, 'autoload');
        $autoloadMethod->setAccessible(true);
        foreach ($classes as $class) {
            if (\class_exists($class, false)) {
                continue;
            }
            $autoloadMethod->invoke(static::$autoloader, $class);
            $this->assertTrue(\class_exists($class, false));
        }
    }

    public function testAutoloadNotFound()
    {
        $autoloadMethod = new \ReflectionMethod(static::$autoloader, 'autoload');
        $autoloadMethod->setAccessible(true);
        $class = 'ding\\dang';
        $autoloadMethod->invoke(static::$autoloader, $class);
        $this->assertFalse(\class_exists($class, false));
    }
}
