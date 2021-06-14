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
    /** @var array attribs added here when dumping val*/
    /** @var HtmlString string dumper */
    protected $string;

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->string = new HtmlString($this);
    }

    /**
     * Is value a timestamp?
     * Add classname & title if so
     *
     * @param mixed $val value to check
     *
     * @return string|false
     */
    public function checkTimestamp($val)
    {
        $date = parent::checkTimestamp($val);
        if ($date) {
            $template = $this->debug->html->buildTag(
                'span',
                array(
                    'class' => array('timestamp', 'value-container'),
                    'data-type' => $this->getDumpOpt('type'),
                    'title' => $date,
                ),
                '{val}'
            );
            $this->setDumpOpt('template', $template);
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
            'template' => null,
        ), $opts);
        $val = parent::dump($val, $opts);
        $tagName = $this->dumpOptions['tagName'];
        if ($tagName === '__default__') {
            $tagName = 'span';
            if ($this->dumpOptions['type'] === Abstracter::TYPE_OBJECT) {
                $tagName = 'div';
            }
        }
        if ($tagName) {
            $this->dumpOptions['attribs']['class'][] = 't_' . $this->dumpOptions['type'];
            if ($this->dumpOptions['typeMore'] !== null) {
                $this->dumpOptions['attribs']['data-type-more'] = \trim($this->dumpOptions['typeMore']);
            }
            $val = $this->debug->html->buildTag($tagName, $this->dumpOptions['attribs'], $val);
        }
        if ($this->dumpOptions['template']) {
            $val = $this->debug->utility->strInterpolate($this->dumpOptions['template'], array(
                'val' => $val,
            ));
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
     * @param mixed  $val     classname or classname(::|->)name (method/property/const)
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs (optional) additional html attributes for classname span
     * @param bool   $wbr     (false)
     *
     * @return string
     */
    public function markupIdentifier($val, $tagName = 'span', $attribs = array(), $wbr = false)
    {
        $classname = '';
        $operator = '::';
        $identifier = '';
        $regex = '/^(.+)(::|->)(.+)$/';
        if ($val instanceof Abstraction) {
            $val = $val['value'];
        }
        $classname = $val;
        $matches = array();
        if (\is_array($val)) {
            list($classname, $identifier) = $val;
        } elseif (\preg_match($regex, $val, $matches)) {
            $classname = $matches[1];
            $operator = $matches[2];
            $identifier = $matches[3];
        } elseif (\preg_match('/^(.+)(\\\\\{closure\})$/', $val, $matches)) {
            $classname = $matches[1];
            $operator = '';
            $identifier = $matches[2];
        }
        $operator = '<span class="t_operator">' . \htmlspecialchars($operator) . '</span>';
        if ($classname) {
            $idx = \strrpos($classname, '\\');
            if ($idx) {
                $classname = '<span class="namespace">' . \str_replace('\\', '\\<wbr />', \substr($classname, 0, $idx + 1)) . '</span>'
                    . \substr($classname, $idx + 1);
            }
            $classname = $this->debug->html->buildTag(
                $tagName,
                $this->debug->arrayUtil->mergeDeep(array(
                    'class' => array('classname'),
                ), (array) $attribs),
                $classname
            ) . '<wbr />';
        }
        if ($identifier) {
            $identifier = '<span class="t_identifier">' . $identifier . '</span>';
        }
        $parts = \array_filter(array($classname, $identifier), 'strlen');
        $html = \implode($operator, $parts);
        if ($wbr === false) {
            $html = \str_replace('<wbr />', '', $html);
        }
        return $html;
    }

    /**
     * Markup type-hint / type declaration
     *
     * @param string $type    type declaration
     * @param array  $attribs (optional) additional html attributes
     *
     * @return string
     */
    public function markupType($type, $attribs = array())
    {
        $phpPrimatives = array(
            // scalar
            Abstracter::TYPE_BOOL, Abstracter::TYPE_FLOAT, Abstracter::TYPE_INT, Abstracter::TYPE_STRING,
            // compound
            Abstracter::TYPE_ARRAY, Abstracter::TYPE_CALLABLE, Abstracter::TYPE_OBJECT, 'iterable',
            // "special"
            Abstracter::TYPE_NULL, Abstracter::TYPE_RESOURCE,
        );
        $typesOther = array(
            '$this','false','mixed','static','self','true','void',
        );
        $typesPrimative = \array_merge($phpPrimatives, $typesOther);
        $types = \preg_split('/\s*\|\s*/', $type);
        foreach ($types as $i => $type) {
            $isArray = false;
            if (\substr($type, -2) === '[]') {
                $isArray = true;
                $type = \substr($type, 0, -2);
            }
            if (!\in_array($type, $typesPrimative)) {
                $type = $this->markupIdentifier($type);
            }
            if ($isArray) {
                $type .= '<span class="t_punct">[]</span>';
            }
            $types[$i] = '<span class="t_type">' . $type . '</span>';
        }
        $types = \implode('<span class="t_punct">|</span>', $types);
        $attribs = \array_filter($attribs);
        if ($attribs) {
            $type = $this->debug->html->buildtag('span', $attribs, $types);
        }
        return $types;
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
        $method = $logEntry['method'];
        $meta = \array_merge(array(
            'attribs' => array(),
            'detectFiles' => null,
            'glue' => null,
            'icon' => null,
            'sanitize' => true,         // apply htmlspecialchars (to non-first arg)?
            'sanitizeFirst' => null,    // if null, use meta.sanitize
        ), $logEntry['meta']);
        if ($meta['sanitizeFirst'] === null) {
            $meta['sanitizeFirst'] = $meta['sanitize'];
        }
        $logEntry->setMeta($meta);
        $channelName = $logEntry->getChannelName();
        // phpError channel is handled separately
        if (!isset($this->channels[$channelName]) && $channelName !== $this->channelNameRoot . '.phpError') {
            $this->channels[$channelName] = $logEntry->getSubject();
        }
        $this->string->detectFiles = $meta['detectFiles'];
        $this->logEntryAttribs = $this->debug->arrayUtil->mergeDeep(array(
            'class' => array('m_' . $method),
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
     * Insert a row containing code snip & arguments after the given row
     *
     * @param string $html    <tr>...</tr>
     * @param array  $row     Row values
     * @param array  $rowInfo Row info / meta
     * @param int    $index   Row index
     *
     * @return string
     */
    public function tableAddContextRow($html, $row, $rowInfo, $index)
    {
        if (!$rowInfo['context']) {
            return $html;
        }
        $html = \str_replace('<tr>', '<tr' . ($index === 0 ? ' class="expanded"' : '') . ' data-toggle="next">', $html);
        $html .= '<tr class="context" ' . ($index === 0 ? 'style="display:table-row;"' : '' ) . '>'
            . '<td colspan="4">'
                . '<pre class="highlight line-numbers" data-line="' . $row['line'] . '" data-start="' . \key($rowInfo['context']) . '">'
                    . '<code class="language-php">'
                        . \htmlspecialchars(\implode($rowInfo['context']))
                    . '</code>'
                . '</pre>'
                . '{{arguments}}'
            . '</td>' . "\n"
            . '</tr>' . "\n";
        $crateRawWas = $this->crateRaw;
        $this->crateRaw = true;
        $args = $rowInfo['args']
            ? '<hr />Arguments = ' . $this->dump($rowInfo['args'])
            : '';
        $this->crateRaw = $crateRawWas;
        return \str_replace('{{arguments}}', $args, $html);
    }

    /**
     * Format trace table's function column
     *
     * @param string $html <tr>...</tr>
     * @param array  $row  row values
     *
     * @return string
     */
    public function tableMarkupFunction($html, $row)
    {
        if (isset($row['function'])) {
            $regex = '/^(.+)(::|->)(.+)$/';
            $replace = \preg_match($regex, $row['function']) || \strpos($row['function'], '{closure}')
                ? $this->markupIdentifier($row['function'], 'span', array(), true)
                : '<span class="t_identifier">' . \htmlspecialchars($row['function']) . '</span>';
            $replace = '<td class="col-function no-quotes t_string">' . $replace . '</td>';
            $html = \str_replace(
                '<td class="t_string">' . \htmlspecialchars($row['function']) . '</td>',
                $replace,
                $html
            );
        }
        return $html;
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
        if (\count($args) === 0) {
            return '';
        }
        $glueDefault = ', ';
        $glueAfterFirst = true;
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]) . ' ';
            } elseif (\count($args) === 2) {
                $glueDefault = ' = ';
            }
        }
        $glue = $meta['glue'] ?: $glueDefault;
        $args = $this->buildArgStringArgs($args, $meta);
        return $glueAfterFirst
            ? \implode($glue, $args)
            : $args[0] . \implode($glue, \array_slice($args, 1));
    }

    /**
     * Return array of dumped arguments
     *
     * @param array $args arguments
     * @param array $meta meta values
     *
     * @return array
     */
    private function buildArgStringArgs($args, $meta)
    {
        foreach ($args as $i => $v) {
            list($type, $typeMore) = $this->debug->abstracter->getType($v);
            $typeMore2 = $typeMore === Abstracter::TYPE_ABSTRACTION
                ? $v['typeMore']
                : $typeMore;
            $args[$i] = $this->dump($v, array(
                'addQuotes' => $i !== 0 || $typeMore2 === Abstracter::TYPE_STRING_NUMERIC,
                'sanitize' => $i === 0
                    ? $meta['sanitizeFirst']
                    : $meta['sanitize'],
                'type' => $type,
                'typeMore' => $typeMore,
                'visualWhiteSpace' => $i !== 0,
            ));
        }
        return $args;
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
            $html .= $showKeys
                ? "\t" . '<li>'
                    . '<span class="t_key' . (\is_int($key) ? ' t_int' : '') . '">'
                        . $this->dump($key, array('tagName' => null)) // don't wrap it
                    . '</span>'
                    . '<span class="t_operator">=&gt;</span>'
                    . $this->dump($val)
                . '</li>' . "\n"
                : "\t" . $this->dump($val, array('tagName' => 'li')) . "\n";
        }
        $html .= '</ul>'
            . '<span class="t_punct">)</span>';
        return $html;
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
     * @param Abstraction $abs object abstraction
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
        $object = new HtmlObject($this);
        $this->readOnly['object'] = $object;
        return $object;
    }

    /**
     * Getter for this->table
     *
     * @return HtmlTable
     */
    protected function getTable()
    {
        $table = new HtmlTable($this);
        $this->readOnly['table'] = $table;
        return $table;
    }

    /**
     * Handle alert method
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function methodAlert(LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        $attribs = \array_merge(array(
            'class' => array(),
            'role' => 'alert',
        ), $this->logEntryAttribs);
        $attribs['class'][] = 'alert-' . $meta['level'];
        $args = $logEntry['args'];
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
        return $this->debug->html->buildTag('div', $attribs, $html);
    }

    /**
     * Handle html output of default/standard methods
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return string
     */
    protected function methodDefault(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'errorCat' => null,  //  should only be applicable for error & warn methods
            'uncollapse' => null,
        ), $logEntry['meta']);
        $attribs = $this->logEntryAttribs;
        if (isset($meta['file']) && $logEntry->getChannelName() !== $this->channelNameRoot . '.phpError') {
            // PHP errors will have file & line as one of the arguments
            //    so no need to store file & line as data args
            $attribs = \array_merge(array(
                'data-file' => $meta['file'],
                'data-line' => $meta['line'],
            ), $attribs);
        }
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if ($meta['errorCat']) {
                $attribs['class'][] = 'error-' . $meta['errorCat'];
            }
            if ($meta['uncollapse'] === false) {
                $attribs['data-uncollapse'] = false;
            }
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
        }
        return $this->debug->html->buildTag(
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
    protected function methodGroup(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if ($method === 'groupEnd') {
            return '</ul>' . "\n" . '</li>';
        }
        $args = $logEntry['args'];
        $meta = \array_merge(array(
            'argsAsParams' => true,
            'boldLabel' => true,
            'isFuncName' => false,
            'level' => null,
        ), $logEntry['meta']);
        $str = '';
        $label = \array_shift($args);
        if ($meta['isFuncName']) {
            $label = $this->markupIdentifier($label);
        }
        $labelClasses = \implode(' ', \array_keys(\array_filter(array(
            'font-weight-bold' => $meta['boldLabel'],
            'group-label' => true,
        ))));
        $levelClass = $meta['level']
            ? 'level-' . $meta['level']
            : null;
        $headerAppend = '';
        $headerInner = $label;
        if ($args) {
            foreach ($args as $k => $v) {
                $args[$k] = $this->dump($v);
            }
            $argStr = \implode(', ', $args);
            $headerInner .= $meta['argsAsParams']
                ? '(</span>' . $argStr . '<span class="' . $labelClasses . '">)'
                : ':';
            $headerAppend = $meta['argsAsParams']
                ? ''
                : ' ' . $argStr;
        }

        $classes = (array) $this->logEntryAttribs['class'];
        if ($method === 'group') {
            // groupCollapsed doesn't get expanded
            $classes[] = 'expanded';
        }
        $classes = \implode(' ', $classes);
        $classes = \str_replace('m_' . $method, 'm_group', $classes);
        $this->logEntryAttribs['class'] = $classes;
        $str = '<li' . $this->debug->html->buildAttribString($this->logEntryAttribs) . '>' . "\n";
        /*
            Header / label / toggle
        */
        $str .= $this->debug->html->buildTag(
            'div',
            array(
                'class' => array(
                    'group-header',
                    $levelClass,
                ),
            ),
            '<span class="' . $labelClasses . '">'
                . $headerInner
                . '</span>'
                . $headerAppend
        ) . "\n";
        /*
            Group open
        */
        $str .= '<ul' . $this->debug->html->buildAttribString(array(
            'class' => array(
                'group-body',
                $levelClass,
            ),
        )) . '>';
        return $str;
    }

    /**
     * Handle profile(End), table, & trace methods
     *
     * @param LogEntry $logEntry logEntry instance
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
            $meta['onBuildRow'][] = array($this, 'tableMarkupFunction');
        }
        if ($logEntry->getMeta('inclContext')) {
            $meta['onBuildRow'][] = array($this, 'tableAddContextRow');
        }
        return $this->debug->html->buildTag(
            'li',
            $this->logEntryAttribs,
            "\n" . $this->table->build($logEntry['args'][0], $meta) . "\n"
        );
    }

    /**
     * Coerce value to string
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
