<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

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

use bdk\Debug\Abstraction\Object\Helper as ObjectHelper;

/*
    Support HandlerInterface with/without return type in handle() method definition
*/

$refClass = new \ReflectionClass('Monolog\\Handler\\HandlerInterface');
$refMethod = $refClass->getMethod('handle');

if (\method_exists($refMethod, 'hasReturnType') && $refMethod->hasReturnType()) {
    $refParam = $refMethod->getParameters()[0];
    $type = ObjectHelper::getType(null, $refParam);
    require $type === 'array'
        ? __DIR__ . '/CompatTrait_2.0.php'
        : __DIR__ . '/CompatTrait_3.0.php';
} elseif (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
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
        public function handle(array $record)
        {
            return $this->doHandle($record);
        }
    }
}
