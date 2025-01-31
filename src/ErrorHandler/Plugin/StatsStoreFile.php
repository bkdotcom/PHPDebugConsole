<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     v3.2
 */

namespace bdk\ErrorHandler\Plugin;

use bdk\ErrorHandler\AbstractComponent;
use bdk\ErrorHandler\Error;

/**
 * Retrieve and store stats in a single json file
 *
 * @psalm-type errorStatus = array{
 *      count: int,
 *      info: array{
 *         file: string,
 *         line: int,
 *         message: string,
 *         type: int,
 *      ),
 *      tsAdded: int,
 *      tsLastOccur: int,
 *      email?: array{
 *         countSince: int,
 *         ...
 *      },
 *   }
 */
class StatsStoreFile extends AbstractComponent implements StatsStoreInterface
{
    /**
     * @var array{
     *   errors: array<string,errorStats>,
     *   tsGarbageCollection: int,
     * }
     */
    protected $data = array();

    /** @var list<errorStats> */
    protected $summaryErrors = [];

    /**
     * Constructor
     *
     * @param array $cfg Configuration
     */
    public function __construct($cfg = array())
    {
        $this->cfg = array(
            'errorStatsFile' => __DIR__ . '/error_stats.json',
            'ttl' => 3600, // any errors that haven't occurred in this timespan are subject to garbage collection
        );
        $this->setCfg($cfg);
        $this->dataRead();
    }

    /**
     * {@inheritDoc}
     */
    public function errorUpsert(Error $error)
    {
        $hash = $error['hash'];
        $tsNow = \time();
        if (isset($this->data['errors'][$hash]) === false) {
            $this->data['errors'][$hash] = array(
                'count' => 1,
                'info' => array(
                    'file'    => $error['file'],
                    'line'    => $error['line'],
                    'message'  => $error['message'],
                    'type' => $error['type'],
                ),
                'tsAdded' => $tsNow,
                'tsLastOccur' => $tsNow,
            );
        }
        $this->data['errors'][$hash] = \array_merge(
            $this->data['errors'][$hash],
            $error['stats'],
            array(
                'tsLastOccur' => $tsNow,
            )
        );
        return $this->dataWrite();
    }

    /**
     * {@inheritDoc}
     */
    public function findByError(Error $error)
    {
        return $this->findByHash($error['hash']);
    }

    /**
     * {@inheritDoc}
     */
    public function findByHash($hash)
    {
        return isset($this->data['errors'][$hash])
            ? $this->data['errors'][$hash]
            : array();
    }

    /**
     * {@inheritDoc}
     */
    public function flush()
    {
        $this->data = array(
            'errors' => array(),
            'tsGarbageCollection' => \time(),
        );
        $this->dataWrite();
    }

    /**
     * {@inheritDoc}
     */
    public function getSummaryErrors()
    {
        return $this->summaryErrors;
    }

    /**
     * Populate $this->data
     *
     * Uses cfg[errorStatsRead] callable if set, otherwise, reads from cfg['errorStatsFile']
     *
     * @return void
     */
    protected function dataRead()
    {
        $data = array();
        $file = $this->cfg['errorStatsFile'];
        if ($file && \is_readable($file)) {
            $data = \file_get_contents($file);
            $data = \json_decode($data, true);
        }
        $this->data = \array_merge(array(
            'errors' => array(),
            'tsGarbageCollection' => \time(),
        ), $data ?: array());
    }

    /**
     * Export/Save/Write stats data
     *
     * Uses cfg[errorStatsWrite] callable if set, otherwise, writes to cfg['errorStatsFile']
     *
     * @return bool false on error
     */
    protected function dataWrite()
    {
        $this->garbageCollection();
        if ($this->cfg['errorStatsFile']) {
            $wrote = $this->fileWrite($this->cfg['errorStatsFile'], \json_encode($this->data, JSON_PRETTY_PRINT));
            if ($wrote !== false) {
                return true;
            }
        }
        \error_log(\sprintf(
            __METHOD__ . ': error writing data %s',
            $this->cfg['errorStatsFile']
                ? 'to ' . $this->cfg['errorStatsFile']
                : '(no errorStatsFile specified)'
        ));
        return false;
    }

    /**
     * Write string to file / creates file if doesn't exist
     *
     * @param string $file filepath
     * @param string $str  string to write
     *
     * @return int|false number of bytes written or false on error
     */
    protected function fileWrite($file, $str)
    {
        $return = false;
        $dir = \dirname($file);
        if (\file_exists($dir) === false) {
            \mkdir($dir, 0755, true);
        }
        if (\is_writable($file) || (\file_exists($file) === false && \is_writeable($dir))) {
            $return = \file_put_contents($file, $str);
        }
        return $return;
    }

    /**
     * Remove errors from data that haven't occurred recently
     * If error(s) have occurred since they were last emailed, a summary email may be sent
     *
     * @return void
     */
    protected function garbageCollection()
    {
        $tsNow    = \time();
        $tsCutoff = $tsNow - $this->cfg['ttl'];
        if ($this->data['tsGarbageCollection'] > $tsCutoff) {
            // we've recently performed garbage collection
            return;
        }
        $this->data['tsGarbageCollection'] = $tsNow;
        foreach ($this->data['errors'] as $hash => $err) {
            $this->garbageCollectionErr($err, $hash, $tsCutoff);
        }
    }

    /**
     * Check if error should be included in summary email
     * Remove from stats data if hasn't occurred recently
     *
     * @param array  $errorStats Error instance
     * @param string $hash       Error's index in data[errors]
     * @param int    $tsCutoff   cfg['emailMin'] ago
     *
     * @return bool whether removed
     */
    private function garbageCollectionErr($errorStats, $hash, $tsCutoff)
    {
        if ($errorStats['tsLastOccur'] > $tsCutoff) {
            return false;
        }
        // it's been a while since this error was emailed
        unset($this->data['errors'][$hash]);
        // determine if error has occurred since last notification
        //   is so, add to summaryErrors
        foreach ($errorStats as $val) {
            if (\is_array($val) && !empty($val['countSince'])) {
                $this->summaryErrors[] = $errorStats;
                break;
            }
        }
        return true;
    }

    /**
     * Handle updated cfg values
     *
     * @param array $cfg  new config values
     * @param array $prev previous config values
     *
     * @return void
     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        if (isset($cfg['errorStatsFile']) && $cfg['errorStatsFile'] !== $prev['errorStatsFile']) {
            $this->dataRead();
        }
    }
}
