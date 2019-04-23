<?php
/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Output;

use bdk\Debug;
use bdk\Debug\LogEntry;
use bdk\PubSub\Event;

/**
 * Output log as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 * @property HtmlTable  $table  lazy-loaded HtmlTable... only loaded if outputing a table
 */
class Html extends Base
{

    protected $errorSummary;
    protected $wrapAttribs = array();
    protected $channels = array();

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
            'data-options' => array(
                'drawer' => $this->debug->getCfg('output.drawer'),
                'sidebar' => $this->debug->getCfg('output.sidebar'),
            ),
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
            $str .= '<script>window.jQuery || document.write(\'<script src="'.$this->debug->getCfg('output.jqueryUrl').'"><\/script>\')</script>'."\n";
            $str .= '<script type="text/javascript">'
                    .$this->debug->output->getScript()."\n"
                .'</script>'."\n";
        }
        $str .= '<header class="debug-menu-bar">PHPDebugConsole</header>'."\n";
        $str .= '<div class="debug-body">'."\n";
        $str .= $this->processAlerts();
        /*
            If outputing script, initially hide the output..
            this will help page load performance (fewer redraws)... by magnitudes
        */
        $style = null;
        if ($this->debug->getCfg('output.outputScript')) {
            $str .= '<div class="loading">Loading <i class="fa fa-spinner fa-pulse fa-2x fa-fw" aria-hidden="true"></i></div>'."\n";
            $style = 'display:none;';
        }
        $str .= '<ul'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug-log-summary group-body',
            'style' => $style,
        )).">\n".$this->processSummary().'</ul>'."\n";
        $str .= '<ul'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug-log group-body',
            'style' => $style,
        )).">\n".$this->processLog().'</ul>'."\n";
        $str .= '</div>'."\n";  // close .debug-body
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
     * @param LogEntry $logEntry log entry instance
     *
     * @return string|void
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $str = '';
        $method = $logEntry['method'];
        $meta = $logEntry['meta'];
        if (!\in_array($meta['channel'], $this->channels) && $meta['channel'] !== 'phpError') {
            $this->channels[] = $meta['channel'];
        }
        if ($meta['channel'] === $this->channelNameRoot) {
            $logEntry->setMeta('channel', null);
        }
        if ($method == 'alert') {
            $str = $this->buildMethodAlert($logEntry);
        } elseif (\in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $str = $this->buildMethodGroup($logEntry);
        } elseif (\in_array($method, array('profileEnd','table','trace'))) {
            $str = $this->buildMethodTabular($logEntry);
        } else {
            $str = $this->buildMethodDefault($logEntry);
        }
        $str = \strtr($str, array(
            ' data-channel="null"' => '',
            ' data-icon="null"' => '',
        ));
        $str .= "\n";
        return $str;
    }

    /**
     * Convert all arguments to html and join them together.
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return string html
     */
    protected function buildArgString($args, $meta = array())
    {
        $glue = ', ';
        $glueAfterFirst = true;
        $meta = \array_merge(array(
            'sanitize' => true, // apply htmlspecialchars (to non-first arg)?
        ), $meta);
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
            } elseif (\count($args) == 2) {
                $glue = ' = ';
            }
        }
        foreach ($args as $i => $v) {
            $args[$i] = $i > 0
                ? $this->dump($v, $meta['sanitize'])
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
     * Handle alert method
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return array array($method, $args)
     */
    protected function buildMethodAlert(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'class' => null,    // additional css classes
            'icon' => null,
            'style' => null,
        ), $logEntry['meta']);
        $attribs = array(
            'class' => 'm_alert alert-'.$meta['level'].' '.$meta['class'],
            'data-channel' => $meta['channel'],
            'data-icon' => $meta['icon'],
            'role' => 'alert',
            'style' => $meta['style'],
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
     * Handle html output of default/standard methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function buildMethodDefault(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'class' => null,
            'errorCat' => null,
            'icon' => null,
            'sanitize' => true,
            'style' => null,
        ), $logEntry['meta']);
        $attribs = array(
            'class' => 'm_'.$method.' '.$meta['class'],
            'data-channel' => $meta['channel'],
            'data-icon' => $meta['icon'],
            'title' => isset($meta['file'])
                ? $meta['file'].': line '.$meta['line']
                : null,
            'style' => $meta['style'],
        );
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if ($meta['errorCat']) {
                //  should only be applicable for error & warn methods
                $attribs['class'] .= ' error-'.$meta['errorCat'];
            }
            if (\count($args) > 1 && \is_string($args[0])) {
                $hasSubs = false;
                $args = $this->processSubstitutions($args, $hasSubs);
                if ($hasSubs) {
                    $args = array( \implode('', $args) );
                }
            }
        }
        return $this->debug->utilities->buildTag(
            'li',
            $attribs,
            $this->buildArgString($args, $meta)
        );
    }

    /**
     * Handle html output of group, groupCollapsed, & groupEnd
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function buildMethodGroup(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'argsAsParams' => true,
            'boldLabel' => true,
            'icon' => null,
            'isMethodName' => false,
            'level' => null,
            'style' => null,
        ), $logEntry['meta']);
        $str = '';
        if (\in_array($method, array('group','groupCollapsed'))) {
            $label = \array_shift($args);
            $levelClass = $meta['level']
                ? 'level-'.$meta['level']
                : null;
            foreach ($args as $k => $v) {
                $args[$k] = $this->dump($v);
            }
            $argStr = \implode(', ', $args);
            if ($meta['argsAsParams']) {
                if ($meta['isMethodName']) {
                    $label = $this->markupClassname($label);
                }
                $argStr = '<span class="group-label group-label-bold">'.$label.'(</span>'
                    .$argStr
                    .'<span class="group-label group-label-bold">)</span>';
                $argStr = \str_replace('(</span><span class="group-label group-label-bold">)', '', $argStr);
            } else {
                $argStr = '<span class="group-label group-label-bold">'.$label.':</span> '
                    .$argStr;
                $argStr = \preg_replace("#:</span> $#", '</span>', $argStr);
            }
            if (!$meta['boldLabel']) {
                $argStr = \str_replace(' group-label-bold', '', $argStr);
            }
            $str .= '<li'.$this->debug->utilities->buildAttribString(array(
                'class' => 'm_group',
                'data-channel' => $meta['channel'],
                'data-icon' => $meta['icon'],
                'style' => $meta['style'],
            )).'>'."\n";
            /*
                Header / label / toggle
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
                ),
                $argStr
            )."\n";
            /*
                Group open
            */
            $str .= '<ul'.$this->debug->utilities->buildAttribString(array(
                'class' => array(
                    'group-body',
                    $levelClass,
                ),
            )).'>';
        } elseif ($method == 'groupEnd') {
            $str = '</ul>'."\n".'</li>';
        }
        return $str;
    }

    /**
     * Handle profile(End), table, & trace methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function buildMethodTabular(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'caption' => null,
            'class' => null,
            'columns' => array(),
            'icon' => null,
            'sortable' => false,
            'style' => null,
            'totalCols' => array(),
        ), $logEntry['meta']);
        $asTable = \is_array($args[0]) && $args[0];
        if (!$asTable && $meta['caption']) {
            \array_unshift($args, $meta['caption']);
        }
        return $this->debug->utilities->buildTag(
            'li',
            array(
                'class' => 'm_'.$logEntry['method'].' '.$meta['class'],
                'data-channel' => $meta['channel'],
                'data-icon' => $meta['icon'],
                'style' => $meta['style'],
            ),
            $asTable
                ? "\n"
                    .$this->table->build(
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
     * Getter for this->table
     *
     * @return HtmlObject
     */
    protected function getTable()
    {
        $this->table = new HtmlTable($this->debug);
        return $this->table;
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
            \array_unshift($this->data['alerts'], new LogEntry(
                $this->debug,
                'alert',
                array(
                    $errorSummary
                ),
                array(
                    'class' => 'error-summary',
                    'dismissible' => false,
                    'level' => 'danger',
                )
            ));
        }
        return parent::processAlerts();
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
