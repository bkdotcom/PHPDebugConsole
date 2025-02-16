<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.4
 */

namespace bdk\Debug\Collector;

use bdk\Debug;
use bdk\Debug\AbstractComponent;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\LogEntry;
use bdk\HttpMessage\Utility\ContentType;
use bdk\PubSub\Event;

/**
 * Log sql statement info
 */
class StatementInfoLogger extends AbstractComponent
{
    /** @var array<string,mixed> */
    protected $cfg = array(
        'slowQueryDurationMs' => 500, // milliseconds
    );

    /** @var array<int,string> */
    protected static $constants = array();

    /** @var Debug */
    protected $debug;

    /** @var StatementInfo */
    protected $info;

    /** @var int */
    protected static $id = 0;

    /** @var list<StatementInfo> */
    protected $loggedStatements = array();

    /** @var int */
    private $prettifyErrors = 0;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     * @param array $cfg   Configuration
     */
    public function __construct(Debug $debug, array $cfg = array())
    {
        $this->debug = $debug;
        $cfgDefault = \array_intersect_key($debug->getCfg(null, Debug::CONFIG_DEBUG), $this->cfg);
        $cfg = \array_merge($cfgDefault, $cfg);
        $this->setCfg($cfg);
        if (!self::$constants) {
            $this->setConstants();
        }
        $this->debug->eventManager->subscribe(Debug::EVENT_CONFIG, [$this, 'onConfig']);
    }

    /**
     * Returns the accumulated execution time of statements
     *
     * @return float
     */
    public function getTimeSpent()
    {
        return \array_reduce($this->loggedStatements, static function ($val, StatementInfo $info) {
            return $val + $info->duration;
        });
    }

    /**
     * Returns the peak memory usage while performing statements
     *
     * @return int
     */
    public function getPeakMemoryUsage()
    {
        return \array_reduce($this->loggedStatements, static function ($carry, StatementInfo $info) {
            $mem = $info->memoryUsage;
            return $mem > $carry
                ? $mem
                : $carry;
        });
    }

    /**
     * Returns the list of executed statements as StatementInfo objects
     *
     * @return StatementInfo[]
     */
    public function getLoggedCount()
    {
        return \count($this->loggedStatements);
    }

    /**
     * Returns the list of executed statements as StatementInfo objects
     *
     * @return StatementInfo[]
     */
    public function getLoggedStatements()
    {
        return $this->loggedStatements;
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
     * Add statement info to debug log
     *
     * @param StatementInfo $info         StatementInfo instance
     * @param array         $metaOverride (optional) group meta values
     *
     * @return void
     */
    public function log(StatementInfo $info, array $metaOverride = array())
    {
        $this->info = $info;
        $this->loggedStatements[] = $info;
        $label = $this->getGroupLabel();
        $this->debug->groupCollapsed($label, $this->debug->meta(\array_merge(array(
            'boldLabel' => false,
            'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
            'id' => 'statementInfo' . (++ self::$id),
        ), $metaOverride)));
        $this->logQuery($label);
        $this->logParams();
        $this->logDurationMemory();
        $this->performQueryAnalysis();
        if ($info->exception) {
            $code = $info->exception->getCode();
            $msg = $info->exception->getMessage();
            if (\strpos($msg, (string) $code) === false) {
                $msg .= ' (code ' . $code . ')';
            }
            $this->debug->warn(\get_class($info->exception) . ': ' . \trim($msg));
            $this->debug->groupUncollapse();
        } elseif ($info->rowCount !== null) {
            $this->debug->log('rowCount', $info->rowCount);
        }
        $this->logSlowQuery();
        $this->debug->groupEnd();
    }

    /**
     * Debug::EVENT_CONFIG subscriber
     *
     * @param Event $event Event instance
     *
     * @return void
     */
    public function onConfig(Event $event)
    {
        if (!$event['isTarget'] || !$event['debug']) {
            return;
        }
        $cfg = \array_intersect_key($event['debug'], $this->cfg);
        $this->setCfg($cfg);
    }

    /**
     * Were attempts to prettify successful?
     *
     * @return bool
     */
    public function prettified()
    {
        return $this->prettifyErrors === 0;
    }

    /**
     * Get group's label
     *
     * @return string
     */
    private function getGroupLabel()
    {
        $label = \preg_replace('/[\r\n\s]+/', ' ', $this->info->sql);
        $label = $this->debug->sql->replaceParams($label, $this->info->params);
        $parsed = $this->debug->sql->parse($label);
        if ($parsed === false) {
            return $label;
        }
        $label = $parsed['method']; // method + table
        $afterWhereKeys = ['groupBy', 'having', 'window', 'orderBy', 'limit', 'for'];
        $afterWhereValues = \array_intersect_key($parsed, \array_flip($afterWhereKeys));
        $haveMore = \count($afterWhereValues) > 0;
        if ($parsed['where'] && \strlen($parsed['where']) < 35) {
            $label .= $parsed['afterMethod'] ? ' (…)' : '';
            $label .= ' WHERE ' . $parsed['where'];
        } elseif (\array_filter([$parsed['afterMethod'], $parsed['where']])) {
            $haveMore = true;
        }
        if ($haveMore) {
            $label .= '…';
        }
        if (\strlen($label) > 100 && $parsed['select']) {
            $label = \str_replace($parsed['select'], ' (…)', $label);
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
        if ($this->info->duration !== null) {
            $this->debug->time('duration', $this->info->duration, $this->debug->meta(
                'level',
                $this->info->duration * 1000 >= $this->cfg['slowQueryDurationMs']
                    ? 'warn' // highlight duration for slow queries
                    : null
            ));
        }
        if ($this->info->memoryUsage !== null) {
            $memory = $this->debug->utility->getBytes($this->info->memoryUsage);
            $this->debug->log('memory usage', $memory);
        }
    }

    /**
     * Log statement bound params
     *
     * @return void
     */
    protected function logParams()
    {
        if (!$this->info->params) {
            return;
        }
        $this->info->types
            ? $this->logParamsTypes()
            : $this->debug->log('parameters', $this->info->params);
    }

    /**
     * Log params with types as table
     *
     * @return void
     */
    private function logParamsTypes()
    {
        $params = array();
        foreach ($this->info->params as $name => $value) {
            $params[$name] = array(
                'value' => $value,
            );
            if (!isset($this->info->types[$name])) {
                continue;
            }
            $type = $this->info->types[$name];
            $isIntOrString = \is_int($type) || \is_string($type);
            $params[$name]['type'] = $isIntOrString && isset(self::$constants[$type])
                ? new Abstraction(Type::TYPE_IDENTIFIER, array(
                    'backedValue' => $type,
                    'typeMore' => Type::TYPE_IDENTIFIER_CONST,
                    'value' => self::$constants[$type],
                ))
                : $type; // integer value (or enum)
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
        if (\preg_replace('/[\r\n\s]+/', ' ', $this->info->sql) === $label) {
            return;
        }
        $stringMaxLenBak = $this->debug->setCfg('stringMaxLen', -1, Debug::CONFIG_NO_PUBLISH);
        $sqlPretty = $this->debug->prettify($this->info->sql, ContentType::SQL);
        $isPrettified = $sqlPretty instanceof Abstraction;
        $this->prettifyErrors += $isPrettified ? 0 : 1;
        if ($isPrettified) {
            $sqlPretty['prettifiedTag'] = false; // don't add "(prettified)" to output
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
     * "Flag" slow queries
     *
     * @return void
     */
    private function logSlowQuery()
    {
        if ($this->info->duration * 1000 < $this->cfg['slowQueryDurationMs']) {
            return;
        }
        $this->debug->log(new LogEntry(
            $this->debug,
            'groupEndValue',
            [$this->debug->abstracter->crateWithVals('slow', array(
                'attribs' => array('class' => 'badge bg-warn fw-bold'),
            ))],
            array(
                'level' => 'warn',
            )
        ));
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
        $this->debug->sqlQueryAnalysis->analyze($this->info->sql);
    }

    /**
     * Set PDO & Doctrine constants as a static val => constName array
     *
     * @return void
     */
    private function setConstants()
    {
        $this->setConstantsPdo();
        if (\defined('Doctrine\\DBAL\\Connection::PARAM_INT_ARRAY')) {
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
