<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\HttpMessage\Utility\ContentType;
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
    /** @var Debug */
    protected $debug;

    /** @var float|null */
    protected $duration;
    /** @var Exception|null */
    protected $exception;
    /** @var bool */
    protected $isSuccess = false;
    /** @var float|null */
    protected $memoryEnd;
    /** @var float */
    protected $memoryStart;
    /** @var int|null */
    protected $memoryUsage;
    /** @var array|null */
    protected $params;
    /** @var string|null */
    protected $prettified = null;
    /** @var int|null */
    protected $rowCount;
    /** @var string */
    protected $sql;
    /** @var float|null */
    protected $timeEnd;
    /** @var float */
    protected $timeStart;
    /** @var array|null */
    protected $types;

    /** @var list<string> */
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

    /** @var array<int,string> */
    protected static $constants = array();

    /** @var int */
    protected static $id = 0;

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
     * @param Debug $debug        Debug instance
     * @param array $metaOverride (optional) meta override
     *
     * @return void
     */
    public function appendLog(Debug $debug, array $metaOverride = array())
    {
        $this->debug = $debug;
        $label = $this->getGroupLabel();
        $debug->groupCollapsed($label, $debug->meta(\array_merge(array(
            'boldLabel' => false,
            'icon' => $debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'id' => 'statementInfo' . (++ self::$id),
        ), $metaOverride)));
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
            $debug->groupUncollapse();
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
     * Return the value of the previously output id attribute
     *
     * @return string
     */
    public static function lastGroupId()
    {
        return 'statementInfo' . self::$id;
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
        $matches = array(
            'more' => '',
            'select' => '',
        );
        if (\preg_match($regex, $label, $matches)) {
            $label = $matches[1];
            if (\strlen($matches['select']) > 100) {
                $label = \str_replace($matches['select'], '(…)', $label);
            }
        }
        $haveMore = !empty($matches['more']);
        $label = \preg_replace('/[\r\n\s]+/', ' ', $label);
        return \strlen($label) > 100
            ? \substr($label, 0, 100) . '…'
            : $label . ($haveMore ? '…' : '');
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
        $this->types
            ? $this->logParamsTypes()
            : $this->debug->log('parameters', $this->params);
    }

    /**
     * Log params with types as table
     *
     * @return void
     */
    private function logParamsTypes()
    {
        $params = array();
        foreach ($this->params as $name => $value) {
            $params[$name] = array(
                'value' => $value,
            );
            if (!isset($this->types[$name])) {
                continue;
            }
            $type = $this->types[$name];
            $params[$name]['type'] = isset(self::$constants[$type])
                ? new Abstraction(Type::TYPE_CONST, array(
                    'name' => self::$constants[$type],
                    'value' => $type,
                ))
                : $type; // integer value
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
        $stringMaxLenBak = $this->debug->setCfg('stringMaxLen', -1, Debug::CONFIG_NO_PUBLISH);
        $sqlPretty = $this->debug->prettify($this->sql, ContentType::SQL);
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
        $this->debug->setCfg('stringMaxLen', $stringMaxLenBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
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
        $this->debug->sqlQueryAnalysis->analyze($this->sql);
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
        $constants = array();
        $pdoConstants = array();
        /** @psalm-suppress ArgumentTypeCoercion ignore expects class-string */
        if (\class_exists('PDO')) {
            $ref = new \ReflectionClass('PDO');
            $pdoConstants = $ref->getConstants();
        }
        foreach ($pdoConstants as $name => $val) {
            if (\strpos($name, 'PARAM_') === 0 && \strpos($name, 'PARAM_EVT_') !== 0) {
                $constants[$val] = 'PDO::' . $name;
            }
        }
        self::$constants += $constants;
    }
}
