<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v1.4.0
 */

namespace bdk\Debug;

/**
 * Email error details on error
 *
 * Emails an error report on error and throttles said email so does not excessively send email
 */
class ErrorEmailer
{

    protected $cfg = array();
    protected $throttleData = array();

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
    {
        $this->cfg = array(
            'emailFunc' => 'mail',
            'emailMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
            'emailMin' => 15,
            'emailThrottleFile' => __DIR__.'/error_emails.json',
            'emailThrottledSummary' => true,    // if errors have been throttled, should we email a summary email of throttled errors?
                                                //    (first occurance of error is never throttled)
            'emailTo' => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'emailTraceMask' => E_WARNING | E_USER_ERROR | E_USER_NOTICE,
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
        if ($key === null) {
            return $this->cfg;
        }
        if (isset($this->cfg[$key])) {
            return $this->cfg[$key];
        }
        return null;
    }

    /**
     * Email error
     *
     * @param Event $error error event
     *
     * @return void
     */
    public function onErrorEmail(Event $error)
    {
        if ($error['email'] && $this->cfg['emailMin'] > 0) {
            $this->throttleDataSet($error);
            $tsCutoff = time() - $this->cfg['emailMin'] * 60;
            $error['email'] = $error['stats']['tsEmailed'] <= $tsCutoff;
        }
        if ($error['email']) {
            $this->emailErr($error);
        }
    }

    /**
     * load throttle stats for passed error
     *
     * @param Event $error error event
     *
     * @return void
     */
    public function onErrorAddEmailData(Event $error)
    {
        $this->throttleDataImport();
        $hash = $error['hash'];
        $error['email'] = ( $error['type'] & $this->cfg['emailMask'] )
            && $error['firstOccur']
            && $this->cfg['emailTo'];
        $error['stats'] = array(
            'tsEmailed'  => 0,
            'countSince' => 0,
            'emailedTo'  => '',
        );
        if (isset($this->throttleData['errors'][$hash])) {
            $stats = array_intersect_key($this->throttleData['errors'][$hash], $error['stats']);
            $error['stats'] = array_merge($error['stats'], $stats);
        }
        return;
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
        $values = array();
        if (is_string($mixed)) {
            $key = $mixed;
            $ret = isset($this->cfg[$key])
                ? $this->cfg[$key]
                : null;
            $values = array(
                $key => $newVal,
            );
        } elseif (is_array($mixed)) {
            $values = $mixed;
        }
        $this->cfg = array_merge($this->cfg, $values);
        return $ret;
    }

    /**
     * Get formatted backtrace string for error
     *
     * @param Event $error error event
     *
     * @return string
     */
    protected function backtraceStr($error)
    {
        /*
            backtrace:
            0: here
            1: call_user_func_array
            2: errorHandler
            3: where error occured
        */
        $backtrace = debug_backtrace(false); // no object info
        $backtrace = array_slice($backtrace, 3);
        foreach ($backtrace as $k => $frame) {
            if ($frame['file'] == $error['file'] && $frame['line'] == $error['line']) {
                $backtrace = array_slice($backtrace, $k);
                break;
            }
        }
        $backtrace[0]['vars'] = $error['vars'];
        $debug = __NAMESPACE__;
        if (class_exists($debug)) {
            $debug = $debug::getInstance();
            $str = $debug->output->outputText->dump($backtrace);
        } else {
            $search = array(
                ")\n\n",
            );
            $replace = array(
                ")\n",
            );
            $str = print_r($backtrace, true);
            $str = preg_replace('/Array\s+\(\s+\)/s', 'Array()', $str); // single-lineify empty arrays
            $str = str_replace($search, $replace, $str);
            $str = substr($str, 0, -1);
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
        call_user_func($this->cfg['emailFunc'], $toAddr, $subject, $body);
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
        $errMsg     = preg_replace('/ \[<a.*?\/a>\]/i', '', $error['message']);   // remove links from errMsg
        $countSince = $error['stats']['countSince'];
        $subject    = 'Website Error: '.$_SERVER['SERVER_NAME'].': '.$errMsg.($countSince ? ' ('.$countSince.'x)' : '');
        $emailBody  = '';
        if (!empty($countSince)) {
            $dateTimePrev = date($dateTimeFmt, $error['stats']['tsEmailed']);
            $emailBody .= 'Error has occurred '.$countSince.' times since last email ('.$dateTimePrev.').'."\n\n";
        }
        $emailBody .= ''
            .'datetime: '.date($dateTimeFmt)."\n"
            .'errormsg: '.$errMsg."\n"
            .'errortype: '.$error['type'].' ('.$error['typeStr'].')'."\n"
            .'file: '.$error['file']."\n"
            .'line: '.$error['line']."\n"
            .'remote_addr: '.$_SERVER['REMOTE_ADDR']."\n"
            .'http_host: '.$_SERVER['HTTP_HOST']."\n"
            .'referer: '.$_SERVER['HTTP_REFERER']."\n"
            .'request_uri: '.$_SERVER['REQUEST_URI']."\n"
            .'';
        if (!empty($_POST)) {
            $emailBody .= 'post params: '.var_export($_POST, true)."\n";
        }
        if ($error['type'] & $this->cfg['emailTraceMask']) {
            $emailBody .= "\n".'backtrace: '.$this->backtraceStr($error);
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
        if (!file_exists($file)) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);    // 3rd param is php 5
            }
        }
        file_put_contents($file, $str);
        return $return;
    }

    /**
     * Export throttle data
     *
     * @return void
     */
    protected function throttleDataExport()
    {
        if ($this->cfg['emailThrottleFile']) {
            $this->throttleTrashCollection();
            $this->fileWrite($this->cfg['emailThrottleFile'], json_encode($this->throttleData));
        }
        return;
    }

    /**
     * Load & populate $this->throttleData if not alrady imported
     *
     * @return void
     */
    protected function throttleDataImport()
    {
        if (!$this->throttleData && $this->cfg['emailThrottleFile']) {
            $throttleData = false;
            if (is_readable($this->cfg['emailThrottleFile'])) {
                $throttleData = json_decode(
                    file_get_contents($this->cfg['emailThrottleFile']),
                    true
                );
            }
            if (!is_array($throttleData)) {
                $throttleData = array(
                    'tsTrashCollection' => time(),
                    'errors' => array(),
                );
            }
            $this->throttleData = $throttleData;
        }
        return;
    }

    /**
     * Adds/Updates this error's throttle data
     *
     * @param Event $error error event
     *
     * @return void
     */
    protected function throttleDataSet(Event $error)
    {
        $tsNow = time();
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
        $this->throttleDataExport();
        return;
    }

    /**
     * Remove errors in throttleData that haven't occured recently
     * If error(s) have occured since they were last emailed, a summary email will be sent
     *
     * @return void
     */
    protected function throttleTrashCollection()
    {
        $tsNow     = time();
        $tsCutoff  = $tsNow - $this->cfg['emailMin'] * 60;
        if ($this->throttleData['tsTrashCollection'] > $tsCutoff) {
            // we've recently performed trash collection
            return;
        }
        // trash collection time
        $emailBody = '';
        $sendEmailSummary = false;
        $this->throttleData['tsTrashCollection'] = $tsNow;
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
                $dateLastEmailed = date('Y-m-d H:i:s', $err['tsEmailed']);
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
}
