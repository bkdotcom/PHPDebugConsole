<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2024-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector\Doctrine;

use bdk\Debug\Collector\StatementInfo;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\LoggerCompatTrait', false) === false) {
    /**
     * Method signature for Php >= 8.4
     */
    trait LoggerCompatTrait
    {
        /**
         * Logs a SQL statement somewhere.
         *
         * @param string                                                                    $sql    SQL statement
         * @param array<int, mixed>|array<string, mixed>|null                               $params Statement parameters
         * @param array<int, Type|int|string|null>|array<string, Type|int|string|null>|null $types  Parameter types
         *
         * @return void
         *
         * @phpcs:disable Generic.Classes.DuplicateClassName.Found
         */
        public function startQuery($sql, ?array $params = null, ?array $types = null)
        {
            $this->statementInfo = new StatementInfo($sql, $params, $types);
        }
    }
}
