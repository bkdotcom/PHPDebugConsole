<?php

namespace bdk\Test\I18n;

use bdk\I18n\NumberFormatter;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\I18n\NumberFormatter
 */
class NumberFormatterTest extends TestCase
{
    use ExpectExceptionTrait;

    public static function setUpBeforeClass(): void
    {
        $refProp = new \ReflectionProperty('bdk\\I18n\\NumberFormatter', 'localeconv');
        $refProp->setAccessible(true);
        $refProp->setValue(null, array(
            'fr_CA' => array(
                'currency_symbol' => '$',
                'frac_digits' => 2,
                'mon_decimal_point' => ',',
                'mon_thousands_sep' => ' ',
                'negative_sign' => '-',
                'n_cs_precedes' => 0,
                'n_sep_by_space' => 1,
                'n_sign_posn' => 2,
                'positive_sign' => '',
                'p_cs_precedes' => 0,
                'p_sep_by_space' => 1,
                'p_sign_posn' => 1,
            ),
            'te_0' => array(
                'currency_symbol' => '₹',
                'frac_digits' => 2,
                'mon_decimal_point' => '.',
                'mon_thousands_sep' => ',',
                'negative_sign' => '-',
                'n_cs_precedes' => 1,
                'n_sep_by_space' => 0,
                'n_sign_posn' => 0,
                'positive_sign' => '',
                'p_cs_precedes' => 1,
                'p_sep_by_space' => 0,
                'p_sign_posn' => 0,
            ),
            'te_2' => array(
                'currency_symbol' => '💩',
                'frac_digits' => 2,
                'mon_decimal_point' => '.',
                'mon_thousands_sep' => ',',
                'negative_sign' => '-',
                'n_cs_precedes' => 1,
                'n_sep_by_space' => 0,
                'n_sign_posn' => 2,
                'positive_sign' => '',
                'p_cs_precedes' => 1,
                'p_sep_by_space' => 0,
                'p_sign_posn' => 2,
            ),
            'te_3' => array(
                'currency_symbol' => '🐶',
                'frac_digits' => 2,
                'mon_decimal_point' => '.',
                'mon_thousands_sep' => ',',
                'negative_sign' => '-',
                'n_cs_precedes' => 1,
                'n_sep_by_space' => 0,
                'n_sign_posn' => 3,
                'positive_sign' => '',
                'p_cs_precedes' => 1,
                'p_sep_by_space' => 0,
                'p_sign_posn' => 3,
            ),
            'te_4' => array(
                'currency_symbol' => '😺',
                'frac_digits' => 2,
                'mon_decimal_point' => '.',
                'mon_thousands_sep' => ',',
                'negative_sign' => '-',
                'n_cs_precedes' => 1,
                'n_sep_by_space' => 0,
                'n_sign_posn' => 4,
                'positive_sign' => '',
                'p_cs_precedes' => 1,
                'p_sep_by_space' => 0,
                'p_sign_posn' => 4,
            ),
        ));
    }

    /**
     * @dataProvider providerFormat
     */
    public function testFormat($locale, $val, $style, $expect, $exception = false)
    {
        if ($exception) {
            $this->expectException('DomainException');
            // $this->expectExceptionMessage($exception);
        }

        $formatter = new NumberFormatter($locale);
        $result = $formatter->format($val, $style);
        self::assertSame($expect, $result);

        /*
        if ($errorMessage) {
            self::assertSame(-1, $formatter->getErrorCode());
            self::assertSame($errorMessage, $formatter->getErrorMessage());
        }
        */
    }

    public static function providerFormat()
    {
        $tests = [
            'currency' => [
                'en_US',
                12345.1234,
                'currency',
                '$12,345.12',
            ],

            'currency.negative' => [
                'en_US',
                -12345.1234,
                'currency',
                '-$12,345.12',
            ],

            'currency.negative.fr_CA' => [
                'fr_CA',
                -12345.1234,
                'currency',
                '12 345,12 $-',
            ],

            'currency.te_0' => [
                'te_0',
                -12345.1234,
                'currency',
                '(₹12,345.12)',
            ],

            'currency.te_2' => [
                'te_2',
                -12345.1234,
                'currency',
                '💩12,345.12-',
            ],

            'currency.te_3' => [
                'te_3',
                -12345.1234,
                'currency',
                '-🐶12,345.12',
            ],

            'currency.te_4' => [
                'te_4',
                -12345.1234,
                'currency',
                '😺-12,345.12',
            ],

            'default' => [
                'en_US',
                12345.1234,
                'default',
                '12,345.1234',
            ],

            'exception' => [
                'en_US',
                'not a number',
                'default',
                null,
                true,
            ],

            'integer' => [
                'en_US',
                41.56,
                'integer',
                '42',
            ],

            'integer.noLocaleConv' => [
                'xx_xx',
                3.14,
                null,
                '3.14',
            ],

            'percent' => [
                'en_US',
                0.42,
                'percent',
                '42%',
            ],
        ];

        return \array_map(static function ($test) {
            return \array_replace(array(
                'en_US',
                1.23,
                'default',
                1.23,
            ), $test);
        }, $tests);
    }
}
