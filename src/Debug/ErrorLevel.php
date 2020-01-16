<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

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
        /*
            version_compare considers 1 < 1.0 < 1.0.0
        */
        $phpVer = \preg_match('/^\d+\.\d+$/', $phpVer)
            ? $phpVer.'.0'
            : $phpVer;
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
        /*
            E_ALL value:
            >= 5.4: 32767
               5.3: 30719 (doesn't include E_STRICT)
               5.2: 6143 (doesn't include E_STRICT)
             < 5.2: 2047 (doesn't include E_STRICT)
        */
        $constants['E_ALL'] = \array_sum($constants);
        if (isset($constants['E_STRICT']) && \version_compare($phpVer, '5.4.0', '<')) {
            // E_STRICT not included in E_ALL until 5.4
            $constants['E_ALL'] -= $constants['E_STRICT'];
        }
        return $constants;
    }

    /**
     * Convert PHP error-level integer (bitmask) to constant bitwise representation
     *
     * @param integer $level          Error Level (bitmask) value
     * @param string  $phpVer         (PHP_VERSION) php Version
     * @param boolean $explicitStrict (true) if level === E_ALL, always include/exclude E_STRICT for disambiguation / portability
     *
     * @return string
     */
    public static function toConstantString($level = null, $phpVer = null, $explicitStrict = true)
    {
        $string = '';
        $level = $level === null
            ? \error_reporting()
            : $level;
        $allConstants = self::getConstants($phpVer); // includes E_ALL
        $levelConstants = self::filterConstantsByLevel($allConstants, $level); // excludes E_ALL
        $eAll = $allConstants['E_ALL'];
        unset($allConstants['E_ALL']);
        if (\count($levelConstants) > \count($allConstants) / 2) {
            $on = array('E_ALL');
            $off = array();
            foreach ($allConstants as $constantName => $value) {
                $isExplicit = $explicitStrict && $constantName == 'E_STRICT';
                if (self::inBitmask($value, $level)) {
                    if (!self::inBitmask($value, $eAll) || $isExplicit) {
                        // only thing that wouldn't be in E_ALL is E_STRICT
                        $on[] = $constantName;
                    }
                } else {
                    if (self::inBitmask($value, $eAll) || $isExplicit) {
                        $off[] = $constantName;
                    }
                }
            }
            $on = \count($on) > 1 && $off
                ? '( ' . \implode(' | ', $on) . ' )'
                : \implode(' | ', $on);
            $off = \join('', \array_map(function ($constantName) {
                return ' & ~' . $constantName;
            }, $off));
            $string = $on . $off;
        } else {
            $string = \implode(' | ', \array_keys($levelConstants));
        }
        return $string ?: '0';
    }

    /**
     * Get all constants included in specified error level
     * excludes E_ALL
     *
     * @param array   $constants constantName => value array
     * @param integer $level     error level
     *
     * @return array
     */
    private static function filterConstantsByLevel($constants, $level)
    {
        foreach ($constants as $constantName => $value) {
            if (!self::inBitmask($value, $level)) {
                unset($constants[$constantName]);
            }
        }
        unset($constants['E_ALL']);
        return $constants;
    }

    /**
     * Test if value is incl in bitmask
     *
     * @param integer $value   value to check
     * @param integer $bitmask bitmask
     *
     * @return boolean
     */
    private static function inBitmask($value, $bitmask)
    {
        return ($bitmask & $value) === $value;
    }
}
