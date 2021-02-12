<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector\MySqli;

use bdk\Debug\Collector\MySqli;
use bdk\Debug\Collector\StatementInfo;
use Exception;
use mysqli_stmt as mysqliStmtBase;

/**
 * A mysqli_stmt proxy which traces statements
 */
class MySqliStmt extends mysqliStmtBase
{

    private $query;
    private $mysqli;
    private $params = array();
    private $types = array();

    /**
     * Constructor
     *
     * @param MySqli $mysqli mysqli instance
     * @param string $query  SQL query
     */
    public function __construct(MySqli $mysqli, $query = null)
    {
        parent::__construct($mysqli, $query);
        $this->mysqli = $mysqli;
        $this->query = $query;
    }

    /**
     * {@inheritDoc}
     *
     * Requires php >= 5.6 (variadic syntax)
     *
     * @param string $types   A string that contains one or more characters which specify the types for the corresponding bind variables
     * @param mixed  $val1    First value
     * @param mixed  ...$vals The number of variables and length of string types must match the parameters in the statement
     *
     * @return bool
     */
    public function bind_param($types, &$val1, &...$vals) // @phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    {
        if ($this->mysqli->connectionAttempted === false) {
            return false;
        }
        $this->params = \array_merge(array($val1), $vals);
        $this->types = \str_split($types);
        return parent::bind_param($types, $val1, ...$vals);
    }

    /**
     * {@inheritDoc}
     */
    public function execute()
    {
        $statementInfo = new StatementInfo($this->query, $this->params, $this->types);
        $return = $this->mysqli->connectionAttempted
            ? parent::execute()
            : false;
        $exception = $this->mysqli->connectionAttempted
            ? null
            : new Exception('Not connected');
        $statementInfo->end($exception, $return ? $this->affected_rows : null);
        $this->mysqli->addStatementInfo($statementInfo);
        return $return;
    }
}
