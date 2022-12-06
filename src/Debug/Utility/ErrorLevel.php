<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug\Utility;

/**
 * Utility to convert error level mask to user friendly string
 *
 * @see http://php.net/manual/en/errorfunc.constants.php
 * @see https://github.com/maximivanov/php-error-reporting-calculator javascript code inspiration
 */
class ErrorLevel
{
    /**
     * Get error level constants understood by specified php version
     *
     * @param string $phpVer (PHP_VERSION) PHP verion
     *
     * @return array
     */
    public static function getConstants($phpVer = null)
    {
        $phpVer = $phpVer ?: PHP_VERSION;
        $phpVer = self::normalizePhpVer($phpVer);
        $constants = array(
            'E_ERROR' => 1,
            'E_WARNING' => 2,
            'E_PARSE' => 4,
            'E_NOTICE' => 8,
            'E_CORE_ERROR' => 16,
            'E_CORE_WARNING' => 32,
            'E_COMPILE_ERROR' => 64,
            'E_COMPILE_WARNING' => 128,
            'E_USER_ERROR' => 256,
            'E_USER_WARNING' => 512,
            'E_USER_NOTICE' => 1024,
            'E_STRICT' => \version_compare($phpVer, '5.0.0', '>=') ? 2048 : null,
            'E_RECOVERABLE_ERROR' => \version_compare($phpVer, '5.2.0', '>=') ? 4096 : null,
            'E_DEPRECATED' => \version_compare($phpVer, '5.3.0', '>=') ? 8192 : null,
            'E_USER_DEPRECATED' => \version_compare($phpVer, '5.3.0', '>=') ? 16384 : null,
            'E_ALL' => null, // calculated below
        );
        $constants = \array_filter($constants);
        $constants['E_ALL'] = static::calculateEall($constants, $phpVer);
        return $constants;
    }

    /**
     * Convert PHP error-level integer (bitmask) to constant bitwise representation
     *
     * @param int    $errorReportingLevel Error Level (bitmask) value
     * @param string $phpVer              (PHP_VERSION) php Version
     * @param bool   $explicitStrict      (true) if level === E_ALL, always include/exclude E_STRICT for disambiguation / portability
     *
     * @return string
     */
    public static function toConstantString($errorReportingLevel = null, $phpVer = null, $explicitStrict = true)
    {
        $errorReportingLevel = $errorReportingLevel === null
            ? \error_reporting()
            : $errorReportingLevel;
        $allConstants = self::getConstants($phpVer); // includes E_ALL
        $flags = array(
            'on' => \array_keys(self::filterConstantsByLevel($allConstants, $errorReportingLevel)), // excludes E_ALL
            'off' => array(),
        );
        $eAll = $allConstants['E_ALL'];
        unset($allConstants['E_ALL']);
        if (\count($flags['on']) > \count($allConstants) / 2) {
            $flags = self::getNegateFlags($errorReportingLevel, $allConstants, $eAll, $explicitStrict);
        }
        $string = self::joinOnOff($flags['on'], $flags['off']);
        return $string ?: '0';
    }

    /**
     * Calculate E_ALL for given php version
     *
     * E_ALL value:
     *   >= 5.4: 32767
     *      5.3: 30719 (doesn't include E_STRICT)
     *      5.2: 6143 (doesn't include E_STRICT)
     *    < 5.2: 2047 (doesn't include E_STRICT)
     *
     * @param array  $constants constant values (sans E_ALL)
     * @param string $phpVer    php version
     *
     * @return int
     */
    private static function calculateEall($constants, $phpVer)
    {
        $eAll = \array_sum($constants);
        if (isset($constants['E_STRICT']) && \version_compare($phpVer, '5.4.0', '<')) {
            // E_STRICT not included in E_ALL until 5.4
            $eAll -= $constants['E_STRICT'];
        }
        return $eAll;
    }

    /**
     * Get all constants included in specified error level
     * excludes E_ALL
     *
     * @param array $constants constName => value array
     * @param int   $level     error level
     *
     * @return array
     */
    private static function filterConstantsByLevel($constants, $level)
    {
        foreach ($constants as $constName => $constValue) {
            if (!self::inBitmask($constValue, $level)) {
                unset($constants[$constName]);
            }
        }
        unset($constants['E_ALL']);
        return $constants;
    }

    /**
     * Get on/off flags starting with E_ALL
     *
     * @param int   $errorReportingLevel Error Level (bitmask) value
     * @param array $allConstants        constName => $constValue array
     * @param int   $eAll                E_ALL value for specified php version
     * @param bool  $explicitStrict      explicitly specify E_STRICT?
     *
     * @return array
     */
    private static function getNegateFlags($errorReportingLevel, $allConstants, $eAll, $explicitStrict)
    {
        $flags = array(
            'on' => array('E_ALL'),
            'off' => array(),
        );
        foreach ($allConstants as $constName => $constValue) {
            $isExplicit = $explicitStrict && $constName === 'E_STRICT';
            // only thing that may not be in E_ALL is E_STRICT
            $inclInEall = self::inBitmask($constValue, $eAll);
            $inclInLevel = self::inBitmask($constValue, $errorReportingLevel);
            $incl = $inclInEall !== $inclInLevel || $isExplicit;
            if ($incl) {
                $onOrOff = $inclInLevel
                    ? 'on'
                    : 'off';
                $flags[$onOrOff][] = $constName;
            }
        }
        return $flags;
    }

    /**
     * Test if value is incl in bitmask
     *
     * @param int $value   value to check
     * @param int $bitmask bitmask
     *
     * @return bool
     */
    private static function inBitmask($value, $bitmask)
    {
        return ($bitmask & $value) === $value;
    }

    /**
     * Build error-level string representation from on & off flags
     *
     * @param array $flagsOn  constant names
     * @param array $flagsOff constant names
     *
     * @return string
     */
    private static function joinOnOff($flagsOn, $flagsOff)
    {
        $flagsOn = \count($flagsOn) > 1 && $flagsOff
            ? '( ' . \implode(' | ', $flagsOn) . ' )'
            : \implode(' | ', $flagsOn);
        $flagsOff = \implode('', \array_map(static function ($flag) {
            return ' & ~' . $flag;
        }, $flagsOff));
        return $flagsOn . $flagsOff;
    }

    /**
     * Make sur phpVersion string is of form x.x.x
     * version_compare considers 1 < 1.0 < 1.0.0
     *
     * @param string $phpVer Php version string
     *
     * @return string
     */
    private static function normalizePhpVer($phpVer)
    {
        return \preg_match('/^\d+\.\d+$/', $phpVer)
            ? $phpVer . '.0'
            : $phpVer;
    }
}
