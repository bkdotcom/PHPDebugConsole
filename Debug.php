<?php
/**
 * Web-browser/javascript like console class for PHP
 *
 * @author Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.1
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

    const VALUE_ABSTRACTION = "\x00debug\x00";

    /**
     * Constructor
     *
     * @param array $cfg config
     */
    public function __construct($cfg = array())
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
            'emailLog'  => false,           // whether to email a debug log. false, 'onError' (true), or 'always'
                                            //   requires 'collect' to also be true
            'emailTo'   => !empty($_SERVER['SERVER_ADMIN'])
                ? $_SERVER['SERVER_ADMIN']
                : null,
            'onOutput'  => null,                // set to something callable
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
        require_once dirname(__FILE__).'/ErrorHandler.php';
        $this->errorHandler = new ErrorHandler(array(), $this);
        $this->set($cfg);
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
                    $contentType = $this->getResponseHeader();
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
        if ($eC && $this->data['groupDepth'] < $eC['depth']) {
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
        }
        $this->data['log'] = array();
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
            $new['collect'] = $keyPassed == $new['key'];
            $new['output'] = $new['collect'];
        }
        if (isset($new['emailLog']) && $new['emailLog'] === true) {
            $new['emailLog'] = 'onError';
        }
        if (isset($new['errorHandler'])) {
            $this->errorHandler->set($new['errorHandler']);
            unset($new['errorHandler']);
        }
        if ($what == 'data') {
            $this->data = array_merge($this->data, $new);
        } else {
            $this->cfg = $this->arrayMergeDeep($this->cfg, $new);
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
        $this->errorHandler->setErrorCaller($caller);
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
            $errTypesGrouped = $this->errorHandler->get('errTypesGrouped');
            foreach ($errTypesGrouped as $type => $errTypes) {
                if (!in_array($error['type'], $errTypes)) {
                    continue;
                }
                if (!isset($counts[$type])) {
                    $counts[$type] = array(
                        'inConsole' => 0,
                        'notInConsole' => 0,
                    );
                }
                $k = $error['inConsole'] ? 'inConsole' : 'notInConsole';
                $counts[$type][$k]++;
                break;
            }
        }
        if ($counts) {
            $totals = array(
                'inConsole' => 0,
                'inConsoleTypes' => 0,
                'notInConsole' => 0,
                // 'both' => 0,
            );
            foreach ($counts as $a) {
                $totals['inConsole'] += $a['inConsole'];
                $totals['notInConsole'] += $a['notInConsole'];
                if ($a['inConsole']) {
                    $totals['inConsoleTypes']++;
                }
            }
            // $totals['both'] = $totals['inConsole'] + $a['notInConsole'];
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
     * Display non-printable characters as hex
     *
     * @param string  $str     string containing binary
     * @param boolean $htmlout add html markup?
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
     * Callback used by getDisplayBinary's preg_replace_callback
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
     * Formats an array as a table
     *
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
     * Returns string representation of value
     *
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
                /*
                    getDisplayValue() called directly?
                */
                if (is_array($v) || is_object($v) || is_resource($v)) {
                    $v = $this->appendLogPrep($v);
                }
            }
        }
        if (is_array($v) && in_array(self::VALUE_ABSTRACTION, $v, true)) {
            /*
                array (recursion), object, or resource
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
                $html = preg_replace(
                    '#^([^\n]+)\n(.+)$#s',
                    '<span class="t_object-class">\1</span>'."\n".'<span class="t_object-inner">\2</span>',
                    $v
                );
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
     * Email Log if emailLog is 'always' or 'onError'
     *
     * @return void
     */
    public function shutdownFunction()
    {
        $email = false;
        // data['log']  will likely be non-empty... initial debug info is always collected
        if ($this->cfg['emailTo'] && !$this->cfg['output'] && $this->data['log']) {
            $unsuppressedError = false;
            $errors = $this->errorHandler->get('errors');
            foreach ($errors as $error) {
                if (!$error['suppressed']) {
                    $unsuppressedError = true;
                    continue;
                }
            }
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
     * Add whitespace markup
     *
     * @param string $str string which to add whitespace html markup
     *
     * @return string
     */
    public function visualWhiteSpace($str)
    {
        // display \r, \n, & \t
        $str = preg_replace_callback('/(\r\n|\r|\n)/', array($this, 'visualWhiteSpaceCallback'), $str);
        $str = preg_replace('#(<br />)?\n$#', '', $str);
        $str = str_replace("\t", '<span class="ws_t">'."\t".'</span>', $str);
        return $str;
    }

    /**
     * Adds whitespace markup
     *
     * @param array $matches passed from preg_replace_callback
     *
     * @return string
     */
    protected function visualWhiteSpaceCallback($matches)
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
    protected function appendLog($method, $args)
    {
        foreach ($args as $i => $v) {
            if (is_array($v) || is_object($v) || is_resource($v)) {
                $args[$i] = $this->appendLogPrep($v);
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
            $viaErrorHandler = false;
            foreach ($backtrace as $k => $a) {
                if ($a['function'] == 'handler' && $a['class'] == 'bdk\Debug\ErrorHandler') {
                    // no need to store originating file/line... it's part of error message
                    // store errorCat -> can output as a className
                    $viaErrorHandler = true;
                    $lastError = $this->errorHandler->get('lastError');
                    $errTypesGrouped = $this->errorHandler->get('errTypesGrouped');
                    // find errorCat
                    foreach ($errTypesGrouped as $errorCat => $errTypes) {
                        if (in_array($lastError['type'], $errTypes)) {
                            break;
                        }
                    }
                    $args[] = array(
                        '__debugMeta__' => true,
                        'errorType' => $lastError['type'],
                        'errorCat' => $errorCat,
                    );
                    break;
                }
            }
            if (!$viaErrorHandler) {
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
    protected function appendLogPrep($mixed, $hist = array(), $path = array())
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
                $v_new = $this->appendLogPrep($v, $hist, $path_new);
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
                        $args[$k] = $this->getDisplayValue($v);
                    } elseif ($num_args == 1) {
                        $args[$k] = $this->visualWhiteSpace($v);
                    }
                }
                $args = implode($glue, $args);
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
     * Go through all the "rows" of array to determine what the keys are and their order
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
                    // put on remaining from last_stack
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
     * Recursively merge two arrays
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
     * Basic html attrib builder
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
     * Returns a sent/pending response header value
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
     * @return boolean
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
     * @return boolean
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
     * @return boolean
     * @internal
     * @link http://stackoverflow.com/questions/9105816/is-there-a-way-to-detect-circular-arrays-in-pure-php
     */
    public function isRecursive($mixed, $k = null)
    {
        $recursive = false;
        // "Array *RECURSION" or "Object *RECURSION*"
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
     * Returns a path to first recursive loop found or false if no recursion
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
     * Determine if string is UTF-8 encoded
     *
     * @param string  $str   string to check
     * @param boolean &$ctrl does string contain a "non-printable" control char?
     *
     * @return boolean
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
     * Attempt to convert string to UTF-8 encoding
     *
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
