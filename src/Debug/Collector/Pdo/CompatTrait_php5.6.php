<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector\Pdo;

/**
 * @phpcs:disable Generic.Classes.DuplicateClassName.Found
 */
trait CompatTrait
{
    /**
     * Executes an SQL statement, returning a result set as a PDOStatement object
     *
     * @param string $statement        The SQL statement to prepare and execute.
     * @param int    $fetchMode        PDO::FETCH_COLUMN | PDO::FETCH_CLASS | PDO::FETCH_INTO
     * @param mixed  ...$fetchModeArgs Additional mode dependent args
     *
     * @return \PDOStatement|false PDO::query returns a PDOStatement object, or `false` on failure.
     * @link   http://php.net/manual/en/pdo.query.php
     */
    #[\ReturnTypeWillChange]
    public function query($statement = null, $fetchMode = null, ...$fetchModeArgs) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        return $this->profileCall('query', $statement, \func_get_args());
    }
}
