<?php

namespace bdk\Debug;

/**
 * Output methods
 */
class Output
{

	/**
	 * Constructor
	 *
	 * @param array &$cfg  cfg
	 * @param array &$data data
	 */
	public function __construct(&$cfg = array(), &$data = array())
	{
		$this->debug = Debug::getInstance();
		$this->cfg = &$cfg;
		$this->data = &$data;
	}

    /**
     * Returns an error summary
     *
     * @return string html
     */
    public function errorSummary()
    {
        $html = '';
        $errors = $this->debug->errorHandler->get('errors');
        $counts = array();
        $totals = array(
            'inConsole' => 0,
            'inConsoleCategories' => 0,
            'notInConsole' => 0,
        );
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
        foreach ($counts as $a) {
            $totals['inConsole'] += $a['inConsole'];
            $totals['notInConsole'] += $a['notInConsole'];
            if ($a['inConsole']) {
                $totals['inConsoleCategories']++;
            }
        }
        ksort($counts);
        /*
            first show logged counts
            then show not-logged counts
        */
        if ($totals['inConsole']) {
            $html .= $this->errorSummaryInConsole($totals, $counts);
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
        return $html;
    }

    /**
     * returns summary for errors that were logged to console (while this->collect = true)
     *
     * @param array $totals totals
     * @param array $counts category counts
     *
     * @return string
     */
    protected function errorSummaryInConsole($totals, $counts)
    {
        if ($totals['inConsoleCategories'] == 1) {
            // all same category of error
            reset($counts);
            $category = key($counts);
            if ($totals['inConsole'] == 1) {
                $html = 'There was 1 error';
                if ($category == 'fatal') {
                    $html = ''; // don't bother with this alert..
                                // fatal are still prominently displayed
                } elseif ($category != 'error') {
                    $html .= ' ('.$category.')';
                }
            } else {
                $html = 'There were '.$totals['inConsole'].' errors';
                if ($category != 'error') {
                    $html .= ' of type '.$category;
                }
            }
            if ($html) {
                $html = '<h3 class="error-'.$category.'">'.$html.'</h3>'."\n";
            }
        } else {
            // multiple error categories
            $html = '<h3>There were '.$totals['inConsole'].' errors:</h3>'."\n";
            $html .= '<ul class="list-unstyled indent">';
            foreach ($counts as $category => $a) {
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
     * Pass the log to FirePHP methods
     *
     * @return void
     */
    public function outputFirephp()
    {
        if (!file_exists($this->cfg['firephpInc'])) {
            return;
        }
        require_once $this->cfg['firephpInc'];
        $this->firephp = \FirePHP::getInstance(true);
        $this->firephp->setOptions($this->get('firephpOptions'));
        $this->firephpMethods = get_class_methods($this->firephp);
        if (!empty($this->data['alert'])) {
            $alert = str_replace('<br />', "\n", $this->data['alert']);
            array_unshift($this->data['log'], array('error', $alert));
        }
        foreach ($this->data['log'] as $i => $args) {
            $method = array_shift($args);
            $this->outputFirephpLogEntry($method, $args);
        }
        while ($this->data['groupDepth'] > 0) {
            call_user_func(array($firephp,'groupEnd'));
            $this->data['groupDepth']--;
        }
        return;
    }

    /**
     * output a log entry to Firephp
     *
     * @param string $method method
     * @param array  $args   args
     *
     * @return void
     */
    protected function outputFirephpLogEntry($method, $args)
    {
        $opts = array();
        foreach ($args as $k => $arg) {
            $args[$k] = $this->debug->display->getDisplayValue($arg, array('html'=>false,'boolNullToString'=>false));
        }
        if (in_array($method, array('group','groupCollapsed','groupEnd'))) {
            list($method, $args, $opts) = $this->outputFirephpGroupMethod($method, $args);
        } elseif ($method == 'table' && is_array($args[0])) {
            $label = isset($args[1])
                ? $args[1]
                : 'table';
            $keys = $this->debug->utilities->arrayColkeys($args[0]);
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
        if (!in_array($method, $this->firephpMethods)) {
            $method = 'log';
        }
        if ($opts) {
            // opts array needs to be 2nd arg for group method, 3rd arg for all others
            if ($method !== 'group' && count($args) == 1) {
                $args[] = null;
            }
            $args[] = $opts;
        }
        call_user_func_array(array($this->firephp,$method), $args);
        return;
    }

    /**
     * handle firephp output of group, groupCollapsed, & groupEnd
     *
     * @param string $method method
     * @param array  $args   args passed to method
     *
     * @return array [$method, $args, $opts]
     */
    protected function outputFirephpGroupMethod($method, $args = array())
    {
        $opts = array();
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
        }
        return array($method, $args, $opts);
    }

    /**
     * Return the log as HTML
     *
     * @return string
     */
    public function outputHtml()
    {
        $str = '<div class="debug">'."\n";
        if ($this->cfg['outputCss']) {
            $str .= '<style type="text/css">'."\n"
                    .$this->debug->get('css')."\n"
                .'</style>'."\n";
        }
        if ($this->cfg['outputScript']) {
            $str .= '<script type="text/javascript">'
                .file_get_contents($this->cfg['filepathScript'])
                .'</script>';
        }
        $lastError = $this->debug->errorHandler->get('lastError');
        if ($lastError && $lastError['category'] === 'fatal') {
        	foreach (array('type', 'category', 'hash', 'firstOccur', 'suppressed', 'inConsole') as $k) {
        		unset($lastError[$k]);
        	}
            array_unshift($this->data['log'], array('error error-fatal',$lastError));
        }
        $str .= '<h3>Debug Log:</h3>'."\n";
        if (!empty($this->data['alert'])) {
            $str .= '<div class="alert alert-danger">'.$this->data['alert'].'</div>';
        }
        $str .= '<div class="debug-content clearfix">'."\n";
        foreach ($this->data['log'] as $k_log => $args) {
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
            $str = call_user_func_array(array($this->debug->display,'getTable'), $args);
        } elseif ($method == 'time') {
            $str = '<div class="time">'.$args[0].': '.$args[1].'</div>';
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
                    $args[$k] = $this->debug->display->getDisplayValue($v);
                } elseif ($num_args == 1) {
                    $args[$k] = $this->debug->display->visualWhiteSpace($v);
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
                        $args[$k] = $this->debug->display->getDisplayValue($v);
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
        }
        return $str;
    }
}
