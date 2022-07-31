<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk\ErrorHandler\Plugin;

use bdk\ErrorHandler\AbstractComponent;
use bdk\ErrorHandler\Error;

/**
 * Retrieve and store stats in a single json file
 */
class StatsStoreFile extends AbstractComponent implements StatsStoreInterface
{
    protected $data = array();
    protected $summaryErrors = array();

    /**
     * Constructor
     *
     * @param array $cfg Configuration
     */
    public function __construct($cfg = array())
    {
        $this->cfg = array(
            'ttl' => 3600, // any errors that haven't occurred in this timespan are subject to garbage collection
            'errorStatsFile' => __DIR__ . '/error_stats.json',
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
                'info' => array(
                    'type' => $error['type'],
                    'message'  => $error['message'],
                    'file'    => $error['file'],
                    'line'    => $error['line'],
                ),
                'tsAdded' => $tsNow,
                'tsLastOccur' => $tsNow,
                'count' => 1,
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
            'tsGarbageCollection' => \time(),
            'errors' => array(),
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
            'tsGarbageCollection' => \time(),
            'errors' => array(),
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
        $return = false;
        if ($this->cfg['errorStatsFile']) {
            $wrote = $this->fileWrite($this->cfg['errorStatsFile'], \json_encode($this->data, JSON_PRETTY_PRINT));
            if ($wrote !== false) {
                $return = true;
            }
        }
        if ($return === false) {
            \error_log(\sprintf(
                __METHOD__ . ': error writing data %s',
                $this->cfg['errorStatsFile']
                    ? 'to ' . $this->cfg['errorStatsFile']
                    : '(no errorStatsFile specified)'
            ));
        }
        return $return;
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
        if (\is_writable($file) || \file_exists($file) === false && \is_writeable($dir)) {
            $return = \file_put_contents($file, $str);
        }
        return $return;
    }

    /**
     * Remove errors from data that haven't occured recently
     * If error(s) have occured since they were last emailed, a summary email may be sent
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
     * Remove from stats data if hasn't occured recently
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
        // determine if error has occured since last notification
        //   is so, add to summaryErrors
        foreach ($errorStats as $val) {
            if (\is_array($val) && !empty($val['countSince'])) {
                $this->summaryErrors[] = $errorStats;
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)     */
    protected function postSetCfg($cfg = array(), $prev = array())
    {
        if (isset($cfg['errorStatsFile']) && $cfg['errorStatsFile'] !== $prev['errorStatsFile']) {
            $this->dataRead();
        }
    }
}
