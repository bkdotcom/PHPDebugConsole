<?php

namespace bdk\I18n;

use DomainException;

/**
 * Format a number
 *
 * This is not intended to be a polyfill for PHP's NumberFormatter class
 */
class NumberFormatter
{
    /** @var string */
    private $locale;

    /** @var array locale to localeconv info */
    private static $localeconv = array();

    /** @var array default localeconv info */
    private static $localeConvDefault = array(
        'currency_symbol' => '$',
        'decimal_point' => '.',
        'frac_digits' => 2,
        'grouping' => [3,3],
        'int_curr_symbol' => 'USD ',
        'int_frac_digits' => 2,
        'mon_decimal_point' => '.',
        'mon_grouping' => [3,3],
        'mon_thousands_sep' => ',',
        'negative_sign' => '-',
        'n_cs_precedes' => 1,
        'n_sep_by_space' => 0,
        'n_sign_posn' => 1,
        'positive_sign' => '',
        'p_cs_precedes' => 1,
        'p_sep_by_space' => 0,
        'p_sign_posn' => 1,
        'thousands_sep' => ',',
    );

    /**
     * Constructor
     *
     * @param string $locale Locale to use when formatting
     */
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Format number
     *
     * @param numeric     $value numeric value
     * @param string|null $style currency, integer, percent
     *
     * @return string
     *
     * @throws DomainException
     */
    public function format($value, $style = 'default')
    {
        if (!\is_numeric($value)) {
            throw new DomainException('Non numeric value.');
        }

        if ($style === 'currency') {
            return $this->formatCurrency($value);
        }

        if ($style === 'percent') {
            $value = $value * 100;
            return $value . '%';
        }

        $decPos = \strpos($value, '.');
        $localeInfo = $this->localeconv();
        return \number_format(
            $value,
            $style === 'integer' || $decPos === false ? 0 : \strlen($value) - 1 - $decPos,
            $localeInfo['decimal_point'],
            $localeInfo['thousands_sep']
        );
    }

    /**
     * Format number as currency
     *
     * @param int|float $value Numeric value
     *
     * @return string
     */
    public function formatCurrency($value)
    {
        $info = $this->currencyLocaleInfo($value);

        $formattedAmt = $info['formatted'];

        // default/start with symbol after amount
        $formatted = $formattedAmt . $info['separator'] . $info['symbol'];
        if ($info['csPrecedes']) {
            // currency symbol in front
            $currencySymbol = $info['symbol'];
            if ($info['signPosN'] === 3) {
                // The sign string immediately precedes the currency_symbol
                $currencySymbol = $info['sign'] . $currencySymbol;
            } elseif ($info['signPosN'] === 4) {
                // The sign string immediately succeeds the currency_symbol
                $currencySymbol = $currencySymbol . $info['sign'];
            }
            $formatted = $currencySymbol . $info['separator'] . $formattedAmt;
        }
        switch ($info['signPosN']) {
            case 0:
                // Parentheses surround the quantity and currency_symbol
                return '(' . $formatted . ')';
            case 1:
                // The sign string precedes the quantity and currency_symbol
                return $info['sign'] . $formatted;
            case 2:
                // The sign string succeeds the quantity and currency_symbol
                return $formatted . $info['sign'];
        }
        return $formatted;
    }

    /**
     * Get currency formatting info for value
     *
     * @param numeric $value Currency amount
     *
     * @return array
     */
    private function currencyLocaleInfo($value)
    {
        $localeInfo = $this->localeconv();

        $formatted = \number_format(
            \abs($value),
            $localeInfo['frac_digits'],
            $localeInfo['mon_decimal_point'],
            $localeInfo['mon_thousands_sep']
        );

        $info = $value >= 0
            ? array(
                'csPrecedes' => $localeInfo['p_cs_precedes'],
                'sepBySpace' => $localeInfo['p_sep_by_space'],
                'sign' => $localeInfo['positive_sign'],
                'signPosN' => $localeInfo['p_sign_posn'],
            )
            : array(
                'csPrecedes' => $localeInfo['n_cs_precedes'],
                'sepBySpace' => $localeInfo['n_sep_by_space'],
                'sign' => $localeInfo['negative_sign'],
                'signPosN' => $localeInfo['n_sign_posn'],
            );

        return \array_merge(array(
            'formatted' => $formatted,
            'separator' => $info['sepBySpace'] ? ' ' : '',
            'symbol' => $localeInfo['currency_symbol'],
        ), $info);
    }

    /**
     * Get localeconv info for current locale
     *
     * @return array
     */
    private function localeconv()
    {
        if (isset(self::$localeconv[$this->locale])) {
            return self::$localeconv[$this->locale];
        }
        $localeWasNumeric = \setlocale(LC_NUMERIC, 0);
        $localeWasMonetary = \setlocale(LC_MONETARY, 0);
        \setlocale(LC_NUMERIC, $this->locale);
        \setlocale(LC_MONETARY, $this->locale);
        $localeConv = \localeconv();
        if ($localeConv['p_sign_posn'] === 127) {
            // It would appear we don't have locale information
            $localeConv = \array_filter($localeConv, static function ($value) {
                $emptyVals = [127, '', []];
                return \in_array($value, $emptyVals, true) === false;
            });
            $localeConv = \array_merge(self::$localeConvDefault, $localeConv);
        }
        \setlocale(LC_NUMERIC, $localeWasNumeric);
        \setlocale(LC_MONETARY, $localeWasMonetary);
        self::$localeconv[$this->locale] = $localeConv;
        return $localeConv;
    }
}
