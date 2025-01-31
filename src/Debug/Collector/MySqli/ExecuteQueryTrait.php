<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2022-2025 Brad Kent
 * @since     3.0
 */

/*
    PHP 8.2's new execute_query method requires signatures to match..
    to maintain compatibility with php < 8.2 we'll use a trait
*/

namespace bdk\Debug\Collector\MySqli;

if (PHP_VERSION_ID >= 80200) {
    require __DIR__ . '/ExecuteQueryTrait_php8.2.php';
} else {
    /**
     * @phpcs:disable Generic.Classes.DuplicateClassName.Found
     */
    trait ExecuteQueryTrait
    {
        // execute_query did not exist in PHP < 8.2
    }
}
