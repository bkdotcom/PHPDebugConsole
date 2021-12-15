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
use bdk\ErrorHandler\ErrorEmailerThrottle;
use bdk\PubSub\Manager as EventManager;
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
    protected $errTypes = array();  // populated onError
    protected $serverParams = array();
    protected $throttle;

    /**
     * Constructor
     *
     * @param array $cfg config
     *
     * @SuppressWarnings(PHPMD.Superglobals)
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
            'emailTo' => !empty($this->serverParams['SERVER_ADMIN'])
                ? $this->serverParams['SERVER_ADMIN']
                : null,
            'emailTraceMask' => E_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'dateTimeFmt' => 'Y-m-d H:i:s T',
            // throttle options
            'emailThrottleFile' => __DIR__ . '/error_emails.json',
            'emailThrottleRead' => null,    // callable that returns throttle data
            'emailThrottleWrite' => null,   // callable that writes throttle data.  receives single array param
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
            EventManager::EVENT_PHP_SHUTDOWN => array('onPhpShutdown'),
        );
    }

    /**
     * Add throttle stats to passed error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorHighPri(Error $error)
    {
        if (!$this->throttle) {
            $this->throttleInit();
        }
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
        $statsThrottle = $this->throttle->errorGet($error);
        if ($statsThrottle) {
            $stats = \array_intersect_key($statsThrottle, $error['stats']);
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
            $success = $this->throttle->errorAdd($error);
            $tsCutoff = \time() - $this->cfg['emailMin'] * 60;
            $error['email'] = $success && $error['stats']['tsEmailed'] <= $tsCutoff;
        }
        if ($error['email']) {
            $this->emailErr($error);
        }
    }

    /**
     * Php shutdown event listener
     * Send a summary of errors that have not occured recently, but have occured since notification
     *
     * @return void
     */
    public function onPhpShutdown()
    {
        if (!$this->throttle) {
            return;
        }
        $summaryErrors = $this->throttle->getSummaryErrors();
        if (\count($summaryErrors) > 0  && $this->cfg['emailThrottledSummary']) {
            $this->email(
                $this->cfg['emailTo'],
                'Website Errors: ' . $this->serverParams['SERVER_NAME'],
                $this->buildBodySummary($summaryErrors)
            );
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
            $mixed = array($mixed => $newVal);
        } elseif (\is_array($mixed)) {
            $ret = \array_intersect_key($this->cfg, $mixed);
        }
        $this->cfg = \array_merge($this->cfg, $mixed);
        if (isset($this->throttle)) {
            $throttleCfg = $this->throttleCfg($mixed);
            $this->throttle->setCfg($throttleCfg);
        }
        return $ret;
    }

    /**
     * Get formatted backtrace string for error
     *
     * @param Error $error Error instance
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     */
    protected function backtraceStr(Error $error)
    {
        $backtrace = $error->getTrace() ?: $error->getSubject()->backtrace->get();
        if (empty($backtrace) || \count($backtrace) < 2) {
            return '';
        }
        if ($error['vars']) {
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
     * Build error email body
     *
     * @param Error $error Error instance
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function buildBodyError(Error $error)
    {
        $emailBody = ''
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
        return $emailBody;
    }

    /**
     * Build summary of errors that haven't occured in a while
     *
     * @param array $errors errors to include in summary
     *
     * @return string
     */
    protected function buildBodySummary($errors)
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
        $emailBody .= $this->buildBodyError($error);
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
     * Get config options that apply to throttling
     *
     * @param array $cfg config options
     *
     * @return array
     */
    private function throttleCfg($cfg = array())
    {
        $cfg = \array_intersect_key($cfg ?: $this->cfg, \array_flip(array(
            'emailMin',
            'emailThrottleFile',
            'emailThrottleRead',
            'emailThrottleWrite',
            'emailTo',
        )));
        foreach (array('emailThrottleRead','emailThrottleWrite') as $key) {
            if (isset($cfg[$key]) === false) {
                unset($cfg[$key]);
            }
        }
        return $cfg;
    }

    /**
     * Initialize throttle data
     *
     * @return void
     */
    private function throttleInit()
    {
        $cfg = $this->throttleCfg();
        $this->throttle = new ErrorEmailerThrottle($cfg);
    }
}
