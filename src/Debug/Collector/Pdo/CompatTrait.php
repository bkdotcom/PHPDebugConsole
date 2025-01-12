<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbol

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

/*
    PHP 8 requires signatures to match.. which relies on variadic....
    to maintain compatibility with ancient PHP we'll do this via trait
*/

namespace bdk\Debug\Collector\Pdo;

if (PHP_VERSION_ID >= 50600) {
    require __DIR__ . '/CompatTrait_php5.6.php';
} else {
    /**
     * @phpcs:disable Generic.Classes.DuplicateClassName.Found
     */
    trait CompatTrait
    {
        /**
         * Executes an SQL statement, returning a result set as a PDOStatement object
         *
         * @param string $statement The SQL statement to prepare and execute.
         *
         * @return \PDOStatement|false PDO::query returns a PDOStatement object, or `false` on failure.
         * @link   http://php.net/manual/en/pdo.query.php
         */
        public function query($statement = null)
        {
            return $this->profileCall('query', $statement, \func_get_args());
        }
    }
}
