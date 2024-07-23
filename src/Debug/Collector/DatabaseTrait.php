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
use bdk\Debug\Collector\StatementInfo;

/**
 * Used by DoctrineLogger, MySqli, and Pdo
 */
trait DatabaseTrait
{
    /** @var list<StatementInfo> */
    protected $loggedStatements = array();

    /** @var string */
    protected $icon = 'fa fa-database';

    /**
     * Logs StatementInfo
     *
     * @param StatementInfo $info statement info instance
     *
     * @return void
     */
    public function addStatementInfo(StatementInfo $info)
    {
        $this->loggedStatements[] = $info;
        $info->appendLog($this->debug);
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
    public function getLoggedStatements()
    {
        return $this->loggedStatements;
    }

    /**
     * Log runtime information
     *
     * @param Debug $debug Debug instance
     *
     * @return void
     */
    private function logRuntime(Debug $debug)
    {
        $database = $this->currentDatabase();
        if ($database) {
            $debug->log('database', $database);
        }
        $debug->log('logged operations: ', \count($this->loggedStatements));
        $debug->time('total time', $this->getTimeSpent());
        $debug->log('max memory usage', $debug->utility->getBytes($this->getPeakMemoryUsage()));
        $debug->log('server info', $this->serverInfo());
        if ($this->prettified() === false) {
            $debug->info('install jdorn/sql-formatter to prettify logged sql statements');
        }
    }

    /**
     * Call debug method with styling
     *
     * Replace/wrap %c with style
     *
     * @param string $method  Debug method
     * @param string $message Log message
     *
     * @return void
     */
    protected function logWithStyling($method, $message)
    {
        $params = array(
            $message,
        );
        $cCount = \substr_count($params[0], '%c');
        for ($i = 0; $i < $cCount; $i += 2) {
            $params[] = 'font-family:monospace;';
            $params[] = '';
        }
        \call_user_func_array(array($this->debug, $method), $params);
    }

    /**
     * Get meta argument
     *
     * @param array $values Values to metafy
     *
     * @return array
     */
    protected function meta(array $values = array())
    {
        return $this->debug->meta(\array_merge(array(
            'icon' => $this->debug->getCfg('channelIcon', Debug::CONFIG_DEBUG),
        ), $values));
    }

    /**
     * Were attempts to prettify successful?
     *
     * @return bool
     */
    private function prettified()
    {
        $falseCount = 0;
        foreach ($this->loggedStatements as $info) {
            $prettified = $info->prettified;
            if ($prettified === true) {
                return true;
            }
            if ($prettified === false) {
                $falseCount++;
            }
        }
        return $falseCount === 0;
    }
}
