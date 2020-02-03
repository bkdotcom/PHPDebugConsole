<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
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
     * @param mixed  ...$vals The number of variables and length of string types must match the parameters in the statement
     *
     * @return bool
     */
    public function bind_param($types, &...$vals)
    {
        if (!$this->mysqli->connectionAttempted) {
            return false;
        }
        $this->params = $vals;
        $this->types = \str_split($types);
        return parent::bind_param($types, ...$vals);
    }

    /**
     * {@inheritDoc}
     */
    public function execute()
    {
        $statementInfo = new StatementInfo($this->query, $this->params, $this->types);
        $exception = null;
        $return = false;
        if ($this->mysqli->connectionAttempted) {
            $return = parent::execute();
        } else {
            $exception = new Exception('Not connected');
        }
        $statementInfo->end($exception, $return ? $this->affected_rows : null);
        $this->mysqli->addStatementInfo($statementInfo);
        return $return;
    }
}
