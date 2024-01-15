<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * PHP 8.2's new execute_query method requires signatures to match..
 * to maintain compatibility with php < 8.2 we'll use a trait
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
    }
}
