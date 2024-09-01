<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * PHP 8.4 deprecates implicit nullable types
 */

namespace bdk\Debug\Collector\DoctrineLogger;

$require = PHP_VERSION_ID >= 80400
    ? __DIR__ . '/CompatTrait_php8.4.php'
    : __DIR__ . '/CompatTrait_legacy.php';

require $require;
