<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.1
 */

namespace bdk\ErrorHandler;

use bdk\ErrorHandler\Error;

/**
 * Keep track of when errors were last emailed
 */
class ErrorEmailerThrottle
{
    protected $cfg = array();
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
            'emailMin' => 60,
            'emailThrottleFile' => __DIR__ . '/error_emails.json',
            'emailThrottleRead' => array($this, 'throttleDataReader'),    // callable that returns throttle data
            'emailThrottleWrite' => array($this, 'throttleDataWriter'),   // callable that writes throttle data.  receives single array param
            'emailTo' => !empty($this->serverParams['SERVER_ADMIN'])
                ? $this->serverParams['SERVER_ADMIN']
                : null,
        );
        $this->setCfg($cfg);
        $this->dataRead();
    }

    /**
     * Clear throttle data
     *
     * @return void
     */
    public function dataClear()
    {
        $this->data = array(
            'tsGarbageCollection' => \time(),
            'errors' => array(),
        );
        $this->dataWrite();
    }

    /**
     * Adds/Updates this error's throttle data
     *
     * @param Error $error Error instance
     *
     * @return bool
     */
    public function errorAdd(Error $error)
    {
        $hash = $error['hash'];
        $tsNow = \time();
        $tsCutoff = $tsNow - $this->cfg['emailMin'] * 60;
        if ($error['stats']['tsEmailed'] > $tsCutoff) {
            // This error was recently emailed
            $this->data['errors'][$hash]['countSince']++;
            return true;
        }
        // hasn't been emailed recently
        $this->data['errors'][$hash] = array(
            'file'       => $error['file'],
            'line'       => $error['line'],
            'errType'    => $error['type'],
            'errMsg'     => $error['message'],
            'tsEmailed'  => $tsNow,
            'emailedTo'  => $this->cfg['emailTo'],
            'countSince' => 0,
        );
        return $this->dataWrite();
    }

    /**
     * Get throttle stats for given error
     *
     * @param Error $error Error instance
     *
     * @return array
     */
    public function errorGet(Error $error)
    {
        $hash = $error['hash'];
        return isset($this->data['errors'][$hash])
            ? $this->data['errors'][$hash]
            : array();
    }

    /**
     * Return list of errors that have
     * not occured since their cutoff
     * have occured since their last email
     *
     * @return array
     */
    public function getSummaryErrors()
    {
        return $this->summaryErrors;
    }

    /**
     * Set one or more config values
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string|array $mixed  key=>value array or key
     * @param mixed        $newVal value
     *
     * @return mixed old value(s)
     */
    public function setCfg($mixed, $newVal = null)
    {
        $ret = null;
        if (\is_string($mixed)) {
            $ret = isset($this->cfg[$mixed])
                ? $this->cfg[$mixed]
                : null;
            $this->cfg[$mixed] = $newVal;
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
            $this->cfg = \array_merge($this->cfg, $mixed);
        }
        return $ret;
    }

    /**
     * Populate $this->data
     *
     * Uses cfg[emailThrottleRead] callable if set, otherwise, reads from cfg['emailThrottleFile']
     *
     * @return void
     */
    protected function dataRead()
    {
        $throttleData = \is_callable($this->cfg['emailThrottleRead'])
            ? \call_user_func($this->cfg['emailThrottleRead'])
            : array();
        if (!\is_array($throttleData)) {
            $throttleData = array();
        }
        $this->data = \array_merge(array(
            'tsGarbageCollection' => \time(),
            'errors' => array(),
        ), $throttleData);
    }

    /**
     * Read throttle data from file
     *
     * This is the default callable for reading throttle data
     *
     * @return array
     */
    protected function dataReader()
    {
        $throttleData = array();
        $file = $this->cfg['emailThrottleFile'];
        if ($file && \is_readable($file)) {
            $throttleData = \file_get_contents($file);
            $throttleData = \json_decode($throttleData, true);
        }
        return $throttleData;
    }

    /**
     * Export/Save/Write throttle data
     *
     * Uses cfg[emailThrottleWrite] callable if set, otherwise, writes to cfg['emailThrottleFile']
     *
     * @return bool
     */
    protected function dataWrite()
    {
        $return = true;
        $this->garbageCollection();
        if (\is_callable($this->cfg['emailThrottleWrite'])) {
            $return = \call_user_func($this->cfg['emailThrottleWrite'], $this->data);
            if (!$return) {
                \error_log('ErrorEmailer: emailThrottleWrite() returned false');
            }
        }
        return $return;
    }

    /**
     * Write throttle data to file
     *
     * @param array $throttleData throttle data
     *
     * @return bool
     */
    protected function dataWriter($throttleData)
    {
        $return = false;
        if ($this->cfg['emailThrottleFile']) {
            $wrote = $this->fileWrite($this->cfg['emailThrottleFile'], \json_encode($throttleData, JSON_PRETTY_PRINT));
            if ($wrote !== false) {
                $return = true;
            }
        }
        return $return;
    }

    /**
     * Check if error should be included in summary email
     * Remove from throttle data if hasn't occured recently
     *
     * @param Error $error    Error instance
     * @param int   $index    Error's index in throttleData[errors]
     * @param int   $tsNow    current time
     * @param int   $tsCutoff cfg['emailMin'] ago
     *
     * @return bool
     */
    private function errorTest(Error $error, $index, $tsNow, $tsCutoff)
    {
        if ($error['tsEmailed'] > $tsCutoff) {
            return false;
        }
        // it's been a while since this error was emailed
        if ($error['emailedTo'] !== $this->cfg['emailTo']) {
            // it was emailed to a different address
            if ($error['countSince'] < 1 || $error['tsEmailed'] < $tsNow - 60 * 60 * 24) {
                unset($this->data['errors'][$index]);
            }
            return false;
        }
        unset($this->data['errors'][$index]);
        return $error['countSince'] > 0;
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
        if (!\file_exists($dir)) {
            \mkdir($dir, 0755, true);
        }
        if (\is_writable($file) || !\file_exists($file) && \is_writeable($dir)) {
            $return = \file_put_contents($file, $str);
        }
        return $return;
    }

    /**
     * Remove errors in throttleData that haven't occured recently
     * If error(s) have occured since they were last emailed, a summary email may be sent
     *
     * @return void
     */
    protected function garbageCollection()
    {
        // $sendEmailSummary = false;
        $tsNow    = \time();
        $tsCutoff = $tsNow - $this->cfg['emailMin'] * 60;
        if ($this->data['tsGarbageCollection'] > $tsCutoff) {
            // we've recently performed garbage collection
            return;
        }
        $this->data['tsGarbageCollection'] = $tsNow;
        foreach ($this->data['errors'] as $k => $err) {
            if ($this->errorTest($err, $k, $tsNow, $tsCutoff)) {
                $this->summaryErrors[] = $err;
            }
        }
    }
}
