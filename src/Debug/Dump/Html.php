<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2021 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Dump\HtmlObject;
use bdk\Debug\Dump\HtmlString;
use bdk\Debug\Dump\HtmlTable;
use bdk\Debug\LogEntry;

/**
 * Dump val as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 * @property HtmlTable  $table  lazy-loaded HtmlTable... only loaded if outputing a table
 */
class Html extends Base
{

    protected $channels = array();

    /** @var array LogEntry meta attribs */
    protected $logEntryAttribs = array();

    /** @var HtmlHelper helper class */
    protected $helper;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /** @var HtmlString string dumper */
    protected $string;

    /** @var HtmlObject */
    protected $lazyObject;

    /** @var HtmlTable */
    protected $lazyTable;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->helper = new HtmlHelper($this, $debug);
        $this->html = $debug->html;
        $this->string = new HtmlString($this);
    }

    /**
     * Is value a timestamp?
     * Add classname & title if so
     *
     * Extends Base
     *
     * @param mixed $val value to check
     *
     * @return string|false
     */
    public function checkTimestamp($val)
    {
        $date = parent::checkTimestamp($val);
        if ($date) {
            $this->setDumpOpt('postDump', function ($dumped, $opts) use ($val, $date) {
                $attribs = array(
                    'class' => array('timestamp', 'value-container'),
                    'data-type' => $opts['type'],
                    'title' => $date,
                );
                if ($opts['tagName'] === 'td') {
                    $wrapped = $this->html->buildTag('span', $attribs, $val);
                    return $this->html->buildTag(
                        'td',
                        array(
                            'class' => 't_' . $opts['type']
                        ),
                        $wrapped
                    );
                }
                return $this->html->buildTag('span', $attribs, $dumped);
            });
            return $date;
        }
        return false;
    }

    /**
     * Dump value as html
     *
     * @param mixed $val  value to dump
     * @param array $opts options for string values
     *                      addQuotes, sanitize, visualWhitespace, etc
     *
     * @return string
     */
    public function dump($val, $opts = array())
    {
        $opts = $this->setDumpOptDefaults($val, $opts);
        $val = parent::dump($val, $opts);
        $this->dumpOptions['attribs']['class'][] = 't_' . $this->dumpOptions['type'];
        if ($this->dumpOptions['typeMore'] !== null) {
            $this->dumpOptions['attribs']['data-type-more'] = \trim($this->dumpOptions['typeMore']);
        }
        $tagName = $this->dumpOptions['tagName'];
        if ($tagName === '__default__') {
            $tagName = $this->dumpOptions['type'] === Abstracter::TYPE_OBJECT
                ? 'div'
                : 'span';
        }
        if ($tagName) {
            $val = $this->html->buildTag($tagName, $this->dumpOptions['attribs'], $val);
        }
        if ($this->dumpOptions['postDump']) {
            $val = \call_user_func($this->dumpOptions['postDump'], $val, $this->dumpOptions);
        }
        return $val;
    }

    /**
     * Get "option" of value being dumped
     *
     * @param string $what (optional) name of option to get (ie sanitize, type, typeMore)
     *
     * @return mixed
     */
    public function getDumpOpt($what = null)
    {
        $val = parent::getDumpOpt($what);
        if ($what === 'tagName' && $val === '__default__') {
            $val = 'span';
            if (parent::getDumpOpt('type') === Abstracter::TYPE_OBJECT) {
                $val = 'div';
            }
        }
        return $val;
    }

    /**
     * Wrap classname in span.classname
     * if namespaced additionally wrap namespace in span.namespace
     * If callable, also wrap with .t_operator and .t_identifier
     *
     * Extends Base
     *
     * @param mixed  $val     classname or classname(::|->)name (method/property/const)
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs (optional) additional html attributes for classname span
     * @param bool   $wbr     (false)
     *
     * @return string
     */
    public function markupIdentifier($val, $tagName = 'span', $attribs = array(), $wbr = false)
    {
        return $this->helper->markupIdentifier($val, $tagName, $attribs, $wbr);
    }

    /**
     * Return a log entry as HTML
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string|void
     */
    public function processLogEntry(LogEntry $logEntry)
    {
        $meta = $this->setMetaDefaults($logEntry);
        $channelName = $logEntry->getChannelName();
        // phpError channel is handled separately
        if (!isset($this->channels[$channelName]) && $channelName !== $this->channelNameRoot . '.phpError') {
            $this->channels[$channelName] = $logEntry->getSubject();
        }
        $this->string->detectFiles = $meta['detectFiles'];
        $this->logEntryAttribs = $this->debug->arrayUtil->mergeDeep(array(
            'class' => array('m_' . $logEntry['method']),
            'data-channel' => $channelName !== $this->channelNameRoot
                ? $channelName
                : null,
            'data-detect-files' => $meta['detectFiles'],
            'data-icon' => $meta['icon'],
        ), $meta['attribs']);
        $html = parent::processLogEntry($logEntry);
        $html = \strtr($html, array(
            ' data-channel="null"' => '',
            ' data-detect-files="null"' => '',
            ' data-icon="null"' => '',
        ));
        return $html . "\n";
    }

    /**
     * Set "option" of value being dumped
     *
     * @param array|string $what name of value to set (or key/value array)
     * @param mixed        $val  value
     *
     * @return void
     */
    public function setDumpOpt($what, $val = null)
    {
        if ($what === 'attribs' && empty($val['class'])) {
            // make sure class is set
            $val['class'] = array();
        }
        parent::setDumpOpt($what, $val);
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
            return '<span class="t_keyword">array</span>'
                . '<span class="t_punct">()</span>';
        }
        $opts = \array_merge(array(
            'asFileTree' => false,
            'expand' => null,
            'showListKeys' => true,
        ), $this->getDumpOpt());
        if ($opts['expand'] !== null) {
            $this->setDumpOpt('attribs.data-expand', $opts['expand']);
        }
        if ($opts['asFileTree']) {
            $this->setDumpOpt('attribs.class.__push__', 'array-file-tree');
        }
        $showKeys = $opts['showListKeys'] || !$this->debug->arrayUtil->isList($array);
        $html = '<span class="t_keyword">array</span>'
            . '<span class="t_punct">(</span>' . "\n"
            . '<ul class="array-inner list-unstyled">' . "\n";
        foreach ($array as $key => $val) {
            $html .= $this->dumpArrayValue($key, $val, $showKeys);
        }
        $html .= '</ul>'
            . '<span class="t_punct">)</span>';
        return $html;
    }

    /**
     * Dump an array key/value pair
     *
     * @param int|string $key     key
     * @param mixed      $val     value
     * @param bool       $withKey include key with value?
     *
     * @return string
     */
    private function dumpArrayValue($key, $val, $withKey)
    {
        return $withKey
            ? "\t" . '<li>'
                . $this->html->buildTag(
                    'span',
                    array(
                        'class' => array(
                            't_key',
                            't_int' => \is_int($key),
                        ),
                    ),
                    $this->dump($key, array('tagName' => null)) // don't wrap it
                )
                . '<span class="t_operator">=&gt;</span>'
                . $this->dump($val)
            . '</li>' . "\n"
            : "\t" . $this->dump($val, array('tagName' => 'li')) . "\n";
    }

    /**
     * Dump boolean
     *
     * @param bool $val boolean value
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
     * @param Abstraction $abs callable abstraction
     *
     * @return string
     */
    protected function dumpCallable(Abstraction $abs)
    {
        return (!$abs['hideType'] ? '<span class="t_type">callable</span> ' : '')
            . $this->markupIdentifier($abs);
    }

    /**
     * Dump "const" abstration as html
     *
     * Object constant or method param's default value
     *
     * @param Abstraction $abs const abstraction
     *
     * @return string
     */
    protected function dumpConst(Abstraction $abs)
    {
        $this->setDumpOpt('attribs.title', $abs['value']
            ? 'value: ' . $this->debug->getDump('text')->dump($abs['value'])
            : null);
        return $this->markupIdentifier($abs['name']);
    }

    /**
     * Dump float value
     *
     * @param float $val float value
     *
     * @return float|string
     */
    protected function dumpFloat($val)
    {
        $this->checkTimestamp($val);
        if ($val === Abstracter::TYPE_FLOAT_INF) {
            return 'INF';
        }
        if ($val === Abstracter::TYPE_FLOAT_NAN) {
            return 'NaN';
        }
        return $val;
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
     * @param Abstraction $abs Object Abstraction instance
     *
     * @return string
     */
    protected function dumpObject(Abstraction $abs)
    {
        /*
            Were we debugged from inside or outside of the object?
        */
        $this->setDumpOpt('attribs.data-accessible', $abs['scopeClass'] === $abs['className']
            ? 'private'
            : 'public');
        return $this->object->dump($abs);
    }

    /**
     * Dump recursion (array recursion)
     *
     * @return string
     */
    protected function dumpRecursion()
    {
        $this->setDumpOpt('tagName', null); // don't wrap value span
        return '<span class="t_keyword">array</span> <span class="t_recursion">*RECURSION*</span>';
    }

    /**
     * Dump string
     *
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, Abstraction $abs = null)
    {
        return $this->string->dump($val, $abs);
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
     * Get & reset logged channels
     *
     * @return \bdk\Debug[]
     */
    protected function getChannels()
    {
        $channels = $this->channels;
        $this->channels = array();
        return $channels;
    }

    /**
     * Getter for this->object
     *
     * @return HtmlObject
     */
    protected function getObject()
    {
        if (!$this->lazyObject) {
            $this->lazyObject = new HtmlObject($this, $this->helper, $this->html);
        }
        return $this->lazyObject;
    }

    /**
     * Getter for this->table
     *
     * @return HtmlTable
     */
    protected function getTable()
    {
        if (!$this->lazyTable) {
            $this->lazyTable = new HtmlTable($this);
        }
        return $this->lazyTable;
    }

    /**
     * Process substitutions and return updated args and meta
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array array($args, $meta)
     */
    protected function handleSubstitutions(LogEntry $logEntry)
    {
        $args = $logEntry['args'];
        $meta = $logEntry['meta'];
        if ($logEntry->containsSubstitutions()) {
            $args[0] = $this->dump($args[0], array(
                'sanitize' => $meta['sanitizeFirst'],
                'tagName' => null,
            ));
            $args = $this->processSubstitutions($args, array(
                'replace' => true,
                'sanitize' => $meta['sanitize'],
                'style' => true,
            ));
            $meta['sanitizeFirst'] = false;
        }
        return array($args, $meta);
    }

    /**
     * Handle alert method
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    protected function methodAlert(LogEntry $logEntry)
    {
        list($args, $meta) = $this->handleSubstitutions($logEntry);
        $attribs = \array_merge(array(
            'class' => array(),
            'role' => 'alert',
        ), $this->logEntryAttribs);
        $attribs['class'][] = 'alert-' . $meta['level'];
        $html = $this->dump($args[0], array(
            'sanitize' => $meta['sanitizeFirst'],
            'tagName' => null, // don't wrap value span
            'visualWhiteSpace' => false,
        ));
        if ($meta['dismissible']) {
            $attribs['class'][] = 'alert-dismissible';
            $html = '<button type="button" class="close" data-dismiss="alert" aria-label="Close">'
                . '<span aria-hidden="true">&times;</span>'
                . '</button>'
                . $html;
        }
        return $this->html->buildTag('div', $attribs, $html);
    }

    /**
     * Handle html output of default/standard methods
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    protected function methodDefault(LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        $attribs = $this->logEntryAttribs;
        if (isset($meta['file']) && $logEntry->getChannelName() !== $this->channelNameRoot . '.phpError') {
            // PHP errors will have file & line as one of the arguments
            //    so no need to store file & line as data args
            $attribs = \array_merge(array(
                'data-file' => $meta['file'],
                'data-line' => $meta['line'],
            ), $attribs);
        }
        if ($meta['errorCat']) {
            $attribs['class'][] = 'error-' . $meta['errorCat'];
        }
        if ($meta['uncollapse'] === false) {
            $attribs['data-uncollapse'] = false;
        }
        list($args, $meta) = $this->handleSubstitutions($logEntry);
        $append = !empty($meta['context'])
            ? $this->helper->buildContext($meta['context'], $meta['line'])
            : '';
        return $this->html->buildTag(
            'li',
            $attribs,
            $this->helper->buildArgString($args, $meta) . $append
        );
    }

    /**
     * Handle html output of group, groupCollapsed, & groupEnd
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    protected function methodGroup(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if ($method === 'groupEnd') {
            return '</ul>' . "\n" . '</li>';
        }
        $meta = $this->methodGroupPrep($logEntry);

        $str = '<li' . $this->html->buildAttribString($this->logEntryAttribs) . '>' . "\n";
        $str .= $this->html->buildTag(
            'div',
            array(
                'class' => 'group-header',
            ),
            $this->methodGroupHeader($logEntry['args'], $meta)
        ) . "\n";
        $str .= '<ul' . $this->html->buildAttribString(array(
            'class' => 'group-body',
        )) . '>';
        return $str;
    }

    /**
     * Adds 'class' value to `$this->logEntryAttribs`
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array meta values
     */
    private function methodGroupPrep(LogEntry $logEntry)
    {
        $meta = \array_merge(array(
            'argsAsParams' => true,
            'boldLabel' => true,
            'hideIfEmpty' => false,
            'isFuncName' => false,
            'level' => null,
        ), $logEntry['meta']);

        $classes = (array) $this->logEntryAttribs['class'];
        if ($logEntry['method'] === 'group') {
            // groupCollapsed doesn't get expanded
            $classes[] = 'expanded';
        }
        if ($meta['hideIfEmpty']) {
            $classes[] = 'hide-if-empty';
        }
        if ($meta['level']) {
            $classes[] = 'level-' . $meta['level'];
        }
        $classes = \implode(' ', $classes);
        $classes = \str_replace('m_' . $logEntry['method'], 'm_group', $classes);
        $this->logEntryAttribs['class'] = $classes;
        return $meta;
    }

    /**
     * Build group header
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return string
     */
    private function methodGroupHeader($args, $meta)
    {
        $label = \array_shift($args);
        if ($meta['isFuncName']) {
            $label = $this->markupIdentifier($label);
        }
        $labelClasses = \implode(' ', \array_keys(\array_filter(array(
            'font-weight-bold' => $meta['boldLabel'],
            'group-label' => true,
        ))));

        $headerAppend = '';

        if ($args) {
            foreach ($args as $k => $v) {
                $args[$k] = $this->dump($v);
            }
            $argStr = \implode(', ', $args);
            $label .= $meta['argsAsParams']
                ? '(</span>' . $argStr . '<span class="' . $labelClasses . '">)'
                : ':';
            $headerAppend = $meta['argsAsParams']
                ? ''
                : ' ' . $argStr;
        }

        return '<span class="' . $labelClasses . '">'
            . $label
            . '</span>'
            . $headerAppend;
    }

    /**
     * Handle profile(End), table, & trace methods
     *
     * Extends Base
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    protected function methodTabular(LogEntry $logEntry)
    {
        $meta = $this->debug->arrayUtil->mergeDeep(array(
            'attribs' => array(
                'class' => \array_keys(\array_filter(array(
                    'table-bordered' => true,
                    'sortable' => $logEntry->getMeta('sortable'),
                    'trace-context' => $logEntry->getMeta('inclContext'),
                ))),
            ),
            'caption' => null,
            'onBuildRow' => array(),
            'tableInfo' => array(),
        ), $logEntry['meta']);
        if ($logEntry['method'] === 'trace') {
            $meta['onBuildRow'][] = array($this->helper, 'tableMarkupFunction');
        }
        if ($logEntry->getMeta('inclContext')) {
            $meta['onBuildRow'][] = array($this->helper, 'tableAddContextRow');
        }
        return $this->html->buildTag(
            'li',
            $this->logEntryAttribs,
            "\n" . $this->table->build($logEntry['args'][0], $meta) . "\n"
        );
    }

    /**
     * Get dump options
     *
     * @param mixed $val  value being dumpted
     * @param array $opts options for string values
     *                      addQuotes, sanitize, visualWhitespace, etc
     *
     * @return array
     */
    private function setDumpOptDefaults($val, $opts)
    {
        $attribs = array(
            'class' => array(),
        );
        if ($val instanceof Abstraction && \is_array($val['attribs'])) {
            $attribs = \array_merge(
                $attribs,
                $val['attribs']
            );
        }
        $opts = \array_merge(array(
            'tagName' => '__default__',
            'attribs' => $attribs,
            'postDump' => null,
        ), $opts);
        return $opts;
    }

    /**
     * Set default meta values
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array all meta values
     */
    private function setMetaDefaults(LogEntry $logEntry)
    {
        $meta = \array_merge(array(
            'attribs' => array(),
            'detectFiles' => null,
            'errorCat' => null,         // should only be applicable for error & warn methods
            'glue' => null,
            'icon' => null,
            'sanitize' => true,         // apply htmlspecialchars (to non-first arg)?
            'sanitizeFirst' => null,    // if null, use meta.sanitize
            'uncollapse' => null,
        ), $logEntry['meta']);
        if ($meta['sanitizeFirst'] === null) {
            $meta['sanitizeFirst'] = $meta['sanitize'];
        }
        $logEntry->setMeta($meta);
        return $meta;
    }

    /**
     * Coerce value to string
     *
     * Extends Base
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    protected function substitutionAsString($val, $opts)
    {
        // function array dereferencing = php 5.4
        $type = $this->debug->abstracter->getType($val)[0];
        if ($type === Abstracter::TYPE_STRING) {
            return $this->string->dumpAsSubstitution($val, $opts);
        }
        if ($type === Abstracter::TYPE_ARRAY) {
            $count = \count($val);
            return '<span class="t_keyword">array</span>'
                . '<span class="t_punct">(</span>' . $count . '<span class="t_punct">)</span>';
        }
        if ($type === Abstracter::TYPE_OBJECT) {
            $opts['tagName'] = null;
            $toStr = (string) $val; // objects __toString or its classname
            return $toStr === $val['className']
                ? $this->markupIdentifier($toStr)
                : $this->dump($toStr, $opts);
        }
        return $this->dump($val);
    }
}
