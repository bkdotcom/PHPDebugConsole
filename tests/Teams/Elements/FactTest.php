<?php

namespace bdk\Test\Teams\Elements;

use bdk\Teams\Elements\Fact;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Teams\Elements\Fact
 */
class FactTest extends TestCase
{
    public function testGetContent()
    {
        $fact = new Fact('Title me this', 'Value me that');
        self::assertSame(array(
            'title' => 'Title me this',
            'value' => 'Value me that',
        ), $fact->getContent(1.2));
    }
}
