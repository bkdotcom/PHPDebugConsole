<?php
/**
 * Output log as html or to FirePHP
 *
 * @package PHPDebugConsole
 * @author  Brad Kent <bkfake-github@yahoo.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @version v1.3b
 */

namespace bdk\Debug;

/**
 * Output methods
 */
class Output
{

    private $cfg = array();
    private $data = array();
    private $debug;

    /**
     * Constructor
     *
     * @param array $cfg  configuration
     * @param array $data data
     */
    public function __construct($cfg = array(), &$data = array())
    {
        $this->debug = Debug::getInstance();
        $this->cfg = array(
            'css' => '',                    // additional "override" css
            'filepathCss' => __DIR__.'/css/Debug.css',
            'filepathScript' => __DIR__.'/js/Debug.jquery.min.js',
            'firephpInc' => __DIR__.'/FirePHP.class.php',
            'firephpOptions' => array(
                'useNativeJsonEncode'   => true,
                'includeLineNumbers'    => false,
            ),
            'onOutput'  => null,            // set to something callable
            'outputAs'  => null,            // 'html', 'script', 'text', or 'firephp', if null, will be determined automatically
            'outputCss' => true,
            'outputScript' => true,
        );
        $this->set($cfg);
        $this->data = &$data;
    }

    /**
     * Serializes and emails log
     *
     * @return void
     */
    public function emailLog()
    {
        $body = '';
        $unsuppressedError = false;
        /*
            List errors that occured
        */
        $errors = $this->debug->errorHandler->get('errors');
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
            "attach" "serialized" log
        */
        $body .= 'Request: '.$_SERVER['REQUEST_METHOD'].': '.$_SERVER['REQUEST_URI']."\n\n";
        $body .= $this->debug->utilities->serializeLog($this->debug->get('data/log'));
        /*
            Now email
        */
        $this->debug->email($this->debug->get('emailTo'), $subject, $body);
        return;
    }

    /**
     * get error statistics from errorHandler
     * how many errors were captured in/out of console
     * breakdown per error category
     *
     * @return array
     */
    protected function errorStats()
    {
        $errors = $this->debug->errorHandler->get('errors');
        $stats = array(
            'inConsole' => 0,
            'inConsoleCategories' => 0,
            'notInConsole' => 0,
            'counts' => array(),
        );
        foreach ($errors as $error) {
            if ($error['suppressed']) {
                continue;
            }
            $category = $error['category'];
            if (!isset($stats['counts'][$category])) {
                $stats['counts'][$category] = array(
                    'inConsole' => 0,
                    'notInConsole' => 0,
                );
            }
            $k = $error['inConsole'] ? 'inConsole' : 'notInConsole';
            $stats['counts'][$category][$k]++;
        }
        foreach ($stats['counts'] as $a) {
            $stats['inConsole'] += $a['inConsole'];
            $stats['notInConsole'] += $a['notInConsole'];
            if ($a['inConsole']) {
                $stats['inConsoleCategories']++;
            }
        }
        ksort($stats['counts']);
        return $stats;
    }

    /**
     * Returns an error summary
     *
     * @return string html
     */
    public function errorSummary()
    {
        $html = '';
        $errorStats = $this->errorStats();
        if ($errorStats['inConsole']) {
            $html .= $this->errorSummaryInConsole($errorStats);
        }
        if ($errorStats['notInConsole']) {
            $count = 0;
            $errors = $this->debug->errorHandler->get('errors');
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
            $html .= '<h3>'.($errorStats['inConsole'] ? 'Additionally, there ' : 'There ')
                .$count.' captured while not collecting debug info</h3>'
                .$htmlNotIn;
        }
        return $html;
    }

    /**
     * returns summary for errors that were logged to console (while this->collect = true)
     *
     * @param array $stats stats as returned from errorStats()
     *
     * @return string
     */
    protected function errorSummaryInConsole($stats)
    {
        if ($stats['inConsoleCategories'] == 1) {
            // all same category of error
            reset($stats['counts']);
            $category = key($stats['counts']);
            if ($stats['inConsole'] == 1) {
                $html = 'There was 1 error';
                if ($category == 'fatal') {
                    $html = ''; // don't bother with this alert..
                                // fatal are still prominently displayed
                } elseif ($category != 'error') {
                    $html .= ' ('.$category.')';
                }
            } else {
                $html = 'There were '.$stats['inConsole'].' errors';
                if ($category != 'error') {
                    $html .= ' of type '.$category;
                }
            }
            if ($html) {
                $html = '<h3 class="error-'.$category.'">'.$html.'</h3>'."\n";
            }
        } else {
            // multiple error categories
            $html = '<h3>There were '.$stats['inConsole'].' errors:</h3>'."\n";
            $html .= '<ul class="list-unstyled indent">';
            foreach ($stats['counts'] as $category => $a) {
                if (!$a['inConsole']) {
                    continue;
                }
                $html .= '<li class="error-'.$category.'">'.$category.': '.$a['inConsole'].'</li>';
            }
            $html .= '</ul>';
        }
        return $html;
    }

    /**
     * Get config val
     *
     * @param string $path what to get
     *
     * @return mixed
     */
    public function get($path)
    {
        if ($path == 'outputAs') {
            $ret = $this->cfg['outputAs'];
            if (empty($ret)) {
                /*
                    determine outputAs automatically
                */
                $ret = 'html';
                $requestedWith = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                    ? $_SERVER['HTTP_X_REQUESTED_WITH']
                    : null;
                $isAjax = $requestedWith == 'XMLHttpRequest';
                if ($isAjax) {
                    $ret = 'firephp';
                } elseif (php_sapi_name() == 'cli') {
                    // console
                    $ret = 'text';
                } else {
                    $contentType = $this->debug->utilities->getResponseHeader();
                    if ($contentType && $contentType !== 'text/html') {
                        $ret = 'firephp';
                    }
                }
            }
        } elseif ($path == 'css') {
            $ret = $this->getCss();
        } else {
            $path = preg_split('#[\./]#', $path);
            $ret = $this->cfg;
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
     * Return the log's CSS
     *
     * @return string
     */
    public function getCss()
    {
        $return = file_get_contents($this->cfg['filepathCss']);
        if (!empty($this->cfg['css'])) {
            $return .=  "\n".$this->cfg['css']."\n";
        }
        return $return;
    }

    /**
     * Returns meta-data and removes it from the passed arguments
     *
     * @param array $args args to check
     *
     * @return array|false meta array or false
     */
    public function getMetaArg(&$args)
    {
        $return = false;
        $end = end($args);
        if (is_array($end) && in_array(Debug::META, $end)) {
            array_pop($args);
            $return = $end;
        }
        return $return;
    }

    /**
     * Returns output
     *
     * @return mixed
     */
    public function output()
    {
        $outputAs = $this->get('outputAs');
        if (is_callable($this->cfg['onOutput'])) {
            call_user_func($this->cfg['onOutput'], $outputAs);
        }
        $outputAs = $this->get('outputAs');
        $this->data['groupDepth'] = 0;
        if ($outputAs == 'html') {
            $return = $this->outputAsHtml();
        } elseif ($outputAs == 'firephp') {
            $this->uncollapseErrors();
            $outputFirephp = new OutputFirephp($this->data);
            $outputFirephp->output();
            $return = null;
        } elseif ($outputAs == 'script') {
            $this->uncollapseErrors();
            $return = $this->outputAsScript();
        } else {
            $return = $this->outputAsText();
        }
        return $return;
    }

    /**
     * return log entry for writing to file
     *
     * @param string  $method method
     * @param array   $args   arguments
     * @param integer $depth  group depth (for indentation)
     *
     * @return string
     */
    public function getLogEntryAsText($method, $args, $depth)
    {
        if ($method == 'table' && count($args) == 2) {
            $caption = array_pop($args);
            array_unshift($args, $caption);
        }
        if (count($args) == 1 && is_string($args[0])) {
            $args[0] = strip_tags($args[0]);
        }
        foreach ($args as $k => $v) {
            if ($k > 0 || !is_string($v)) {
                $args[$k] = $this->debug->varDump->dump($v, 'text');
            }
        }
        $num_args = count($args);
        $glue = ', ';
        if ($num_args == 2) {
            $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                ? ''
                : ' = ';
        }
        $strIndent = str_repeat('    ', $depth);
        $str = implode($glue, $args);
        $str = $strIndent.str_replace("\n", "\n".$strIndent, $str);
        return $str;
    }

    /**
     * Return the log as HTML
     *
     * @return string
     */
    protected function outputAsHtml()
    {
        $this->data['alert'] = $this->errorSummary();
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
        $lastError = $this->debug->errorHandler->get('lastError');
        if ($lastError && $lastError['category'] === 'fatal') {
            $keysKeep = array('typeStr','message','file','line');
            $keysRemove = array_diff(array_keys($lastError), $keysKeep);
            foreach ($keysRemove as $k) {
                unset($lastError[$k]);
            }
            array_unshift($this->data['log'], array('error error-fatal',$lastError));
        }
        $str .= '<div class="debug-header"><h3>Debug Log</h3></div>'."\n";
        if (!empty($this->data['alert'])) {
            $str .= '<div class="alert alert-danger">'.$this->data['alert'].'</div>';
        }
        $str .= '<div class="debug-content clearfix">'."\n";
        foreach ($this->data['log'] as $args) {
            $method = array_shift($args);
            $str .= $this->outputHtmlLogEntry($method, $args);
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
     * output a log entry as HTML
     *
     * @param string $method method
     * @param array  $args   args
     *
     * @return string
     */
    protected function outputHtmlLogEntry($method, $args)
    {
        $str = '';
        if (in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $str = $this->outputHtmlGroupMethod($method, $args);
        } elseif ($method == 'table') {
            $str = call_user_func_array(array($this->debug->varDump,'dumpTable'), $args);
        } else {
            $attribs = array(
                'class' => 'm_'.$method,
                'title' => null,
            );
            if (in_array($method, array('error','warn'))) {
                $meta = $this->getMetaArg($args);
                if ($meta) {
                    if (isset($meta['file'])) {
                        $attribs['title'] = $meta['file'].': line '.$meta['line'];
                    }
                    if (isset($meta['errorCat'])) {
                        $attribs['class'] .= ' error-'.$meta['errorCat'];
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
                    $args[$k] = $this->debug->varDump->dump($v, 'html');
                } elseif ($num_args == 1) {
                    $args[$k] = $this->debug->varDump->visualWhiteSpace($v);
                }
            }
            $args = implode($glue, $args);
            $str = '<div '.$this->debug->utilities->buildAttribString($attribs).'>'.$args.'</div>';
        }
        $str .= "\n";
        return $str;
    }

    /**
     * handle html output of group, groupCollapsed, & groupEnd
     *
     * @param string $method method
     * @param array  $args   args passed to method
     *
     * @return string
     */
    protected function outputHtmlGroupMethod($method, $args = array())
    {
        $str = '';
        if (in_array($method, array('group','groupCollapsed'))) {
            $this->data['groupDepth']++;
            $collapsed_class = '';
            if (!empty($args)) {
                $label = array_shift($args);
                $arg_str = '';
                if ($args) {
                    foreach ($args as $k => $v) {
                        $args[$k] = $this->debug->varDump->dump($v);
                    }
                    $arg_str = implode(', ', $args);
                }
                $collapsed_class = $method == 'groupCollapsed'
                    ? 'collapsed'
                    : 'expanded';
                $str .= '<div class="group-header '.$collapsed_class.'">'
                        .'<span class="group-label">'
                            .$label
                            .( !empty($arg_str)
                                ? '(</span>'.$arg_str.'<span class="group-label">)'
                                : '' )
                        .'</span>'
                    .'</div>'."\n";
            }
            $str .= '<div class="m_group">';
        } elseif ($method == 'groupEnd') {
            if ($this->data['groupDepth'] > 0) {
                $this->data['groupDepth']--;
                $str .= '</div>';
            }
        }
        return $str;
    }

    /**
     * output the log as javascript
     *    which outputs the log to the console
     *
     * @return string
     */
    protected function outputAsScript()
    {
        $label = 'PHP';
        $errorStats = $this->errorStats();
        if ($errorStats['inConsole']) {
            $label .= ' - Errors (';
            foreach ($errorStats['counts'] as $category => $vals) {
                $label .= $vals['inConsole'].' '.$category.', ';
            }
            $label = substr($label, 0, -2);
            $label .= ')';
        }
        $str = '<script type="text/javascript">'."\n";
        $str .= 'console.groupCollapsed("'.$label.'");'."\n";
        foreach ($this->data['log'] as $args) {
            $method = array_shift($args);
            if ($method == 'assert') {
                array_unshift($args, false);
            } elseif ($method == 'count' || $method == 'time') {
                $method = 'log';
            } elseif ($method == 'table') {
                foreach ($args as $i => $v) {
                    if (!is_array($v)) {
                        unset($args[$i]);
                    }
                }
            } elseif (in_array($method, array('error','warn'))) {
                $meta = $this->getMetaArg($args);
                if ($meta && isset($meta['file'])) {
                    $args[] = $meta['file'].': line '.$meta['line'];
                }
            }
            foreach ($args as $k => $arg) {
                $args[$k] = $this->debug->varDump->dump($arg, 'script');
            }
            $str .= 'console.'.$method.'('.implode(',', $args).");\n";
        }
        while ($this->data['groupDepth'] > 0) {
            $this->data['groupDepth']--;
            $str .='groupEnd();';
        }
        $str .= 'console.groupEnd();';
        $str .= '</script>';
        return $str;
    }

    /**
     * output the log as text
     *
     * @return string
     */
    protected function outputAsText()
    {
        $str = '';
        $depth = 0;
        foreach ($this->data['log'] as $args) {
            $method = array_shift($args);
            $str .= $this->getLogEntryAsText($method, $args, $depth)."\n";
            if (in_array($method, array('group','groupCollapsed'))) {
                $depth ++;
            } elseif ($method == 'groupEnd' && $depth > 0) {
                $depth --;
            }
        }
        return $str;
    }

    /**
     * when outputting to script and firephp make sure all nested errors are in uncollapsed groups
     *
     * @return void
     */
    protected function uncollapseErrors()
    {
        $groupStack = array();
        for ($i = 0, $count = count($this->data['log']); $i < $count; $i++) {
            $method = $this->data['log'][$i][0];
            if (in_array($method, array('group', 'groupCollapsed'))) {
                $groupStack[] = $i;
            } elseif ($method == 'groupEnd') {
                array_pop($groupStack);
            } elseif (in_array($method, array('error', 'warn'))) {
                foreach ($groupStack as $i2) {
                    $this->data['log'][$i2][0] = 'group';
                }
            }
        }
    }

    /**
     * Set one or more config values
     *
     * If setting a single value via method a or b, old value is returned
     *
     * @param string $path   key
     * @param mixed  $newVal value
     *
     * @return mixed returns previous value
     */
    public function set($path, $newVal = null)
    {
        $ret = null;
        $new = array();
        if (is_string($path)) {
            $path = preg_split('#[\./]#', $path);
            $ref = &$new;
            $ret = $this->cfg;
            foreach ($path as $k) {
                $ret = isset($ret[$k])
                    ? $ret[$k]
                    : null;
                $ref[$k] = array(); // initialize this level
                $ref = &$ref[$k];
            }
            $ref = $newVal;
        } elseif (is_array($path)) {
            $new = $path;
        }
        $this->cfg = $this->debug->utilities->arrayMergeDeep($this->cfg, $new);
        return $ret;
    }
}
