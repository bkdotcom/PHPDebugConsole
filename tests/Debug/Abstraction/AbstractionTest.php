<?php

namespace bdk\Test\Container;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Debug\Abstraction\Abstraction
 */
class AbstractionTest extends TestCase
{
    public function testConstruct()
    {
        $abs = new Abstraction('myType');
        $this->assertInstanceOf('\bdk\PubSub\Event', $abs);
        $this->assertSame('myType', $abs['type']);
        $abs = new Abstraction('myType', array('foo' => 'bar'));
        $this->assertSame('myType', $abs->getValue('type'));
        $this->assertSame('bar', $abs->getValue('foo'));
    }

    public function testToString()
    {
        $abs = new Abstraction('myType', array('foo' => 'bar'));
        $this->assertSame('', (string) $abs);
        $abs['value'] = 'someVal';
        $this->assertSame('someVal', (string) $abs);

        $abs = new Abstraction(Abstracter::TYPE_OBJECT, array(
            'className' => 'myNamespace\myClass',
            'stringified' => null,
        ));
        $this->assertSame('myNamespace\myClass', (string) $abs);
        $abs['methods'] = array(
            '__toString' => array(
                'returnValue' => '__toString return val',
            ),
        );
        $this->assertSame('__toString return val', (string) $abs);

        $abs['stringified'] = 'stringified val';
        $this->assertSame('stringified val', (string) $abs);
    }

    public function testUnserialize()
    {
        $abs = \unserialize('O:33:"bdk\Debug\Abstraction\Abstraction":2:{s:3:"foo";s:3:"bar";s:4:"type";s:6:"myType";}');
        $this->assertInstanceOf('bdk\Debug\Abstraction\Abstraction', $abs);
        $this->assertSame(array(
            'foo' => 'bar',
            'type' => 'myType',
        ), $abs->getValues());
    }

    public function testSetSubject()
    {
        $abs = new Abstraction('myType');
        $abs->setSubject($this);
        $this->assertSame($this, $abs->getSubject());
    }

    public function testJsonSerialize()
    {
        $abs = new Abstraction('myType', array('foo' => 'bar'));
        $json = \json_encode($abs);
        $this->assertSame('{"foo":"bar","type":"myType","debug":"\u0000debug\u0000"}', $json);
    }

    public function testSerialize()
    {
        $abs = new Abstraction('myType', array('foo' => 'bar'));
        $serialized = \serialize($abs);
        $this->assertSame('O:33:"bdk\Debug\Abstraction\Abstraction":2:{s:3:"foo";s:3:"bar";s:4:"type";s:6:"myType";}', $serialized);
    }

    public function testOnSet()
    {
        $abs = new Abstraction('myType', array(
            'attribs' => array(),
        ));
        $this->assertSame(array(
            'class' => array(),
        ), $abs['attribs']);

        $abs['attribs'] = array('class' => 'foo bar');
        $this->assertSame(array(
            'class' => array('foo', 'bar'),
        ), $abs['attribs']);
    }
}
