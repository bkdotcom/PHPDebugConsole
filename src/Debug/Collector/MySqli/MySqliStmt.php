<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Collector\MySqli;

use bdk\Debug\Collector\MySqliProxyListener;
use bdk\Debug\Collector\StatementInfo;
use Exception;
use mysqli;
use mysqli_stmt as mysqliStmtBase;

/**
 * A mysqli_stmt proxy which traces statements
 */
class MySqliStmt extends mysqliStmtBase
{
    /** @var MySqliProxyListener */
    private $listener;

    /** @var mysqli */
    private $mysqli;

    /** @var string */
    private $query;

    /** @var list<mixed> */
    private $params = array();

    /** @var list<string> */
    private $types = array();

    /**
     * Constructor
     *
     * @param mysqli              $mysqli   mysqli instance
     * @param MySqliProxyListener $listener MySqliProxyListener instance
     * @param string|null         $query    SQL query
     */
    public function __construct(mysqli $mysqli, MySqliProxyListener $listener, $query = null)
    {
        parent::__construct($mysqli, $query);
        $this->mysqli = $mysqli;
        $this->query = $query;
        $this->listener = $listener;
    }

    /**
     * {@inheritDoc}
     *
     * Requires php >= 5.6 (variadic syntax)
     *
     * @param string $types   A string that contains one or more characters which specify the types for the corresponding bind variables
     * @param mixed  ...$vals The number of variables and length of string types must match the parameters in the statement
     *
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function bind_param($types, &...$vals) // @phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->listener->connectionAttempted === false) {
            return false;
        }
        $this->params = $vals;
        $this->types = \str_split($types);
        return parent::bind_param($types, ...$vals);
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function execute($params = null)
    {
        $statementInfo = new StatementInfo($this->query, $this->params, $this->types);
        $return = $this->listener->connectionAttempted
            ? (PHP_VERSION_ID >= 80100 ? parent::execute($params) : parent::execute())
            : false;
        $exception = $this->listener->connectionAttempted
            ? null
            : new Exception('Not connected');
        $statementInfo->end($exception, $return ? $this->affected_rows : null);
        $this->listener->addStatementInfo($statementInfo);
        return $return;
    }
}
