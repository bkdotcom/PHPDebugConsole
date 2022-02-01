<?php

/**
 * PHP 8 requires signatures to match.. which relies on variadic....
 * to maintain compatibility with ancient PHP we'll do this via trait
 *
 * @phpcs:disable Generic.Classes.DuplicateClassName.Found
 * @phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * @phpcs:disable Generic.Files.OneTraitPerFile.MultipleFound
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 */

namespace bdk\Debug\Collector\Pdo;

if (PHP_VERSION_ID >= 50600) {
    require __DIR__ . '/MethodSignatureCompatTrait_php5.6.php';
} else {
    trait MethodSignatureCompatTrait
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
