<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.0b3
 */

namespace bdk\Debug\Collector\MySqli;

use mysqli_result;

/**
 * Define HP 8.2's mysqli::execute_query method
 *
 * @phpcs:disable Generic.Classes.DuplicateClassName.Found
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
trait ExecuteQueryTrait
{
    /**
     * {@inheritDoc}
     */
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        return $this->profileCall('execute_query', $query, \func_get_args());
    }
}
