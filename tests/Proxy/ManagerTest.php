<?php

namespace bdk\Test\Proxy;

use bdk\Cache\FileSystem as FileSystemCache;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Proxy\Manager;
use bdk\Test\Proxy\Fixture\Listener;
use bdk\Test\Proxy\Fixture\VariadicAndReference;
use bdk\Test\Proxy\Fixture\Widget;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Proxy\Manager
 */
class ManagerTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    private static $cache;
    private static $manager;

    public static function setUpBeforeClass(): void
    {
        self::$cache = new FileSystemCache();
        self::$cache->clear();
        self::$manager = new Manager(self::$cache);
    }

    public static function tearDownAfterClass(): void
    {
        self::$cache->clear();
    }

    /*
    public function testCreateProxyFromInterface(): void
    {
        $manager = $this->getManager();

        $graph = new Graph();
        $object = $manager->createProxy($graph);
        $this->assertIsObject($object);
        $this->assertSame('bdk_Test_Proxy_Fixture_GraphInterfaceProxy', \get_class($object));

        $this->assertSame(2, $object->nodesCount(1));
        $this->assertNull($object->getError());
        $this->assertFalse($object->hasError());
        $this->assertSame('Log', $object->getLog());

        $this->expectException(Error::class);
        $message = 'Call to undefined method bdk_Test_Proxy_Fixture_GraphInterfaceProxy::edgesCount()';
        $this->expectExceptionMessage($message);
        $object->edgesCount();
    }
    */

    public function testBuildFromClassNameException(): void
    {
        $manager = $this->getManager();
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Class name must be a string');
        $manager->buildFromClassName($manager);
    }

    public function testBuildFromSubjectException(): void
    {
        $manager = $this->getManager();
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Subject must be an object');
        $manager->buildFromSubject('not an object');
    }

    public function testCreateProxyWithoutCache(): void
    {
        $manager = new Manager();

        $widget = new Widget();
        $object = $manager->buildFromSubject($widget);

        $this->assertIsObject($object);
        $this->assertInstanceOf('bdk\\Test\\Proxy\\Fixture\\Widget', $object);
        $this->assertSame('"best"', $object->test('best'));
    }

    public function testBasic(): void
    {
        $manager = $this->getManager();
        $listener = new Listener();

        $widget = new Widget();
        $widgetProxy = $manager->buildFromSubject($widget)
            ->setListener($listener);
        $this->assertSame($widget, $widgetProxy->getSubject());
        $this->assertSame('bdk_Test_Proxy_Fixture_WidgetProxy', \get_class($widgetProxy));

        $widgetProxy->foo = 'bar';
        $this->assertSame('default', $widgetProxy->value);
        $this->assertSame('bar', $widgetProxy->foo);
        $this->assertSame('"besty"', $widgetProxy->test('besty'));
        $this->assertStringMatchesFormat(\strtr(\json_encode([
            array(
                'event' => 'init',
                'proxy' => 'bdk_Test_Proxy_Fixture_WidgetProxy',
                'subject' => 'bdk\\Test\\Proxy\\Fixture\\Widget',
            ),
            array(
                'arguments' => ['foo', 'bar'],
                'exception' => null,
                'initValues' => [
                    'memoryStart' => '%d',
                    'timeStart' => '%f',
                ],
                'method' => '__set',
                'result' => null,
            ),
            array(
                'arguments' => ['foo'],
                'exception' => null,
                'initValues' => [
                    'memoryStart' => '%d',
                    'timeStart' => '%f',
                ],
                'method' => '__get',
                'result' => 'bar',
            ),
            array(
                'arguments' => ['besty'],
                'exception' => null,
                'initValues' => [
                    'memoryStart' => '%d',
                    'timeStart' => '%f',
                ],
                'method' => 'test',
                'result' => '"besty"',
            ),
        ], JSON_PRETTY_PRINT), array('"%d"' => '%d', '"%f"' => '%f')), \json_encode($listener->getLog(), JSON_PRETTY_PRINT));
    }

    /**
     * @requires PHP >= 5.6
     */
    public function testVariadicAndReferenceParameters(): void
    {
        if (PHP_VERSION_ID < 50600) {
            $this->markTestSkipped('Variadic params require PHP 5.6');
        }

        $manager = $this->getManager();
        $object = new VariadicAndReference();
        $objectProxy = $manager->buildFromSubject($object);

        $val = null;
        $objectProxy->byRef($val);
        $this->assertSame('modified null', $val);

        $return = $objectProxy->variadic('foo', 'bar');
        $this->assertSame(['foo', 'bar'], $return);
        // $this->assertSame([], [$a, $b]);

        $a = 'foo';
        $b = 'bar';
        $return = $objectProxy->variadicByRef($a, $b);
        $this->assertSame(['modified "foo"', 'modified "bar"'], $return);
    }

    public function testMethodReturningInstanceOfSameType(): void
    {
        $manager = $this->getManager();

        $widget = new Widget();
        $widgetProxy = $manager->buildFromSubject($widget);

        $this->assertSame($widget, $widgetProxy->getSubject()); // same instance

        $this->assertInstanceOf(\get_class($widgetProxy), $widgetProxy->factory());
        $this->assertNotSame($widgetProxy, $widgetProxy->factory()); // not same instance
    }

    public function testMethodThrowingException(): void
    {
        $manager = $this->getManager();

        $widget = new Widget();
        $object = $manager->buildFromSubject($widget);
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Not working currently.');
        $object->broken();
    }

    public function testCreateProxyFromClass(): void
    {
        $manager = $this->getManager();
        $listener = new Listener();

        $widget = new Widget();
        $object = $manager->buildFromClassName(\get_class($widget))
            ->setSubject($widget)
            ->setListener($listener);

        $this->assertSame('1', $object->test(1));
        $this->assertSame('false', $object->test(false));
        $this->assertStringMatchesFormat(\strtr(\json_encode([
            array(
                'event' => 'init',
                'proxy' => 'bdk_Test_Proxy_Fixture_WidgetProxy',
                'subject' => 'bdk\\Test\\Proxy\\Fixture\\Widget',
            ),
            array(
                'arguments' => [1],
                // 'duration' => '%f',
                'exception' => null,
                'initValues' => [
                    'memoryStart' => '%d',
                    'timeStart' => '%f',
                ],
                'method' => 'test',
                'result' => '1',
                // 'timeStart' => '%f',
            ),
            array(
                'arguments' => [false],
                // 'duration' => '%f',
                'exception' => null,
                'initValues' => [
                    'memoryStart' => '%d',
                    'timeStart' => '%f',
                ],
                'method' => 'test',
                'result' => 'false',
                // 'timeStart' => '%f',
            ),
        ], JSON_PRETTY_PRINT), array('"%d"' => '%d', '"%f"' => '%f')), \json_encode($listener->getLog(), JSON_PRETTY_PRINT));
    }

    /**
     * Get new Manager instance
     *
     * @return Manager
     */
    protected function getManager()
    {
        return self::$manager;
    }
}
