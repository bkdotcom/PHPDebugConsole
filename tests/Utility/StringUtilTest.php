<?php

namespace bdk\DebugTests\Utility;

use bdk\Debug\Utility\StringUtil;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Utility class
 */
class StringUtilTest extends TestCase
{

    /**
     * Test
     *
     * @return void
     */
    public function testIsBase64Encoded()
    {
        $base64Str = \base64_encode(\chunk_split(\str_repeat('zippity do dah', 50)));
        $this->assertTrue(StringUtil::isBase64Encoded($base64Str));

        $this->assertFalse(StringUtil::isBase64Encoded('I\'m just a bill.'));
        $this->assertFalse(StringUtil::isBase64Encoded('onRenderComplete'));
        $this->assertFalse(StringUtil::isBase64Encoded('/Users/jblow/not/base64/'));
    }
}
