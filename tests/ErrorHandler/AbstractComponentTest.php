<?php

namespace bdk\Test\ErrorHandler;

use bdk\Test\ErrorHandler\Fixture\ExtendsComponent;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\ErrorHandler\AbstractComponent
 */
class AbstractComponentTest extends TestCase
{
    protected $obj = null;

    public function setUp(): void
    {
        $this->obj = new ExtendsComponent();
    }

    public function testGetReadOnly()
    {
        self::assertSame('bar', $this->obj->foo);
    }

    public function testGetUnavail()
    {
        self::assertNull($this->obj->baz);
    }

    public function testGetCfgViaArray()
    {
        self::assertSame(true, $this->obj->getCfg(array('doMagic')));
    }

    public function testGetCfgEmptyKey()
    {
        self::assertSame(array(
            'doMagic' => true,
        ), $this->obj->getCfg());
    }

    public function testGetCfgUndefined()
    {
        self::assertNull($this->obj->getCfg('what'));
    }
}
