<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug\Output;

use bdk\Debug;
use bdk\Debug\MethodTable;
use bdk\PubSub\Event;

/**
 * Output log as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 */
class Html extends Base
{

    protected $errorSummary;
    protected $wrapAttribs = array();
    protected $channels = array();
    protected $tableInfo;

    /**
     * Constructor
     *
     * @param \bdk\Debug $debug debug instance
     */
    public function __construct(Debug $debug)
    {
        $this->errorSummary = new HtmlErrorSummary($this, $debug->errorHandler);
        parent::__construct($debug);
    }

    /**
     * Formats an array as a table
     *
     * @param array $rows    array of \Traversable
     * @param array $options options
     *                           'attribs' : key/val array (or string - interpreted as class value)
     *                           'caption' : optional caption
     *                           'columns' : array of columns to display (defaults to all)
     *                           'totalCols' : array of column keys that will get totaled
     *
     * @return string
     */
    public function buildTable($rows, $options = array())
    {
        $options = \array_merge(array(
            'attribs' => array(),
            'caption' => null,
            'columns' => array(),
            'totalCols' => array(),
        ), $options);
        if (\is_string($options['attribs'])) {
            $options['attribs'] = array(
                'class' => $options['attribs'],
            );
        }
        if ($this->debug->abstracter->isAbstraction($rows) && $rows['traverseValues']) {
            $options['caption'] .= ' ('.$this->markupClassname(
                $rows['className'],
                'span',
                array(
                    'title' => $rows['phpDoc']['summary'] ?: null,
                )
            ).')';
            $options['caption'] = \trim($options['caption']);
            $rows = $rows['traverseValues'];
        }
        $keys = $options['columns'] ?: $this->debug->methodTable->colKeys($rows);
        $this->tableInfo = array(
            'colClasses' => \array_fill_keys($keys, null),
            'haveObjRow' => false,
            'totals' => \array_fill_keys($options['totalCols'], null),
        );
        $tBody = '';
        foreach ($rows as $k => $row) {
            $tBody .= $this->buildTableRow($row, $keys, $k);
        }
        if (!$this->tableInfo['haveObjRow']) {
            $tBody = \str_replace('<td class="t_classname"></td>', '', $tBody);
        }
        return $this->debug->utilities->buildTag(
            'table',
            $options['attribs'],
            "\n"
                .($options['caption'] ? '<caption>'.$options['caption'].'</caption>'."\n" : '')
                .$this->buildTableHeader($keys)
                .'<tbody>'."\n".$tBody.'</tbody>'."\n"
                .$this->buildTableFooter($keys)
        );
    }

    /**
     * Dump value as html
     *
     * @param mixed        $val      value to dump
     * @param boolean      $sanitize (true) apply htmlspecialchars?
     * @param string|false $tagName  (span) tag to wrap value in (or false)
     *
     * @return string
     */
    public function dump($val, $sanitize = true, $tagName = 'span')
    {
        $this->wrapAttribs = array(
            'class' => array(),
            'title' => null,
        );
        $this->sanitize = $sanitize;
        $val = parent::dump($val);
        if ($tagName && !\in_array($this->dumpType, array('recursion'))) {
            $wrapAttribs = $this->debug->utilities->arrayMergeDeep(
                array(
                    'class' => array(
                        't_'.$this->dumpType,
                        $this->dumpTypeMore,
                    ),
                ),
                $this->wrapAttribs
            );
            $val = $this->debug->utilities->buildTag($tagName, $wrapAttribs, $val);
        }
        $this->wrapAttribs = array();
        return $val;
    }

    /**
     * Wrap classname in span.t_classname
     * if namespaced'd additionally wrap namespace in span.namespace
     * If callable, also wrap .t_operator and .t_method-name
     *
     * @param string $str     classname or classname(::|->)methodname
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs additional html attributes
     *
     * @return string
     */
    public function markupClassname($str, $tagName = 'span', $attribs = array())
    {
        if (\preg_match('/^(.+)(::|->)(.+)$/', $str, $matches)) {
            $classname = $matches[1];
            $opMethod = '<span class="t_operator">'.\htmlspecialchars($matches[2]).'</span>'
                    . '<span class="method-name">'.$matches[3].'</span>';
        } else {
            $classname = $str;
            $opMethod = '';
        }
        $idx = \strrpos($classname, '\\');
        if ($idx) {
            $classname = '<span class="namespace">'.\substr($classname, 0, $idx + 1).'</span>'
                . \substr($classname, $idx + 1);
        }
        $attribs = \array_merge(array(
            'class' => 't_classname',
        ), $attribs);
        return $this->debug->utilities->buildTag($tagName, $attribs, $classname)
            .$opMethod;
    }

    /**
     * Return the log as HTML
     *
     * @param Event $event event object
     *
     * @return string|void
     */
    public function onOutput(Event $event)
    {
        $this->data = $this->debug->getData();
        $this->channels = array();
        $str = '<div'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug',
            // channel list gets built as log processed...  we'll str_replace this...
            'data-channels' => '{{channels}}',
            'data-channel-root' => $this->channelNameRoot,
        )).">\n";
        if ($this->debug->getCfg('output.outputCss')) {
            $str .= '<style type="text/css">'."\n"
                    .$this->debug->output->getCss()."\n"
                .'</style>'."\n";
        }
        if ($this->debug->getCfg('output.outputScript')) {
            $str .= '<script type="text/javascript">'
                .\file_get_contents($this->debug->getCfg('filepathScript'))
                .'</script>'."\n";
        }
        $str .= '<div class="debug-bar"><h3>Debug Log</h3></div>'."\n";
        $str .= $this->processAlerts();
        /*
            If outputing script, initially hide the output..
            this will help page load performance (fewer redraws)... by magnitudes
        */
        if ($this->debug->getCfg('output.outputScript')) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>'."\n";
        }
        $str .= '<div class="debug-header m_group"'.($this->debug->getCfg('outputScript') ? ' style="display:none;"' : '').'>'."\n";
        $str .= $this->processSummary();
        $str .= '</div>'."\n";
        $str .= '<div class="debug-content m_group"'.($this->debug->getCfg('outputScript') ? ' style="display:none;"' : '').'>'."\n";
        $str .= $this->processLog();
        $str .= '</div>'."\n";  // close .debug-content
        $str .= '</div>'."\n";  // close .debug
        $str = \strtr($str, array(
            '{{channels}}' => \htmlspecialchars(\json_encode($this->buildChannelTree(), JSON_FORCE_OBJECT)),
        ));
        $this->data = array();
        $event['return'] .= $str;
    }

    /**
     * Return a log entry as HTML
     *
     * @param string $method method
     * @param array  $args   args
     * @param array  $meta   meta values
     *
     * @return string|void
     */
    public function processLogEntry($method, $args = array(), $meta = array())
    {
        $str = '';
        if (!\in_array($meta['channel'], $this->channels) && $meta['channel'] !== 'phpError') {
            $this->channels[] = $meta['channel'];
        }
        if ($meta['channel'] === $this->channelNameRoot) {
            $meta['channel'] = null;
        }
        if ($method == 'alert') {
            $str = $this->methodAlert($args, $meta);
        } elseif (\in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $str = $this->buildGroupMethod($method, $args, $meta);
        } elseif (\in_array($method, array('profileEnd','table','trace'))) {
            $meta = \array_merge(array(
                'caption' => null,
                'columns' => array(),
                'sortable' => false,
                'totalCols' => array(),
            ), $meta);
            $asTable = \is_array($args[0]) && $args[0];
            if (!$asTable && $meta['caption']) {
                \array_unshift($args, $meta['caption']);
            }
            $str = $this->debug->utilities->buildTag(
                'div',
                array(
                    'class' => 'm_'.$method,
                    'data-channel' => $meta['channel'],
                ),
                $asTable
                    ? "\n"
                        .$this->buildTable(
                            $args[0],
                            array(
                                'attribs' => array(
                                    'class' => array(
                                        'table-bordered',
                                        $meta['sortable'] ? 'sortable' : null,
                                    ),
                                ),
                                'caption' => $meta['caption'],
                                'columns' => $meta['columns'],
                                'totalCols' => $meta['totalCols'],
                            )
                        )."\n"
                    : $this->buildArgString($args)
            );
        } else {
            $sanitize = isset($meta['sanitize'])
                ? $meta['sanitize']
                : true;
            $attribs = array(
                'class' => 'm_'.$method,
                'data-channel' => $meta['channel'],
                'title' => isset($meta['file'])
                    ? $meta['file'].': line '.$meta['line']
                    : null,
            );
            if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
                if (\in_array($method, array('error','warn'))) {
                    if (isset($meta['errorCat'])) {
                        $attribs['class'] .= ' error-'.$meta['errorCat'];
                    }
                }
                if (\count($args) > 1 && \is_string($args[0])) {
                    $hasSubs = false;
                    $args = $this->processSubstitutions($args, $hasSubs);
                    if ($hasSubs) {
                        $args = array( \implode('', $args) );
                    }
                }
            }
            $str = $this->debug->utilities->buildTag(
                'div',
                $attribs,
                $this->buildArgString($args, $sanitize)
            );
        }
        $str = \str_replace(' data-channel="null"', '', $str);
        $str .= "\n";
        return $str;
    }

    /**
     * Convert all arguments to html and join them together.
     *
     * @param array   $args     arguments
     * @param boolean $sanitize apply htmlspecialchars (to non-first arg)?
     *
     * @return string html
     */
    protected function buildArgString($args, $sanitize = true)
    {
        $glue = ', ';
        $glueAfterFirst = true;
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]).' ';
            } elseif (\count($args) == 2) {
                $glue = ' = ';
            }
        }
        foreach ($args as $i => $v) {
            $args[$i] = $i > 0
                ? $this->dump($v, $sanitize)
                : $this->dump($v, false);
        }
        if (!$glueAfterFirst) {
            return $args[0].\implode($glue, \array_slice($args, 1));
        } else {
            return \implode($glue, $args);
        }
    }

    /**
     * Build a tree of all channels that have been output
     *
     * @return array
     */
    protected function buildChannelTree()
    {
        if ($this->channels == array($this->channelNameRoot)) {
            return array();
        }
        \sort($this->channels);
        // move root to the top
        $rootKey = \array_search($this->channelNameRoot, $this->channels);
        if ($rootKey !== false) {
            unset($this->channels[$rootKey]);
            \array_unshift($this->channels, $this->channelName);
        }
        $tree = array();
        foreach ($this->channels as $channel) {
            $ref = &$tree;
            $path = \explode('.', $channel);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array();
                }
                $ref = &$ref[$k];
            }
        }
        return $tree;
    }

    /**
     * handle html output of group, groupCollapsed, & groupEnd
     *
     * @param string $method group|groupCollapsed|groupEnd
     * @param array  $args   args passed to method
     * @param array  $meta   meta values
     *
     * @return string
     */
    protected function buildGroupMethod($method, $args = array(), $meta = array())
    {
        $str = '';
        if (\in_array($method, array('group','groupCollapsed'))) {
            $label = \array_shift($args);
            $levelClass = isset($meta['level'])
                ? 'level-'.$meta['level']
                : null;
            if (!empty($meta['isMethodName'])) {
                $label = $this->markupClassname($label);
            }
            foreach ($args as $k => $v) {
                $args[$k] = $this->dump($v);
            }
            $argStr = \implode(', ', $args);
            /*
                Header
            */
            $str .= $this->debug->utilities->buildTag(
                'div',
                array(
                    'class' => array(
                        'group-header',
                        $method == 'groupCollapsed'
                            ? 'collapsed'
                            : 'expanded',
                        $levelClass,
                    ),
                    'data-channel' => $meta['channel'],
                ),
                '<span class="group-label">'
                    .$label
                    .(!empty($argStr)
                        ? '(</span>'.$argStr.'<span class="group-label">)'
                        : '')
                .'</span>'
            )."\n";
            /*
                Group open
            */
            $str .= '<div'.$this->debug->utilities->buildAttribString(array(
                'class' => array(
                    'm_group',
                    $levelClass,
                ),
            )).'>';
        } elseif ($method == 'groupEnd') {
            $str = '</div>';
        }
        return $str;
    }

    /**
     * Returns table's tfoot
     *
     * @param array $keys column header values (keys of array or property names)
     *
     * @return string
     */
    protected function buildTableFooter($keys)
    {
        $haveTotal = false;
        $cells = array();
        foreach ($keys as $key) {
            $colHasTotal = isset($this->tableInfo['totals'][$key]);
            $cells[] = $colHasTotal
                ? $this->dump(\round($this->tableInfo['totals'][$key], 6), true, 'td')
                : '<td></td>';
            $haveTotal = $haveTotal || $colHasTotal;
        }
        if (!$haveTotal) {
            return '';
        }
        return '<tfoot>'."\n"
            .'<tr><td>&nbsp;</td>'
                .($this->tableInfo['haveObjRow'] ? '<td>&nbsp;</td>' : '')
                .\implode('', $cells)
            .'</tr>'."\n"
            .'</tfoot>'."\n";
    }

    /**
     * Returns table's thead
     *
     * @param array $keys column header values (keys of array or property names)
     *
     * @return string
     */
    protected function buildTableHeader($keys)
    {
        $headers = array();
        foreach ($keys as $key) {
            $headers[$key] = $key === MethodTable::SCALAR
                ? 'value'
                : \htmlspecialchars($key);
            if ($this->tableInfo['colClasses'][$key]) {
                $headers[$key] .= ' '.$this->markupClassname($this->tableInfo['colClasses'][$key]);
            }
        }
        return '<thead>'."\n"
            .'<tr><th>&nbsp;</th>'
                .($this->tableInfo['haveObjRow'] ? '<th>&nbsp;</th>' : '')
                .'<th>'.\implode('</th><th scope="col">', $headers).'</th>'
            .'</tr>'."\n"
            .'</thead>'."\n";
    }

    /**
     * Returns table row
     *
     * @param mixed $row    should be array or abstraction
     * @param array $keys   column keys
     * @param array $rowKey row key
     *
     * @return string
     */
    protected function buildTableRow($row, $keys, $rowKey)
    {
        $str = '';
        $values = $this->debug->methodTable->keyValues($row, $keys, $objInfo);
        $parsed = $this->debug->utilities->parseTag($this->dump($rowKey));
        $str .= '<tr>';
        $str .= $this->debug->utilities->buildTag(
            'th',
            array(
                'class' => 't_key text-right '.$parsed['attribs']['class'],
                'scope' => 'row',
            ),
            $parsed['innerhtml']
        );
        if ($objInfo['row']) {
            $str .= $this->markupClassname($objInfo['row']['className'], 'td', array(
                'title' => $objInfo['row']['phpDoc']['summary'] ?: null,
            ));
            $this->tableInfo['haveObjRow'] = true;
        } else {
            $str .= '<td class="t_classname"></td>';
        }
        foreach ($values as $v) {
            $str .= $this->dump($v, true, 'td');
        }
        $str .= '</tr>'."\n";
        $str = \str_replace(' title=""', '', $str);
        foreach (\array_keys($this->tableInfo['totals']) as $k) {
            $this->tableInfo['totals'][$k] += $values[$k];
        }
        foreach ($objInfo['cols'] as $k2 => $classname) {
            if ($this->tableInfo['colClasses'][$k2] === false) {
                // column values not of the same type
                continue;
            }
            if ($this->tableInfo['colClasses'][$k2] === null) {
                $this->tableInfo['colClasses'][$k2] = $classname;
            }
            if ($this->tableInfo['colClasses'][$k2] !== $classname) {
                $this->tableInfo['colClasses'][$k2] = false;
            }
        }
        return $str;
    }

    /**
     * Dump array as html
     *
     * @param array $array array
     *
     * @return string html
     */
    protected function dumpArray($array)
    {
        if (empty($array)) {
            $html = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">()</span>';
        } else {
            $displayKeys = $this->debug->getCfg('output.displayListKeys') || !$this->debug->utilities->isList($array);
            $html = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">(</span>'."\n";
            if ($displayKeys) {
                $html .= '<span class="array-inner">'."\n";
                foreach ($array as $key => $val) {
                    $html .= "\t".'<span class="key-value">'
                            .'<span class="t_key'.(\is_int($key) ? ' t_int' : '').'">'
                                .$this->dump($key, true, false) // don't wrap it
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
                    $html .= $this->dump($val, true, 'li');
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
        return '<span class="t_type">callable</span> '
            .$this->markupClassname($abs['values'][0].'::'.$abs['values'][1]);
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
     * @param array $abs object abstraction
     *
     * @return string
     */
    protected function dumpObject($abs)
    {
        /*
            Were we debugged from inside or outside of the object?
        */
        $dump = $this->object->dump($abs);
        $this->wrapAttribs['data-accessible'] = $abs['scopeClass'] == $abs['className']
            ? 'private'
            : 'public';
        return $dump;
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        return '<span class="t_keyword">array</span> <span class="t_recursion">*RECURSION*</span>';
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
        if (\is_numeric($val)) {
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
     * Getter for this->object
     *
     * @return HtmlObject
     */
    protected function getObject()
    {
        $this->object = new HtmlObject($this->debug);
        return $this->object;
    }

    /**
     * Handle alert method
     *
     * @param array $args arguments
     * @param array $meta meta info
     *
     * @return array array($method, $args)
     */
    protected function methodAlert($args, $meta)
    {
        $attribs = array(
            'class' => 'alert alert-'.$meta['class'],
            'data-channel' => $meta['channel'],
            'role' => 'alert',
        );
        if ($meta['dismissible']) {
            $attribs['class'] .= ' alert-dismissible';
            $args[0] = '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                .'<span aria-hidden="true">&times;</span>'
                .'</button>'
                .$args[0];
        }
        return $this->debug->utilities->buildTag('div', $attribs, $args[0]);
    }

    /**
     * process alerts
     *
     * @return string
     */
    protected function processAlerts()
    {
        $errorSummary = $this->errorSummary->build($this->debug->internal->errorStats());
        if ($errorSummary) {
            \array_unshift($this->data['alerts'], array(
                'alert',
                array($errorSummary),
                array(
                    'class' => 'danger error-summary',
                    'dismissible' => false,
                )
            ));
        }
        return parent::processAlerts();
    }

    /**
     * Coerce value to string
     *
     * @param mixed $val value
     *
     * @return string
     */
    protected function substitutionAsString($val)
    {
        $type = $this->debug->abstracter->getType($val);
        if ($type == 'string') {
            $val = $this->dump($val, true, false);
        } elseif ($type == 'array') {
            $count = \count($val);
            $val = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">(</span>'.$count.'<span class="t_punct">)</span>';
        } elseif ($type == 'object') {
            $val = $this->markupClassname($val['className']);
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
        $str = \preg_replace_callback('/(\r\n|\r|\n)/', array($this, 'visualWhiteSpaceCallback'), $str);
        $str = \preg_replace('#(<br />)?\n$#', '', $str);
        $str = \str_replace("\t", '<span class="ws_t">'."\t".'</span>', $str);
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
        return \str_replace($search, $replace, $matches[1]);
    }
}
