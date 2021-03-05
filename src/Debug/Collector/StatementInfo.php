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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use Exception;

/**
 * Holds information about a SQL statement
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
    protected $prettified = null;
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
        if (!self::$constants) {
            $ref = new \ReflectionClass('PDO');
            $consts = array();
            $constsAll = $ref->getConstants();
            foreach ($constsAll as $name => $val) {
                if (\strpos($name, 'PARAM_') === 0 && \strpos($name, 'PARAM_EVT_') !== 0) {
                    $consts[$val] = 'PDO::' . $name;
                }
            }
            if (\class_exists('Doctrine\\DBAL\\Connection')) {
                $consts += array(
                    \Doctrine\DBAL\Connection::PARAM_INT_ARRAY => 'Doctrine\\DBAL\\Connection::PARAM_INT_ARRAY',
                    \Doctrine\DBAL\Connection::PARAM_STR_ARRAY => 'Doctrine\\DBAL\\Connection::PARAM_STR_ARRAY',
                );
            }
            self::$constants = $consts;
        }
        $this->memoryStart = \memory_get_usage(false);
        $this->params = $params;
        $this->sql = \trim($sql);
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
            return $this->{$getter}();
        }
        if (\preg_match('/^is[A-Z]/', $name) && \method_exists($this, $name)) {
            return $this->{$name}();
        }
        if (isset($this->$name)) {
            return $this->{$name};
        }
        return null;
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
        $label = $this->getGroupLabel();
        $debug->groupCollapsed($label, $debug->meta(array(
            'icon' => $debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'boldLabel' => false,
        )));
        if (\preg_replace('/[\r\n\s]+/', ' ', $this->sql) !== $label) {
            $sqlPretty = $debug->prettify($this->sql, 'application/sql');
            if ($sqlPretty instanceof Abstraction) {
                $this->prettified = $sqlPretty['prettified'];
                $sqlPretty['prettifiedTag'] = false; // don't add "(prettified)" to output"
            }
            $debug->log(
                $sqlPretty,
                $debug->meta(array(
                    'attribs' => array(
                        'class' => 'no-indent',
                    ),
                ))
            );
        }
        $this->logParams($debug);
        if ($this->duration !== null) {
            $debug->time('duration', $this->duration);
        }
        if ($this->memoryUsage !== null) {
            $debug->log('memory usage', $debug->utility->getBytes($this->memoryUsage));
        }
        $this->performQueryAnalysis($this->sql, $debug);
        if ($this->exception) {
            $code = $this->exception->getCode();
            $msg = $this->exception->getMessage();
            if (\strpos($msg, (string) $code) === false) {
                $msg .= ' (code ' . $code . ')';
            }
            $debug->warn(\get_class($this->exception) . ': ' . \trim($msg));
        } elseif ($this->rowCount !== null) {
            $debug->log('rowCount', $this->rowCount);
        }
        $debug->groupEnd();
    }

    /**
     * End the statement
     *
     * @param Exception|null $exception (optional) Exception (if statement threw exception)
     * @param int            $rowCount  (optional) Number of rows affected by the last DELETE, INSERT, or UPDATE statement
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
     * Set query's duration
     *
     * @param float $duration duration (in sec)
     *
     * @return void
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    /**
     * Set query's memory usage
     *
     * @param int $memory memory (in bytes)
     *
     * @return void
     */
    public function setMemoryUsaage($memory)
    {
        $this->memoryUsage = $memory;
    }

    /**
     * Returns the exception's code
     *
     * @return int|string
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
        $quoteRight = $quotationChars;
        $quoteLeft = $quoteRight;
        if ($len > 1) {
            $len = (int) \floor($len / 2);
            $quoteLeft = \substr($quotationChars, 0, $len);
            $quoteRight = \substr($quotationChars, $len);
        }

        $sql = $this->sql;

        $cleanBackRefCharMap = array(
            '%' => '%%',
            '$' => '$%',
            '\\' => '\\%'
        );

        foreach ($this->params as $k => $v) {
            $backRefSafeV = \strtr($v, $cleanBackRefCharMap);
            $v = $quoteLeft . $backRefSafeV . $quoteRight;
            $marker = \is_numeric($k)
                ? '?'
                : (\preg_match('/^:/', $k)
                    ? $k
                    : ':' . $k);

            $matchRule = '/(' . $marker . '(?!\w))'
                . '(?='
                . '(?:[^' . $quotationChars . ']|'
                . '[' . $quotationChars . '][^' . $quotationChars . ']*[' . $quotationChars . ']'
                . ')*$)/';
            do {
                $sql = \preg_replace($matchRule, $v, $sql, 1);
            } while (\mb_substr_count($sql, $k));
        }

        return \strtr($sql, \array_flip($cleanBackRefCharMap));
    }

    /**
     * Get group's label
     *
     * @return string
     */
    private function getGroupLabel()
    {
        $label = $this->sql;
        $regex = '/^(
                (?:DROP|SHOW).+$|
                CREATE(?:\sTEMPORARY)?\s+TABLE(?:\sIF\sNOT\sEXISTS)?\s+\S+|
                DELETE.*?FROM\s+\S+|
                INSERT(?:\s+(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE|INTO))*\s+\S+|
                SELECT\s+(?P<select>.*)\s+FROM\s+\S+|
                UPDATE\s+\S+
            )(?P<more>.*)/imsx';
        $matches = array();
        if (\preg_match($regex, $label, $matches)) {
            $haveMore = !empty($matches['more']);
            $label = $matches[1] . ($haveMore ? '…' : '');
            if (\strlen($matches['select']) > 100) {
                $label = \str_replace($matches['select'], '(…)', $label);
            }
            $label = \preg_replace('/[\r\n\s]+/', ' ', $label);
        }
        return $label;
    }

    /**
     * Log statement bound params
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    protected function logParams(Debug $debug)
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
                $params[$name]['type'] = new Abstraction(Abstracter::TYPE_CONST, array(
                    'name' => self::$constants[$type],
                    'value' => $type,
                ));
            }
        }
        $debug->table('parameters', $params);
    }

    /**
     * Find common query performance issues
     *
     * @param string $query SQL query
     * @param Debug  $debug Debug instance
     *
     * @return void
     *
     * @link https://github.com/rap2hpoutre/mysql-xplain-xplain/blob/master/app/Explainer.php
     */
    protected function performQueryAnalysis($query, Debug $debug)
    {
        if (\preg_match('/^\s*SELECT\s*`?[a-zA-Z0-9]*`?\.?\*/i', $query)) {
            $debug->warn(
                'Use %cSELECT *%c only if you need all columns from table',
                'font-family:monospace',
                '',
                $debug->meta('uncollapse', false)
            );
        }
        if (\stripos($query, 'ORDER BY RAND()') !== false) {
            $debug->warn(
                '%cORDER BY RAND()%c is slow, avoid if you can.',
                'font-family:monospace',
                '',
                $debug->meta('uncollapse', false)
            );
        }
        if (\strpos($query, '!=') !== false) {
            $debug->warn(
                'The %c!=%c operator is not standard. Use the %c<>%c operator instead.',
                'font-family:monospace',
                '',
                'font-family:monospace',
                '',
                $debug->meta('uncollapse', false)
            );
        }
        if (\preg_match('/^SELECT\s/i', $query) && \stripos($query, 'WHERE') === false) {
            $debug->warn(
                'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
                'font-family:monospace',
                '',
                'font-family:monospace',
                '',
                $debug->meta('uncollapse', false)
            );
        }
        if (\preg_match('/LIKE\s+[\'"](%.*?)[\'"]/i', $query, $matches)) {
            $debug->warn(
                'An argument has a leading wildcard character: %c' . $matches[1] . '%c and cannot use an index if one exists.',
                'font-family:monospace',
                '',
                $debug->meta('uncollapse', false)
            );
        }
        if (\preg_match('/LIMIT\s/i', $query) && \stripos($query, 'ORDER BY') === false) {
            $debug->warn(
                '%cLIMIT%c without %cORDER BY%c causes non-deterministic results',
                'font-family:monospace',
                '',
                'font-family:monospace',
                '',
                $debug->meta('uncollapse', false)
            );
        }
    }
}
