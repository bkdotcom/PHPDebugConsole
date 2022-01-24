<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\ErrorLevel;

/**
 * Test ErrorLevel
 */
class ErrorLevelTest extends \PHPUnit\Framework\TestCase
{
    const E_ALL_54 = 32767;
    const E_ALL_53 = 30719; // doesn't include E_STRICT
    const E_ALL_52 = 6143;  // doesn't include E_STRICT
    const E_ALL_51 = 2047;  // doesn't include E_STRICT

    /**
     * Test
     *
     * @return void
     */
    public function testErrorLevel()
    {
        /*
            PHP >= 5.4
        */
        $this->assertSame('0', ErrorLevel::toConstantString(0, '5.4'));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_54, '5.4'));
        $this->assertSame('E_ALL', ErrorLevel::toConstantString(self::E_ALL_54, '5.4', false));
        $this->assertSame('E_ALL & ~E_STRICT', ErrorLevel::toConstantString(self::E_ALL_54 & ~E_STRICT, '5.4'));
        $this->assertSame('E_ALL & ~E_STRICT', ErrorLevel::toConstantString(self::E_ALL_54 & ~E_STRICT, '5.4', false));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_54 | E_STRICT, '5.4'));
        $this->assertSame('E_ALL', ErrorLevel::toConstantString(self::E_ALL_54 | E_STRICT, '5.4', false));
        $this->assertSame('E_ERROR | E_WARNING | E_NOTICE', ErrorLevel::toConstantString(E_ERROR | E_WARNING | E_NOTICE, '5.4'));
        // note that we don't specify E_STRICT
        $this->assertSame('( E_ALL | E_STRICT ) & ~E_DEPRECATED', ErrorLevel::toConstantString(self::E_ALL_54 & ~E_DEPRECATED, '5.4'));
        $this->assertSame('E_ALL & ~E_DEPRECATED', ErrorLevel::toConstantString(self::E_ALL_54 & ~E_DEPRECATED, '5.4', false));

        /*
            PHP 5.3
        */
        $this->assertSame('0', ErrorLevel::toConstantString(0, '5.3'));
        $this->assertSame('E_ALL & ~E_STRICT', ErrorLevel::toConstantString(self::E_ALL_53, '5.3'));
        $this->assertSame('E_ALL', ErrorLevel::toConstantString(self::E_ALL_53, '5.3', false));
        $this->assertSame('E_ALL & ~E_STRICT', ErrorLevel::toConstantString(self::E_ALL_53 & ~E_STRICT, '5.3'));
        $this->assertSame('E_ALL', ErrorLevel::toConstantString(self::E_ALL_53 & ~E_STRICT, '5.3', false));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_53 | E_STRICT, '5.3'));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_53 | E_STRICT, '5.3', false));
        $this->assertSame('E_ERROR | E_WARNING | E_NOTICE', ErrorLevel::toConstantString(E_ERROR | E_WARNING | E_NOTICE, '5.3'));
        // note that we don't specify E_STRICT
        $this->assertSame('E_ALL & ~E_STRICT & ~E_DEPRECATED', ErrorLevel::toConstantString(self::E_ALL_53 & ~E_DEPRECATED, '5.3'));
        $this->assertSame('E_ALL & ~E_DEPRECATED', ErrorLevel::toConstantString(self::E_ALL_53 & ~E_DEPRECATED, '5.3', false));

        /*
            PHP 5.2
        */
        $this->assertSame('E_ALL & ~E_STRICT', ErrorLevel::toConstantString(self::E_ALL_52, '5.2'));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_52 | E_STRICT, '5.2'));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_52 | E_STRICT, '5.2', false));

        /*
            PHP <= 5.1
        */
        $this->assertSame('E_ALL & ~E_STRICT', ErrorLevel::toConstantString(self::E_ALL_51, '5.1'));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_51 | E_STRICT, '5.1'));
        $this->assertSame('E_ALL | E_STRICT', ErrorLevel::toConstantString(self::E_ALL_51 | E_STRICT, '5.1', false));
    }
}
