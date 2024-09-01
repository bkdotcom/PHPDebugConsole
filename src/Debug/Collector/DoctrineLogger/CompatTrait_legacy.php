<?php

namespace bdk\Debug\Collector\DoctrineLogger;

use bdk\Debug\Collector\StatementInfo;

/*
    Wrap in condition.
    PHPUnit code coverage scans all files and will conflict
*/
if (\trait_exists(__NAMESPACE__ . '\\CompatTrait', false) === false) {
    /**
     * Method signature for Php < 8.4
     */
    trait CompatTrait
    {
        /**
         * Logs a SQL statement somewhere.
         *
         * @param string                                                                    $sql    SQL statement
         * @param array<int, mixed>|array<string, mixed>|null                               $params Statement parameters
         * @param array<int, Type|int|string|null>|array<string, Type|int|string|null>|null $types  Parameter types
         *
         * @return void
         *
         * @phpcs:disable Generic.Classes.DuplicateClassName.Found
         */
        public function startQuery($sql, array $params = null, array $types = null)
        {
            $this->statementInfo = new StatementInfo($sql, $params, $types);
        }
    }
}
