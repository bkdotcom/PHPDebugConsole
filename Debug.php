<?php

namespace bdk\Debug;

if (!defined('E_STRICT')) {
    define('E_STRICT', 2048);               // PHP 5.0.0
}
if (!defined('E_RECOVERABLE_ERROR')) {
    define('E_RECOVERABLE_ERROR', 4096);    // PHP 5.2.0
}
if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 8192);           // PHP 5.3.0
}
if (!defined('E_USER_DEPRECATED')) {
    define('E_USER_DEPRECATED', 16384);     // PHP 5.3.0
}

/**
 * Browser/javascript like console class for PHP
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @see     http://www.github.com/bkdotcom/
 * @see     https://developer.mozilla.org/en-US/docs/Web/API/console
 */
class Debug
{

    private $errTypes = array(
        E_ERROR             => 'Error',             // handled via shutdown function
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parsing Error',     // handled via shutdown function
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',        // handled via shutdown function
        E_CORE_WARNING      => 'Core Warning',      // handled?
        E_COMPILE_ERROR     => 'Compile Error',     // handled via shutdown function
        E_COMPILE_WARNING   => 'Compile Warning',   // handled?
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_ALL               => 'E_ALL',             // listed here for completeness
        E_STRICT            => 'Runtime Notice (E_STRICT)',
        E_RECOVERABLE_ERROR => 'Fatal Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
    );

    private $errTypesGrouped = array(
        'deprecated'    => array( E_DEPRECATED, E_USER_DEPRECATED ),
        'error'         => array( E_USER_ERROR, E_RECOVERABLE_ERROR ),
        'notice'        => array( E_NOTICE, E_USER_NOTICE ),
        'strict'        => array( E_STRICT ),
        'warning'       => array( E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING ),
        'fatal'         => array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR ),
    );

    private $state = null;  // 'output' while in output()

    private static $instance;

    const VALUE_ABSTRACTION = "\x00debug\x00";

    /**
     * @param array $cfg config
     *
     * @return void
     */
    public function __construct($cfg = array())
    {
        ini_set('display_errors', 0);
        error_reporting(-1);    // report every possible error ( E_ALL | E_STRICT )
                                // not actually necessary as all errors get sent to custom error handler
        $this->cfg = array(
            'addBR'     => false,           // convert \n to <br />\n in strings?
            'css'       => '',
            'collect'   => false,
            'file'      => null,            // if a filepath, will receive log data
            'firephpInc'=> version_compare(PHP_VERSION, '5.0.0', '>=')
                ? dirname(__FILE__).'/FirePHP/FirePHP.class.php'
                : dirname(__FILE__).'/FirePHP/FirePHP.class.php4',
            'key'       => null,
            'output'    => false,           // should output() actually output to browser (either as html or firephp)
            'outputAs'  => null,            // 'html' or 'firephp', if null, will be determined automatically
            'outputCss' => true,
            'emailLog'  => false,           // whether to email a debug log. false, 'onError' (true), or 'always'
                                            //   requires 'collect' to also be true
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'onOutput'  => null,                // set to something callable
            'errorHandler' => array(
                'onError'           => null,    // set to something callable, will receive a single boolean indicating whether error was fatal
                'emailMin'          => 15,
                'emailMask'         => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_WARNING | E_USER_ERROR | E_USER_NOTICE,
                'emailTraceMask'    => E_WARNING | E_USER_ERROR | E_USER_NOTICE,
                'fatalMask'         => array_reduce($this->errTypesGrouped['fatal'], create_function('$a, $b', 'return $a | $b;')),
                'emailThrottleFile' => dirname(__FILE__).'/error_emails.txt',
            ),
        );
        $this->data = array(
            'counts' => array(),    // count method
            'errorHandler' => array(
                'errorCaller'   => array(),
                'errors'        => array(),
                'lastError'     => array(),
                'oldErrorHandler' => set_error_handler(array($this,'errorHandler')),
            ),
            'fileHandle'    => null,
            'groupDepth'    => 0,
            'groupDepthFile'=> 0,
            'log'           => array(),
            'recursion'     => false,
            'timers' => array(      // timer method
                'labels' => array(
                    'debugInit' => microtime(),
                ),
                'stack' => array(),
            ),
        );
        register_shutdown_function(array($this,'shutdownFunction'));
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            list($whole, $dec) = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
            $mt = '.'.$dec.' '.$whole;
            $this->data['timers']['labels']['debugInit'] = $mt;
        }
        $this->setCfg($cfg);
        $this->collect = &$this->cfg['collect'];
        $this->output = &$this->cfg['output'];
        return;
    }

    /**
     * Log a message and stack trace to console if first argument is false
     *
     * @return void
     */
    public function assert()
    {
        if ($this->collect) {
            $args = func_get_args();
            $test = array_shift($args);
            if (!$test) {
                $this->_appendLog('assert', $args);
            }
        }
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * @param mixed $label label
     *
     * @return int
     */
    public function count($label = null)
    {
        $return = null;
        if ($this->collect) {
            $args = array();
            if (isset($label)) {
                $args[] = $label;
            } else {
                $args[] = 'count';
                $bt = debug_backtrace();
                $label = $bt[0]['file'].': '.$bt[0]['line'];
            }
            if (!isset($this->data['counts'][$label])) {
                $this->data['counts'][$label] = 1;
            } else {
                $this->data['counts'][$label]++;
            }
            $args[] = $this->data['counts'][$label];
            $this->_appendLog('count', $args);
            $return = $this->data['counts'][$label];
        }
        return $return;
    }

    /**
     * Outputs an error message.
     *
     * @param mixed $label label
     *
     * @return void
     */
    public function error()
    {
        if ($this->collect) {
            $args = func_get_args();
            $this->_appendLog('error', $args);
        }
    }

    /**
     * retrieve a config value, lastError, or css
     *
     * @param string $k what to get
     *
     * @return mixed
     */
    public function get($k)
    {
        if ($k == 'outputAs') {
            $ret = $this->cfg['outputAs'];
            if (empty($ret)) {
                /**
                 * determine outputAs automatically
                 */
                $ret = 'html';
                $ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
                if ($ajax) {
                    $ret = 'firephp';
                } else {
                    $contentType = $this->getResponseHeader();
                    if ($contentType && $contentType !== 'text/html') {
                        $ret = 'firephp';
                    }
                }
            }
        } elseif ($k == 'lastError') {
            $ret = $this->data['errorHandler']['lastError'];
        } elseif ($k == 'css') {
            $ret = $this->_getCss();
        } else {
            $ret = $this->cfg;
            $path = preg_split('#[\./]#', $k);
            foreach ($path as $k) {
                if (isset($ret[$k])) {
                    $ret = $ret[$k];
                } else {
                    $ret = null;
                    break;
                }
            }
        }
        return $ret;
    }

    /**
     * @param array $cfg optional config
     *
     * @return object
     */
    public static function getInstance($cfg = array())
    {
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            self::$instance = new $className($cfg);
        } elseif ($cfg) {
            self::$instance->setCfg($cfg);
        }
        return self::$instance;
    }

    /**
     * Creates a new inline group
     *
     * @return void
     */
    public function group()
    {
        $this->data['groupDepth']++;
        if ($this->collect) {
            $args = func_get_args();
            if (empty($args)) {
                $args[] = 'group';
            }
            $this->_appendLog('group', $args);
        }
    }

    /**
     * Creates a new inline group
     *
     * @return void
     */
    public function groupCollapsed()
    {
        $this->data['groupDepth']++;
        if ($this->collect) {
            $args = func_get_args();
            if (empty($args)) {
                $args[] = 'group';
            }
            $this->_appendLog('groupCollapsed', $args);
        }
    }

    /**
     * sets current group or groupCollapsed to 'group' (ie, make sure it's uncollapsed)
     *
     * @return void
     */
    public function groupUncollapse()
    {
        for ($i = count($this->data['log']) - 1; $i >=0; $i--) {
            $method = $this->data['log'][$i][0];
            if ($method == 'group') {
                break;
            } elseif ($method == 'groupCollapsed') {
                // change to group
                $this->data['log'][$i][0] = 'group';
                break;
            }
        }
    }

    /**
     * Close current group
     *
     * @return void
     */
    public function groupEnd()
    {
        if ($this->data['groupDepth'] > 0) {
            $this->data['groupDepth']--;
        }
        $eC = $this->data['errorHandler']['errorCaller'];
        if ($eC && $this->data['groupDepth'] < $eC['depth']) {
            $this->data['errorHandler']['errorCaller'] = array();
        }
        if ($this->collect) {
            $args = func_get_args();
            $this->_appendLog('groupEnd', $args);
        }
    }

    /**
     * Informative logging information
     *
     * @return void
     */
    public function info()
    {
        if ($this->collect) {
            $args = func_get_args();
            $this->_appendLog('info', $args);
        }
    }

    /**
     * For logging general information
     *
     * @return void
     */
    public function log()
    {
        if ($this->collect) {
            $args = func_get_args();
            $this->_appendLog('log', $args);
        }
    }

    /**
     *  if outputAs == null -> determined automatically
     *  if outputAs == 'html' -> returns html string
     *  if outputAs == 'firephp' -> returns null
     *
     * @return string or void
     */
    public function output()
    {
        $return = null;
        $this->state = 'output';
        if ($this->output) {
            array_unshift($this->data['log'], array('info','Built In '.$this->timeEnd('debugInit', true).' sec'));
            $outputAs = $this->get('outputAs');
            if (is_callable($this->cfg['onOutput'])) {
                call_user_func($this->cfg['onOutput'], $outputAs);
            }
            $outputAs = $this->get('outputAs');

            /**
             * create an error summary if there were errors
             */
            if (!empty($this->data['errorHandler']['errors'])) {
                $counts = array();
                foreach ($this->data['errorHandler']['errors'] as $error) {
                    if ($error['suppressed']) {
                        continue;
                    }
                    foreach ($this->errTypesGrouped as $k => $errTypes) {
                        if (!in_array($error['type'], $errTypes)) {
                            continue;
                        }
                        $counts[$k] = isset($counts[$k])
                            ? $counts[$k] + 1
                            : 1;
                        break;
                    }
                }
                if ($counts) {
                    $tot = array_sum($counts);
                    ksort($counts);
                    if (count($counts) == 1) {
                        // all same type of error
                        $type = key($counts);
                        if ($tot == 1) {
                            $alert = 'There was 1 error';
                            if ($type == 'fatal') {
                                $alert = null;  // don't bother with this alert..
                                                // fatal are still prominently displayed
                            } elseif ($type != 'error') {
                                $alert .= ' ('.$type.')';
                            }
                        } else {
                            $alert = 'There were '.$tot.' errors';
                            if ($type != 'error') {
                                $alert .= ' of type '.$type;
                            }
                        }
                        if ($alert) {
                            $alert = '<h3>'.$alert.'</h3>'."\n";
                        }
                    } else {
                        $alert = '<h3>There were '.$tot.' errors:</h3>'."\n";
                        $alert .= '<ul class="list-unstyled indent">';
                        foreach ($counts as $k => $v) {
                            $alert .= '<li>'.$k.': '.$v.'</li>';
                        }
                        $alert .= '</ul>';
                    }
                    if ($alert) {
                        $this->alert = $alert;
                    }
                }
            }

            $this->data['groupDepth'] = 0;
            if ($outputAs == 'html') {
                $return = $this->_outputHtml();
            } elseif ($outputAs == 'firephp') {
                $this->_outputFirephp();
                $return = null;
            }
        }
        $this->data['log'] = array();
        $this->state = null;
        return $return;
    }

    /**
     * set one more more config values
     *    setCfg('key', 'value')
     *    setCfg('level1.level2', 'value')
     *    setCfg(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * if setting a single value via method a or b, old value is returned
     *
     *  setting/updating 'key' will also 'collect' and 'output'
     *
     * @param string $k key
     * @param mixed  $v value
     *
     * @return mixed
     */
    public function setCfg($k, $v = null)
    {
        $return = null;
        if (is_string($k)) {
            $path = preg_split('#[\./]#', $k);
            $cfg = array();
            $ref = &$cfg;
            $return = $this->cfg;
            foreach ($path as $k) {
                $return = isset($return[$k])
                    ? $return[$k]
                    : null;
                $ref[$k] = array(); // initialize as array
                $ref = &$ref[$k];
            }
            $ref = $v;
        } elseif (is_array($k)) {
            $cfg = $k;
        }
        if (isset($cfg['key'])) {
            $cfg['collect'] = isset($_REQUEST['debug']) && $_REQUEST['debug'] == $cfg['key'];
            $cfg['output'] = $cfg['collect'];
        }
        if (isset($cfg['emailLog']) && $cfg['emailLog'] === true) {
            $cfg['emailLog'] = 'onError';
        }
        $this->cfg = $this->arrayMergeDeep($this->cfg, $cfg);
        return $return;
    }

    /**
     * set the calling file/line for next error
     * this override will apply until cleared, error occurs, or groupEnd()
     *
     * example.. some wrapper function that is called often:
     *     Rather than reporting that an error occurred within the wrapper, you can use
     *     setErrorCaller() to report the error originating from the file/line that called the function
     *
     * @param array $caller optional. pass null or array() to clear
     *
     * @return void
     */
    public function setErrorCaller($caller = 'notPassed')
    {
        if ($caller === 'notPassed') {
            $backtrace = debug_backtrace();
            $caller = isset($backtrace[1])
                ? $backtrace[1]
                : $backtrace[0];
            $caller = array(
                'depth' => $this->data['groupDepth'],
                'file' => $caller['file'],
                'line' => $caller['line'],
            );
        } elseif (empty($caller)) {
            $caller = array();  // clear
        } elseif (is_array($caller)) {
            $caller['depth'] = $this->data['groupDepth'];
        }
        $this->data['errorHandler']['errorCaller'] = $caller;
        return;
    }

    /**
     * output array as a table
     * accepts
     *    array[, string]
     *    string, array
     *
     * @return void
     */
    public function table()
    {
        if ($this->collect) {
            $args = func_get_args();
            $args_not_array = array();
            $have_array = false;
            foreach ($args as $k => $v) {
                if (!is_array($v) || $have_array) {
                    $args_not_array[] = $v;
                    unset($args[$k]);
                } else {
                    $have_array = true;
                }
            }
            $method = 'table';
            if ($have_array) {
                if (!empty($args_not_array)) {
                    $args[] = implode(' ', $args_not_array);
                }
            } else {
                $method = 'log';
                $args = $args_not_array;
                if (count($args) == 2 && !is_string($args[0])) {
                    $args[] = array_shift($args);
                }
            }
            $this->_appendLog($method, $args);
        }
    }

    /**
     * start a timer identified by label
     *    if timer with label already started, it will not be reset
     * if no label is passed a timer will be added to a timer stack
     *
     * @param string $label unique label
     *
     * @return void
     */
    public function time($label = null)
    {
        if (isset($label)) {
            if (!isset($this->data['timers']['labels'][$label])) {
                $this->data['timers']['labels'][$label] = microtime();
            }
        } else {
            $this->data['timers']['stack'][] = microtime();
        }
        return;
    }

    /**
     * logs time elapsed since started with time()
     * if no label is passed, timer is removed from a timer stack
     *
     * @param string $label  unique label
     * @param bool   $return = false. If true, return elapsed time rather than log it
     *
     * @return void
     */
    public function timeEnd($label = null, $return = false)
    {
        $ret = null;
        if (isset($label)) {
            $mt = $this->data['timers']['labels'][$label];
        } else {
            $mt = array_pop($this->data['timers']['stack']);
            $label = 'time';
        }
        if ($mt) {
            list($a_dec, $a_sec) = explode(' ', $mt);
            list($b_dec, $b_sec) = explode(' ', microtime());
            $ret = (float)$b_sec - (float)$a_sec + (float)$b_dec - (float)$a_dec;
            $ret = round($ret, 4);
            if (!$return) {
                $this->_appendLog('time', array($label, $ret.' sec'));
            }
        }
        return $ret;
    }

    /**
     * Log a warning
     *
     * @return void
     */
    public function warn()
    {
        if ($this->collect) {
            $args = func_get_args();
            $this->_appendLog('warn', $args);
        }
    }

    /**
     * "Non-Public functions"
     */

    /**
     * @param int    $errType the level of the error
     * @param string $errmsg  the error message
     * @param string $file    filepath the error was raised in
     * @param string $line    the line the error was raised in
     * @param array  $vars    active symbol table at point error occured
     *
     * @return  void
     */
    public function errorHandler($errType, $errmsg, $file, $line, $vars = array())
    {
        $eh_cfg = &$this->cfg['errorHandler'];
        $eh_data = &$this->data['errorHandler'];
        $isFatal = $errType & $eh_cfg['fatalMask'];
        /**
         * @see http://www.php.net/manual/en/language.operators.errorcontrol.php
         */
        $isSuppressed = !$isFatal && error_reporting() === 0;
        $errMd5 = md5($file.$line); // use true source for tracking
        $first_occur = !isset($eh_data['errors'][$errMd5]);
        if (!empty($eh_data['errorCaller'])) {
            $file = $eh_data['errorCaller']['file'];
            $line = $eh_data['errorCaller']['line'];
        }
        $err_string = $this->errTypes[$errType].': '.$file.' : '.$errmsg.' on line '.$line;
        $error = array(
            'type'      => $errType,
            'typeStr'   => $this->errTypes[$errType],
            'suppressed'=> $isSuppressed,
            'message'   => $errmsg,
            'file'      => $file,
            'line'      => $line,
        );
        $eh_data['lastError'] = $error;
        $eh_data['errors'][$errMd5] = $error;
        if ($isSuppressed) {
            // @suppressed error
        } elseif ($this->collect) {
            /**
             * log error in 'console'
             *    will not get logged to server's error_log
             *    will not get emailed
             */
            $db_was = $this->setCfg('collect', true);
            if (in_array($errType, array(E_ERROR,E_WARNING,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR))) {
                $this->error($err_string);
            } else {
                $this->warn($err_string);
            }
            if (!$this->output) {
                // not currently outputing... send to error log
                error_log('PHP '.$err_string);
            }
            $this->setCfg('collect', $db_was);
        } elseif ($first_occur) {
            if (!empty($this->cfg['emailTo']) && ( $errType & $eh_cfg['emailMask'] )) {
                $args = func_get_args();
                call_user_func_array(array($this,'emailErr'), $args);
            }
            error_log('PHP '.$err_string);
        }
        if (!$isSuppressed) {
            $eh_data['errorCaller'] = array();
        }
        if ($eh_cfg['onError'] && is_callable($eh_cfg['onError'])) {
            call_user_func($eh_cfg['onError'], $isFatal);
        }
        return;
    }

    /**
     * email this error... if...
     *   uses emailThrottleFile to keep track of when emails were sent
     *
     * @param int    $errType the level of the error
     * @param string $errmsg  the error message
     * @param string $file    filepath the error was raised in
     * @param string $line    the line the error was raised in
     * @param array  $vars    active symbol table at point error occured
     *
     * @return void
     */
    protected function emailErr($errType, $errmsg, $file, $line, $vars = array())
    {
        $eh_cfg     = &$this->cfg['errorHandler'];
        $eh_data    = &$this->data['errorHandler'];
        $email = false;
        if ($eh_cfg['emailMin'] > 0 && $eh_cfg['emailThrottleFile']) {
            $ts_now     = time();
            $ts_cutoff  = $ts_now - $eh_cfg['emailMin'] * 60;
            $data_str   = is_readable($eh_cfg['emailThrottleFile'])
                            ? file_get_contents($eh_cfg['emailThrottleFile'])
                            : '';
            $data       = unserialize($data_str);
            if (!is_array($data)) {
                $data = array(
                    'ts_trash_collection'   => $ts_now,
                    'errors'                => array(),
                );
            }
            #$this->log('ts_cutoff', date('Ymd H:i:s', $ts_cutoff));
            #$this->log('data[ts_trash_collection]', date('Ymd H:i:s', $data['ts_trash_collection']));
            // for key creation:
            //   use true error location
            //   remove "numbers" from error message
            $errmsg_tmp = $errmsg;
            $errmsg_tmp = preg_replace('/(\(.*?)\d+(.*?\))/', '\1x\2', $errmsg_tmp);
            $errmsg_tmp = preg_replace('/\b([a-z]+\d+)+\b/', 'xxx', $errmsg_tmp);
            $errmsg_tmp = preg_replace('/\b[\d.-]{4,}\b/', 'xxx', $errmsg_tmp);
            #debug('errmsg_tmp', $errmsg_tmp);
            $key = md5($file.$line.$errType.$errmsg_tmp);
            if ($eh_data['errorCaller']) {
                $file = $eh_data['errorCaller']['file'];
                $line = $eh_data['errorCaller']['file'];
            }
            //if ( !empty($data['errors'][$key]['tsEmailed']) )
            //  $this->log('tsEmailed', date('Y-m-d H:i:s', $data['errors'][$key]['tsEmailed']));
            if (!isset($data['errors'][$key]) || $data['errors'][$key]['tsEmailed'] < $ts_cutoff) {
                // this error has not occurred recently -> email it
                $email = true;
                if ($this->collect && in_array($this->cfg['emailLog'], array('always','onError'))) {
                    // Don't email error.  Will email log at shutdownFunction
                    $email = false;
                }
                $data['errors'][$key] = array(
                    'file'          => $file,
                    'line'          => $line,
                    'errType'       => $errType,
                    'errmsg'        => $errmsg,
                    'tsEmailed'     => $ts_now,
                    'emailTo'       => $this->cfg['emailTo'],
                    'countSince'    => 0,
                );
            } else {
                // Don't email error.  Was recently emailed.
                $data['errors'][$key]['countSince']++;
            }
            $data = $this->emailTrashCollection($data);
            $wrote = $this->_fileWrite($eh_cfg['emailThrottleFile'], serialize($data));
        }
        if ($email) {
            // send error email!
            $errmsg     = preg_replace('/ \[<a.*?\/a>\]/i', '', $errmsg);   // remove links from errmsg
            $cs         = $data['errors'][$key]['countSince'];
            $subject    = 'Website Error: '.$_SERVER['SERVER_NAME'].': '.$errmsg.( $cs ? ' ('.$cs.'x)' : '' );
            $email_body = '';
            if (!empty($cs)) {
                $email_body .= 'Error has occurred '.$cs.' times since last email.'."\n\n";
            }
            $email_body .= ''
                .'datetime: '.date('Y-m-d H:i:s (T)')."\n"
                .'errormsg: '.$errmsg."\n"
                .'errortype: '.$errType.' ('.$this->errTypes[$errType].')'."\n"
                .'file: '.$file."\n"
                .'line: '.$line."\n"
                .'remote_addr: '.$_SERVER['REMOTE_ADDR']."\n"
                .'http_host: '.$_SERVER['HTTP_HOST']."\n"
                .'request_uri: '.$_SERVER['REQUEST_URI']."\n"
                .'';
            if (!empty($_POST)) {
                $email_body .= 'post params: '.var_export($_POST, true)."\n";
            }
            if ($errType & $eh_cfg['emailTraceMask']) {
                /*
                backtrace
                    0: here
                    1: call_user_func_array
                    2: errorHandler
                    3: where error occured
                */
                $search = array(
                    ")\n\n",
                );
                $replace = array(
                    ")\n",
                );
                $backtrace = debug_backtrace();
                $backtrace = array_slice($backtrace, 3);
                $backtrace[0]['vars'] = $vars;
                $str = print_r($backtrace, true);
                $str = preg_replace('/Array\s+\(\s+\)/s', 'Array()', $str); // single-lineify empty array
                $str = str_replace($search, $replace, $str);
                $str = substr($str, 0, -1);
                $email_body .= "\n".'backtrace: '.$str;
            }
            mail($this->cfg['emailTo'], $subject, $email_body);
        }
        return;
    }

    /**
     * Serializes and emails log
     *
     * @return void
     */
    protected function emailLog()
    {
        $body = '';
        $unsuppressedError = false;
        /**
         * List errors that occured
         */
        $cmp = create_function('$a1, $a2', 'return strcmp($a1["file"].$a1["line"], $a2["file"].$a2["line"]);');
        uasort($this->data['errorHandler']['errors'], $cmp);
        $last_file = '';
        foreach ($this->data['errorHandler']['errors'] as $error) {
            if ($error['suppressed']) {
                continue;
            }
            if ($error['file'] !== $last_file) {
                $body .= $error['file'].':'."\n";
                $last_file = $error['file'];
            }
            $body .= '  Line '.$error['line'].': '.$error['message']."\n";
            $unsuppressedError = true;
        }
        $subject = 'Debug Log: '.$_SERVER['HTTP_HOST'].( $unsuppressedError ? ' (Error)' : '' );
        /**
         * Serialize the log
         */
        $outputCssWas = $this->setCfg('outputCss', false);
        $serialized = $this->_outputHtml();
        $this->setCfg('outputCss', $outputCssWas);
        if (function_exists('gzdeflate')) {
            $serialized = gzdeflate($serialized);
        }
        $serialized = chunk_split(base64_encode($serialized), 1024);
        $body .= "\nSTART DEBUG:\n";
        $body .= $serialized;
        mail($this->cfg['emailTo'], $subject, $body);
        return;
    }

    /**
     * clean out errors stored in emailThrottleFile that havent occured recently
     *
     * @param array $data Data structure as stored in emailThrottleFile
     *
     * @return void
     */
    protected function emailTrashCollection($data)
    {
        $ts_now     = time();
        $ts_cutoff  = $ts_now - $this->cfg['errorHandler']['emailMin'] * 60;
        if ($data['ts_trash_collection'] < $ts_cutoff) {
            // trash collection time
            $data['ts_trash_collection'] = $ts_now;
            $email_body = '';
            foreach ($data['errors'] as $k => $err) {
                if ($err['tsEmailed'] > $ts_cutoff) {
                    continue;
                }
                // it's been a while since this error was emailed
                if ($err['emailTo'] != $this->cfg['emailTo']) {
                    if ($err['countSince'] < 1 || $err['tsEmailed'] < $ts_now - 60*60*24) {
                        unset($data['errors'][$k]);
                    }
                    continue;
                }
                unset($data['errors'][$k]);
                if ($err['countSince'] > 0) {
                    $email_body .= ''
                        .'File: '.$err['file']."\n"
                        .'Line: '.$err['line']."\n"
                        .'Error: '.$this->errTypes[ $err['errType'] ].': '.$err['errmsg']."\n"
                        .'Has occured '.$err['countSince'].' times since '.date('Y-m-d H:i:s', $err['tsEmailed'])."\n\n";
                }
            }
            if ($email_body) {
                mail($this->cfg['emailTo'], 'Website Errors: '.$_SERVER['SERVER_NAME'], $email_body);
            }
        }
        return $data;
    }

    /**
     * @param string $str     string containing binary
     * @param bool   $htmlout add html markup?
     *
     * @return string
     */
    public function getDisplayBinary($str, $htmlout)
    {
        $this->htmlout = $htmlout;
        $this->data['displayBinaryStats'] = array(
            'ascii' => 0,
            'utf8'  => 0,   // bytes, not "chars"
            'other' => 0,
            'cur_text_len' => 0,
            'max_text_len' => 0,
            'text_segments' => 0,   // number of utf8 blocks
        );
        $stats = &$this->data['displayBinaryStats'];
        $regex = <<<EOD
/
( [\x01-\x7F] )                 # single-byte sequences   0xxxxxxx  (ascii 0 - 127)
| (
  (?: [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
    | [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
    | [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3
  ){1,100}                      # ...one or more times
)
| ( [\x80-\xBF] )               # invalid byte in range 10000000 - 10111111   128 - 191
| ( [\xC0-\xFF] )               # invalid byte in range 11000000 - 11111111   192 - 255
| (.)                           # null (including x00 in the regex = fail)
/x
EOD;
        $str_orig = $str;
        $strlen = strlen($str);
        $str = preg_replace_callback($regex, array($this,'getDisplayBinaryCallback'), $str);
        if ($stats['cur_text_len'] > $stats['max_text_len']) {
            $stats['max_text_len'] = $stats['cur_text_len'];
        }
        $percentBinary = $stats['other'] / $strlen * 100;
        if ($percentBinary > 33) {
            // treat it all as binary
            $str = bin2hex($str_orig);
            $str = trim(chunk_split($str, 2, ' '));
            if ($htmlout) {
                $str = '<span class="binary">'.$str.'</span>';
            }
        } else {
            $str = str_replace('</span><span class="binary">', '', $str);
        }
        return $str;
    }

    /**
     * callback used by getDisplayBinary's preg_replace_callback
     *
     * @param array $m matches
     *
     * @return string
     */
    protected function getDisplayBinaryCallback($m)
    {
        $stats = &$this->data['displayBinaryStats'];
        $showHex = false;
        if ($m[1] !== '') {
            // single byte sequence (may contain control char)
            $str = $m[1];
            if (ord($str) < 32 || ord($str) == 127) {
                $showHex = true;
                if (in_array($str, array("\t","\n","\r"))) {
                    $showHex = false;
                }
            }
            if (!$showHex) {
                $stats['ascii']++;
                $stats['cur_text_len']++;
            }
            if ($this->htmlout) {
                $str = htmlspecialchars($str);
            }
        } elseif ($m[2] !== '') {
            // Valid byte sequence. return unmodified.
            $str = $m[2];
            $stats['utf8'] += strlen($str);
            $stats['cur_text_len'] += strlen($str);
            if ($str === "\xef\xbb\xbf") {
                // BOM
                $showHex = true;
            }
        } elseif ($m[3] !== '' || $m[4] !== '') {
            // Invalid byte
            $str = $m[3] != ''
                ? $m[3]
                : $m[4];
            $showHex = true;
        } else {
            // null char
            $str = $m[5];
            $showHex = true;
        }
        if ($showHex) {
            $stats['other']++;
            if ($stats['cur_text_len']) {
                if ($stats['cur_text_len'] > $stats['max_text_len']) {
                    $stats['max_text_len'] = $stats['cur_text_len'];
                }
                $stats['cur_text_len'] = 0;
                $stats['text_segments']++;
            }
            $chars = str_split($str);
            foreach ($chars as $i => $c) {
                $chars[$i] = '\x'.bin2hex($c);
            }
            $str = implode('', $chars);
            if ($this->htmlout) {
                $str = '<span class="binary">'.$str.'</span>';
            }
        }
        return $str;
    }

    /**
     * @param array  $array   array
     * @param string $caption optional caption
     *
     * @return string
     */
    public function getDisplayTable($array, $caption = null)
    {
        $str = '';
        if (!is_array($array)) {
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= $this->getDisplayValue($array);
            $str = '<div class="log">'.$str.'</div>';
        } elseif (empty($array)) {
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= 'array()';
            $str = '<div class="log">'.$str.'</div>';
        } else {
            $keys = $this->arrayColKeys($array);
            $undefined = "\x00".'undefined'."\x00";
            $str = '<table cellpadding="1" cellspacing="0" border="1">'."\n";   // style="border:solid 1px;"
            $values = array();
            foreach ($keys as $key) {
                $values[] = $key === ''
                    ? 'value'
                    : htmlspecialchars($key);
            }
            $str .= '<caption>'.$caption.'</caption>'."\n"
                .'<thead>'
                .'<tr><th>&nbsp;</th><th>'.implode('</th><th scope="col">', $values).'</th></tr>'."\n"
                .'</thead>'."\n"
                .'<tbody>'."\n";
            foreach ($array as $k => $row) {
                $values = array();
                foreach ($keys as $key) {
                    $value = '';
                    if (is_array($row)) {
                        $value = array_key_exists($key, $row)
                            ? $row[$key]
                            : $undefined;
                    } elseif ($key === '') {
                        $value = $row;
                    }
                    if (is_array($value)) {
                        $value = call_user_func(array($this,__FUNCTION__), $value);
                    } elseif ($value === $undefined) {
                        $value = '<span class="t_undefined"></span>';
                    } else {
                        $value = $this->getDisplayValue($value);
                    }
                    $values[] = $value;
                }
                $str .= '<tr valign="top"><td>'.$k.'</td>';
                foreach ($values as $v) {
                    // remove the span wrapper.. add span's class to TD
                    $class = null;
                    if (preg_match('#^<span class="([^"]+)">(.*)</span>$#s', $v, $matches)) {
                        $class = $matches[1];
                        $v = $matches[2];
                    }
                    $str .= $class
                        ? '<td class="'.$class.'">'
                        : '<td>';
                    $str .= $v;
                    $str .= '</td>';
                }
                $str .= '</tr>'."\n";
            }
            $str .= '</tbody>'."\n".'</table>';
        }
        return $str;
    }

    /**
     * @param mixed $v    value
     * @param array $opts options
     * @param array $hist {@internal - used to check for recursion}
     *
     * @return string
     */
    public function getDisplayValue($v, $opts = array(), $hist = array())
    {
        $type = null;
        $typeMore = null;
        if (empty($hist)) {
            $opts = array_merge(array(
                'html' => true,     // use html markup
                'flatten' => false, // flatten array & obj structures (only applies when !html)
                'boolNullToString' => true,
            ), $opts);
            if ($this->state !== 'output') {
                /**
                 * getDisplayValue() called directly?
                 */
                if (is_array($v) || is_object($v) || is_resource($v)) {
                    $v = $this->_appendLogPrep($v);
                }
            }
        }
        if (is_array($v) && in_array(self::VALUE_ABSTRACTION, $v, true)) {
            /**
             * array (recursion), object, or resource
             */
            $type = $v['type'];
            if ($type == 'object') {
                $type = 'object';
                if ($v['isRecursion']) {
                    $v = '<span class="t_object">'
                            .'<span class="t_object-class">'.$v['class'].' object</span>'
                            .' <span class="t_recursion">*RECURSION*</span>'
                        .'</span>';
                    if (!$opts['html']) {
                        $v = strip_tags($v);
                    }
                    $type = null;
                } else {
                    $hist[] = &$v;
                    $v = array(
                        'class'      => $v['class'].' object',
                        'properties' => $this->getDisplayValue($v['properties'], $opts, $hist),
                        'methods'    => $this->getDisplayValue($v['methods'], $opts, $hist),
                    );
                    if ($opts['html'] || $opts['flatten']) {
                        $v = $v['class']."\n"
                            .'    methods: '.$v['methods']."\n"
                            .'    properties: '.$v['properties'];
                        if ($opts['flatten'] && count($hist) > 1) {
                            $v = str_replace("\n", "\n    ", $v);
                        }
                    }
                }
            } elseif ($type == 'array') {
                $v = '<span class="t_array">'
                        .'<span class="t_keyword">Array</span>'
                        .' <span class="t_recursion">*RECURSION*</span>'
                    .'</span>';
                if (!$opts['html']) {
                    $v = strip_tags($v);
                }
                $type = null;
            } else {
                $v = $v['value'];
            }
        } elseif (is_array($v)) {
            $type = 'array';
            $hist[] = 'array';
            foreach ($v as $k => $v2) {
                $v[$k] = $this->getDisplayValue($v2, $opts, $hist);
            }
            if ($opts['flatten']) {
                $v = trim(print_r($v, true));
                if (count($hist) > 1) {
                    $v = str_replace("\n", "\n    ", $v);
                }
            }
        } elseif (is_string($v)) {
            $type = 'string';
            if (is_numeric($v)) {
                $typeMore = 'numeric';
            } elseif ($this->isBinary($v)) {
                // all or partially binary data
                $typeMore = 'binary';
                $v = $this->getDisplayBinary($v, $opts['html']);
            }
        } elseif (is_int($v)) {
            $type = 'int';
        } elseif (is_float($v)) {
            $type = 'float';
        } elseif (is_bool($v)) {
            $type = 'bool';
            $vStr = $v ? 'true' : 'false';
            if ($opts['boolNullToString']) {
                $v = $vStr;
            }
            $typeMore = $vStr;
        } elseif (is_null($v)) {
            $type = 'null';
            if ($opts['boolNullToString']) {
                $v = 'null';
            }
        }
        if ($opts['html']) {
            if ($type == 'array') {
                $html = '<span class="t_keyword">Array</span><br />'."\n"
                    .'<span class="t_punct">(</span>'."\n"
                    .'<span class="t_array-inner">'."\n";
                foreach ($v as $k => $v2) {
                    $html .= "\t".'<span class="t_key_value">'
                            .'<span class="t_key">['.$k.']</span> '
                            .'<span class="t_operator">=&gt;</span> '
                            .$v2
                        .'</span>'."\n";
                }
                $html .= '</span>'
                    .'<span class="t_punct">)</span>';
                $v = '<span class="t_'.$type.'">'.$html.'</span>';
            } elseif ($type == 'object') {
                $html = preg_replace('#^([^\n]+)\n(.+)$#s', '<span class="t_object-class">\1</span>'."\n".'<span class="t_object-inner">\2</span>', $v);
                $html = preg_replace('#\sproperties: #', '<br />properties: ', $html);
                $v = '<span class="t_'.$type.'">'.$html.'</span>';
            } elseif ($type) {
                $attribs = array(
                    'class' => 't_'.$type,
                    'title' => null,
                );
                if (!empty($typeMore) && $typeMore != 'binary') {
                    $attribs['class'] .= ' '.$typeMore;
                }
                if ($type == 'string') {
                    if ($typeMore != 'binary') {
                        $v = htmlspecialchars($this->toUtf8($v), ENT_COMPAT, 'UTF-8');
                    }
                    $v = $this->visualWhiteSpace($v);
                }
                if (in_array($type, array('float','int')) || $typeMore == 'numeric') {
                    $ts_now = time();
                    $secs = 86400 * 90; // 90 days worth o seconds
                    if ($v > $ts_now  - $secs && $v < $ts_now + $secs) {
                        $attribs['class'] .= ' timestamp';
                        $attribs['title'] = date('Y-m-d H:i:s', $v);
                    }
                }
                $v = '<span '.$this->buildAttribString($attribs).'>'.$v.'</span>';
            }
        }
        return $v;
    }

    /**
     * Catch Fatal Error ( if PHP >= 5.2 )
     * Email Log if emailLog = 'always' or 'onError'
     *
     * @return void
     * @requires PHP 5.2.0
     */
    public function shutdownFunction()
    {
        /**
         * if PHP 5.2+ we can check for fatal error
         */
        if (version_compare(PHP_VERSION, '5.2.0', '>=')) {
            $error = error_get_last();
            if ($error['type'] & $this->cfg['errorHandler']['fatalMask']) {
                $this->errorHandler($error['type'], $error['message'], $error['file'], $error['line']);
                echo $this->output();
            }
        }
        /**
         * Email debug log if...
         */
        $email = false; // send shutdown debug log?
        $unsuppressedError = false; // was there a non-suppressed error?
        foreach ($this->data['errorHandler']['errors'] as $error) {
            if (!$error['suppressed']) {
                $unsuppressedError = true;
                continue;
            }
        }
        // data['log']  will likely be non-empty... initial debug info is always collected
        if (!empty($this->cfg['emailTo']) && !$this->output && !empty($this->data['log'])) {
            if ($this->cfg['emailLog'] === 'always') {
                $email = true;
            } elseif ($this->cfg['emailLog'] === 'onError' && $unsuppressedError) {
                $email = true;
            }
        }
        if ($email) {
            $this->emailLog();
        }
        return;
    }

    /**
     * Use to unserialize the log serialized by emailLog
     *
     * @param string $str serialized log
     *
     * @return string
     */
    public function unserialize($str)
    {
        $pos = strpos($str, 'START DEBUG');
        if ($pos !== false) {
            $str = substr($str, $pos+11);
            $str = preg_replace('/^[^\r\n]*[\r\n]+/', '', $str);
        }
        $str = $this->isBase64Encoded($str)
            ? base64_decode($str)
            : false;
        if ($str && function_exists('gzinflate')) {
            $strInflated = @gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        return $str;
    }

    /**
     * @param string $str string which to add whitespace html markup
     *
     * @return string
     */
    public function visualWhiteSpace($str)
    {
        // display \r, \n, & \t
        $str = preg_replace_callback('/(\r\n|\r|\n)/', array($this, '_visualWhiteSpaceCallback'), $str);
        $str = preg_replace('#(<br />)?\n$#', '', $str);
        $str = str_replace("\t", '<span class="ws_t">'."\t".'</span>', $str);
        return $str;
    }

    /**
     * @param array $matches passed from preg_replace_callback
     *
     * @return string
     */
    protected function _visualWhiteSpaceCallback($matches)
    {
        $br = $this->cfg['addBR'] ? '<br />' : '';
        $search = array("\r","\n");
        $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>'.$br."\n");
        return str_replace($search, $replace, $matches[1]);
    }

    /**
     * Store the arguments
     * will be output when output method is called
     *
     * @param string $method error, info, log, warn
     * @param array  $args   arguments passed to method
     *
     * @return void
     */
    protected function _appendLog($method, $args)
    {
        foreach ($args as $i => $v) {
            if (is_array($v) || is_object($v) || is_resource($v)) {
                $args[$i] = $this->_appendLogPrep($v);
            }
        }
        array_unshift($args, $method);
        if (!empty($this->cfg['file'])) {
            $this->_appendLogFile($args);
        }
        /**
         * if logging an error or notice, also log originating file/line
         */
        if (in_array($method, array('error','warn'))) {
            $backtrace = debug_backtrace();
            foreach ($backtrace as $k => $a) {
                if ($a['function'] == 'errorHandler') {
                    // no need to store originating file/line... it's part of error message
                    break;
                }
                if (isset($a['file']) && $a['file'] !== __FILE__) {
                    if (!empty($a['function']) && in_array($a['function'], array('call_user_func','call_user_func_array'))) {
                        continue;
                    }
                    $args[] = array(
                        '__errorCaller__' => true,
                        'file' => $a['file'],
                        'line' => $a['line'],
                    );
                    break;
                }
            }
        }
        $this->data['log'][] = $args;
        return;
    }

    /**
     * @param array $args args
     *
     * @return void
     */
    protected function _appendLogFile($args)
    {
        if (!isset($this->data['fileHandle'])) {
            $this->data['fileHandle'] = fopen($this->cfg['file'], 'a');
            if ($this->data['fileHandle']) {
                fwrite($this->data['fileHandle'], '***** '.date('Y-m-d H:i:s').' *****'."\n");
                //fwrite($this->data['fileHandle'], 'Remote Addr = '.$_SERVER['REMOTE_ADDR']."\n");
                //fwrite($this->data['fileHandle'], 'Request URI = '.$_SERVER['REQUEST_URI']."\n");
            } else {
                // failed to open file
                $this->cfg['file'] = null;
            }
        }
        if ($this->data['fileHandle']) {
            $method = array_shift($args);
            if ($method == 'table') {
                if (count($args) == 2) {
                    $caption = array_pop($args);
                    array_unshift($args, $caption);
                }
            }
            if ($args) {
                if (count($args) == 1 && is_string($args[0])) {
                    $args[0] = strip_tags($args[0]);
                }
                foreach ($args as $k => $v) {
                    if ($k > 0 || !is_string($v)) {
                        $v = $this->getDisplayValue($v, array('html'=>false, 'flatten'=>true));
                        $v = preg_replace('#<span class="t_\w+">(.*?)</span>#', '\\1', $v);
                        $args[$k] = $v;
                    }
                }
                $glue = ', ';
                if (count($args) == 2) {
                    $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                        ? ''
                        : ' = ';
                }
                $indent_string = str_repeat('    ', $this->data['groupDepthFile']);
                $str = implode($glue, $args);
                $str = $indent_string.str_replace("\n", "\n".$indent_string, $str);
                fwrite($this->data['fileHandle'], $str."\n");
            }
            if (in_array($method, array('group','groupCollapsed'))) {
                $this->data['groupDepthFile']++;
            } elseif ($method == 'groupEnd' && $this->data['groupDepthFile'] > 0) {
                $this->data['groupDepthFile']--;
            }
        }
        return;
    }

    /**
     * Want to store a "snapshot" of arrays, objects, & resources
     * Remove any reference to an "external" variable
     *
     * Deep cloning objects = problematic
     *   + some objects are uncloneable & throw fatal error
     *   + difficult to maintain circular references
     * Instead of storing objects in log, store array containing
     *     type, methods, & properties
     *
     * @param array|object $mixed array or object to walk/prep
     * @param array        $hist  (@internal)
     * @param array        $path  {@internal}
     *
     * @return array
     */
    protected function _appendLogPrep($mixed, $hist = array(), $path = array())
    {
        $new = array(
            'debug' => self::VALUE_ABSTRACTION,
            'type' => '',
        );
        if (empty($hist)) {
            $this->data['recursion'] = $this->isRecursive($mixed);
        }
        $vars = array();
        if (is_array($mixed)) {
            $new = array_merge($new, array(
                'type' => 'array',
                'isRecursion' => $path
                    && $this->data['recursion']
                    && $this->isRecursive($mixed, end($path)),
            ));
            if ($new['isRecursion']) {
                return $new;
            } else {
                $hist[] = &$mixed;
                $vars = $mixed;
            }
        } elseif (is_object($mixed)) {
            $new = array_merge($new, array(
                'type' => 'object',
                'class' => get_class($mixed),
                'methods' => get_class_methods($mixed),
                'properties' => array(),
                'isRecursion' => in_array($mixed, $hist, true),
            ));
            if ($new['isRecursion']) {
                return $new;
            } else {
                $hist[] = &$mixed;
                $vars = get_object_vars($mixed);
            }
        } elseif (is_resource($mixed)) {
            $new = array_merge($new, array(
                'type' => 'resource',
                'value' => print_r($mixed, true).': '.get_resource_type($mixed),
            ));
            return $new;
        }
        foreach ($vars as $k => $v) {
            if (is_array($v) || is_object($v) || is_resource($v)) {
                $path_new = $path;
                $path_new[] = $k;
                $v_new = $this->_appendLogPrep($v, $hist, $path_new);
            } else {
                $v_new = $v;
            }
            unset($vars[$k]);   // remove any reference
            $vars[$k] = $v_new;
        }
        if ($new['type']=='array') {
            return $vars;
        } elseif ($new['type']=='object') {
            $new['properties'] = $vars;
            return $new;
        }
    }

    /**
     * @param string $file filepath
     * @param string $str  string to write
     *
     * @return int|false
     */
    protected function _fileWrite($file, $str)
    {
        $return = false;
        if (!file_exists($file)) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);    // 3rd param is php 5
            }
        }
        $fh = fopen($file, 'w');
        if ($fh) {
            $return = fwrite($fh, $str);
            fclose($fh);
        }
    }

    /**
     * @return string
     */
    protected function _getCss()
    {
        $return = <<<EOD
        .debug { clear: both; font-family: Verdana; font-size: 9px; line-height: normal; text-shadow: none; }
        .debug * {
            /*background-color: transparent;*/
            font-size: inherit;
            text-indent: 0;
        }
        .debug h3 { margin-top: 0; font-size: 1.25em; font-weight: bold; }
        .debug img { border:0px; }
        .debug pre {
            padding: 0;
            border: 0;
            margin: 0;
            color: inherit;
            font-size: 1.2em;
            line-height: 1.1em;
            -moz-tab-size: 3;
              -o-tag-size: 3;
                -tab-size: 3;
        }
        /*.debug pre br { display:none; }*/
        .debug table { border-collapse:collapse; }
        .debug table caption { font-weight:bold; font-style:italic; }
        .debug table th { font-weight:bold; background-color: rgba(0,0,0,0.1); }
        .debug table th, .debug table td { border:1px solid #000; padding:1px .25em; }
        .debug ul { margin-top: 0; margin-bottom: 0; }

        .debug .alert {
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .debug .alert-danger {
            background-color: #FFBABA;
            color: #D8000C;
            border-color: #D8000C;
        }
        .debug .alert h3 { margin-bottom: 4px; }
        .debug .alert h3:last-child { margin-bottom: 0; }
        .debug .list-unstyled{
            list-style: none outside none;
            padding-left: 0;
        }
        .debug .indent { padding-left: 10px; }

        /* methods */
        .debug .assert,
        .debug .count,
        .debug .log,
        .debug .error,
        .debug .group-header,
        .debug .info,
        .debug .time,
        .debug .warn,
        .debug table {
            float:left;
            clear:left;
        }
        .debug .error,
        .debug .info,
        .debug .log,
        .debug .warn {
            padding-left: 10px;
            text-indent: -10px;
        }
        .debug .group {
            clear: left;
            margin-left: 1em;
            border-left: 1px solid rgba(0, 0, 0, 0.25);
            padding-left: 1px;
        }
        .debug .group-label { font-weight:bold; }
        /* clearfix group */
        .debug .group:before,
        .debug .group:after {
            content: " ";
            display: table;
        }
        .debug .group:after {
            clear: both;
        }
        .debug .assert{ background-color: rgba(255,204,204,.75); }
        .debug .error { background-color: #FFBABA; color: #D8000C; }
        .debug .info  { background-color: #BDE5F8; color: #00529B; }
        .debug .warn  { background-color: #FEEFB3; color: #9F6000; }
        .error-fatal:before {
            display: block;
            padding-left: 10px;
            margin-bottom: 5px;
            content: "Fatal error";
            font-size: 1.2em;
            font-weight: bold;
        }
        .debug .error-fatal {
            padding: 10px;
            margin-bottom: 11px;
            border-left: solid 2px #D8000C;
        }
        /* data types */
        .debug .t_string { white-space: pre-wrap; }
        .debug .t_string .binary {
            margin: 0 .1em;
            padding: .1em .5em;
            background-color: #c0c0c0;
            color: #003;
            font-weight: bold;
        }
        .debug .t_bool.true { color: #993; text-shadow: 1px 1px 2px rgba(153, 153, 51, 0.5); }
        .debug .t_bool.false { color: #C33; text-shadow: 1px 1px 2px rgba(204, 51, 51, 0.5); }
        .debug .t_int,
        .debug .t_float,
        .debug .t_string.numeric {
            font-family: Courier New,monospace,Ariel;
            color: #009;
            font-size: 1.15em;
        }
        .debug .t_null { opacity: 0.3; }
        .debug .t_object-class { color: inherit; font-weight: bold; text-decoration: underline; }
        .debug .t_object + br { display: none; }
        .debug .t_recursion { font-weight: bold; color:#F00; }
        .debug .t_resource { font-style: italic; }
        .debug .t_array-inner,
        .debug .t_object-inner { display: block; margin-left: 1.5em; }
        .debug td.t_string { display: table-cell; }
        .debug .t_string:before {
            content: open-quote;
            opacity: 0.33;
        }
        .debug .t_string:after {
            content: close-quote;
            opacity: 0.33;
        }
        .debug .t_undefined { background-color: rgba(0, 0, 0, 0.1); }
        .debug .t_key_value {
            display: block;
            padding-left: 10px;
            text-indent: -10px;
        }
        .debug .t_key { opacity: .75; }
        .debug .t_keyword { color: #07A; }
        .debug .t_operator { color: #A67F59; }
        .debug .t_punct { color: #999; }
        /* Whitespace */
        .ws_s, .ws_t, .ws_r, .ws_n, .ws_p { opacity: 0.33; }
        .debug .ws_t:before { content: "\\203A"; display: inline-block; width: 1em; }   /* &rsaquo; */
        .debug .ws_r:before { content: "\\\\r"; }
        .debug .ws_n:before { content: "\\\\n"; }
EOD;
        if (!empty($this->cfg['css'])) {
            $return .=  "\n".$this->cfg['css']."\n";
        }
        return $return;
    }

    /**
     * @return void
     */
    protected function _outputFirephp()
    {
        if (!file_exists($this->cfg['firephpInc'])) {
            return;
        }
        require_once $this->cfg['firephpInc'];
        $firephp = FirePHP::getInstance(true);
        $firephpMethods = get_class_methods($firephp);
        $firephp->setOptions(array(
            'useNativeJsonEncode'   => true,
            'includeLineNumbers'    => false,
            //'maxArrayDepth'       => 2,
            //'maxObjectDepth'      => 2,
            //'maxDepth'            => 2,
        ));
        if (!empty($this->alert)) {
            $alert = str_replace('<br />', "\n", $this->alert);
            array_unshift($this->data['log'], array('error', $alert));
        }
        foreach ($this->data['log'] as $i => $args) {
            $method = array_shift($args);
            $opts = array();
            foreach ($args as $k => $arg) {
                $args[$k] = $this->getDisplayValue($arg, array('html'=>false,'boolNullToString'=>false));
            }
            if (in_array($method, array('group','groupCollapsed'))) {
                $this->data['groupDepth']++;
                $method = 'group';
                $opts = array(
                    'Collapsed' => true,    // collapse both group and groupCollapsed
                );
                if (empty($args)) {
                    $args[] = 'group';
                } elseif (count($args) > 1) {
                    $more = array();
                    while (count($args) > 1) {
                        $v = array_splice($args, 1, 1);
                        $more[] = reset($v);
                    }
                    $args[0] .= ' - '.implode(', ', $more);
                }
                //
                if ($opts['Collapsed']) {
                    $i++;
                    $d = 0;
                    while ($i < count($this->data['log'])) {
                        $args2 = $this->data['log'][$i];
                        $m2 = array_shift($args2);
                        if (in_array($m2, array('error','warn'))) {
                            $opts['Collapsed'] = false;
                        } elseif (in_array($m2, array('group','groupCollapsed'))) {
                            $d++;
                        } elseif ($m2 == 'groupEnd') {
                            $d--;
                        }
                        if ($d < 0 || !$opts['Collapsed']) {
                            break;
                        }
                        $i++;
                    }
                }
            } elseif ($method == 'groupEnd') {
                $this->data['groupDepth']--;
            } elseif ($method == 'table' && is_array($args[0])) {
                $label = isset($args[1])
                    ? $args[1]
                    : 'table';
                $keys = $this->arrayColkeys($args[0]);
                $table = array();
                $table[] = $keys;
                array_unshift($table[0], '');
                foreach ($args[0] as $k => $row) {
                    $values = array($k);
                    foreach ($keys as $key) {
                        $values[] = isset($row[$key])
                            ? $row[$key]
                            : null;
                    }
                    $table[] = $values;
                }
                $args = array($label, $table);
            } elseif ($method == 'table') {
                $method = 'log';
            } else {
                if (in_array($method, array('error','warn'))) {
                    $end = end($args);
                    if (is_array($end) && isset($end['__errorCaller__'])) {
                        array_pop($args);
                        $opts = array(
                            'File' => $end['file'],
                            'Line' => $end['line'],
                        );
                    }
                }
                if (count($args) > 1) {
                    $label = array_shift($args);
                    if (count($args) > 1) {
                        $args = array( implode(', ', $args) );
                    }
                    $args[] = $label;
                } elseif (is_string($args[0])) {
                    $args[0] = strip_tags($args[0]);
                }
            }
            if (!in_array($method, $firephpMethods)) {
                $method = 'log';
            }
            if ($opts) {
                // opts array needs to be 2nd arg for group method, 3rd arg for all others
                if ($method !== 'group' && count($args) == 1) {
                    $args[] = null;
                }
                $args[] = $opts;
            }
            call_user_func_array(array($firephp,$method), $args);
        }
        while ($this->data['groupDepth'] > 0) {
            call_user_func(array($firephp,'groupEnd'));
            $this->data['groupDepth']--;
        }
        return;
    }

    /**
     * @return string
     */
    protected function _outputHtml()
    {
        $str = '<div class="debug">'."\n";
        if ($this->cfg['outputCss']) {
            $str .= '<style type="text/css">'."\n"
                    .$this->_getCss()."\n"
                .'</style>'."\n";
        }
        $lastError = $this->get('lastError');
        if ($lastError && $lastError['type'] & $this->cfg['errorHandler']['fatalMask']) {
            array_unshift($this->data['log'], array('error error-fatal',$lastError));
        }
        $str .= '<h3>Debug Log:</h3>'."\n";
        if (!empty($this->alert)) {
            $str .= '<div class="alert alert-danger">'.$this->alert.'</div>';
        }
        $str .= '<div class="debug-content clearfix">'."\n";
        foreach ($this->data['log'] as $k_log => $args) {
            $method = array_shift($args);
            if (in_array($method, array('group','groupCollapsed'))) {
                $this->data['groupDepth']++;
                $collapsed_class = '';
                if (!empty($args)) {
                    $label = array_shift($args);
                    $arg_str = '';
                    if ($args) {
                        foreach ($args as $k => $v) {
                            $args[$k] = $this->getDisplayValue($v);
                        }
                        $arg_str = implode(', ', $args);
                    }
                    $collapsed_class = $method == 'groupCollapsed'
                        ? 'collapsed'
                        : 'expanded';
                    $str .= '<div class="group-header">'
                            .'<span class="group-label">'
                                .$label
                                .( !empty($arg_str)
                                    ? '(</span>'.$arg_str.'<span class="group-label">)'
                                    : '' )
                            .'</span>'
                        .'</div>'."\n";
                }
                $str .= '<div class="group '.$collapsed_class.'">';
            } elseif ($method == 'groupEnd') {
                if ($this->data['groupDepth'] > 0) {
                    $this->data['groupDepth']--;
                    $str .= '</div>';
                }
            } elseif ($method == 'table') {
                $str .= call_user_func_array(array($this,'getDisplayTable'), $args);
            } elseif ($method == 'time') {
                $str .= '<div class="time">'.$args[0].': '.$args[1].'</div>';
            } else {
                $attribs = array(
                    'class' => $method,
                    'title' => null,
                );
                if (in_array($method, array('error','warn'))) {
                    $end = end($args);
                    if (is_array($end) && isset($end['__errorCaller__'])) {
                        $a = array_pop($args);
                        $attribs['title'] = $a['file'].': line '.$a['line'];
                    }
                }
                //
                $num_args = count($args);
                $glue = ', ';
                if ($num_args == 2) {
                    $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                        ? ''
                        : ' = ';
                }
                foreach ($args as $k => $v) {
                    /**
                     * first arg, if string will be left untouched
                     * unless it is only arg, which will be visualWhiteSpaced'd
                     */
                    if ($k > 0 || !is_string($v)) {
                        $args[$k] = $this->getDisplayValue($v);
                    } elseif ($num_args == 1) {
                        $args[$k] = $this->visualWhiteSpace($v);
                    }
                }
                /*
                $wrapPre = false;
                foreach ($args as $i => $arg) {
                    if (strpos($arg, '<pre') === 0) {
                        $wrapPre = true;
                        $args[$i] = preg_replace('#^<pre(.*)pre>$#s', '<span\1span>', $arg);
                    }
                }
                */
                $args = implode($glue, $args);
                /*
                if ($wrapPre) {
                    $args = '<pre>'.$args.'</pre>';
                }
                */
                $str .= '<div '.$this->buildAttribString($attribs).'>'.$args.'</div>';
            }
            $str .= "\n";
        }
        while ($this->data['groupDepth'] > 0) {
            $this->data['groupDepth']--;
            $str .='</div><!--unclosed group-->'."\n";
        }
        $str .= '</div>'."\n";  // close debug-content
        $str .= '</div>';       // close debug
        return $str;
    }

    /**
     * Utilities
     */

    /**
     * go through all the "rows" of array to determine what the keys are and their order
     *
     * @param array $rows array
     *
     * @return array
     */
    public function arrayColKeys($rows)
    {
        $last_stack = array();
        $new_stack = array();
        $current_stack = array();
        if (is_array($rows)) {
            foreach ($rows as $row_key => $row) {
                $current_stack = is_array($row)
                    ? array_keys($row)
                    : array('');
                if (empty($last_stack)) {
                    $last_stack = $current_stack;
                } elseif ($current_stack != $last_stack) {
                    $new_stack = array();
                    while (!empty($current_stack)) {
                        $current_key = array_shift($current_stack);
                        if (!empty($last_stack) && $current_key === $last_stack[0]) {
                            array_push($new_stack, $current_key);
                            array_shift($last_stack);
                        } elseif (false !== $position = array_search($current_key, $last_stack, true)) {
                            $segment = array_splice($last_stack, 0, $position+1);
                            array_splice($new_stack, count($new_stack), 0, $segment);
                        } elseif (!in_array($current_key, $new_stack, true)) {
                            array_push($new_stack, $current_key);
                        }
                    }
                    //put on remaining from last_stack
                    array_splice($new_stack, count($new_stack), 0, $last_stack);
                    $new_stack = array_unique($new_stack);
                    $last_stack = $new_stack;
                }
            }
        }
        $keys = $last_stack;
        return $keys;
    }

    /**
     * [array_merge_deep description]
     *
     * @param array $def_array default array
     * @param array $a2        array 2
     *
     * @return array
     */
    public function arrayMergeDeep($def_array, $a2)
    {
        if (!is_array($def_array) || !is_array($a2)) {
            $def_array = $a2;
        } else {
            foreach ($a2 as $k2 => $v2) {
                if (is_int($k2)) {
                    if (!in_array($v2, $def_array)) {
                        $def_array[] = $v2;
                    }
                } elseif (!isset($def_array[$k2])) {
                    $def_array[$k2] = $v2;
                } elseif (!is_array($v2)) {
                    $def_array[$k2] = $v2;
                } else {
                    $def_array[$k2] = $this->arrayMergeDeep($def_array[$k2], $v2);
                }
            }
        }
        return $def_array;
    }

    /**
     * basic html attrib builder
     *
     * @param array $attribs key/pair values
     *
     * @return string
     */
    public function buildAttribString($attribs)
    {
        $attrib_pairs = array();
        foreach ($attribs as $k => $v) {
            if (isset($v)) {
                $attrib_pairs[] = $k.'="'.htmlspecialchars($v).'"';
            }
        }
        return implode(' ', $attrib_pairs);
    }

    /**
     * returns a sent/pending response header value
     * only works with php >= 5
     *
     * @param string $key default = 'Content-Type', header to return
     *
     * @return string
     */
    public function getResponseHeader($key = 'Content-Type')
    {
        $value = null;
        if (function_exists('headers_list')) {
            $headers = headers_list();
            $key = 'Content-Type';
            foreach ($headers as $header) {
                if (preg_match('/^'.$key.':\s*([^;]*)/i', $header, $matches)) {
                    $value = $matches[1];
                    break;
                }
            }
        }
        return $value;
    }

    /**
     * Checks if a given string is base64 encoded
     *
     * @param string $str string to check
     *
     * @return bool
     */
    public function isBase64Encoded($str)
    {
        return preg_match('%^[a-zA-Z0-9(!\s+)?\r\n/+]*={0,2}$%', trim($str));
    }

    /**
     * Intent is to check if a given string is "binary" data or readable text
     *
     * @param string $str string to check
     *
     * @return bool
     */
    public function isBinary($str)
    {
        $b = false;
        if (is_string($str)) {
            $isUtf8 = $this->isUtf8($str, $ctrl);
            if (!$isUtf8 || $ctrl) {
                $b = true;
            }
        }
        return $b;
    }

    /**
     * Determine if passed array contains a self referencing loop
     *
     * @param mixed $mixed array or object to check
     * @param mixed $k     check if this is the key/value that is the reference
     *
     * @return bool
     * @internal
     * @link http://stackoverflow.com/questions/9105816/is-there-a-way-to-detect-circular-arrays-in-pure-php
     */
    public function isRecursive($mixed, $k = null)
    {
        $recursive = false;
        //"Array *RECURSION" or "Object *RECURSION*"
        if (strpos(print_r($mixed, true), "\n *RECURSION*\n") !== false) {
            // contains recursion somewhere
            $recursive = true;
            if ($k !== null) {
                // array contains recursion or a string containing "Array *RECURSION*"
                $recursive = $this->isRecursiveIteration($mixed);
                if ($recursive) { // && $k !== null
                    // test if this is the value that's the reference
                    $recursive = $k === $recursive[0];
                }
            }
        }
        return $recursive;
    }

    /**
     * returns a path to first recursive loop found or false if no recursion
     *
     * @param array &$array array
     * @param mixed $unique some unique value/object
     *          this value will be appended to the array and checked for in nested structure
     * @param array $path   {@internal}
     *
     * @return mixed false, or path to reference
     * @internal
     */
    public function isRecursiveIteration(&$array, $unique = null, $path = array())
    {
        if ($unique === null) {
            $unique = new \stdclass();
        } elseif ($unique === end($array)) {
            return $path;
        }
        if (is_array($array)) {
            $type = 'array';
            $array[] = $unique;
            $ks = array_keys($array);
        } else {
            $type = 'object';
            $ks = array_keys(get_object_vars($array));
        }
        foreach ($ks as $k) {
            if ($type == 'array') {
                $v = &$array[$k];
            } else {
                $v = &$object->{$k};
            }
            $path_new = $path;
            $path_new[] = $k;
            if (is_array($v) || is_object($v)) {
                $path_new = $this->isRecursiveIteration($v, $unique, $path_new);
                if ($path_new) {
                    if (end($array) === $unique) {
                        unset($array[key($array)]);
                    }
                    return $path_new;
                }
            }
        }
        if (end($array) === $unique) {
            unset($array[key($array)]);
        }
        return array();
    }

    /**
     * @param string $str   string to check
     * @param bool   &$ctrl does string contain a "non-printable" control char?
     *
     * @return bool
     */
    public function isUtf8($str, &$ctrl = false)
    {
        $length = strlen($str);
        $ctrl = false;
        for ($i=0; $i < $length; $i++) {
            $c = ord($str[$i]);
            if ($c < 0x80) {                    # 0bbbbbbb
                $n = 0;
            } elseif (($c & 0xE0) == 0xC0) {    # 110bbbbb
                $n=1;
            } elseif (($c & 0xF0) == 0xE0) {    # 1110bbbb
                $n=2;
            } elseif (($c & 0xF8) == 0xF0) {    # 11110bbb
                $n=3;
            } elseif (($c & 0xFC) == 0xF8) {    # 111110bb
                $n=4;
            } elseif (($c & 0xFE) == 0xFC) {    # 1111110b
                $n=5;
            } else {                            # Does not match any model
                return false;
            }
            for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
                if ((++$i == $length) || ( (ord($str[$i]) & 0xC0) != 0x80 )) {
                    return false;
                }
            }
            if ($n == 0 && ( $c < 32 || $c == 127 )) {
                if (!in_array($str[$i], array("\t","\n","\r"))) {
                    $ctrl = true;
                }
            }
        }
        if (strpos($str, "\xef\xbb\xbf") !== false) {
            $ctrl = true;   // treat BOM as ctrl char
        }
        return true;
    }

    /**
     * @param string $str string to convert
     *
     * @return string
     */
    public function toUtf8($str)
    {
        if (extension_loaded('mbstring') && function_exists('iconv')) {
            $encoding = mb_detect_encoding($str, mb_detect_order(), true);
            if ($encoding && !in_array($encoding, array('ASCII','UTF-8'))) {
                $str_new = iconv($encoding, 'UTF-8', $str);
                if ($str_new !== false) {
                    $str = $str_new;
                } else {
                    // iconv error?
                }
            }
        }
        return $str;
    }
}
