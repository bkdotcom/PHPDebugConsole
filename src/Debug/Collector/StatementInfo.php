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

namespace bdk\Debug\Collector;

use Exception;
use bdk\Debug;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Utilities;

/**
 * Holds information about a statement
 *
 * @property-read integer   $duration
 * @property-read integer   $errorCode
 * @property-read string    $errorMessage
 * @property-read Exception $exception
 * @property-read boolean   $isSuccess
 * @property-read integer   $memoryEnd
 * @property-read integer   $memoryStart
 * @property-read integer   $memoryUsage
 * @property-read array     $params
 * @property-read integer   $rowCount      number of rows affected by the last DELETE, INSERT, or UPDATE statement
 *                                          or number of rows returned by SELECT statement
 * @property-read string    $sql
 * @property-read string    $sqlWithParams
 * @property-read float     $timeEnd
 * @property-read float     $timeStart
 * @property-read array     $types
 */
class StatementInfo
{

    protected $duration;
    protected $exception;
    protected $isSuccess;
    protected $memoryEnd;
    protected $memoryStart;
    protected $memoryUsage;
    protected $params;
    protected $rowCount;
    protected $sql;
    protected $timeEnd;
    protected $timeStart;
    protected $types;
    protected static $constants;

    /**
     * @param string $sql    SQL
     * @param array  $params bound params
     * @param array  $types  bound types
     */
    public function __construct($sql, $params = null, $types = null)
    {
        $sql = Utilities::prettySql($sql);
        if (!self::$constants) {
            $ref = new \ReflectionClass('PDO');
            $consts = array();
            $constsAll = $ref->getConstants();
            foreach ($constsAll as $name => $val) {
                if (\strpos($name, 'PARAM_') === 0 && \strpos($name, 'PARAM_EVT_') !== 0) {
                    $consts[$val] = '\\PDO::' . $name;
                }
            }
            if (\class_exists('\\Doctrine\\DBAL\\Connection')) {
                $consts += array(
                    \Doctrine\DBAL\Connection::PARAM_INT_ARRAY => '\\Doctrine\\DBAL\\Connection::PARAM_INT_ARRAY',
                    \Doctrine\DBAL\Connection::PARAM_STR_ARRAY => '\\Doctrine\\DBAL\\Connection::PARAM_STR_ARRAY',
                );
            }
            self::$constants = $consts;
        }
        $this->memoryStart = \memory_get_usage(false);
        $this->params = $params;
        $this->sql = $sql;
        $this->timeStart = \microtime(true);
        $this->types = $types;
    }

    /**
     * Magic method
     *
     * @return array
     */
    public function __debugInfo()
    {
        return array(
            'duration' => $this->duration,
            'exception' => $this->exception,
            'memoryUsage' => $this->memoryUsage,
            'params' => $this->params,
            'rowCount' => $this->rowCount,
            'sql' => $this->sql,
            'types' => $this->types,
        );
    }

    /**
     * Magic getter
     *
     * @param string $name property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get' . \ucfirst($name);
        if (\method_exists($this, $getter)) {
            return $this->$getter();
        } elseif (\preg_match('/^is[A-Z]/', $name) && \method_exists($this, $name)) {
            return $this->$name();
        } elseif (isset($this->$name)) {
            return $this->{$name};
        } else {
            return null;
        }
    }

    /**
     * Add this info to debug log
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    public function appendLog(Debug $debug)
    {
        $logSql = true;
        $label = $this->sql;
        $regex = '/^(
                (?:DROP|SHOW).+$|
                CREATE(?:\sTEMPORARY)?\s+TABLE(?:\sIF\sNOT\sEXISTS)?\s+\S+|
                DELETE.*?FROM\s+\S+|
                INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE|INTO))*\s+\S+|
                SELECT\s*(?P<select>.*?)\s+FROM\s+\S+|
                UPDATE\s+\S+
            )(?P<more>.*)/imsx';
        if (\preg_match($regex, $label, $matches)) {
            $logSql = !empty($matches['more']);
            $label = $matches[1] . ($logSql ? '…' : '');
            if (\strlen($matches['select']) > 100) {
                $label = \str_replace($matches['select'], '(…)', $label);
            }
            $label = \preg_replace('/[\r\n\s]+/', ' ', $label);
        }
        $debug->groupCollapsed($label, $debug->meta(array(
            'icon' => $debug->getCfg('channelIcon'),
            'boldLabel' => false,
        )));
        if ($logSql) {
            $debug->log(
                new Abstraction(array(
                    'type' => 'string',
                    'attribs' => array(
                        'class' => 'language-sql prism',
                    ),
                    'addQuotes' => false,
                    'visualWhiteSpace' => false,
                    'value' => $this->sql,
                )),
                $debug->meta(array(
                    'attribs' => array(
                        'class' => 'no-indent',
                    ),
                ))
            );
        }
        $this->logParams($debug);
        $debug->time('duration', $this->duration);
        $debug->log('memory usage', $debug->utilities->getBytes($this->memoryUsage));
        if ($this->rowCount !== null) {
            $debug->log('rowCount', $this->rowCount);
        }
        $debug->groupEnd();
    }

    /**
     * End the statement
     *
     * @param Exception|null $exception (optional) Exception (if statement threw exception)
     * @param integer        $rowCount  (optional) Number of rows affected by the last DELETE, INSERT, or UPDATE statement
     *
     * @return void
     */
    public function end(Exception $exception = null, $rowCount = null)
    {
        $this->exception = $exception;
        $this->rowCount = $rowCount;
        $this->timeEnd = \microtime(true);
        $this->memoryEnd = \memory_get_usage(false);
        $this->duration = $this->timeEnd - $this->timeStart;
        $this->memoryUsage = \max($this->memoryEnd - $this->memoryStart, 0);
        $this->isSuccess = $exception === null;
    }

    /**
     * Returns the exception's code
     *
     * @return string
     */
    protected function getErrorCode()
    {
        return $this->exception
            ? $this->exception->getCode()
            : 0;
    }

    /**
     * Returns the exception's message
     *
     * @return string
     */
    protected function getErrorMessage()
    {
        return $this->exception
            ? $this->exception->getMessage()
            : '';
    }

    /**
     * Returns the SQL string with any parameters used embedded
     *
     * @param string $quotationChars Quotation character(s)
     *
     * @return string
     */
    protected function getSqlWithParams($quotationChars = '<>')
    {
        $len = \strlen($quotationChars);
        if ($len > 1) {
            $len = \floor($len / 2);
            $quoteLeft = \substr($quotationChars, 0, $len);
            $quoteRight = \substr($quotationChars, $len);
        } else {
            $quoteLeft = $quoteRight = $quotationChars;
        }

        $sql = $this->sql;
        foreach ($this->params as $k => $v) {
            $v = "$quoteLeft$v$quoteRight";
            if (!\is_numeric($k)) {
                $sql = \preg_replace('/' . $k . '\b/', $v, $sql);
            } else {
                $p = \strpos($sql, '?') ?: 0;
                $sql = \substr($sql, 0, $p) . $v . \substr($sql, $p + 1);
            }
        }
        return $sql;
    }

    /**
     * Log statement bound params
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logParams(Debug $debug)
    {
        if (!$this->params) {
            return;
        }
        if (!$this->types) {
            $debug->log('parameters', $this->params);
            return;
        }
        $params = array();
        foreach ($this->params as $name => $value) {
            $params[$name] = array(
                'value' => $value,
            );
            if (!isset($this->types[$name])) {
                continue;
            }
            $type = $this->types[$name];
            $params[$name]['type'] = $type; // integer value
            if (isset(self::$constants[$type])) {
                $params[$name]['type'] = new Abstraction(array(
                    'type' => 'const',
                    'name' => self::$constants[$type],
                    'value' => $type,
                ));
            }
        }
        $debug->table('parameters', $params);
    }
}
