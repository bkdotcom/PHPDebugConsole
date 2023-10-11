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
    Support HandlerInterface with/without return type in handle() method definition
*/

$refClass = new \ReflectionClass('Monolog\\Handler\\HandlerInterface');
$refMethod = $refClass->getMethod('handle');

if (\method_exists($refMethod, 'hasReturnType') && $refMethod->hasReturnType()) {
    $refParam = $refMethod->getParameters()[0];
    $type = \bdk\Debug\Abstraction\Object\Helper::getParamType($refParam);
    require $type === 'array'
        ? __DIR__ . '/MonologHandlerCompatTrait_2.0.php'
        : __DIR__ . '/MonologHandlerCompatTrait_3.0.php';
} elseif (\trait_exists(__NAMESPACE__ . '\\MonologHandlerCompatTrait', false) === false) {
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
        public function handle(array $record)
        {
            return $this->doHandle($record);
        }
    }
}
