<?php

namespace bdk\Test\PubSub;

use bdk\PubSub\Event;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for PubSub\Event
 *
 * @coversDefaultClass \bdk\PubSub\Event
 * @uses               \bdk\PubSub\Event
 */
class EventTest extends TestCase
{
    /**
     * @var \bdk\PubSub\Event
     */
    protected $event;

    /**
     * This method is called before a test is executed.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->event = new Event($this, array('foo' => 'bar'));
        $this->event->setValue('ding', 'dong');
        $this->event['mellow'] = 'yellow';
    }

    /**
     * This method is called after a test is executed.
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->event = null;
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        self::assertSame($this, $this->event->getSubject());
        self::assertSame(array(
            'ding' => 'dong',
            'foo' => 'bar',
            'mellow' => 'yellow',
        ), $this->event->getValues());
    }

    /**
     * @covers ::__debugInfo
     */
    public function testDebugInfo()
    {
        /*
        $callDirect = false;
        $haveXdebug = \extension_loaded('xdebug');
        if (PHP_VERSION_ID < 50600) {
            $callDirect = true;
        }
        if ($haveXdebug) {
            $xdebugVer = \phpversion('xdebug');
            if (\version_compare($xdebugVer, '3.0.0', '<')) {
                // xDebug < 3.0.0 ignores __debugInfo
                $callDirect = true;
            }
        }
        */

        self::assertEquals(array(
            'propagationStopped' => false,
            'subject' => __CLASS__,
            'values' => array(
                'foo' => 'bar',
                'ding' => 'dong',
                'mellow' => 'yellow',
            ),
        ), $this->event->__debugInfo());

        self::assertEquals(array(
            'propagationStopped' => false,
            'subject' => 'beans',
            'values' => array(
                'foo' => 'bar',
            ),
        ), (new Event('beans', array('foo' => 'bar')))->__debugInfo());
    }

    /**
     * @covers ::getSubject
     */
    public function testGetSubject()
    {
        self::assertInstanceOf(__CLASS__, $this->event->getSubject());
    }

    /**
     * @covers ::getValue
     */
    public function testGetValue()
    {
        self::assertSame('bar', $this->event->getValue('foo'));
        self::assertSame(null, $this->event->getValue('undefined'));
    }

    /**
     * @covers ::setValues
     * @covers ::getValues
     */
    public function testGetSetValues()
    {
        self::assertSame(array(
            'ding' => 'dong',
            'foo' => 'bar',
            'mellow' => 'yellow',
        ), $this->event->getValues());
        self::assertSame('bar', $this->event->getValue('foo'));
        self::assertSame('bar', $this->event['foo']);
        $this->event->setValues(array('pizza' => 'pie'));
        self::assertSame(array(
            'pizza' => 'pie',
        ), $this->event->getValues());
        self::assertFalse($this->event->hasValue('foo'));
    }

    /**
     * @covers ::hasValue
     */
    public function testHasValue()
    {
        self::assertTrue($this->event->hasValue('foo'));
        self::assertFalse($this->event->hasValue('waldo'));
    }

    /**
     * @covers ::offsetExists
     */
    public function testOffsetExists()
    {
        self::assertSame(true, isset($this->event['foo']));
        self::assertSame(false, isset($this->event['undefined']));
    }

    /**
     * @covers ::offsetGet
     */
    public function testOffsetGet()
    {
        self::assertSame('bar', $this->event['foo']);
        self::assertSame(null, $this->event['undefined']);
    }

    /**
     * @covers ::offsetSet
     * @covers ::setValue
     * @covers ::onSet
     */
    public function testOffsetSet()
    {
        self::assertSame('yellow', $this->event->getValue('mellow'));
    }

    /**
     * @covers ::offsetUnset
     */
    public function testOffsetUnset()
    {
        unset($this->event['foo'], $this->event['undefined']);
        self::assertSame(array(
            'ding' => 'dong',
            'mellow' => 'yellow',
        ), $this->event->getValues());
    }

    /**
     * @covers ::getIterator
     */
    public function testGetIterator()
    {
        $vals = array();
        foreach ($this->event as $k => $v) {
            $vals[$k] = $v;
        }
        self::assertSame(array(
            'ding' => 'dong',
            'foo' => 'bar',
            'mellow' => 'yellow',
        ), $vals);
    }

    /**
     * @covers ::isPropagationStopped
     */
    public function testIsPropagationStoppedFalse()
    {
        self::assertFalse($this->event->isPropagationStopped());
    }

    /**
     * @covers ::stopPropagation
     * @covers ::isPropagationStopped
     */
    public function testStopPropagation()
    {
        $this->event->stopPropagation();
        self::assertTrue($this->event->isPropagationStopped());
    }
}
