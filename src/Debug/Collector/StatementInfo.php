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

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use Closure;
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
class StatementInfo extends AbstractComponent
{
    protected $debug;

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
    protected $readOnly = array(
        'duration',
        'exception',
        'isSuccess',
        'memoryEnd',
        'memoryStart',
        'memoryUsage',
        'params',
        'prettified',
        'rowCount',
        'sql',
        'timeEnd',
        'timeStart',
        'types',
    );
    protected static $constants = array();

    /**
     * @param string $sql    SQL
     * @param array  $params bound params
     * @param array  $types  bound types
     */
    public function __construct($sql, $params = null, $types = null)
    {
        $this->memoryStart = \memory_get_usage(false);
        $this->params = $params;
        $this->sql = \trim($sql);
        $this->timeStart = \microtime(true);
        $this->types = $types;
        if (!self::$constants) {
            $this->setConstants();
        }
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
     * Add this info to debug log
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    public function appendLog(Debug $debug)
    {
        $this->debug = $debug;
        $label = $this->getGroupLabel();
        $debug->groupCollapsed($label, $debug->meta(array(
            'icon' => $debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'boldLabel' => false,
        )));
        $this->logQuery($label);
        $this->logParams();
        $this->logDurationMemory();
        $this->performQueryAnalysis();
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
    public function setMemoryUsage($memory)
    {
        $this->memoryUsage = $memory;
    }

    /**
     * Set bound params
     *
     * @param array $params parameter values
     *
     * @return void
     */
    public function setParams($params = array())
    {
        $this->params = $params;
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
            $sql = $this->replaceParam($sql, $k, $v, $quotationChars);
        }
        return \strtr($sql, \array_flip($cleanBackRefCharMap));
    }

    /**
     * Replace param with value in query
     *
     * @param string $sql            SQL query
     * @param string $key            param key
     * @param string $value          param value
     * @param string $quotationChars quotation characters
     *
     * @return string
     */
    private function replaceParam($sql, $key, $value, $quotationChars)
    {
        $marker = \is_numeric($key)
            ? '?'
            : (\preg_match('/^:/', $key)
                ? $key
                : ':' . $key);
        $matchRule = '/(' . $marker . '(?!\w))'
            . '(?='
            . '(?:[^' . $quotationChars . ']|'
            . '[' . $quotationChars . '][^' . $quotationChars . ']*[' . $quotationChars . ']'
            . ')*$)/';
        do {
            $sql = \preg_replace($matchRule, $value, $sql, 1);
        } while (\mb_substr_count($sql, $key));
        return $sql;
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
                SELECT\s+(?P<select>.*?)\s+FROM\s+\S+|
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
     * Log duration & memory usage
     *
     * @return void
     */
    private function logDurationMemory()
    {
        if ($this->duration !== null) {
            $this->debug->time('duration', $this->duration);
        }
        if ($this->memoryUsage !== null) {
            $this->debug->log('memory usage', $this->debug->utility->getBytes($this->memoryUsage));
        }
    }

    /**
     * Log statement bound params
     *
     * @return void
     */
    protected function logParams()
    {
        if (!$this->params) {
            return;
        }
        if (!$this->types) {
            $this->debug->log('parameters', $this->params);
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
        $this->debug->table('parameters', $params);
    }

    /**
     * Log the sql query
     *
     * @param string $label The abbrev'd sql statement
     *
     * @return void
     */
    private function logQuery($label)
    {
        if (\preg_replace('/[\r\n\s]+/', ' ', $this->sql) === $label) {
            return;
        }
        $sqlPretty = $this->debug->prettify($this->sql, 'application/sql');
        if ($sqlPretty instanceof Abstraction) {
            $this->prettified = $sqlPretty['prettified'];
            $sqlPretty['prettifiedTag'] = false; // don't add "(prettified)" to output"
        }
        $this->debug->log(
            $sqlPretty,
            $this->debug->meta(array(
                'attribs' => array(
                    'class' => 'no-indent',
                ),
            ))
        );
    }

    /**
     * Find common query performance issues
     *
     * @return void
     *
     * @link https://github.com/rap2hpoutre/mysql-xplain-xplain/blob/master/app/Explainer.php
     */
    protected function performQueryAnalysis()
    {
        \array_map(array($this, 'performQueryAnalysisTest'), array(
            array(\preg_match('/^\s*SELECT\s*`?[a-zA-Z0-9]*`?\.?\*/i', $this->sql) === 1,
                'Use %cSELECT *%c only if you need all columns from table',
            ),
            array(\stripos($this->sql, 'ORDER BY RAND()') !== false,
                '%cORDER BY RAND()%c is slow, avoid if you can.',
            ),
            array(\strpos($this->sql, '!=') !== false,
                'The %c!=%c operator is not standard. Use the %c<>%c operator instead.',
            ),
            array(\preg_match('/^SELECT\s/i', $this->sql) && \stripos($this->sql, 'WHERE') === false,
                'The %cSELECT%c statement has no %cWHERE%c clause and could examine many more rows than intended',
            ),
            function () {
                $matches = array();
                return \preg_match('/LIKE\s+[\'"](%.*?)[\'"]/i', $this->sql, $matches)
                    ? 'An argument has a leading wildcard character: %c' . $matches[1] . '%c and cannot use an index if one exists.'
                    : false;
            },
            array(\preg_match('/LIMIT\s/i', $this->sql) && \stripos($this->sql, 'ORDER BY') === false,
                '%cLIMIT%c without %cORDER BY%c causes non-deterministic results',
            ),
        ));
    }

    /**
     * Process query analysys test and log result if test fails
     *
     * @param array|closure $test query test
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function performQueryAnalysisTest($test)
    {
        if ($test instanceof Closure) {
            $test = $test();
            $test = array(
                $test,
                $test,
            );
        }
        if ($test[0] === false) {
            return;
        }
        $params = array(
            $test[1],
        );
        $cCount = \substr_count($params[0], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace';
            $params[] = '';
        }
        $params[] = $this->debug->meta('uncollapse', false);
        \call_user_func_array(array($this->debug, 'warn'), $params);
    }

    /**
     * Set PDO & Doctrine constants as a static val => constName array
     *
     * @return void
     */
    private function setConstants()
    {
        $this->setConstantsPdo();
        if (\class_exists('Doctrine\\DBAL\\Connection')) {
            self::$constants += array(
                \Doctrine\DBAL\Connection::PARAM_INT_ARRAY => 'Doctrine\\DBAL\\Connection::PARAM_INT_ARRAY',
                \Doctrine\DBAL\Connection::PARAM_STR_ARRAY => 'Doctrine\\DBAL\\Connection::PARAM_STR_ARRAY',
            );
        }
    }

    /**
     * Set PDO constants as a static val => constName array
     *
     * @return void
     */
    private function setConstantsPdo()
    {
        $consts = array();
        $pdoConsts = array();
        /** @psalm-suppress ArgumentTypeCoercion ignore expects class-string */
        if (\class_exists('PDO')) {
            $ref = new \ReflectionClass('PDO');
            $pdoConsts = $ref->getConstants();
        }
        foreach ($pdoConsts as $name => $val) {
            if (\strpos($name, 'PARAM_') === 0 && \strpos($name, 'PARAM_EVT_') !== 0) {
                $consts[$val] = 'PDO::' . $name;
            }
        }
        self::$constants += $consts;
    }
}
