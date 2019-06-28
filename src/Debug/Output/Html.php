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
use bdk\Debug\Abstraction\Abstraction;
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
    protected $argAttribs = array();
    protected $logEntryAttribs = array();
    protected $channels = array();
    protected $detectFiles = false;
    protected $argStringOpts = array();     // per-argument string options

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
     * @param mixed        $val     value to dump
     * @param array        $opts    options for string values
     * @param string|false $tagName (span) tag to wrap value in (or false)
     *
     * @return string
     */
    public function dump($val, $opts = array(), $tagName = 'span')
    {
        $this->argAttribs = array(
            'class' => array(),
            'title' => null,
        );
        $optsDefault = array(
            'addQuotes' => true,
            'sanitize' => true,
            'visualWhiteSpace' => true,
        );
        if (\is_bool($opts)) {
            $keys = \array_keys($optsDefault);
            $opts = \array_fill_keys($keys, $opts);
        } else {
            $opts = \array_merge($optsDefault, $opts);
        }
        $absAttribs = array();
        if ($val instanceof Abstraction) {
            $absAttribs = $val['attribs'];
            foreach (\array_keys($opts) as $k) {
                if ($val[$k] !== null) {
                    $opts[$k] = $val[$k];
                }
            }
        }
        $this->argStringOpts = $opts;
        $val = parent::dump($val);
        if ($tagName && !\in_array($this->dumpType, array('recursion'))) {
            $argAttribs = $this->debug->utilities->arrayMergeDeep(
                array(
                    'class' => array(
                        't_'.$this->dumpType,
                        $this->dumpTypeMore,
                    ),
                ),
                $this->argAttribs
            );
            if ($absAttribs) {
                $absAttribs['class'] = isset($absAttribs['class'])
                    ? (array) $absAttribs['class']
                    : array();
                $argAttribs = $this->debug->utilities->arrayMergeDeep(
                    $argAttribs,
                    $absAttribs
                );
            }
            $val = $this->debug->utilities->buildTag($tagName, $argAttribs, $val);
        }
        $this->argAttribs = array();
        return $val;
    }

    /**
     * Wrap classname in span.classname
     * if namespaced additionally wrap namespace in span.namespace
     * If callable, also wrap with .t_operator and .t_identifier
     *
     * @param string $str     classname or classname(::|->)name (method/property/const)
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs (optional) additional html attributes
     *
     * @return string
     */
    public function markupIdentifier($str, $tagName = 'span', $attribs = array())
    {
        if (\preg_match('/^(.+)(::|->)(.+)$/', $str, $matches)) {
            $classname = $matches[1];
            $opIdentifier = '<span class="t_operator">'.\htmlspecialchars($matches[2]).'</span>'
                    . '<span class="t_identifier">'.$matches[3].'</span>';
        } else {
            $classname = $str;
            $opIdentifier = '';
        }
        $idx = \strrpos($classname, '\\');
        if ($idx) {
            $classname = '<span class="namespace">'.\substr($classname, 0, $idx + 1).'</span>'
                . \substr($classname, $idx + 1);
        }
        $attribs = \array_merge(array(
            'class' => 'classname',
        ), $attribs);
        return $this->debug->utilities->buildTag($tagName, $attribs, $classname)
            .$opIdentifier;
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
        $lftDefault = \strtr(\ini_get('xdebug.file_link_format'), array('%f'=>'%file','%l'=>'%line'));
        $str = '<div'.$this->debug->utilities->buildAttribString(array(
            'class' => 'debug',
            'data-options' => array(
                'drawer' => $this->debug->getCfg('output.drawer'),
                'sidebar' => $this->debug->getCfg('output.sidebar'),
                'linkFilesTemplateDefault' => $lftDefault ?: null,
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
        $meta = \array_merge(array(
            'attribs' => array(),
            'detectFiles' => null,
            'icon' => null,
        ), $logEntry['meta']);
        $channelName = $logEntry->getChannel();
        // phpError channel is handled separately
        if (!isset($this->channels[$channelName]) && $channelName !== 'phpError') {
            $this->channels[$channelName] = $logEntry->getSubject();
        }
        $this->detectFiles = $meta['detectFiles'];
        $this->logEntryAttribs = array(
            'class' => '',
            'data-channel' => $channelName !== $this->channelNameRoot
                ? $channelName
                : null,
            'data-detect-files' => $meta['detectFiles'],
            'data-icon' => $meta['icon'],
        );
        $this->logEntryAttribs = \array_merge($this->logEntryAttribs, $meta['attribs']);
        $this->logEntryAttribs['class'] .= ' m_'.$method;
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
            ' data-detect-files="null"' => '',
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
            'sanitize' => true,         // apply htmlspecialchars (to non-first arg)?
            'sanitizeFirst' => null,    // if null, use meta.sanitize
        ), $meta);
        if ($meta['sanitizeFirst'] === null) {
            $meta['sanitizeFirst'] = $meta['sanitize'];
        }
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
            $args[$i] = $this->dump($v, array(
                'sanitize' => $i === 0
                    ? $meta['sanitizeFirst']
                    : $meta['sanitize'],
                'addQuotes' => $i !== 0,
                'visualWhiteSpace' => $i !== 0,
            ));
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
        if (\array_keys($this->channels) == array($this->channelNameRoot)) {
            return array();
        }
        \ksort($this->channels);
        // move root to the top
        if (isset($this->channels[$this->channelNameRoot])) {
            // move root to the top
            $this->channels = array($this->channelNameRoot => $this->channels[$this->channelNameRoot]) + $this->channels;
        }
        $tree = array();
        foreach ($this->channels as $channelName => $channel) {
            $ref = &$tree;
            $path = \explode('.', $channelName);
            foreach ($path as $k) {
                if (!isset($ref[$k])) {
                    $ref[$k] = array(
                        'options' => array(
                            'icon' => $channel->getCfg('channelIcon'),
                            'show' => $channel->getCfg('channelShow'),
                        ),
                        'channels' => array(),
                    );
                }
                $ref = &$ref[$k]['channels'];
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
        $meta = $logEntry['meta'];
        $attribs = \array_merge($this->logEntryAttribs, array(
            'class' => 'alert-'.$meta['level'].' '.$this->logEntryAttribs['class'],
            'role' => 'alert',
        ));
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
            'errorCat' => null,
        ), $logEntry['meta']);
        $attribs = \array_merge($this->logEntryAttribs, array(
            'title' => isset($meta['file']) && $logEntry->getChannel() !== 'phpError'
                ? $meta['file'].': line '.$meta['line']
                : null,
        ));
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if ($meta['errorCat']) {
                //  should only be applicable for error & warn methods
                $attribs['class'] .= ' error-'.$meta['errorCat'];
            }
            if (\count($args) > 1 && \is_string($args[0])) {
                $hasSubs = false;
                $args = $this->processSubstitutions($args, $hasSubs);
                if ($hasSubs) {
                    $meta['sanitizeFirst'] = false;
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
            'isFuncName' => false,
            'level' => null,
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
                if ($meta['isFuncName']) {
                    $label = $this->markupIdentifier($label);
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
            $this->logEntryAttribs['class'] = \str_replace('m_'.$method, 'm_group', $this->logEntryAttribs['class']);
            $str .= '<li'.$this->debug->utilities->buildAttribString($this->logEntryAttribs).'>'."\n";
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
            'columns' => array(),
            'sortable' => false,
            'totalCols' => array(),
        ), $logEntry['meta']);
        $asTable = false;
        if (\is_array($args[0])) {
            $asTable = (bool) $args[0];
        } elseif ($this->debug->abstracter->isAbstraction($args[0], 'object')) {
            $asTable = true;
        }
        if (!$asTable && $meta['caption']) {
            \array_unshift($args, $meta['caption']);
        }
        return $this->debug->utilities->buildTag(
            'li',
            $this->logEntryAttribs,
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
                            .'</span>'
                            .'<span class="t_operator">=&gt;</span>'
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
     * @param Abstraction $abs array/callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return '<span class="t_type">callable</span> '
            .$this->markupIdentifier($abs['values'][0].'::'.$abs['values'][1]);
    }

    /**
     * Dump "const" abstration as html
     *
     * @param Abstraction $abs const abstraction
     *
     * @return string
     */
    protected function dumpConst(Abstraction $abs)
    {
        $this->argAttribs['title'] = $abs['value']
            ? 'value: '.$this->debug->output->text->dump($abs['value'])
            : null;
        return $this->markupIdentifier($abs['name']);
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
            $this->argAttribs['class'][] = 'timestamp';
            $this->argAttribs['title'] = $date;
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
     * @param Abstraction $abs object abstraction
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        /*
            Were we debugged from inside or outside of the object?
        */
        $dump = $this->object->dump($abs);
        $this->argAttribs['data-accessible'] = $abs['scopeClass'] == $abs['className']
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
                $this->argAttribs['class'][] = 'timestamp';
                $this->argAttribs['title'] = $date;
            }
        } else {
            if ($this->detectFiles && !\preg_match('/[\r\n]/', $val) && \is_file($val)) {
                $this->argAttribs['class'][] = 'file';
            }
            if ($this->argStringOpts['sanitize']) {
                $val = $this->debug->utf8->dump($val, true, true);
            } else {
                $val = $this->debug->utf8->dump($val, true, false);
            }
            if ($this->argStringOpts['visualWhiteSpace']) {
                $val = $this->visualWhiteSpace($val);
            }
        }
        if (!$this->argStringOpts['addQuotes']) {
            $this->argAttribs['class'][] = 'no-quotes';
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
     * Coerce value to string
     *
     * @param mixed $val value
     *
     * @return string
     */
    protected function substitutionAsString($val)
    {
        // function array dereferencing = php 5.4
        $type = $this->debug->abstracter->getType($val)[0];
        if ($type == 'string') {
            $val = $this->dump($val, true, false);
        } elseif ($type == 'array') {
            $count = \count($val);
            $val = '<span class="t_keyword">array</span>'
                .'<span class="t_punct">(</span>'.$count.'<span class="t_punct">)</span>';
        } elseif ($type == 'object') {
            $val = $this->markupIdentifier($val['className']);
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
