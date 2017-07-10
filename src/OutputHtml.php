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
 * Output log as HTML
 */
class OutputHtml extends OutputBase
{

    /**
     * Formats an array as a table
     *
     * @param array  $array   array of \Traversable
     * @param string $caption optional caption
     *
     * @return string
     */
    public function buildTable($array, $caption = null)
    {
        $str = '';
        if (!is_array($array)) {
            // trying to table a non-array
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= $this->dump($array);
            $str = '<div class="m_log">'.$str.'</div>';
        } elseif (empty($array)) {
            // empty array/value
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= $this->dump($array);
            $str = '<div class="m_log">'.$str.'</div>';
        } else {
            $keys = $this->debug->utilities->arrayColKeys($array);
            $headers = array();
            foreach ($keys as $key) {
                $headers[] = $key === ''
                    ? 'value'
                    : htmlspecialchars($key);
            }
            $haveObj = false;
            foreach ($array as $k => $row) {
                $str .= $this->buildTableRow($row, $keys, $k, $isObj);
                if ($isObj) {
                    $haveObj = true;
                }
            }
            if (!$haveObj) {
                $str = str_replace('<td class="t_object-class"></td>', '', $str);
            }
            $str = '<table>'."\n"
                .'<caption>'.$caption.'</caption>'."\n"
                .'<thead>'
                .'<tr><th>&nbsp;</th>'
                    .($haveObj ? '<th>&nbsp;</th>' : '')
                    .'<th>'.implode('</th><th scope="col">', $headers).'</th>'
                .'</tr>'."\n"
                .'</thead>'."\n"
                .'<tbody>'."\n"
                .$str
                .'</tbody>'."\n"
                .'</table>';
        }
        return $str;
    }

    /**
     * Dump value as html
     *
     * @param mixed   $val      value to dump
     * @param boolean $sanitize (true) apply htmlspecialchars?
     * @param boolean $wrap     (true) whether to wrap in a <span>
     *
     * @return string
     */
    public function dump($val, $sanitize = true, $wrap = true)
    {
        $wrapAttribs = array(
            'class' => array(),
            'title' => null,
        );
        $this->sanitize = $sanitize;
        $this->wrapAttribs = array();
        $val = parent::dump($val);
        if (in_array($this->dumpType, array('object', 'recursion'))) {
            $wrap = false;
        }
        if ($wrap) {
            $wrapAttribs['class'][] = 't_'.$this->dumpType;
            if ($this->dumpTypeMore) {
                $wrapAttribs['class'][] = $this->dumpTypeMore;
            }
            $wrapAttribs = $this->debug->utilities->arrayMergeDeep($wrapAttribs, $this->wrapAttribs);
            $val = '<span '.$this->debug->utilities->buildAttribString($wrapAttribs).'>'.$val.'</span>';
        }
        return $val;
    }

    /**
     * Return the log as HTML
     *
     * @param Event $event event object
     *
     * @return mixed
     */
    public function output(Event $event = null)
    {
        $data = $this->debug->getData();
        array_unshift($data['alerts'], $this->errorSummary());
        $str = '<div class="debug">'."\n";
        if ($this->debug->getCfg('output.outputCss')) {
            $str .= '<style type="text/css">'."\n"
                    .$this->debug->output->getCss()."\n"
                .'</style>'."\n";
        }
        if ($this->debug->getCfg('output.outputScript')) {
            $str .= '<script type="text/javascript">'
                .file_get_contents($this->debug->getCfg('filepathScript'))
                .'</script>';
        }
        $str .= '<div class="debug-bar"><h3>Debug Log</h3></div>'."\n";
        $str .= $this->processAlerts($data['alerts']);

        /*
            If outputing script, initially hide the output..
            this will help page load performance (fewer redraws)... by magnitudes
        */
        if ($this->debug->getCfg('outputScript')) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>';
        }

        $str .= '<div class="debug-header clearfix" '.($this->debug->getCfg('outputScript') ? 'style="display:none;"' : '').'>'."\n";
        krsort($data['logSummary']);
        $data['logSummary'] = call_user_func_array('array_merge', $data['logSummary']);
        foreach ($data['logSummary'] as $row) {
            $method = array_shift($row);
            $str .= $this->processEntry($method, $row);
        }
        $str .= $this->processFatalError();
        $str .= '</div>';

        $str .= '<div class="debug-content clearfix" '.($this->debug->getCfg('outputScript') ? 'style="display:none;"' : '').'>'."\n";
        foreach ($data['log'] as $row) {
            $method = array_shift($row);
            $str .= $this->processEntry($method, $row);
        }
        $str .= '</div>'."\n";  // close debug-content
        $str .= '</div>';       // close debug
        if ($event) {
            $event['output'] .= $str;
        } else {
            return $str;
        }
    }

    /**
     * handle html output of group, groupCollapsed, & groupEnd
     *
     * @param string $method method
     * @param array  $args   args passed to method
     *
     * @return string
     */
    protected function buildGroupMethod($method, $args = array())
    {
        $str = '';
        if (in_array($method, array('group','groupCollapsed'))) {
            $collapsedClass = '';
            if (!empty($args)) {
                $label = array_shift($args);
                $argStr = '';
                if ($args) {
                    foreach ($args as $k => $v) {
                        $args[$k] = $this->dump($v);
                    }
                    $argStr = implode(', ', $args);
                }
                $collapsedClass = $method == 'groupCollapsed'
                    ? 'collapsed'
                    : 'expanded';
                $str .= '<div class="group-header '.$collapsedClass.'">'
                        .'<span class="group-label">'
                            .$label
                            .( !empty($argStr)
                                ? '(</span>'.$argStr.'<span class="group-label">)'
                                : '' )
                        .'</span>'
                    .'</div>'."\n";
            }
            $str .= '<div class="m_group">';
        } elseif ($method == 'groupSummary') {
            $str = '<div class="m_groupSummary">';
        } elseif ($method == 'groupEnd') {
            $str = '</div>';
        }
        return $str;
    }

    /**
     * Returns table row
     *
     * @param mixed $row         should be array or abstraction
     * @param array $keys        column keys
     * @param array $rowKey      row key
     * @param array $rowIsObject will get set to true|false
     *
     * @return string
     */
    protected function buildTableRow($row, $keys, $rowKey, &$rowIsObject)
    {
        $str = '';
        $values = $this->debug->abstracter->keyValues($row, $keys, $objInfo);
        $classAndInner = $this->debug->utilities->parseAttribString($this->dump($rowKey));
        $classAndInner['class'] = trim('t_key '.$classAndInner['class']);
        $str .= '<tr>';
        $str .= '<td class="'.$classAndInner['class'].'">'.$classAndInner['innerhtml'].'</td>';
        if ($objInfo) {
            $rowIsObject = true;
            $str .= '<td class="t_object-class"'
                .($objInfo['phpDoc']['summary'] ? ' title="'.htmlspecialchars($objInfo['phpDoc']['summary']).'"' : '')
                .'>'.$objInfo['className'].'</td>';
        } else {
            $rowIsObject = false;
            $str .= '<td class="t_object-class"></td>';
        }
        foreach ($values as $v) {
            // remove the span wrapper.. add span's class to TD
            $v = $this->dump($v);
            $classAndInner = $this->debug->utilities->parseAttribString($v);
            $str .= $classAndInner['class']
                ? '<td class="'.$classAndInner['class'].'">'
                : '<td>';
            $str .= $classAndInner['innerhtml'];
            $str .= '</td>';
        }
        $str .= '</tr>'."\n";
        $str = str_replace(' title=""', '', $str);
        return $str;
    }

    /**
     * Dump array as html
     *
     * @param array $array array
     * @param array $path  {@internal}
     *
     * @return string html
     */
    protected function dumpArray($array, $path = array())
    {
        if (empty($array)) {
            $html = '<span class="t_keyword">Array</span>'
                .'<span class="t_punct">()</span>';
        } else {
            $html = '<span class="t_keyword">Array</span>'
                .'<span class="t_punct">(</span>'."\n"
                .'<span class="array-inner">'."\n";
            foreach ($array as $key => $val) {
                $html .= "\t".'<span class="key-value">'
                        .'<span class="t_key'.(is_int($key) ? ' t_int' : '').'">'
                            .$this->dump($key, true, false) // don't wrap it
                        .'</span> '
                        .'<span class="t_operator">=&gt;</span> '
                        .$this->dump($val)
                    .'</span>'."\n";
            }
            $html .= '</span>'
                .'<span class="t_punct">)</span>';
        }
        return $html;
    }

    /**
     * Dump boolean
     *
     * @param boolean $val boolean value
     *
     * @return string
     */
    protected function dumpBool($val)
    {
        return $val ? 'true' : 'false';
    }

    /**
     * Dump "Callable" as html
     *
     * @param array $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable($abs)
    {
        return '<span class="t_type">callable</span>'
                .' '.$abs['values'][0].'::'.$abs['values'][1];
    }

    /**
     * Dump float value
     *
     * @param integer $val float value
     *
     * @return float
     */
    protected function dumpFloat($val)
    {
        $date = $this->checkTimestamp($val);
        if ($date) {
            $this->wrapAttribs['class'][] = 'timestamp';
            $this->wrapAttribs['title'] = $date;
        }
        return $val;
    }

    /**
     * Dump integer value
     *
     * @param integer $val integer value
     *
     * @return integer
     */
    protected function dumpInt($val)
    {
        return $this->dumpFloat($val);
    }

    /**
     * Dump null value
     *
     * @return string
     */
    protected function dumpNull()
    {
        return 'null';
    }

    /**
     * Dump object as html
     *
     * @param array $abs  object abstraction
     * @param array $path {@internal}
     *
     * @return string
     */
    protected function dumpObject($abs, $path = array())
    {
        $title = trim($abs['phpDoc']['summary']."\n\n".$abs['phpDoc']['description']);
        $strClassName = '<span class="t_object-class"'.($title ? ' title="'.htmlspecialchars($title).'"' : '').'>'.$abs['className'].'</span>';
        if ($abs['isRecursion']) {
            $html = $strClassName
                .' <span class="t_recursion">*RECURSION*</span>';
        } elseif ($abs['excluded']) {
            $html = $strClassName
                .' <span class="excluded">(not inspected)</span>';
        } else {
            $toStringMarkup = '';
            $toStringVal = null;
            if ($abs['stringified']) {
                $toStringVal = $abs['stringified'];
            } elseif (isset($abs['methods']['__toString']['returnValue'])) {
                $toStringVal = $abs['methods']['__toString']['returnValue'];
            }
            if ($toStringVal) {
                $toStringValAppend = '';
                if (strlen($toStringVal) > 100) {
                    $toStringLen = strlen($toStringVal);
                    $toStringVal = substr($toStringVal, 0, 100);
                    $toStringValAppend = '&hellip; <i>('.($toStringLen - 100).' more chars)</i>';
                }
                $toStringDump = $this->dump($toStringVal);
                $classAndInner = $this->debug->utilities->parseAttribString($toStringDump);
                $toStringMarkup = '<span class="'.$classAndInner['class'].' t_stringified"'
                    .(!$abs['stringified'] ? ' title="__toString()"' : '').'>'
                    .$classAndInner['innerhtml']
                    .$toStringValAppend
                    .'</span> ';
            }
            $html = $toStringMarkup
                .$strClassName
                .'<dl class="object-inner">'
                    .'<dt>extends</dt><dd>'.implode('<br />', $abs['extends']).'</dd>'
                    .'<dt>implements</dt><dd class="interface">'.implode('</dd><dd class="interface">', $abs['implements']).'</dd>'
                    .$this->dumpConstants($abs['constants'])
                    .$this->dumpProperties($abs['properties'], array('viaDebugInfo'=>$abs['viaDebugInfo']))
                    .($abs['collectMethods'] && $this->debug->output->getCfg('outputMethods')
                        ? $this->dumpMethods($abs['methods'])
                        : ''
                    )
                    .$this->dumpPhpDoc($abs['phpDoc'])
                .'</dl>';
            // remove empty <dt>s
            $html = preg_replace('#<dt[^>]*>\w+</dt><dd[^>]*></dd>#', '', $html);
        }
        /*
            Were we debugged from inside or outside of the object?
        */
        $accessible = $abs['scopeClass'] == $abs['className']
            ? 'private'
            : 'public';
        $html = '<span class="t_object" data-accessible="'.$accessible.'">'.$html.'</span>';
        $html = str_replace(' title=""', '', $html);
        return $html;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return '<span class="t_keyword">Array</span> <span class="t_recursion">*RECURSION*</span>';
    }

    /**
     * Dump string
     *
     * @param string $val string value
     *
     * @return string
     */
    protected function dumpString($val)
    {
        if (is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            if ($date) {
                $this->wrapAttribs['class'][] = 'timestamp';
                $this->wrapAttribs['title'] = $date;
            }
        } else {
            if ($this->sanitize) {
                $val = $this->debug->utf8->dump($val, true, true);
                $val = $this->visualWhiteSpace($val);
            } else {
                $this->wrapAttribs['class'][] = 'no-pseudo';
                $val = $this->debug->utf8->dump($val, true, false);
            }
        }
        return $val;
    }

    /**
     * Dump undefined
     *
     * @return string
     */
    protected function dumpUndefined()
    {
        return '';
    }

    /**
     * dump object constants as html
     *
     * @param array $constants array of name=>value
     *
     * @return string html
     */
    protected function dumpConstants($constants)
    {
        $str = '';
        if ($constants && $this->debug->output->getCfg('outputConstants')) {
            $str = '<dt class="constants">constants</dt>';
            foreach ($constants as $k => $value) {
                $str .= '<dd class="constant">'
                    .'<span class="constant-name">'.$k.'</span>'
                    .' <span class="t_operator">=</span> '
                    .$this->dump($value)
                    .'</dd>';
            }
        }
        return $str;
    }

    /**
     * Dump object methods as html
     *
     * @param array $methods methods as returned from getMethods
     *
     * @return string html
     */
    protected function dumpMethods($methods)
    {
        $label = count($methods)
            ? 'methods'
            : 'no methods';
        $str = '<dt class="methods">'.$label.'</dt>';
        foreach ($methods as $methodName => $info) {
            $paramStr = $this->dumpParams($info['params']);
            $modifiers = array_keys(array_filter(array(
                'final' => $info['isFinal'],
                $info['visibility'] => true,
                'static' => $info['isStatic'],
            )));
            foreach ($modifiers as $i => $modifier) {
                $modifiers[$i] = '<span class="t_modifier">'.$modifier.'</span>';
            }
            $str .= '<dd'
                .' class="method visibility-'.$info['visibility'].($info['isDeprecated'] ? ' deprecated' : '').'"'
                .' data-implements="'.$info['implements'].'">'
                .implode(' ', $modifiers)
                .(isset($info['phpDoc']['return'][0])
                    ? ' <span class="t_type"'
                            .' title="'.htmlspecialchars($info['phpDoc']['return'][0]['desc']).'"'
                        .'>'.$info['phpDoc']['return'][0]['type'].'</span>'
                    : ''
                )
                .' <span class="method-name"'
                        .' title="'.htmlspecialchars($info['phpDoc']['summary'])
                            .($this->debug->output->getCfg('outputMethodDescription')
                                ? trim("\n\n". htmlspecialchars($info['phpDoc']['description']))
                                : ''
                            ).'"'
                    .'>'.$methodName.'</span>'
                .'<span class="t_punct">(</span>'.$paramStr.'<span class="t_punct">)</span>'
                .($methodName == '__toString'
                    ? '<br /><span class="indent">'.$this->dump($info['returnValue']).'</span>'
                    : ''
                )
                .'</dd>'."\n";
        }
        $str = str_replace(' title=""', '', $str);  // t_type && method-name
        $str = str_replace(' data-implements=""', '', $str);
        return $str;
    }

    /**
     * Dump method parameters as HTML
     *
     * @param array $params params as returned from getPaarams()
     *
     * @return string html
     */
    protected function dumpParams($params)
    {
        $paramStr = '';
        foreach ($params as $info) {
            $paramStr .= '<span class="parameter">';
            if (!empty($info['type'])) {
                $paramStr .= '<span class="t_type">'.$info['type'].'</span> ';
            }
            $paramStr .= '<span class="t_parameter-name"'
                .' title="'.htmlspecialchars(str_replace("\n", ' ', $info['desc'])).'"'
                .'>'.htmlspecialchars($info['name']).'</span>';
            if ($info['defaultValue'] != $this->debug->abstracter->UNDEFINED) {
                $defaultValue = $info['defaultValue'];
                if (is_string($defaultValue)) {
                    $defaultValue = str_replace("\n", ' ', $defaultValue);
                }
                $paramStr .= ' <span class="t_operator">=</span> ';
                $paramStr .= '<span class="t_parameter-default">'.$this->dump($defaultValue).'</span>';
            }
            $paramStr .= '</span>, '; // end .parameter
        }
        $paramStr = trim($paramStr, ', ');
        return $paramStr;
    }

    /**
     * Dump phpDoc info as html
     *
     * @param array $phpDoc parsed phpDoc
     *
     * @return string html
     */
    protected function dumpPhpDoc($phpDoc)
    {
        $str = '';
        foreach ($phpDoc as $k => $values) {
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                $str .= '<dd class="constant">'
                    .'<span class="phpdoc-tag">'.$k.'</span>'
                    .' <span class="t_operator">=</span> '
                    .implode(' ', array_map('htmlspecialchars', $value))
                    .'</dd>';
            }
        }
        if ($str) {
            $str = '<dt class="phpDoc">phpDoc</dt>'.$str;
        }
        return $str;
    }

    /**
     * Dump object properties as HTML
     *
     * @param array $properties properties as returned from getProperties()
     * @param array $meta       meta information (viaDebugInfo)
     *
     * @return string
     */
    protected function dumpProperties($properties, $meta = array())
    {
        $label = count($properties)
            ? 'properties'
            : 'no properties';
        if ($meta['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        $str = '<dt class="properties">'.$label.'</dt>';
        foreach ($properties as $k => $info) {
            $viaDebugInfo = !empty($info['viaDebugInfo']);
            $isPrivateAncestor = $info['visibility'] == 'private' && $info['inheritedFrom'];
            $str .= '<dd class="property visibility-'.$info['visibility']
                    .($viaDebugInfo ? ' debug-value' : '')
                    .($isPrivateAncestor ? ' private-ancestor' : '')
                .'">'
                .'<span class="t_modifier">'.$info['visibility'].'</span>'
                .($isPrivateAncestor
                    ? ' (<i>'.$info['inheritedFrom'].'</i>)'
                    : ''
                )
                .($info['type']
                    ? ' <span class="t_type">'.$info['type'].'</span>'
                    : ''
                )
                .' <span class="property-name"'
                    .' title="'.htmlspecialchars($info['desc']).'"'
                    .'>'.$k.'</span>'
                .' <span class="t_operator">=</span> '
                .$this->dump($info['value'])
                .'</dd>'."\n";
        }
        return $str;
    }

    /**
     * Returns an error summary
     *
     * @return string html
     */
    protected function errorSummary()
    {
        $html = '';
        $errorStats = $this->debug->output->errorStats();
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
     * process alerts
     *
     * @param array $alerts alerts data
     *
     * @return string
     */
    protected function processAlerts($alerts)
    {
        $str = '';
        foreach ($alerts as $alert) {
            if (is_string($alert)) {
                // errorSummary
                $alert = array(
                    'message' => $alert,
                    'class' => 'danger',
                    'dismissible' => false,
                );
            }
            if ($alert['message']) {
                if ($alert['dismissible']) {
                    $alert['message'] = '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                        .'<span aria-hidden="true">&times;</span>'
                        .'</button>'
                        .$alert['message'];
                    $alert['class'] .= ' alert-dismissible';
                }
                $str .= '<div class="alert alert-'.$alert['class'].'" role="alert">'.$alert['message'].'</div>';
            }
        }
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
    protected function processEntry($method, $args)
    {
        $str = '';
        if (in_array($method, array('group', 'groupCollapsed', 'groupEnd', 'groupSummary'))) {
            $str = $this->buildGroupMethod($method, $args);
        } elseif ($method == 'table') {
            $str = call_user_func_array(array($this,'buildTable'), $args);
        } else {
            $attribs = array(
                'class' => 'm_'.$method,
                'title' => null,
            );
            if (in_array($method, array('error','warn'))) {
                $meta = $this->debug->output->getMetaArg($args);
                if (isset($meta['file'])) {
                    $attribs['title'] = $meta['file'].': line '.$meta['line'];
                }
                if (isset($meta['errorCat'])) {
                    $attribs['class'] .= ' error-'.$meta['errorCat'];
                }
            }
            $numArgs = count($args);
            $glue = ', ';
            if ($numArgs == 2) {
                $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                    ? ''
                    : ' = ';
            }
            foreach ($args as $k => $v) {
                if ($k > 0 || !is_string($v)) {
                    $args[$k] = $this->dump($v);
                } else {
                    // first arg && string -> don't apply htmlspecialchars()
                    $args[$k] = $this->dump($v, false);
                }
            }
            $args = implode($glue, $args);
            $str = '<div '.$this->debug->utilities->buildAttribString($attribs).'>'.$args.'</div>';
        }
        $str .= "\n";
        return $str;
    }

    /**
     * If lastError was fatal, output the error
     *
     * @return string
     */
    protected function processFatalError()
    {
        $str = '';
        $lastError = $this->debug->errorHandler->get('lastError');
        if ($lastError && $lastError['category'] === 'fatal') { // && ($lastErrorValues = $lastError->getValues())
            $keysKeep = array('typeStr','message','file','line');
            $keysRemove = array_diff(array_keys($lastError), $keysKeep);
            foreach ($keysRemove as $k) {
                unset($lastError[$k]);
            }
            $str = $this->processEntry('error', array(
                $lastError,
                array(
                    'debug' => \bdk\Debug::META,
                    'errorCat' => 'fatal',
                ),
            ));
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
    protected function visualWhiteSpace($str)
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
        $strBr = $this->debug->getCfg('addBR') ? '<br />' : '';
        $search = array("\r","\n");
        $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>'.$strBr."\n");
        return str_replace($search, $replace, $matches[1]);
    }
}
