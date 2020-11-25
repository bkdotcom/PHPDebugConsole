<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
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
    protected $detectFiles = false;
    protected $logEntryAttribs = array();
    protected $valAttribs = array();
    protected $valAttribsStack = array();

    /**
     * Dump value as html
     *
     * @param mixed             $val     value to dump
     * @param array             $opts    options for string values
     *                                     addQuotes, sanitize, visualWhitespace
     * @param string|false|null $tagName (span) tag to wrap value in (or false)
     *
     * @return string
     */
    public function dump($val, $opts = array(), $tagName = '__default__')
    {
        $this->valAttribs = array(
            'class' => array(),
            'title' => null,
        );
        $absAttribs = $val instanceof Abstraction
            ? $val['attribs']
            : array();
        $val = parent::dump($val, $opts);
        if ($tagName === '__default__') {
            $tagName = 'span';
            if ($this->dumpType === Abstracter::TYPE_OBJECT) {
                $tagName = 'div';
            } elseif ($this->dumpType === Abstracter::TYPE_RECURSION) {
                $tagName = null;
            }
        }
        if ($tagName) {
            $valAttribs = $this->debug->utility->arrayMergeDeep(
                array(
                    'class' => array(
                        't_' . $this->dumpType,
                        $this->dumpTypeMore,
                    ),
                ),
                $this->valAttribs
            );
            if ($absAttribs) {
                $absAttribs['class'] = isset($absAttribs['class'])
                    ? (array) $absAttribs['class']
                    : array();
                $valAttribs = $this->debug->utility->arrayMergeDeep(
                    $valAttribs,
                    $absAttribs
                );
            }
            $val = $this->debug->html->buildTag($tagName, $valAttribs, $val);
        }
        $this->valAttribs = array();
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
                \array_merge(array(
                    'class' => 'classname',
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
        $types = \preg_split('#\s*\|\s*#', $type);
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
        $str = '';
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
        if (!isset($this->channels[$channelName]) && $channelName !== 'general.phpError') {
            $this->channels[$channelName] = $logEntry->getSubject();
        }
        $this->detectFiles = $meta['detectFiles'];
        $this->logEntryAttribs = \array_merge(array(
            'class' => '',
            'data-channel' => $channelName !== $this->channelNameRoot
                ? $channelName
                : null,
            'data-detect-files' => $meta['detectFiles'],
            'data-icon' => $meta['icon'],
        ), $meta['attribs']);
        $this->logEntryAttribs['class'] .= ' m_' . $method;
        $str = parent::processLogEntry($logEntry);
        $str = \strtr($str, array(
            ' data-channel="null"' => '',
            ' data-detect-files="null"' => '',
            ' data-icon="null"' => '',
        ));
        return $str . "\n";
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
        $glue = ', ';
        $glueAfterFirst = true;
        if (\count($args) === 0) {
            return '';
        }
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]) . ' ';
            } elseif (\count($args) === 2) {
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
        $glue = $meta['glue'] ?: $glue;
        if ($glueAfterFirst === false) {
            return $args[0] . \implode($glue, \array_slice($args, 1));
        }
        return \implode($glue, $args);
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
        ), $this->valOpts);
        if ($opts['expand'] !== null) {
            $this->valAttribs['data-expand'] = $opts['expand'];
        }
        if ($opts['asFileTree']) {
            $this->valAttribs['class'][] = 'array-file-tree';
        }
        $showKeys = $opts['showListKeys'] || !$this->debug->utility->arrayIsList($array);
        $this->valAttribsStack[] = $this->valAttribs;
        $html = '<span class="t_keyword">array</span>'
            . '<span class="t_punct">(</span>' . "\n"
            . '<ul class="array-inner list-unstyled">' . "\n";
        foreach ($array as $key => $val) {
            $html .= $showKeys
                ? "\t" . '<li>'
                    . '<span class="t_key' . (\is_int($key) ? ' t_int' : '') . '">'
                        . $this->dump($key, array(), null) // don't wrap it
                    . '</span>'
                    . '<span class="t_operator">=&gt;</span>'
                    . $this->dump($val)
                . '</li>' . "\n"
                : "\t" . $this->dump($val, array(), 'li');
        }
        $html .= '</ul>'
            . '<span class="t_punct">)</span>';
        $this->valAttribs = \array_pop($this->valAttribsStack);
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
        $this->valAttribs['title'] = $abs['value']
            ? 'value: ' . $this->debug->getDump('text')->dump($abs['value'])
            : null;
        return $this->markupIdentifier($abs['name']);
    }

    /**
     * Dump float value
     *
     * @param float $val float value
     *
     * @return float
     */
    protected function dumpFloat($val)
    {
        $date = $this->checkTimestamp($val);
        if ($date) {
            $this->valAttribs['class'][] = 'timestamp';
            $this->valAttribs['title'] = $date;
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
        $this->valAttribs['data-accessible'] = $abs['scopeClass'] === $abs['className']
            ? 'private'
            : 'public';
        $this->valAttribsStack[] = $this->valAttribs;
        $html = $this->object->dump($abs);
        $this->valAttribs = \array_pop($this->valAttribsStack);
        return $html;
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
     * @param string      $val string value
     * @param Abstraction $abs (optional) full abstraction
     *
     * @return string
     */
    protected function dumpString($val, Abstraction $abs = null)
    {
        if (\is_numeric($val)) {
            $date = $this->checkTimestamp($val);
            if ($date) {
                $this->valAttribs['class'][] = 'timestamp';
                $this->valAttribs['title'] = $date;
            }
        }
        if ($this->detectFiles && $this->debug->utility->isFile($val)) {
            $this->valAttribs['data-file'] = true;
        }
        $val = $this->debug->utf8->dump($val, array(
            'sanitizeNonBinary' => $this->valOpts['sanitize'],
            'useHtml' => true,
        ));
        if ($abs && $abs['strlen']) {
            $val .= '<span class="maxlen">&hellip; ' . ($abs['strlen'] - \strlen($val)) . ' more bytes (not logged)</span>';
        }
        if ($this->valOpts['visualWhiteSpace']) {
            $val = $this->visualWhiteSpace($val);
        }
        if (!$this->valOpts['addQuotes']) {
            $this->valAttribs['class'][] = 'no-quotes';
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
        $attribs = \array_merge($this->logEntryAttribs, array(
            'class' => 'alert-' . $meta['level'] . ' ' . $this->logEntryAttribs['class'],
            'role' => 'alert',
        ));
        $html = $this->dump(
            $logEntry['args'][0],
            array(
                'sanitize' => $meta['sanitizeFirst'],
                'visualWhiteSpace' => false,
            ),
            false // don't wrap in span
        );
        if ($meta['dismissible']) {
            $attribs['class'] .= ' alert-dismissible';
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
        if (isset($meta['file']) && $logEntry->getChannelName() !== 'general.phpError') {
            // PHP errors will have file & line as one of the arguments
            //    so no need to store file & line as data args
            $attribs = \array_merge(array(
                'data-file' => $meta['file'],
                'data-line' => $meta['line'],
            ), $attribs);
        }
        if (\in_array($method, array('assert','clear','error','info','log','warn'))) {
            if ($meta['errorCat']) {
                $attribs['class'] .= ' error-' . $meta['errorCat'];
            }
            if ($meta['uncollapse'] === false) {
                $attribs['data-uncollapse'] = false;
            }
            if ($this->containsSubstitutions($logEntry)) {
                $args[0] = $this->dump($args[0], array(
                    'sanitize' => $meta['sanitizeFirst'],
                ), null);
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
            'group-label' => true,
            'group-label-bold' => $meta['boldLabel'],
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
        $meta = $this->debug->utility->arrayMergeDeep(array(
            'attribs' => array(
                'class' => array(
                    'table-bordered',
                    $logEntry->getMeta('sortable') ? 'sortable' : null,
                    $logEntry->getMeta('inclContext') ? 'trace-context' : null,
                ),
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
            // we do NOT wrap in <span>...  log('<a href="%s">link</a>', $url);
            return $this->dump($val, $opts, null);
        }
        if ($type === Abstracter::TYPE_ARRAY) {
            $count = \count($val);
            return '<span class="t_keyword">array</span>'
                . '<span class="t_punct">(</span>' . $count . '<span class="t_punct">)</span>';
        }
        if ($type === Abstracter::TYPE_OBJECT) {
            $toStr = (string) $val; // objects __toString or its classname
            return $toStr === $val['className']
                ? $this->markupIdentifier($toStr)
                : $this->dump($toStr, $opts, null);
        }
        return $this->dump($val);
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
        $str = \str_replace("\t", '<span class="ws_t">' . "\t" . '</span>', $str);
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
        $search = array("\r","\n");
        $replace = array('<span class="ws_r"></span>','<span class="ws_n"></span>' . "\n");
        return \str_replace($search, $replace, $matches[1]);
    }
}
