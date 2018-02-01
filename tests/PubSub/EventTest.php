<?php

use bdk\PubSub\Event;

/**
 * PHPUnit tests for Debug class
 */
class EventTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var \bdk\PubSub\Event
     */
    protected $event;

    /**
     * This method is called before a test is executed.
     */
    public function setUp()
    {
        $this->event = new Event($this, array('foo'=>'bar'));
        $this->event->setValue('ding', 'dong');
        $this->event['mellow'] = 'yellow';
    }

    /**
     * This method is called after a test is executed.
     */
    public function tearDown()
    {
        $this->event = null;
    }

    public function testSubject()
    {
        $this->assertInstanceOf('EventTest', $this->event->getSubject());
    }

    public function testHasValue()
    {
        $this->assertTrue($this->event->hasValue('foo'));
        $this->assertFalse($this->event->hasValue('waldo'));
    }

    public function testValues()
    {
        $this->assertSame(array(
            'foo'=>'bar',
            'ding' => 'dong',
            'mellow' => 'yellow',
        ), $this->event->getValues());
        $this->assertSame('bar', $this->event->getValue('foo'));
        $this->assertSame('bar', $this->event['foo']);
        $this->event->setValues(array('pizza'=>'pie'));
        $this->assertSame(array(
            'pizza'=>'pie',
        ), $this->event->getValues());
        $this->assertFalse($this->event->hasValue('foo'));
    }

    public function testPropagationNotStopped()
    {
        $this->assertFalse($this->event->isPropagationStopped());
    }

    public function testStopPropagation()
    {
        $this->event->stopPropagation();
        $this->assertTrue($this->event->isPropagationStopped());
    }
}
