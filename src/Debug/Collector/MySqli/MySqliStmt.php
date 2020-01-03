<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector\MySqli;

use mysqli_stmt as mysqliStmtBase;
use bdk\Debug;
use bdk\Debug\Collector\MySqli;
use bdk\Debug\Collector\StatementInfo;

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
     */
    public function bind_param($types, &...$vals)
    {
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
        $return = parent::execute();
        $statementInfo->end(null, $this->affected_rows);
        $this->mysqli->addStatementInfo($statementInfo);
        return $return;
    }
}
