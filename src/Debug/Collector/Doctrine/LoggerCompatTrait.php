<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2024-2025 Brad Kent
 * @since     3.3
 */

 /*
    PHP 8.4 deprecates implicit nullable types
 */

namespace bdk\Debug\Collector\Doctrine;

$require = PHP_VERSION_ID >= 80400
    ? __DIR__ . '/LoggerCompatTrait_php8.4.php'
    : __DIR__ . '/LoggerCompatTrait_legacy.php';

require $require;
