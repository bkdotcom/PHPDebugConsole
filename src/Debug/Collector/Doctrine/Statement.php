<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2024-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Collector\Doctrine;

use bdk\Debug\Collector\Doctrine\Connection;
use bdk\Debug\Collector\StatementInfo;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

/**
 * Statement middleware for logging
 */
class Statement extends AbstractStatementMiddleware
{
    /** @var Connection */
    private $connection;

    /** @var string */
    private $sql;

    /** @var array<int,mixed>|array<string,mixed> */
    private $params = [];

    /** @var array<int,ParameterType>|array<string,ParameterType> */
    private $types = [];

    /**
     * Constructor
     *
     * @param StatementInterface $statement  Statement instance
     * @param Connection         $connection Connection instance (where we're storing logged statements)
     * @param string             $sql        Sql string
     */
    public function __construct(
        StatementInterface $statement,
        Connection $connection,
        string $sql
    )
    {
        parent::__construct($statement);
        $this->connection = $connection;
        $this->sql = $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function bindValue($param, $value, $type = ParameterType::STRING): void
    {
        $this->params[$param] = $value;
        $this->types[$param] = $type;
        parent::bindValue($param, $value, $type);
    }

    /**
     * {@inheritDoc}
     */
    public function execute($params = null): ResultInterface
    {
        $info = new StatementInfo($this->sql, $this->params, $this->types);
        $result  = parent::execute();

        $exception = null;
        $result = false;
        $rowCount = null;
        try {
            $result = parent::execute();
            $rowCount = $result->rowCount();
        } catch (\Exception $e) {
            $exception = $e;
        }

        $info->end($exception, $rowCount);
        $this->connection->addStatementInfo($info);

        if ($exception) {
            throw $exception;
        }
        return $result;
    }
}
