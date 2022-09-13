<?php

namespace bdk\Debug\Collector\MySqli;

use mysqli_result;

/**
 * Define HP 8.2's mysqli::execute_query method
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
trait ExecuteQueryTrait
{
    /**
     * {@inheritDoc}
     */
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool
    {
        return $this->profileCall('execute_query', $query, \func_get_args());
    }
}
