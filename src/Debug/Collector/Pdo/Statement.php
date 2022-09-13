<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector\Pdo;

use bdk\Debug\Collector\Pdo as DebugCollectorPdo;
use bdk\Debug\Collector\StatementInfo;
use PDO as PdoBase;   // PDO conflicts with namespace
use PDOException;
use PDOStatement;

/**
 * Debuggable PDOStatement
 */
class Statement extends PDOStatement
{
    protected $pdo;
    protected $boundParameters = array();
    protected $boundParameterTypes = array();

    /**
     * Constructor.
     *
     * @param DebugCollectorPdo $pdo \bdk\Debug\Collector\Pdo instance
     */
    protected function __construct(DebugCollectorPdo $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Bind a column to a PHP variable
     *
     * @param mixed $column     Number of the column (1-indexed) or name of the column in the result set
     * @param mixed $param      Name of the PHP variable to which the column will be bound.
     * @param int   $type       [optional] Data type of the parameter, specified by the PDO::PARAM_* constants.
     * @param int   $maxlen     [optional] A hint for pre-allocation.
     * @param mixed $driverdata [optional] Optional parameter(s) for the driver.
     *
     * @return bool
     * @link   http://php.net/manual/en/pdostatement.bindcolumn.php
     */
    #[\ReturnTypeWillChange]
    public function bindColumn($column, &$param, $type = null, $maxlen = null, $driverdata = null)
    {
        $this->boundParameters[$column] = $param;
        $this->boundParameterTypes[$column] = $type;
        $args = \array_merge(array($column, &$param), \array_slice(\func_get_args(), 2));
        return \call_user_func_array(array('PDOStatement', 'bindColumn'), $args);
    }

    /**
     * Binds a parameter to the specified variable name
     *
     * @param mixed $parameter     Parameter identifier. For a prepared statement using named placeholders,
     *                               this will be a parameter name of the form :name. For a prepared statement using
     *                               question mark placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $variable      Name of the PHP variable to bind to the SQL statement parameter.
     * @param int   $dataType      [optional] Explicit data type for the parameter using the PDO::PARAM_* constants.
     * @param int   $length        [optional] Length of the data type. To indicate that a parameter is an OUT
     *                               parameter from a stored procedure, you must explicitly set the length.
     * @param mixed $driverOptions [optional]
     *
     * @return bool
     * @link   http://php.net/manual/en/pdostatement.bindparam.php
     */
    #[\ReturnTypeWillChange]
    public function bindParam($parameter, &$variable, $dataType = PdoBase::PARAM_STR, $length = null, $driverOptions = null)
    {
        $this->boundParameters[$parameter] = $variable;
        $this->boundParameterTypes[$parameter] = $dataType;
        $args = \array_merge(array($parameter, &$variable), \array_slice(\func_get_args(), 2));
        return \call_user_func_array(array('PDOStatement', 'bindParam'), $args);
    }

    /**
     * Binds a value to a parameter
     *
     * @param mixed $parameter Parameter identifier. For a prepared statement using named placeholders,
     *                             this will be a parameter name of the form :name. For a prepared statement using
     *                             question mark placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value     The value to bind to the parameter.
     * @param int   $dataType  [optional] Explicit data type for the parameter using the PDO::PARAM_* constants.
     *
     * @return bool
     * @link   http://php.net/manual/en/pdostatement.bindvalue.php
     */
    #[\ReturnTypeWillChange]
    public function bindValue($parameter, $value, $dataType = PdoBase::PARAM_STR)
    {
        $this->boundParameters[$parameter] = $value;
        $this->boundParameterTypes[$parameter] = $dataType;
        return \call_user_func_array(array('PDOStatement', 'bindValue'), \func_get_args());
    }

    /**
     * Executes a prepared statement
     *
     * @param array $inputParameters [optional] An array of values with as many elements as there
     *   are bound parameters in the SQL statement being executed. All values are treated as
     *   PDO::PARAM_STR.
     *
     * @return bool
     * @link   http://php.net/manual/en/pdostatement.execute.php
     * @throws PDOException
     */
    #[\ReturnTypeWillChange]
    public function execute($inputParameters = null)
    {
        $info = new StatementInfo(
            $this->queryString,
            $this->mergeParams($inputParameters),
            $this->boundParameterTypes
        );
        $isExceptionMode = $this->pdo->getAttribute(PdoBase::ATTR_ERRMODE) === PdoBase::ERRMODE_EXCEPTION;

        $exception = null;
        $result = false;
        try {
            $result = parent::execute($inputParameters);
            if (!$isExceptionMode && $result === false) {
                $error = $this->errorInfo();
                $exception = new PDOException($error[2], (int) $error[0]);
            }
        } catch (PDOException $e) {
            $exception = $e;
        }

        $info->end($exception, $this->rowCount());
        $this->pdo->addStatementInfo($info);

        if ($isExceptionMode && $exception) {
            throw $exception;
        }
        return $result;
    }

    /**
     * Combine execute's inputParameters with already bound parameters for statementInfo
     *
     * @param array $inputParameters parameters passed to execute
     *
     * @return array
     */
    private function mergeParams($inputParameters)
    {
        return \is_array($inputParameters)
            ? \array_merge($this->boundParameters, $inputParameters)
            : $this->boundParameters;
    }
}
