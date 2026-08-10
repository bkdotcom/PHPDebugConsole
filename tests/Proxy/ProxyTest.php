<?php

namespace bdk\Test\Proxy;

use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use bdk\Proxy\Manager;
use bdk\Test\Proxy\Fixture\Listener;
use bdk\Test\Proxy\Fixture\Widget;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Proxy\ProxyTrait
 */
class ProxyTest extends TestCase
{
    use ExpectExceptionTrait;

    public function testGetListener(): void
    {
        $proxy = $this->getWidgetProxy();
        $listener = new Listener();
        $proxy->setListener($listener);
        $this->assertSame($listener, $proxy->getListener());
    }

    public function testGetSubject(): void
    {
        $proxy = $this->getWidgetProxy();
        $subject = $proxy->getSubject();
        $this->assertInstanceOf('bdk\\Test\\Proxy\\Fixture\\Widget', $subject);
    }

    public function testSetListener(): void
    {
        $proxy = $this->getWidgetProxy();
        \bdk\Debug\Utility\Reflection::propSet($proxy, 'listenerInstance', null);
        $newListener = new Listener();
        $proxy->setListener($newListener);
        $this->assertSame($newListener, $proxy->getListener());
        $this->assertSame($newListener, \bdk\Debug\Utility\Reflection::propGet($proxy, 'listenerInstance'));
    }

    public function testSetListenerNull(): void
    {
        $proxy = $this->getWidgetProxy();
        $newListener = new Listener();
        $proxy->setListener($newListener);
        $proxy->setListener(null);
        $this->assertNull($proxy->getListener());
    }

    public function testSetListenerException(): void
    {
        $this->expectException('InvalidArgumentException');
        $proxy = $this->getWidgetProxy();
        $proxy->setListener('foo');
    }

    public function testSetSubject(): void
    {
        $proxy = $this->getWidgetProxy();
        $newSubject = new Widget();
        $proxy->setSubject($newSubject);
        $this->assertSame($newSubject, $proxy->getSubject());
    }

    public function testCall()
    {
        $proxy = $this->getWidgetProxy();
        $result = $proxy->test('test');
        $this->assertSame('"test"', $result);
    }

    public function testCallGetThis()
    {
        $proxy = $this->getWidgetProxy();
        $this->assertSame($proxy, $proxy->getInstance());
    }

    public function testException()
    {
        $proxy = $this->getWidgetProxy();
        $listener = new Listener();
        $proxy->setListener($listener);
        $exception = null;
        try {
            $proxy->broken('test');
        } catch (\Exception $exception) {
            // ignore
        }
        $this->assertInstanceOf('RuntimeException', $exception);
        $this->assertStringMatchesFormat(
            \strtr(\json_encode([
                array(
                    'event' => 'init',
                    'proxy' => 'bdk_Test_Proxy_Fixture_WidgetProxy',
                    'subject' => 'bdk\Test\Proxy\Fixture\Widget',
                ),
                array(
                    'arguments' => ['test'],  // broken doesn't have any arguments, but func_get_args() is used
                    'exception' => $exception,
                    'initValues' => array(
                        'memoryStart' => '%d',
                        'timeStart' => '%f',
                    ),
                    'method' => 'broken',
                    'result' => null,
                ),
            ], JSON_PRETTY_PRINT), array('"%d"' => '%d', '"%f"' => '%f')),
            \json_encode($listener->getLog(), JSON_PRETTY_PRINT)
        );
    }

    public function testCallStatic()
    {
        $proxy = $this->getWidgetProxy();
        $result = $proxy->factory();
        $this->assertInstanceOf('bdk\Test\Proxy\Fixture\Widget', $result);
    }

    protected function getWidgetProxy()
    {
        $manager = new Manager();
        $widget = new Widget();
        return $manager->buildFromSubject($widget);
    }
}
