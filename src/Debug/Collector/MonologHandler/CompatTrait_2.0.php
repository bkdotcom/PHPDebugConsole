<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0.5
 */

namespace bdk\Debug\Collector\MonologHandler;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
     * Provide handle method (with return type-hint)
     *
     * @phpcs:disable Generic.Classes.DuplicateClassName.Found
     */
    trait CompatTrait
    {
        /**
         * Handles a record.
         *
         * @param array $record The record to handle
         *
         * @return bool true means that this handler handled the record, and that bubbling is not permitted.
         *                      false means the record was either not processed or that this handler allows bubbling.
         */
        public function handle(array $record): bool
        {
            return $this->doHandle($record);
        }
    }
}
