<?php
/**
 * Web-browser/javascript like console class for PHP
 *
 * @author Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.2
 *
 * @link    http://www.github.com/bkdotcom/PHPDebugConsole
 * @link    https://developer.mozilla.org/en-US/docs/Web/API/console
 */

namespace bdk\Debug;

/**
 * Web-browser/javascript like console class for PHP
 */
class Debug
{

    private static $instance;
    protected $state = null;  // 'output' while in output()
    protected $cfg = array();
    protected $data = array();
    protected $collect;
    protected $outputSent = false;
    public $display;
    public $utilities;

    /**
     * Constructor
     *
     * @param array  $cfg          config
     * @param object $errorHandler optional - uses \bdk\Debug\ErrorHandler if not passed
     */
    public function __construct($cfg = array(), $errorHandler = null)
    {
        $this->cfg = array(
            'addBR'     => false,           // convert \n to <br />\n in strings?
            'css'       => '',
            'collect'   => false,
            'file'      => null,            // if a filepath, will receive log data
            'firephpInc' => dirname(__FILE__).'/FirePHP.class.php',
            'firephpOptions' => array(
                'useNativeJsonEncode'   => true,
                'includeLineNumbers'    => false,
            ),
            'key'       => null,
            'output'    => false,           // should output() actually output to browser (either as html or firephp)
            'outputAs'  => null,            // 'html' or 'firephp', if null, will be determined automatically
            'outputCss' => true,
            'outputScript' => true,
            'filepathCss' => dirname(__FILE__).'/Debug.css',
            'filepathScript' => dirname(__FILE__).'/Debug.jquery.min.js',
            // errorMask = errors that appear as "error" in debug console... all other errors are "warn"
            'errorMask' => E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR
                            | E_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR,
            'emailLog'  => false,           // whether to email a debug log. false, 'onError' (true), or 'always'
                                            //   requires 'collect' to also be true
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'onOutput'  => null,            // set to something callable
        );
        $this->data = array(
            'alert' => '',
            'counts' => array(),    // count method
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
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            list($whole, $dec) = explode('.', $_SERVER['REQUEST_TIME_FLOAT']);
            $mt = '.'.$dec.' '.$whole;
            $this->data['timers']['labels']['debugInit'] = $mt;
        }
        // Initialize self::$instance if not set
        //    so that self::getInstance() will always return original instance
        //    as opposed the the last instance created with new Debug()
        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        require_once dirname(__FILE__).'/Utilities.php';
        require_once dirname(__FILE__).'/Display.php';
        $this->utilities = new Utilities();
        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
        } else {
            require_once dirname(__FILE__).'/ErrorHandler.php';
            $this->errorHandler = ErrorHandler::getInstance();
        }
        $this->errorHandler->registerOnErrorFunction(array($this,'onError'));
        $this->set($cfg);
        $this->display = new Display(array(
            'addBR' => $this->cfg['addBR'],
        ), $this->utilities);
        $this->collect = &$this->cfg['collect'];
        register_shutdown_function(array($this, 'shutdownFunction'));
        return;
    }

    /**
     * Log a message and stack trace to console if first argument is false.
     *
     * @return void
     */
    public function assert()
    {
        if ($this->collect) {
            $args = func_get_args();
            $test = array_shift($args);
            if (!$test) {
                $this->appendLog('assert', $args);
            }
        }
    }

    /**
     * Log the number of times this has been called with the given label.
     *
     * @param mixed $label label
     *
     * @return integer
     */
    public function count($label = null)
    {
        $return = 0;
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
            $this->appendLog('count', $args);
            $return = $this->data['counts'][$label];
        }
        return $return;
    }

    /**
     * Outputs an error message.
     *
     * @param mixed $label,... label
     *
     * @return void
     */
    public function error()
    {
        if ($this->collect) {
            $args = func_get_args();
            $this->appendLog('error', $args);
        }
    }

    /**
     * Retrieve a config value, lastError, or css
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
                /*
                    determine outputAs automatically
                */
                $ret = 'html';
                $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                    ? $_SERVER['HTTP_X_REQUESTED_WITH']
                    : null;
                $ajax = $requestedWith == 'XMLHttpRequest';
                if ($ajax) {
                    $ret = 'firephp';
                } else {
                    $contentType = $this->utilities->getResponseHeader();
                    if ($contentType && $contentType !== 'text/html') {
                        $ret = 'firephp';
                    }
                }
            }
        } elseif ($k == 'lastError') {
            $ret = $this->errorHandler->get('lastError');
        } elseif ($k == 'css') {
            $ret = $this->getCss();
        } else {
            $path = preg_split('#[\./]#', $k);
            if ($path[0] == 'errorHandler') {
                $ret = $this->errorHandler->get($path[1]);
            } else {
                if ($path[0] == 'data') {
                    $ret = $this->data;
                    array_shift($path);
                } else {
                    $ret = $this->cfg;
                }
                foreach ($path as $k) {
                    if (isset($ret[$k])) {
                        $ret = $ret[$k];
                    } else {
                        $ret = null;
                        break;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @param array $cfg optional config
     *
     * @return object
     */
    public static function getInstance($cfg = array())
    {
        if (!isset(self::$instance)) {
            $className = __CLASS__;
            // self::$instance set in __construct
            new $className($cfg);
        } elseif ($cfg) {
            self::$instance->set($cfg);
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
            $this->appendLog('group', $args);
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
            $this->appendLog('groupCollapsed', $args);
        }
    }

    /**
     * Sets current group or groupCollapsed to 'group' (ie, make sure it's uncollapsed)
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
        $eC = $this->errorHandler->get('errorCaller');
        if ($eC && isset($eC['depth']) && $this->data['groupDepth'] < $eC['depth']) {
            $this->errorHandler->setErrorCaller(null);
        }
        if ($this->collect) {
            $args = func_get_args();
            $this->appendLog('groupEnd', $args);
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
            $this->appendLog('info', $args);
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
            $this->appendLog('log', $args);
        }
    }

    /**
     * Return the log (formatted as html), or send to FirePHP
     *
     *  If outputAs == null -> determined automatically
     *  If outputAs == 'html' -> returns html string
     *  If outputAs == 'firephp' -> returns null
     *
     * @return string or void
     */
    public function output()
    {
        $return = null;
        $this->state = 'output';
        if ($this->cfg['output']) {
            array_unshift($this->data['log'], array('info','Built In '.$this->timeEnd('debugInit', true).' sec'));
            $outputAs = $this->get('outputAs');
            if (is_callable($this->cfg['onOutput'])) {
                call_user_func($this->cfg['onOutput'], $outputAs);
            }
            $outputAs = $this->get('outputAs');
            $errorSummary = $this->errorSummary();
            if ($errorSummary) {
                $this->data['alert'] = $errorSummary;
            }
            $this->data['groupDepth'] = 0;
            if ($outputAs == 'html') {
                $return = $this->outputHtml();
            } elseif ($outputAs == 'firephp') {
                $this->outputFirephp();
                $return = null;
            }
            $this->outputSent = true;
            $this->data['log'] = array();
        }
        $this->state = null;
        return $return;
    }

    /**
     * Set one or more config values
     *
     * If setting a single value via method a or b, old value is returned
     *
     * Setting/updating 'key' will also set 'collect' and 'output'
     *
     *    set('key', 'value')
     *    set('level1.level2', 'value')
     *    set(array('k1'=>'v1', 'k2'=>'v2'))
     *
     * @param string $k key
     * @param mixed  $v value
     *
     * @return mixed
     */
    public function set($k, $v = null)
    {
        $ret = null;
        $what = 'cfg';
        $new = array(); // the new value(s) to merge
        if (is_string($k)) {
            $path = preg_split('#[\./]#', $k);
            $ref = &$new;
            if ($path[0] == 'data') {
                $what = 'data';
                $ret = $this->data;
                array_shift($path);
            } else {
                $ret = $this->cfg;
            }
            foreach ($path as $k) {
                $ret = isset($ret[$k])
                    ? $ret[$k]
                    : null;
                $ref[$k] = array(); // initialize this level
                $ref = &$ref[$k];
            }
            $ref = $v;
        } elseif (is_array($k)) {
            $new = $k;
        }
        if (isset($new['key'])) {
            $keyPassed = null;
            if (isset($_REQUEST['debug'])) {
                $keyPassed = $_REQUEST['debug'];
            } elseif (isset($_COOKIE['debug'])) {
                $keyPassed = $_COOKIE['debug'];
            }
            $validKey = $keyPassed == $new['key'];
            if ($validKey) {
                // only enable collect / don't disable it
                $new['collect'] = true;
            }
            $new['output'] = $validKey;
        }
        if (isset($new['emailLog']) && $new['emailLog'] === true) {
            $new['emailLog'] = 'onError';
        }
        if (isset($new['emailTo']) && !isset($new['errorHandler']['emailTo'])) {
            // also set errorHandler's emailTo
            $this->errorHandler->set('emailTo', $new['emailTo']);
        }
        if (isset($new['errorHandler'])) {
            $this->errorHandler->set($new['errorHandler']);
            unset($new['errorHandler']);
        }
        if ($what == 'data') {
            $this->data = array_merge($this->data, $new);
        } else {
            $this->cfg = $this->utilities->arrayMergeDeep($this->cfg, $new);
        }
        return $ret;
    }

    /**
     * Set one or more config values
     *
     * @param string $k,... key
     * @param mixed  $v,... value
     *
     * @return mixed
     * @deprecated use set() instead
     */
    public function setCfg()
    {
        $this->errorHandler->setErrorCaller();
        trigger_error('setCfg() is deprecated -> use set instead()', E_USER_DEPRECATED);
        return call_user_func_array(array($this,'set'), func_get_args());
    }

    /**
     * A wrapper for errorHandler->setErrorCaller
     *
     * @param array $caller optional. pass null or array() to clear
     *
     * @return void
     */
    public function setErrorCaller($caller = 'notPassed')
    {
        $this->errorHandler->setErrorCaller($caller, 2);
        if (!empty($caller)) {
            $this->errorHandler->set('data/errorCaller/depth', $this->data['groupDepth']);
        }
    }

    /**
     * Output array as a table
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
            $this->appendLog($method, $args);
        }
    }

    /**
     * Start a timer identified by label
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
     * Logs time elapsed since started with time()
     * If no label is passed, timer is removed from a timer stack
     *
     * @param string  $label  unique label
     * @param boolean $return = false. If true, return elapsed time rather than log it
     *
     * @return float
     */
    public function timeEnd($label = null, $return = false)
    {
        $ret = 0;
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
                $this->appendLog('time', array($label, $ret.' sec'));
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
            $this->appendLog('warn', $args);
        }
    }

    /**
     * "Non-Public" methods
     */

    /**
     * Serializes and emails log
     *
     * @return void
     */
    protected function emailLog()
    {
        $body = '';
        $unsuppressedError = false;
        /*
            List errors that occured
        */
        $errors = $this->errorHandler->get('errors');
        $cmp = create_function('$a1, $a2', 'return strcmp($a1["file"].$a1["line"], $a2["file"].$a2["line"]);');
        uasort($errors, $cmp);
        $lastFile = '';
        foreach ($errors as $error) {
            if ($error['suppressed']) {
                continue;
            }
            if ($error['file'] !== $lastFile) {
                $body .= $error['file'].':'."\n";
                $lastFile = $error['file'];
            }
            $body .= '  Line '.$error['line'].': '.$error['message']."\n";
            $unsuppressedError = true;
        }
        $subject = 'Debug Log: '.$_SERVER['HTTP_HOST'].( $unsuppressedError ? ' (Error)' : '' );
        /*
            Serialize the log
        */
        $outputCssWas = $this->set('outputCss', false);
        $serialized = $this->outputHtml();
        $this->set('outputCss', $outputCssWas);
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
     * Returns an error summary
     *
     * @return string html
     */
    protected function errorSummary()
    {
        $html = '';
        $counts = array();
        $errors = $this->errorHandler->get('errors');
        foreach ($errors as $error) {
            if ($error['suppressed']) {
                continue;
            }
            $category = $error['category'];
            if (!isset($counts[$category])) {
                $counts[$category] = array(
                    'inConsole' => 0,
                    'notInConsole' => 0,
                );
            }
            $k = $error['inConsole'] ? 'inConsole' : 'notInConsole';
            $counts[$category][$k]++;
        }
        if ($counts) {
            $totals = array(
                'inConsole' => 0,
                'inConsoleTypes' => 0,
                'notInConsole' => 0,
            );
            foreach ($counts as $a) {
                $totals['inConsole'] += $a['inConsole'];
                $totals['notInConsole'] += $a['notInConsole'];
                if ($a['inConsole']) {
                    $totals['inConsoleTypes']++;
                }
            }
            ksort($counts);
            /*
                first show logged counts
                then show not-logged counts
            */
            if ($totals['inConsole']) {
                if ($totals['inConsoleTypes'] == 1) {
                    // all same type of error
                    reset($counts);
                    $type = key($counts);
                    if ($totals['inConsole'] == 1) {
                        $html = 'There was 1 error';
                        if ($type == 'fatal') {
                            $html = ''; // don't bother with this alert..
                                        // fatal are still prominently displayed
                        } elseif ($type != 'error') {
                            $html .= ' ('.$type.')';
                        }
                    } else {
                        $html = 'There were '.$totals['inConsole'].' errors';
                        if ($type != 'error') {
                            $html .= ' of type '.$type;
                        }
                    }
                    if ($html) {
                        $html = '<h3 class="error-'.$type.'">'.$html.'</h3>'."\n";
                    }
                } else {
                    // multiple error types
                    $html = '<h3>There were '.$totals['inConsole'].' errors:</h3>'."\n";
                    $html .= '<ul class="list-unstyled indent">';
                    foreach ($counts as $type => $a) {
                        if (!$a['inConsole']) {
                            continue;
                        }
                        $html .= '<li class="error-'.$type.'">'.$type.': '.$a['inConsole'].'</li>';
                    }
                    $html .= '</ul>';
                }
            }
            if ($totals['notInConsole']) {
                $count = 0;
                $htmlNotIn = '<ul class="list-unstyled indent">';
                foreach ($errors as $err) {
                    if ($err['suppressed'] || $err['inConsole']) {
                        continue;
                    }
                    $count ++;
                    $htmlNotIn .= '<li>'.$err['typeStr'].': '.$err['file'].' (line '.$err['line'].'): '.$err['message'].'</li>';
                }
                $htmlNotIn .= '</ul>';
                $count = $count == 1
                    ? 'was 1 error'
                    : 'were '.$count.' errors';
                $html .= '<h3>There '.$count.' captured while not collecting debug info</h3>'
                    . $htmlNotIn;

            }
        }
        return $html;
    }

    /**
     * Email Log if emailLog is 'always' or 'onError'
     *
     * @return void
     */
    public function shutdownFunction()
    {
        $email = false;
        // data['log']  will likely be non-empty... initial debug info is always collected
        if ($this->cfg['emailTo'] && !$this->cfg['output'] && $this->data['log']) {
            if ($this->cfg['emailLog'] === 'always') {
                $email = true;
            } elseif ($this->cfg['emailLog'] === 'onError') {
                $unsuppressedError = false;
                $emailableError = false;
                $errors = $this->errorHandler->get('errors');
                $emailMask = $this->errorHandler->get('emailMask');
                foreach ($errors as $error) {
                    if (!$error['suppressed']) {
                        $unsuppressedError = true;
                    }
                    if ($error['type'] & $emailMask) {
                        $emailableError = true;
                    }
                }
                if ($unsuppressedError && $emailableError) {
                    $email = true;
                }
            }
        }
        if ($email) {
            $this->emailLog();
        }
        /*
            output the log if it hasn't already been output
            this will also output for fatal errors
        */
        if (!$this->outputSent) {
            echo $this->output();
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
        $str = $this->utilities->isBase64Encoded($str)
            ? base64_decode($str)
            : false;
        if ($str && function_exists('gzinflate')) {
            $strInflated = gzinflate($str);
            if ($strInflated) {
                $str = $strInflated;
            }
        }
        return $str;
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
    protected function appendLog($method, $args)
    {
        foreach ($args as $i => $v) {
            if (is_array($v) || is_object($v) || is_resource($v)) {
                $args[$i] = $this->utilities->valuePrep($v);
            }
        }
        array_unshift($args, $method);
        if (!empty($this->cfg['file'])) {
            $this->appendLogFile($args);
        }
        /*
            if logging an error or notice, also log originating file/line
        */
        if (in_array($method, array('error','warn'))) {
            $backtrace = debug_backtrace();
            // path if via ErrorHandler :
            //    ErrorHandler::handler -> call_user_function -> self::onError -> self::warn -> here we are
            $viaErrorHandler = isset($backtrace[4])
                && $backtrace[4]['function'] == 'handler'
                && $backtrace[4]['class'] == 'bdk\Debug\ErrorHandler';
            if ($viaErrorHandler) {
                // no need to store originating file/line... it's part of error message
                // store errorCat -> can output as a className
                $lastError = $this->errorHandler->get('lastError');
                $args[] = array(
                    '__debugMeta__' => true,
                    'errorType' => $lastError['type'],
                    'errorCat' => $lastError['category'],
                );
            } else {
                foreach ($backtrace as $k => $a) {
                    if (isset($a['file']) && $a['file'] !== __FILE__) {
                        if (in_array($a['function'], array('call_user_func','call_user_func_array'))) {
                            continue;
                        }
                        $args[] = array(
                            '__debugMeta__' => true,
                            'file' => $a['file'],
                            'line' => $a['line'],
                        );
                        break;
                    }
                }
            }
        }
        $this->data['log'][] = $args;
        return;
    }

    /**
     * Appends log entry to $this->cfg['file']
     *
     * @param array $args args
     *
     * @return void
     */
    protected function appendLogFile($args)
    {
        if (!isset($this->data['fileHandle'])) {
            $this->data['fileHandle'] = fopen($this->cfg['file'], 'a');
            if ($this->data['fileHandle']) {
                fwrite($this->data['fileHandle'], '***** '.date('Y-m-d H:i:s').' *****'."\n");
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
                        $v = $this->display->getDisplayValue($v, array('html'=>false, 'flatten'=>true));
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
     * Return the log's CSS
     *
     * @return string
     */
    protected function getCss()
    {
        $return = file_get_contents($this->cfg['filepathCss']);
        if (!empty($this->cfg['css'])) {
            $return .=  "\n".$this->cfg['css']."\n";
        }
        return $return;
    }

    /**
     * onError callback
     * called by $this->errorHandler
     * adds error to console as error or warn
     *
     * @param array $error array containing error details
     *
     * @return mixed
     */
    public function onError($error)
    {
        $return = null;
        if ($this->collect) {
            $return = false;    // no need to error_log or email this error
            $errStr = $error['typeStr'].': '.$error['file'].' (line '.$error['line'].'): '.$error['message'];
            if ($error['type'] & $this->cfg['errorMask']) {
                $this->error($errStr);
            } else {
                $this->warn($errStr);
            }
            $this->errorHandler->set('data/errors/'.$error['hash'].'/inConsole', true);
            if (in_array($this->cfg['emailLog'], array('always','onError'))) {
                // Don't let errorHandler email error.  our shutdownFunction will email log
                $this->errorHandler->set('data/currentError/allowEmail', false);
            }
        } elseif (!isset($error['inConsole'])) {
            $this->errorHandler->set('data/errors/'.$error['hash'].'/inConsole', false);
        }
        return $return;
    }

    /**
     * Pass the log to FirePHP methods
     *
     * @return void
     */
    protected function outputFirephp()
    {
        if (!file_exists($this->cfg['firephpInc'])) {
            return;
        }
        require_once $this->cfg['firephpInc'];
        $firephp = \FirePHP::getInstance(true);
        $firephpMethods = get_class_methods($firephp);
        $firephp->setOptions($this->get('firephpOptions'));
        if (!empty($this->data['alert'])) {
            $alert = str_replace('<br />', "\n", $this->data['alert']);
            array_unshift($this->data['log'], array('error', $alert));
        }
        foreach ($this->data['log'] as $i => $args) {
            $method = array_shift($args);
            $opts = array();
            foreach ($args as $k => $arg) {
                $args[$k] = $this->display->getDisplayValue($arg, array('html'=>false,'boolNullToString'=>false));
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
                $keys = $this->utilities->arrayColkeys($args[0]);
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
                    if (is_array($end) && isset($end['__debugMeta__'])) {
                        array_pop($args);
                        if (isset($end['file'])) {
                            $opts = array(
                                'File' => $end['file'],
                                'Line' => $end['line'],
                            );
                        }
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
     * Return the log as HTML
     *
     * @return string
     */
    protected function outputHtml()
    {
        $str = '<div class="debug">'."\n";
        if ($this->cfg['outputCss']) {
            $str .= '<style type="text/css">'."\n"
                    .$this->getCss()."\n"
                .'</style>'."\n";
        }
        if ($this->cfg['outputScript']) {
            $str .= '<script type="text/javascript">'
                .file_get_contents($this->cfg['filepathScript'])
                .'</script>';
        }
        $lastError = $this->get('lastError');
        if ($lastError && $lastError['type'] & $this->errorHandler->get('fatalMask')) {
            array_unshift($this->data['log'], array('error error-fatal',$lastError));
        }
        $str .= '<h3>Debug Log:</h3>'."\n";
        if (!empty($this->data['alert'])) {
            $str .= '<div class="alert alert-danger">'.$this->data['alert'].'</div>';
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
                            $args[$k] = $this->display->getDisplayValue($v);
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
                $str .= call_user_func_array(array($this->display,'getDisplayTable'), $args);
            } elseif ($method == 'time') {
                $str .= '<div class="time">'.$args[0].': '.$args[1].'</div>';
            } else {
                $attribs = array(
                    'class' => $method,
                    'title' => null,
                );
                if (in_array($method, array('error','warn'))) {
                    $end = end($args);
                    if (is_array($end) && isset($end['__debugMeta__'])) {
                        $a = array_pop($args);
                        if (isset($a['file'])) {
                            $attribs['title'] = $a['file'].': line '.$a['line'];
                        }
                        if (isset($a['errorCat'])) {
                            $attribs['class'] .= ' error-'.$a['errorCat'];
                        }
                    }
                }
                $num_args = count($args);
                $glue = ', ';
                if ($num_args == 2) {
                    $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                        ? ''
                        : ' = ';
                }
                foreach ($args as $k => $v) {
                    /*
                        first arg, if string will be left untouched
                        unless it is only arg, which will be visualWhiteSpaced'd
                    */
                    if ($k > 0 || !is_string($v)) {
                        $args[$k] = $this->display->getDisplayValue($v);
                    } elseif ($num_args == 1) {
                        $args[$k] = $this->display->visualWhiteSpace($v);
                    }
                }
                $args = implode($glue, $args);
                $str .= '<div '.$this->utilities->buildAttribString($attribs).'>'.$args.'</div>';
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
}
