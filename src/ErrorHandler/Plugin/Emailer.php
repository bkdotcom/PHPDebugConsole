<?php

/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.2
 */

namespace bdk\ErrorHandler\Plugin;

use bdk\ErrorHandler;
use bdk\ErrorHandler\AbstractComponent;
use bdk\ErrorHandler\Error;
use bdk\PubSub\Manager as EventManager;
use bdk\PubSub\SubscriberInterface;

/**
 * Email error details on error
 *
 * Emails an error report on error and throttles said email so does not excessively send email
 *
 * @property bool $isCli
 */
class Emailer extends AbstractComponent implements SubscriberInterface
{
    /** @var bdk\ErrorHandler\Plugin\Stats */
    private $stats = null;
    protected $serverParams = array();

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
        );
        $this->setCfg($cfg);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscriptions()
    {
        return array(
            ErrorHandler::EVENT_ERROR => array(
                array('onErrorHighPri', PHP_INT_MAX - 1),
                array('onErrorLowPri', PHP_INT_MAX * -1 + 1),
            ),
            EventManager::EVENT_PHP_SHUTDOWN => 'onPhpShutdown',
        );
    }

    /**
     * Initialize error's email (bool) value
     *
     * This function should come after stats added to error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorHighPri(Error $error)
    {
        $error['email'] = ($error['type'] & $this->cfg['emailMask'])
            && $error['isFirstOccur']
            && $this->cfg['emailTo'];
        $error['stats'] = \array_merge(array(
            'email' => array(
                'countSince' => 0,
                'emailedTo'  => null,
                'timestamp'  => null,
            ),
        ), $error['stats']);
        $tsCutoff = \time() - $this->cfg['emailMin'] * 60;
        if ($error['stats']['email']['timestamp'] > $tsCutoff) {
            // This error was recently emailed
            $error['stats']['email']['countSince']++;
        }
    }

    /**
     * Conditionally email error
     *
     * @param Error $error Error instance
     *
     * @return void
     */
    public function onErrorLowPri(Error $error)
    {
        if ($this->stats === null) {
            $this->stats = $error->getSubject()->stats;
        }
        if ($error['throw']) {
            $error['email'] = false;
        }
        if ($error['email'] && $this->cfg['emailMin'] > 0) {
            $tsCutoff = \time() - $this->cfg['emailMin'] * 60;
            $error['email'] = $error['stats']['email']['timestamp'] <= $tsCutoff;
        }
        if ($error['email']) {
            $this->emailErr($error);
            $error['stats']['email']['emailedTo'] = $this->cfg['emailTo'];
            $error['stats']['email']['timestamp'] = \time();
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
        if ($this->cfg['emailThrottledSummary'] === false) {
            return;
        }
        if ($this->stats === null) {
            return;
        }
        $summaryErrors = $this->stats->getSummaryErrors();
        if (\count($summaryErrors) === 0) {
            return;
        }
        $this->email(
            $this->cfg['emailTo'],
            $this->isCli
                ? 'Server Errors: ' . \implode(' ', $this->serverParams['argv'])
                : 'Website Errors: ' . $this->serverParams['SERVER_NAME'],
            $this->buildBodySummary($summaryErrors)
        );
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
        $emailBody = \implode("\n", array(
            'datetime: ' . \date($this->cfg['dateTimeFmt']),
            'type: ' . $error['type'] . ' (' . $error['typeStr'] . ')',
            'message: ' . $error->getMessageText(),
            'file: ' . $error['file'],
            'line: ' . $error['line'],
        )) . "\n";
        if ($this->isCli === false) {
            $emailBody .= \implode("\n", array(
                'remote_addr: ' . $this->serverParams['REMOTE_ADDR'],
                'http_host: ' . $this->serverParams['HTTP_HOST'],
                'referer: ' . (isset($this->serverParams['HTTP_REFERER']) ? $this->serverParams['HTTP_REFERER'] : 'null'),
                'request_uri: ' . $this->serverParams['REQUEST_URI'],
            )) . "\n";
        }
        if (!empty($_POST)) {
            $emailBody .= 'post params: ' . \var_export($_POST, true) . "\n";
        }
        if ($error['type'] & $this->cfg['emailTraceMask']) {
            $backtraceStr = $this->backtraceStr($error);
            $emailBody .= "\n" . ($backtraceStr
                ? 'backtrace: ' . $backtraceStr
                : 'no backtrace');
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
        foreach ($errors as $errStats) {
            $dateLastEmailed = \date($this->cfg['dateTimeFmt'], $errStats['email']['timestamp']) ?: '??';
            $info = $errStats['info'];
            $emailBody .= ''
                . 'File: ' . $info['file'] . "\n"
                . 'Line: ' . $info['line'] . "\n"
                . 'Error: ' . Error::typeStr($info['type']) . ': ' . $info['message'] . "\n"
                . 'Has occured ' . $errStats['email']['countSince'] . ' times since ' . $dateLastEmailed . "\n\n";
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
        $countSince = $error['stats']['email']['countSince'];
        $emailBody = '';
        if (!empty($countSince)) {
            $dateTimePrev = \date($this->cfg['dateTimeFmt'], $error['stats']['email']['timestamp']) ?: '';
            $emailBody .= 'Error has occurred ' . $countSince . ' times since last email (' . $dateTimePrev . ').' . "\n\n";
        }
        $emailBody .= $this->buildBodyError($error);
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
        $countSince = $error['stats']['email']['countSince'];
        $subject = $this->isCli
            ? 'Error: ' . \implode(' ', $this->serverParams['argv'])
            : 'Website Error: ' . $this->serverParams['SERVER_NAME'];
        $subject .= ': ' . $error->getMessageText() . ($countSince ? ' (' . $countSince . 'x)' : '');
        return $subject;
    }

    /**
     * Is script running from command line (or cron)?
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    protected function isCli()
    {
        $valsDefault = array(
            'argv' => null,
            'QUERY_STRING' => null,
        );
        $vals = \array_merge($valsDefault, \array_intersect_key($this->serverParams, $valsDefault));
        return $vals['argv'] && \implode('+', $vals['argv']) !== $vals['QUERY_STRING'];
    }
}
