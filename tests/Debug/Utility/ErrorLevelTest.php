<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug\Utility\ErrorLevel;
use PHPUnit\Framework\TestCase;

/**
 * Test ErrorLevel
 *
 * @covers \bdk\Debug\Utility\ErrorLevel
 */
class ErrorLevelTest extends TestCase
{
    const E_ALL_54 = 32767;
    const E_ALL_53 = 30719; // doesn't include E_STRICT
    const E_ALL_52 = 6143;  // doesn't include E_STRICT
    const E_ALL_51 = 2047;  // doesn't include E_STRICT

    /**
     * Test toConstantString
     *
     * @param int|null    $input          constant bitmask
     * @param string|null $phpVer         php version being tested
     * @param bool        $explicitStrict whether string should be explicitly included
     * @param string      $expect         expected string
     *
     * @return void
     *
     * @dataProvider providerErrorLevel
     */
    public function testToConstantString($input, $phpVer, $explicitStrict, $expect)
    {
        self::assertSame($expect, ErrorLevel::toConstantString($input, $phpVer, $explicitStrict));
    }

    /**
     * Provide inputs / expected output
     *
     * @return array[]
     */
    public static function providerErrorLevel()
    {
        return array(
            /*
                Test "current" error_reporting
            */
            array(null, null, null, ErrorLevel::toConstantString(\error_reporting())),

            /*
                PHP >= 5.4
            */
            array(0, '5.4', null, '0'),
            array(self::E_ALL_54, '5.4', null, 'E_ALL | E_STRICT'),
            array(self::E_ALL_54, '5.4', false, 'E_ALL'),
            array(self::E_ALL_54 & ~E_STRICT, '5.4', null, 'E_ALL & ~E_STRICT'),
            array(self::E_ALL_54 & ~E_STRICT, '5.4', false, 'E_ALL & ~E_STRICT'),
            array(self::E_ALL_54 | E_STRICT, '5.4', null, 'E_ALL | E_STRICT'),
            array(self::E_ALL_54 | E_STRICT, '5.4', false, 'E_ALL'),
            array(E_ERROR | E_WARNING | E_NOTICE, '5.4', null, 'E_ERROR | E_WARNING | E_NOTICE'),
            // note that we don't specify E_STRICT
            array(self::E_ALL_54 & ~E_DEPRECATED, '5.4', null, '( E_ALL | E_STRICT ) & ~E_DEPRECATED'),
            array(self::E_ALL_54 & ~E_DEPRECATED, '5.4', false, 'E_ALL & ~E_DEPRECATED'),

            /*
                PHP 5.3
            */
            array(0, '5.3', null, '0'),
            array(self::E_ALL_53, '5.3', null, 'E_ALL & ~E_STRICT'),
            array(self::E_ALL_53, '5.3', false, 'E_ALL'),
            array(self::E_ALL_53 & ~E_STRICT, '5.3', null, 'E_ALL & ~E_STRICT'),
            array(self::E_ALL_53 & ~E_STRICT, '5.3', false, 'E_ALL'),
            array(self::E_ALL_53 | E_STRICT, '5.3', null, 'E_ALL | E_STRICT'),
            array(self::E_ALL_53 | E_STRICT, '5.3', false, 'E_ALL | E_STRICT'),
            array(E_ERROR | E_WARNING | E_NOTICE, '5.3', null, 'E_ERROR | E_WARNING | E_NOTICE'),
            // note that we don't specify E_STRICT
            array(self::E_ALL_53 & ~E_DEPRECATED, '5.3', null, 'E_ALL & ~E_STRICT & ~E_DEPRECATED'),
            array(self::E_ALL_53 & ~E_DEPRECATED, '5.3', false, 'E_ALL & ~E_DEPRECATED'),

            /*
                PHP 5.2
            */
            array(self::E_ALL_52, '5.2', null, 'E_ALL & ~E_STRICT'),
            array(self::E_ALL_52 | E_STRICT, '5.2', null, 'E_ALL | E_STRICT'),
            array(self::E_ALL_52 | E_STRICT, '5.2', false, 'E_ALL | E_STRICT'),

            /*
                PHP <= 5.1
            */
            array(self::E_ALL_51, '5.1', null, 'E_ALL & ~E_STRICT'),
            array(self::E_ALL_51 | E_STRICT, '5.1', null, 'E_ALL | E_STRICT'),
            array(self::E_ALL_51 | E_STRICT, '5.1', false, 'E_ALL | E_STRICT'),
        );
    }
}
