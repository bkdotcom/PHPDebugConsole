<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2023 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Collector;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\MonologHandlerCompatTrait', false) === false) {
    /**
     * Provide handle method (with return type-hint)
     *
     * @phpcs:disable Generic.Classes.DuplicateClassName.Found
     */
    trait MonologHandlerCompatTrait
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
