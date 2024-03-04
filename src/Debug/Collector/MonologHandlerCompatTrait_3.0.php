<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.1
 */

namespace bdk\Debug\Collector;

use Monolog\LogRecord;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\MonologHandlerCompatTrait', false) === false) {
    /**
     * Provide handle method (with return type-hint)
     */
    trait MonologHandlerCompatTrait
    {
        /**
         * Handles a record.
         *
         * @param LogRecord $record The record to handle
         *
         * @return bool true means that this handler handled the record, and that bubbling is not permitted.
         *                      false means the record was either not processed or that this handler allows bubbling.
         */
        public function handle(LogRecord $record): bool
        {
            return $this->isHandling($record)
                ? $this->doHandle($record->toArray())
                : false;
        }
    }
}
