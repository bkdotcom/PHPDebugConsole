<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.1
 */

namespace bdk\ErrorHandler;

use bdk\ErrorHandler;
use bdk\ErrorHandler\Error;
use bdk\PubSub\SubscriberInterface;

/**
 * Email error details on error
 *
 * Emails an error report on error and throttles said email so does not excessively send email
 */
class ErrorEmailer implements SubscriberInterface
{

    /** @var array */
    protected $cfg = array();
    protected $throttleData = array();
    protected $errTypes = array();  // populated onError
    protected $serverParams = array();

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        $this->serverParams = $_SERVER;
        $this->cfg = array(
            'emailBacktraceDumper' => null, // callable that receives backtrace array & returns string
            'emailFrom' => null,            // null = use php's default (php.ini: sendmail_from)
            'emailFunc' => 'mail',
            'emailMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'emailMin' => 60,               // 0 = no throttle
            'emailThrottledSummary' => true,    // if errors have been throttled, should we email a summary email of throttled errors?
                                                //    (first occurance of error is never throttled)
            'emailThrottleFile' => __DIR__ . '/error_emails.json',
            'emailThrottleRead' => array($this, 'throttleDataReader'),    // callable that returns throttle data
            'emailThrottleWrite' => array($this, 'throttleDataWriter'),   // callable that writes throttle data.  receives single array param
            'emailTo' => !empty($this->serverParams['SERVER_ADMIN'])
                ? $this->serverParams['SERVER_ADMIN']
                : null,
            'emailTraceMask' => E_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'dateTimeFmt' => 'Y-m-d H:i:s (T)',
        );
        $this->setCfg($cfg);
    }

    /**
     * Retrieve a configuration value
     *
     * @param string $key what to get
     *
     * @return mixed
     */
    public function getCfg($key = null)
    {
        if (!\strlen($key)) {
            return $this->cfg;
        }
        if (isset($this->cfg[$key])) {
            return $this->cfg[$key];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            ErrorHandler::EVENT_ERROR => array(
                array('onErrorHighPri', PHP_INT_MAX),
                array('onErrorLowPri', PHP_INT_MAX * -1),
            ),
        );
    }

    /**
     * load throttle stats for passed error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorHighPri(Error $error)
    {
        $this->throttleDataRead();
        $hash = $error['hash'];
        $error['email'] = ($error['type'] & $this->cfg['emailMask'])
            && $error['isFirstOccur']
            && $this->cfg['emailTo'];
        $error['stats'] = array(
            'tsEmailed'  => 0,
            'countSince' => 0,
            'emailedTo'  => '',
        );
        if (empty($this->errTypes)) {
            $this->errTypes = $error->getSubject()->get('errTypes');
        }
        if (isset($this->throttleData['errors'][$hash])) {
            $stats = \array_intersect_key($this->throttleData['errors'][$hash], $error['stats']);
            $error['stats'] = \array_merge($error['stats'], $stats);
        }
    }

    /**
     * Email error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorLowPri(Error $error)
    {
        if ($error['throw']) {
            $error['email'] = false;
        }
        if ($error['email'] && $this->cfg['emailMin'] > 0) {
            $this->throttleDataSet($error);
            $throttleSuccess = $this->throttleDataWrite();
            $tsCutoff = \time() - $this->cfg['emailMin'] * 60;
            $error['email'] = $throttleSuccess && $error['stats']['tsEmailed'] <= $tsCutoff;
        }
        if ($error['email']) {
            $this->emailErr($error);
        }
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
     * Clear throttle data
     *
     * @return void
     */
    public function throttleDataClear()
    {
        $this->throttleData = array(
            'tsGarbageCollection' => \time(),
            'errors' => array(),
        );
        $this->throttleDataWrite();
    }

    /**
     * Get formatted backtrace string for error
     *
     * @param Error $error Error instance
     *
     * @return string
     */
    protected function backtraceStr(Error $error)
    {
        $backtrace = $error->getTrace() ?: $error->getSubject()->backtrace->get();
        if (empty($backtrace) || \count($backtrace) < 2) {
            return '';
        }
        if ($backtrace && $error['vars']) {
            $backtrace[0]['vars'] = $error['vars'];
        }
        if ($this->cfg['emailBacktraceDumper']) {
            return \call_user_func($this->cfg['emailBacktraceDumper'], $backtrace);
        }
        $search = array(
            ")\n\n",
        );
        $replace = array(
            ")\n",
        );
        $str = \print_r($backtrace, true);
        $str = \preg_replace('#\bArray\n\(#', 'array(', $str);
        $str = \preg_replace('/\barray\s+\(\s+\)/s', 'array()', $str); // single-lineify empty arrays
        $str = \str_replace($search, $replace, $str);
        $str = \substr($str, 0, -1);
        return $str;
    }

    /**
     * Build summary of errors that haven't occured in a while
     *
     * @param array $errors errors to include in summary
     *
     * @return string
     */
    protected function buildSummaryBody($errors)
    {
        $emailBody = '';
        foreach ($errors as $err) {
            $dateLastEmailed = \date($this->cfg['dateTimeFmt'], $err['tsEmailed']) ?: '??';
            $emailBody .= ''
                . 'File: ' . $err['file'] . "\n"
                . 'Line: ' . $err['line'] . "\n"
                . 'Error: ' . $this->errTypes[ $err['errType'] ] . ': ' . $err['errMsg'] . "\n"
                . 'Has occured ' . $err['countSince'] . ' times since ' . $dateLastEmailed . "\n\n";
        }
        return $emailBody;
    }

    /**
     * Send an email
     *
     * @param string $toAddr  To
     * @param string $subject Subject
     * @param string $body    Body
     *
     * @return void
     */
    protected function email($toAddr, $subject, $body)
    {
        $addHeadersStr = '';
        $fromAddr = $this->cfg['emailFrom'];
        if ($fromAddr) {
            $addHeadersStr .= 'From: ' . $fromAddr;
        }
        \call_user_func($this->cfg['emailFunc'], $toAddr, $subject, $body, $addHeadersStr);
    }

    /**
     * Email this error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    protected function emailErr(Error $error)
    {
        $countSince = $error['stats']['countSince'];
        $emailBody = '';
        if (!empty($countSince)) {
            $dateTimePrev = \date($this->cfg['dateTimeFmt'], $error['stats']['tsEmailed']) ?: '';
            $emailBody .= 'Error has occurred ' . $countSince . ' times since last email (' . $dateTimePrev . ').' . "\n\n";
        }
        $emailBody .= ''
            . 'datetime: ' . \date($this->cfg['dateTimeFmt']) . "\n"
            . 'errormsg: ' . $error->getMessage() . "\n"
            . 'errortype: ' . $error['type'] . ' (' . $error['typeStr'] . ')' . "\n"
            . 'file: ' . $error['file'] . "\n"
            . 'line: ' . $error['line'] . "\n"
            . '';
        if ($error->getSubject()->isCli === false) {
            $emailBody .= ''
                . 'remote_addr: ' . $this->serverParams['REMOTE_ADDR'] . "\n"
                . 'http_host: ' . $this->serverParams['HTTP_HOST'] . "\n"
                . 'referer: ' . (isset($this->serverParams['HTTP_REFERER']) ? $this->serverParams['HTTP_REFERER'] : 'null') . "\n"
                . 'request_uri: ' . $this->serverParams['REQUEST_URI'] . "\n"
                . '';
        }
        if (!empty($_POST)) {
            $emailBody .= 'post params: ' . \var_export($_POST, true) . "\n";
        }
        if ($error['type'] & $this->cfg['emailTraceMask']) {
            $backtraceStr = $this->backtraceStr($error);
            $emailBody .= "\n";
            $emailBody .= $backtraceStr
                ? 'backtrace: ' . $backtraceStr
                : 'no backtrace';
        }
        $this->email(
            $this->cfg['emailTo'],
            $this->getSubject($error),
            $emailBody
        );
    }

    /**
     * Build email subject
     *
     * @param Error $error Error instance
     *
     * @return string
     */
    private function getSubject(Error $error)
    {
        $countSince = $error['stats']['countSince'];
        $subject = $error->getSubject()->isCli
            ? 'Error: ' . \implode(' ', $this->serverParams['argv'])
            : 'Website Error: ' . $this->serverParams['SERVER_NAME'];
        $subject .= ': ' . $error->getMessage() . ($countSince ? ' (' . $countSince . 'x)' : '');
        return $subject;
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
    protected function throttleDataGarbageCollection()
    {
        $sendEmailSummary = false;
        $summaryErrors = array();
        $tsNow     = \time();
        $tsCutoff  = $tsNow - $this->cfg['emailMin'] * 60;
        if ($this->throttleData['tsGarbageCollection'] > $tsCutoff) {
            // we've recently performed garbage collection
            return;
        }
        $this->throttleData['tsGarbageCollection'] = $tsNow;
        foreach ($this->throttleData['errors'] as $k => $err) {
            if ($err['tsEmailed'] > $tsCutoff) {
                continue;
            }
            // it's been a while since this error was emailed
            if ($err['emailedTo'] !== $this->cfg['emailTo']) {
                // it was emailed to a different address
                if ($err['countSince'] < 1 || $err['tsEmailed'] < $tsNow - 60 * 60 * 24) {
                    unset($this->throttleData['errors'][$k]);
                }
                continue;
            }
            unset($this->throttleData['errors'][$k]);
            if ($err['countSince'] > 0) {
                $sendEmailSummary = $this->cfg['emailThrottledSummary'];
                $summaryErrors[] = $err;
            }
        }
        if ($sendEmailSummary) {
            $this->email(
                $this->cfg['emailTo'],
                'Website Errors: ' . $this->serverParams['SERVER_NAME'],
                $this->buildSummaryBody($summaryErrors)
            );
        }
    }

    /**
     * Load & populate $this->throttleData if not alrady imported
     *
     * Uses cfg[emailThrottleRead] callable if set, otherwise, reads from cfg['emailThrottleFile']
     *
     * @return void
     */
    protected function throttleDataRead()
    {
        if ($this->throttleData) {
            // already imported
            return;
        }
        $throttleData = \is_callable($this->cfg['emailThrottleRead'])
            ? \call_user_func($this->cfg['emailThrottleRead'])
            : array();
        if (!\is_array($throttleData)) {
            $throttleData = array();
        }
        $this->throttleData = \array_merge(array(
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
    protected function throttleDataReader()
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
     * Adds/Updates this error's throttle data
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    protected function throttleDataSet(Error $error)
    {
        $hash = $error['hash'];
        $tsNow = \time();
        $tsCutoff = $tsNow - $this->cfg['emailMin'] * 60;
        if ($error['stats']['tsEmailed'] > $tsCutoff) {
            // This error was recently emailed
            $this->throttleData['errors'][$hash]['countSince']++;
            return;
        }
        // hasn't been emailed recently
        $this->throttleData['errors'][$hash] = array(
            'file'       => $error['file'],
            'line'       => $error['line'],
            'errType'    => $error['type'],
            'errMsg'     => $error['message'],
            'tsEmailed'  => $tsNow,
            'emailedTo'  => $this->cfg['emailTo'],
            'countSince' => 0,
        );
    }

    /**
     * Export/Save/Write throttle data
     *
     * Uses cfg[emailThrottleWrite] callable if set, otherwise, writes to cfg['emailThrottleFile']
     *
     * @return bool
     */
    protected function throttleDataWrite()
    {
        $return = false;
        $this->throttleDataGarbageCollection();
        if (\is_callable($this->cfg['emailThrottleWrite'])) {
            $return = \call_user_func($this->cfg['emailThrottleWrite'], $this->throttleData);
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
    protected function throttleDataWriter($throttleData)
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
}
