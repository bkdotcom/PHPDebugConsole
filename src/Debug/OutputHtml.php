<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2017 Brad Kent
 * @version   v2.0.0
 */

namespace bdk\Debug;

use bdk\PubSub\Event;
use bdk\Debug;

/**
 * Output log as HTML
 */
class OutputHtml extends OutputBase
{

    protected $errorSummary;

    /**
     * Constructor
     *
     * @param object $debug debug instance
     */
    public function __construct($debug)
    {
        $this->errorSummary = new OutputHtmlErrorSummary($this, $debug->errorHandler);
        parent::__construct($debug);
    }

    /**
     * Formats an array as a table
     *
     * @param array  $array   array of \Traversable
     * @param string $caption optional caption
     * @param array  $columns columns to display
     * @param string $class   table's class attribute
     *
     * @return string
     */
    public function buildTable($array, $caption = null, $columns = array(), $class = '')
    {
        $str = '';
        if (!is_array($array) || empty($array)) {
            // empty array/value
            if (isset($caption)) {
                $str = $caption.' = ';
            }
            $str .= $this->dump($array);
            return '<div class="m_log">'.$str.'</div>';
        }
        $headers = array();
        $keys = $columns ?: $this->debug->utilities->arrayColKeys($array);
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
        $attribs = array(
            'class' => $class,
        );
        $str = '<table'.$this->debug->utilities->buildAttribString($attribs).'>'."\n"
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
        return $str;
    }

    /**
     * Dump value as html
     *
     * @param mixed        $val      value to dump
     * @param array        $path     {@internal}
     * @param boolean      $sanitize (true) apply htmlspecialchars?
     * @param string|false $wrap     (span) tag to wrap value in (or false)
     *
     * @return string
     */
    public function dump($val, $path = array(), $sanitize = true, $wrap = 'span')
    {
        $this->wrapAttribs = array(
            'class' => array(),
            'title' => null,
        );
        $this->sanitize = $sanitize;
        $val = parent::dump($val);
        if (in_array($this->dumpType, array('recursion'))) {
            $wrap = false;
        }
        if ($wrap) {
            $wrapAttribs = $this->debug->utilities->arrayMergeDeep(
                array(
                    'class' => array(
                        't_'.$this->dumpType,
                        $this->dumpTypeMore,
                    ),
                ),
                $this->wrapAttribs
            );
            $val = '<'.$wrap.$this->debug->utilities->buildAttribString($wrapAttribs).'>'.$val.'</'.$wrap.'>';
        }
        $this->wrapAttribs = array(
            'class' => array(),
            'title' => null,
        );
        return $val;
    }

    /**
     * Return the log as HTML
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function onOutput(Event $event = null)
    {
        $this->data = $this->debug->getData();
        $this->removeHideIfEmptyGroups();
        array_unshift($this->data['alerts'], $this->errorSummary->build($this->debug->output->errorStats()));
        $str = '<div class="debug">'."\n";
        if ($this->debug->getCfg('output.outputCss')) {
            $str .= '<style type="text/css">'."\n"
                    .$this->debug->output->getCss()."\n"
                .'</style>'."\n";
        }
        if ($this->debug->getCfg('output.outputScript')) {
            $str .= '<script type="text/javascript">'
                .file_get_contents($this->debug->getCfg('filepathScript'))
                .'</script>'."\n";
        }
        $str .= '<div class="debug-bar"><h3>Debug Log</h3></div>'."\n";
        $str .= $this->processAlerts();
        /*
            If outputing script, initially hide the output..
            this will help page load performance (fewer redraws)... by magnitudes
        */
        if ($this->debug->getCfg('outputScript')) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>'."\n";
        }

        $str .= '<div class="debug-header m_group"'.($this->debug->getCfg('outputScript') ? ' style="display:none;"' : '').'>'."\n";
        $str .= $this->processSummary();
        $str .= '</div>'."\n";
        $str .= '<div class="debug-content m_group"'.($this->debug->getCfg('outputScript') ? ' style="display:none;"' : '').'>'."\n";
        $str .= $this->processLog();
        $str .= '</div>'."\n";  // close debug-content
        $str .= '</div>'."\n";  // close debug
        $this->data = array();
        if ($event) {
            $event['output'] .= $str;
        } else {
            return $str;
        }
    }

    /**
     * handle html output of group, groupCollapsed, & groupEnd
     *
     * @param string $method group|groupCollapsed|groupEnd
     * @param array  $args   args passed to method
     *
     * @return string
     */
    protected function buildGroupMethod($method, $args = array())
    {
        $str = '';
        if (in_array($method, array('group','groupCollapsed'))) {
            if (!empty($args)) {
                $label = array_shift($args);
                foreach ($args as $k => $v) {
                    $args[$k] = $this->dump($v);
                }
                $argStr = implode(', ', $args);
                $str .= '<div'.$this->debug->utilities->buildAttribString(array(
                    'class' => array(
                        'group-header',
                        $method == 'groupCollapsed'
                            ? 'collapsed'
                            : 'expanded',
                    ),
                )).'>'
                    .'<span class="group-label">'
                        .$label
                        .( !empty($argStr)
                            ? '(</span>'.$argStr.'<span class="group-label">)'
                            : '' )
                    .'</span>'
                .'</div>'."\n";
            }
            $str .= '<div class="m_group">';
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
        $str .= '<th class="'.$classAndInner['class'].'" scope="row">'.$classAndInner['innerhtml'].'</th>';
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
            $str .= $this->dump($v, null, true, 'td');
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
            $displayKeys = $this->debug->getCfg('output.displayListKeys') || !$this->debug->utilities->isList($array);
            $html = '<span class="t_keyword">Array</span>'
                .'<span class="t_punct">(</span>'."\n";
            if ($displayKeys) {
                $html .= '<span class="array-inner">'."\n";
                foreach ($array as $key => $val) {
                    $html .= "\t".'<span class="key-value">'
                            .'<span class="t_key'.(is_int($key) ? ' t_int' : '').'">'
                                .$this->dump($key, $path, true, false) // don't wrap it
                            .'</span> '
                            .'<span class="t_operator">=&gt;</span> '
                            .$this->dump($val)
                        .'</span>'."\n";
                }
                $html .= '</span>';
            } else {
                // display as list
                $html .= '<ul class="array-inner list-unstyled">'."\n";
                foreach ($array as $val) {
                    $html .= $this->dump($val, $path, true, 'li');
                }
                $html .= '</ul>';
            }
            $html .= '<span class="t_punct">)</span>';
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
        $strClassName = '<span class="t_object-class"'.($title ? ' title="'.htmlspecialchars($title).'"' : '').'>'
            .$abs['className']
            .'</span>';
        if ($abs['isRecursion']) {
            $html = $strClassName
                .' <span class="t_recursion">*RECURSION*</span>';
        } elseif ($abs['isExcluded']) {
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
                    .'</span>'."\n";
            }
            $html = $toStringMarkup
                .$strClassName."\n"
                .'<dl class="object-inner">'."\n"
                    .'<dt>extends</dt>'."\n"
                        .'<dd class="extends">'.implode('</dd><dd class="extends">', $abs['extends']).'</dd>'."\n"
                    .'<dt>implements</dt>'."\n"
                        .'<dd class="interface">'.implode('</dd><dd class="interface">', $abs['implements']).'</dd>'."\n"
                    .$this->dumpConstants($abs['constants'])
                    .$this->dumpProperties($abs)
                    .($abs['collectMethods'] && $this->debug->output->getCfg('outputMethods')
                        ? $this->dumpMethods($abs['methods'])
                        : ''
                    )
                    .$this->dumpPhpDoc($abs['phpDoc'])
                .'</dl>'."\n";
            // remove <dt>'s with empty <dd>'
            $html = preg_replace('#<dt[^>]*>\w+</dt>\s*<dd[^>]*></dd>\s*#', '', $html);
        }
        /*
            Were we debugged from inside or outside of the object?
        */
        $accessible = $abs['scopeClass'] == $abs['className']
            ? 'private'
            : 'public';
        $this->wrapAttribs['data-accessible'] = $accessible;
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
            $str = '<dt class="constants">constants</dt>'."\n";
            foreach ($constants as $k => $value) {
                $str .= '<dd class="constant">'
                    .'<span class="constant-name">'.$k.'</span>'
                    .' <span class="t_operator">=</span> '
                    .$this->dump($value)
                    .'</dd>'."\n";
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
        $str = '<dt class="methods">'.$label.'</dt>'."\n";
        foreach ($methods as $methodName => $info) {
            $paramStr = $this->dumpParams($info['params']);
            $modifiers = array_keys(array_filter(array(
                'final' => $info['isFinal'],
                $info['visibility'] => true,
                'static' => $info['isStatic'],
            )));
            $str .= '<dd'
                .' class="method '.implode(' ', $modifiers).($info['isDeprecated'] ? ' deprecated' : '').'"'
                .' data-implements="'.$info['implements'].'">'
                .implode(' ', array_map(function ($modifier) {
                    return '<span class="t_modifier_'.$modifier.'">'.$modifier.'</span>';
                }, $modifiers))
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
     * @param array $abs object abstraction
     *
     * @return string
     */
    protected function dumpProperties($abs)
    {
        $label = count($abs['properties'])
            ? 'properties'
            : 'no properties';
        if ($abs['viaDebugInfo']) {
            $label .= ' <span class="text-muted">(via __debugInfo)</span>';
        }
        $str = '<dt class="properties">'.$label.'</dt>'."\n";
        if (isset($abs['methods']['__get'])) {
            $str .= '<dd class="magic-method info">This object has a <code>__get()</code> method</dd>'."\n";
        }
        foreach ($abs['properties'] as $k => $info) {
            $isPrivateAncestor = $info['visibility'] == 'private' && $info['inheritedFrom'];
            $classes = array_keys(array_filter(array(
                'property' => true,
                $info['visibility'] => $info['visibility'] != 'debug',
                'debug-value' => !empty($info['viaDebugInfo']),
                'private-ancestor' => $isPrivateAncestor,
            )));
            $str .= '<dd class="'.implode(' ', $classes).'">'
                .'<span class="t_modifier_'.$info['visibility'].'">'.$info['visibility'].'</span>'
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
     * process alerts
     *
     * @return string
     */
    protected function processAlerts()
    {
        $str = '';
        foreach ($this->data['alerts'] as $alert) {
            if (is_string($alert)) {
                // errorSummary
                $alert = array(
                    'message' => $alert,
                    'class' => 'danger error-summary',
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
     * @param array  $meta   meta values
     *
     * @return string
     */
    protected function processEntry($method, $args = array(), $meta = array())
    {
        $str = '';
        if (in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $str = $this->buildGroupMethod($method, $args);
        } elseif ($method == 'table') {
            $str = $this->buildTable($args[0], $args[1], $args[2], 'm_table table-bordered sortable');
        } elseif ($method == 'trace') {
            $str = $this->buildTable($args[0], 'trace', array('file','line','function'), 'm_trace table-bordered');
        } else {
            $attribs = array(
                'class' => 'm_'.$method,
                'title' => null,
            );
            if (in_array($method, array('error','warn'))) {
                if (isset($meta['file'])) {
                    $attribs['title'] = $meta['file'].': line '.$meta['line'];
                }
                if (isset($meta['errorCat'])) {
                    $attribs['class'] .= ' error-'.$meta['errorCat'];
                }
            }
            $numArgs = count($args);
            $hasSubs = false;
            if (in_array($method, array('error','info','log','warn')) && is_string($args[0]) && $numArgs > 1) {
                $args = $this->processSubstitutions($args, $hasSubs);
            }
            if ($hasSubs) {
                $glue = '';
                $args = implode($glue, $args);
                $args = '<span class="t_string no-pseudo">'.$args.'</span>';
            } else {
                $glue = ', ';
                if ($numArgs == 2 && is_string($args[0])) {
                    $glue = preg_match('/[=:] ?$/', $args[0])   // ends with "=" or ":"
                        ? ''
                        : ' = ';
                }
                foreach ($args as $i => $v) {
                    if ($i > 0) {
                        $args[$i] = $this->dump($v, array(), true);
                    } else {
                        // don't apply htmlspecialchars()
                        $args[$i] = $this->dump($v, array(), false);
                    }
                }
                $args = implode($glue, $args);
            }
            $str = '<div'.$this->debug->utilities->buildAttribString($attribs).'>'.$args.'</div>';
        }
        $str .= "\n";
        return $str;
    }

    /**
     * Cooerce value to string
     *
     * @param mixed $val value
     *
     * @return string
     */
    protected function substitutionAsString($val)
    {
        $type = $this->debug->abstracter->getType($val);
        if ($type == 'string') {
            $val = $this->dump($val, array(), true, false);
        } elseif ($type == 'array') {
            $count = count($val);
            $val = '<span class="t_keyword">Array</span>'
                .'<span class="t_punct">(</span>'.$count.'<span class="t_punct">)</span>';
        } elseif ($type == 'object') {
            $val = '<span class="t_object-class">'.$val['className'].'</span>';
        } else {
            $val = $this->dump($val);
        }
        return $val;
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
