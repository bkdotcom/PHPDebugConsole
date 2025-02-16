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
        self::assertSame($countPre + 1, \count(\spl_autoload_functions()));
    }

    public function testUnregsiter()
    {
        $countPre = \count(\spl_autoload_functions());
        static::$autoloader->unregister();
        self::assertSame($countPre - 1, \count(\spl_autoload_functions()));
    }

    public function testAddClass()
    {
        $return = static::$autoloader->addClass('bdk\\Test\\Debug\\Fixture\\TestObj', __DIR__ . '/Fixture/TestObj.php');
        self::assertSame(static::$autoloader, $return);
    }

    public function testAddPsr4()
    {
        $return = static::$autoloader->addPsr4('bdk\\Test\\Debug\\Fixture\\', __DIR__ . '/Fixture');
        self::assertSame(static::$autoloader, $return);
    }

    public function testAutoloadClassMap()
    {
        $classMap = \bdk\Debug\Utility\Reflection::propGet(static::$autoloader, 'classMap');
        $autoloadMethod = new \ReflectionMethod(static::$autoloader, 'autoload');
        $autoloadMethod->setAccessible(true);
        $findClassMethod = new \ReflectionMethod(static::$autoloader, 'findClass');
        $findClassMethod->setAccessible(true);
        foreach (\array_keys($classMap) as $class) {
            self::assertNotFalse($findClassMethod->invoke(static::$autoloader, $class));
            if (\class_exists($class, false)) {
                continue;
            }
            $autoloadMethod->invoke(static::$autoloader, $class);
            self::assertTrue(\class_exists($class, false));
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
        $findClassMethod = new \ReflectionMethod(static::$autoloader, 'findClass');
        $findClassMethod->setAccessible(true);
        foreach ($classes as $class) {
            self::assertNotFalse($findClassMethod->invoke(static::$autoloader, $class));
            if (\class_exists($class, false)) {
                continue;
            }
            $autoloadMethod->invoke(static::$autoloader, $class);
            self::assertTrue(\class_exists($class, false));
        }
    }

    public function testAutoloadNotFound()
    {
        $autoloadMethod = new \ReflectionMethod(static::$autoloader, 'autoload');
        $autoloadMethod->setAccessible(true);
        $class = 'ding\\dang';
        $autoloadMethod->invoke(static::$autoloader, $class);
        self::assertFalse(\class_exists($class, false));
    }
}
