<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * PHP 8.4 deprecates implicit nullable types
 */

namespace bdk\Debug\Collector\Doctrine;

$require = PHP_VERSION_ID >= 80400
    ? __DIR__ . '/LoggerCompatTrait_php8.4.php'
    : __DIR__ . '/LoggerCompatTrait_legacy.php';

require $require;
