<?php
/**
 * @package   bdk\ErrorHandler
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\ErrorHandler;

use bdk\PubSub\Event;
use bdk\PubSub\SubscriberInterface;

/**
 * Email error details on error
 *
 * Emails an error report on error and throttles said email so does not excessively send email
 */
class ErrorEmailer implements SubscriberInterface
{

    protected $cfg = array();
    protected $throttleData = array();
    protected $errTypes = array();  // populated onError

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        $this->cfg = array(
            'emailBacktraceDumper' => null, // callable that receives backtrace array & returns string
            'emailFrom' => null,            // null = use php's default (php.ini: sendmail_from)
            'emailFunc' => 'mail',
            'emailMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'emailMin' => 15,               // 0 = no throttle
            'emailThrottledSummary' => true,    // if errors have been throttled, should we email a summary email of throttled errors?
                                                //    (first occurance of error is never throttled)
            'emailThrottleFile' => __DIR__.'/error_emails.json',
            'emailThrottleRead' => null,    // callable that returns throttle data
            'emailThrottleWrite' => null,   // callable that writes throttle data.  receives single array param
            'emailTo' => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'emailTraceMask' => E_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
        );
        $this->setCfg($cfg);
        return;
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
            'errorHandler.error' => array('onErrorHighPri', PHP_INT_MAX),
            'errorHandler.error' => array('onErrorLowPri', PHP_INT_MAX * -1),
        );
    }

    /**
     * load throttle stats for passed error
     *
     * @param Event $error error event
     *
     * @return void
     */
    public function onErrorHighPri(Event $error)
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
        if (isset($this->throttleData['errors'][$hash])) {
            $stats = \array_intersect_key($this->throttleData['errors'][$hash], $error['stats']);
            $error['stats'] = \array_merge($error['stats'], $stats);
        }
        return;
    }

    /**
     * Email error
     *
     * @param Event $error error event
     *
     * @return void
     */
    public function onErrorLowPri(Event $error)
    {
        if ($error['email'] && $this->cfg['emailMin'] > 0) {
            $throttleSuccess = $this->throttleDataSet($error);
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
     * If setting a single value via method a or b, old value is returned
     *
     *    setCfg('key', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $mixed  key=>value array or key
     * @param mixed  $newVal value
     *
     * @return mixed
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
     * @param Event $error error event
     *
     * @return string
     */
    protected function backtraceStr(Event $error)
    {
        $backtrace = $error['backtrace']
            ? $error['backtrace'] // backtrace provided
            : $error->getSubject()->backtrace();
        if (\count($backtrace) < 2) {
            return '';
        }
        if ($backtrace && $error['vars']) {
            $backtrace[0]['vars'] = $error['vars'];
        }
        if ($this->cfg['emailBacktraceDumper']) {
            $str = \call_user_func($this->cfg['emailBacktraceDumper'], $backtrace);
        } else {
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
        }
        return $str;
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
            $addHeadersStr .= 'From: '.$fromAddr;
        }
        \call_user_func($this->cfg['emailFunc'], $toAddr, $subject, $body, $addHeadersStr);
    }

    /**
     * Email this error
     *
     * @param Event $error error event
     *
     * @return void
     */
    protected function emailErr(Event $error)
    {
        $dateTimeFmt = 'Y-m-d H:i:s (T)';
        $errMsg = $error['message'];
        if ($error['isHtml']) {
            $errMsg = \strip_tags($errMsg);
            $errMsg = \htmlspecialchars_decode($errMsg);
        }
        $countSince = $error['stats']['countSince'];
        $isCli = $this->isCli();
        $subject = $isCli
            ? 'Error: '.\implode(' ', $_SERVER['argv'])
            : 'Website Error: '.$_SERVER['SERVER_NAME'];
        $subject .= ': '.$errMsg.($countSince ? ' ('.$countSince.'x)' : '');
        $emailBody = '';
        if (!empty($countSince)) {
            $dateTimePrev = \date($dateTimeFmt, $error['stats']['tsEmailed']);
            $emailBody .= 'Error has occurred '.$countSince.' times since last email ('.$dateTimePrev.').'."\n\n";
        }
        $emailBody .= ''
            .'datetime: '.\date($dateTimeFmt)."\n"
            .'errormsg: '.$errMsg."\n"
            .'errortype: '.$error['type'].' ('.$error['typeStr'].')'."\n"
            .'file: '.$error['file']."\n"
            .'line: '.$error['line']."\n"
            .'';
        if (!$isCli) {
            $emailBody .= ''
                .'remote_addr: '.$_SERVER['REMOTE_ADDR']."\n"
                .'http_host: '.$_SERVER['HTTP_HOST']."\n"
                .'referer: '.(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'null')."\n"
                .'request_uri: '.$_SERVER['REQUEST_URI']."\n"
                .'';
        }
        if (!empty($_POST)) {
            $emailBody .= 'post params: '.\var_export($_POST, true)."\n";
        }
        if ($error['type'] & $this->cfg['emailTraceMask']) {
            $backtraceStr = $this->backtraceStr($error);
            $emailBody .= "\n".($backtraceStr
                ? 'backtrace: '.$backtraceStr
                : 'no backtrace');
        }
        $this->email($this->cfg['emailTo'], $subject, $emailBody);
        return;
    }

    /**
     * Write string to file / creates file if doesn't exist
     *
     * @param string $file filepath
     * @param string $str  string to write
     *
     * @return integer|boolean number of bytes written or false on error
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
     * Is script running from command line (or cron)?
     *
     * @return boolean
     */
    protected static function isCli()
    {
        return \defined('STDIN') || isset($_SERVER['argv']) && \count($_SERVER['argv']) > 1 || !\array_key_exists('REQUEST_METHOD', $_SERVER);
    }

    /**
     * Remove errors in throttleData that haven't occured recently
     * If error(s) have occured since they were last emailed, a summary email will be sent
     *
     * @return void
     */
    protected function throttleDataGarbageCollection()
    {
        $tsNow     = \time();
        $tsCutoff  = $tsNow - $this->cfg['emailMin'] * 60;
        if ($this->throttleData['tsGarbageCollection'] > $tsCutoff) {
            // we've recently performed garbage collection
            return;
        }
        // garbage collection time
        $emailBody = '';
        $sendEmailSummary = false;
        $this->throttleData['tsGarbageCollection'] = $tsNow;
        foreach ($this->throttleData['errors'] as $k => $err) {
            if ($err['tsEmailed'] > $tsCutoff) {
                continue;
            }
            // it's been a while since this error was emailed
            if ($err['emailedTo'] != $this->cfg['emailTo']) {
                // it was emailed to a different address
                if ($err['countSince'] < 1 || $err['tsEmailed'] < $tsNow - 60*60*24) {
                    unset($this->throttleData['errors'][$k]);
                }
                continue;
            }
            unset($this->throttleData['errors'][$k]);
            if ($err['countSince'] > 0) {
                $dateLastEmailed = \date('Y-m-d H:i:s', $err['tsEmailed']);
                $emailBody .= ''
                    .'File: '.$err['file']."\n"
                    .'Line: '.$err['line']."\n"
                    .'Error: '.$this->errTypes[ $err['errType'] ].': '.$err['errMsg']."\n"
                    .'Has occured '.$err['countSince'].' times since '.$dateLastEmailed."\n\n";
                $sendEmailSummary = $this->cfg['emailThrottledSummary'];
            }
        }
        if ($sendEmailSummary) {
            $this->email($this->cfg['emailTo'], 'Website Errors: '.$_SERVER['SERVER_NAME'], $emailBody);
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
        $throttleData = array();
        if ($this->cfg['emailThrottleRead'] && \is_callable($this->cfg['emailThrottleRead'])) {
            $throttleData = \call_user_func($this->cfg['emailThrottleRead']);
        } elseif ($this->cfg['emailThrottleFile'] && \is_readable($this->cfg['emailThrottleFile'])) {
            $throttleData = \file_get_contents($this->cfg['emailThrottleFile']);
            $throttleData = \json_decode($throttleData, true);
        }
        if (!\is_array($throttleData)) {
            $throttleData = array();
        }
        $this->throttleData = \array_merge(array(
            'tsGarbageCollection' => \time(),
            'errors' => array(),
        ), $throttleData);
        return;
    }

    /**
     * Adds/Updates this error's throttle data
     *
     * @param Event $error error event
     *
     * @return boolean
     */
    protected function throttleDataSet(Event $error)
    {
        $tsNow = \time();
        $hash = $error['hash'];
        $tsCutoff = $tsNow - $this->cfg['emailMin'] * 60;
        if ($error['stats']['tsEmailed'] > $tsCutoff) {
            // This error was recently emailed
            $this->throttleData['errors'][$hash]['countSince']++;
        } else {
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
        if (empty($this->errTypes)) {
            $this->errTypes = $error->getSubject()->get('errTypes');
        }
        return $this->throttleDataWrite();
    }

    /**
     * Export/Save/Write throttle data
     *
     * Uses cfg[emailThrottleWrite] callable if set, otherwise, writes to cfg['emailThrottleFile']
     *
     * @return boolean
     */
    protected function throttleDataWrite()
    {
        $return = true;
        $this->throttleDataGarbageCollection();
        if ($this->cfg['emailThrottleWrite'] && \is_callable($this->cfg['emailThrottleWrite'])) {
            $return = \call_user_func($this->cfg[''], $this->throttleData);
            if (!$return) {
                \error_log('ErrorEmailer: emailThrottleWrite() returned false');
            }
        } elseif ($this->cfg['emailThrottleFile']) {
            $wrote = $this->fileWrite($this->cfg['emailThrottleFile'], \json_encode($this->throttleData, JSON_PRETTY_PRINT));
            if (!$wrote) {
                $return = false;
                \error_log('Unable to write to '.$this->cfg['emailThrottleFile']);
            }
        }
        return $return;
    }
}
