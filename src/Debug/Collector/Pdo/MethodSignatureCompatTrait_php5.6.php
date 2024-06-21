<?php

namespace bdk\Debug\Collector\Pdo;

/**
 * @phpcs:disable Generic.Classes.DuplicateClassName.Found
 */
trait MethodSignatureCompatTrait
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
