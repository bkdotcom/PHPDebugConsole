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

namespace bdk\Debug\Dump;

use bdk\Debug\LogEntry;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\AbstractObject;

/**
 * Dump val as HTML
 *
 * @property HtmlObject $object lazy-loaded HtmlObject... only loaded if dumping an object
 * @property HtmlTable  $table  lazy-loaded HtmlTable... only loaded if outputing a table
 */
class Html extends Base
{

    protected $argAttribs = array();
    protected $channels = array();
    protected $detectFiles = false;
    protected $logEntryAttribs = array();

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
        $absAttribs = $val instanceof Abstraction
            ? $val['attribs']
            : array();
        $val = parent::dump($val, $opts);
        if ($tagName && !\in_array($this->dumpType, array('recursion'))) {
            $argAttribs = $this->debug->utilities->arrayMergeDeep(
                array(
                    'class' => array(
                        't_' . $this->dumpType,
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
     * @param mixed  $val     classname or classname(::|->)name (method/property/const)
     * @param string $tagName ("span") html tag to use
     * @param array  $attribs (optional) additional html attributes
     *
     * @return string
     */
    public function markupIdentifier($val, $tagName = 'span', $attribs = array())
    {
        $classname = '';
        $operator = '::';
        $identifier = '';
        $regex = '/^(.+)(::|->)(.+)$/';
        if ($val instanceof Abstraction) {
            $value = $val['value'];
            if (\is_array($value)) {
                list($classname, $identifier) = $value;
            } else {
                if (\preg_match($regex, $value, $matches)) {
                    $classname = $matches[1];
                    $operator = $matches[2];
                    $identifier = $matches[3];
                } else {
                    $identifier = $value;
                }
            }
        } elseif (\preg_match($regex, $val, $matches)) {
            $classname = $matches[1];
            $operator = $matches[2];
            $identifier = $matches[3];
        } else {
            $classname = $val;
        }
        $operator = '<span class="t_operator">' . \htmlspecialchars($operator) . '</span>';
        if ($classname) {
            $idx = \strrpos($classname, '\\');
            if ($idx) {
                $classname = '<span class="namespace">' . \substr($classname, 0, $idx + 1) . '</span>'
                    . \substr($classname, $idx + 1);
            }
            $classname = $this->debug->utilities->buildTag(
                $tagName,
                \array_merge(array(
                    'class' => 'classname',
                ), $attribs),
                $classname
            );
        } else {
            $operator = '';
        }
        if ($identifier) {
            $identifier = '<span class="t_identifier">' . $identifier . '</span>';
        } else {
            $operator = '';
        }
        return \implode($operator, array($classname, $identifier));
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
            'bool', 'int', 'float', 'string',
            // compound
            'array', 'object', 'callable', 'iterable',
            // "special"
            'resource', 'null',
        );
        $typesOther = array(
            '$this','false','mixed','static','self','true','void',
        );
        $typesPrimative = \array_merge($phpPrimatives, $typesOther);
        $types = \preg_split('#\s*\|\s*#', $type);
        foreach ($types as $i => $type) {
            $isArray = false;
            if (\substr($type, -2) == '[]') {
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
            $type = $this->debug->utilities->buildtag(
                'span',
                $attribs,
                $types
            );
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
            'icon' => null,
            'sanitize' => true,         // apply htmlspecialchars (to non-first arg)?
            'sanitizeFirst' => null,    // if null, use meta.sanitize
        ), $logEntry['meta']);
        if ($meta['sanitizeFirst'] === null) {
            $meta['sanitizeFirst'] = $meta['sanitize'];
        }
        $logEntry->setMeta($meta);
        $channelName = $logEntry->getChannel();
        // phpError channel is handled separately
        if (!isset($this->channels[$channelName]) && $channelName !== 'phpError') {
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
        if ($method == 'alert') {
            $str = $this->methodAlert($logEntry);
        } elseif (\in_array($method, array('group', 'groupCollapsed', 'groupEnd'))) {
            $str = $this->methodGroup($logEntry);
        } elseif (\in_array($method, array('profileEnd','table','trace'))) {
            $str = $this->methodTabular($logEntry);
        } else {
            $str = $this->methodDefault($logEntry);
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
        if (\is_string($args[0])) {
            if (\preg_match('/[=:] ?$/', $args[0])) {
                // first arg ends with "=" or ":"
                $glueAfterFirst = false;
                $args[0] = \rtrim($args[0]) . ' ';
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
            return $args[0] . \implode($glue, \array_slice($args, 1));
        } else {
            return \implode($glue, $args);
        }
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
                . '<span class="t_punct">()</span>';
        } else {
            $showKeys = $this->debug->getCfg('arrayShowListKeys') || !$this->debug->utilities->isList($array);
            $html = '<span class="t_keyword">array</span>'
                . '<span class="t_punct">(</span>' . "\n";
            if ($showKeys) {
                $html .= '<span class="array-inner">' . "\n";
                foreach ($array as $key => $val) {
                    $html .= "\t" . '<span class="key-value">'
                            . '<span class="t_key' . (\is_int($key) ? ' t_int' : '') . '">'
                                . $this->dump($key, true, false) // don't wrap it
                            . '</span>'
                            . '<span class="t_operator">=&gt;</span>'
                            . $this->dump($val)
                        . '</span>' . "\n";
                }
                $html .= '</span>';
            } else {
                // display as list
                $html .= '<ul class="array-inner list-unstyled">' . "\n";
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
        $this->argAttribs['title'] = $abs['value']
            ? 'value: ' . $this->debug->dumpText->dump($abs['value'])
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
        $dump = $this->object->dump($abs);
        /*
            Were we debugged from inside or outside of the object?
        */
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
            if ($this->detectFiles && !\preg_match('#(://|[\r\n\x00])#', $val) && \is_file($val)) {
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
     * Get & reset logged channels
     *
     * @return Debug[]
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
        $this->object = new HtmlObject($this);
        return $this->object;
    }

    /**
     * Getter for this->table
     *
     * @return HtmlTable
     */
    protected function getTable()
    {
        $this->table = new HtmlTable($this);
        return $this->table;
    }

    /**
     * Handle alert method
     *
     * @param LogEntry $logEntry logEntry instance
     *
     * @return array array($method, $args)
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
        return $this->debug->utilities->buildTag('div', $attribs, $html);
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
        ), $logEntry['meta']);
        $attribs = $this->logEntryAttribs;
        if (isset($meta['file']) && $logEntry->getChannel() !== 'phpError') {
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
            $argCount = \count($args);
            if ($argCount > 1 && \is_string($args[0])) {
                $args[0] = $this->dump($args[0], array(
                    'sanitize' => $meta['sanitizeFirst'],
                ), false);
                $args = $this->processSubstitutions($args, array(
                    'replace' => true,
                    'sanitize' => $meta['sanitize'],
                    'style' => true,
                ));
                $meta['sanitizeFirst'] = false;
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
    protected function methodGroup(LogEntry $logEntry)
    {
        $method = $logEntry['method'];
        if ($method == 'groupEnd') {
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
        foreach ($args as $k => $v) {
            $args[$k] = $this->dump($v);
        }
        $argStr = \implode(', ', $args);
        if (!$argStr) {
            $headerStr = '<span class="' . $labelClasses . '">' . $label . '</span>';
        } elseif ($meta['argsAsParams']) {
            $headerStr = '<span class="' . $labelClasses . '">' . $label . '(</span>'
                . $argStr
                . '<span class="' . $labelClasses . '">)</span>';
        } else {
            $headerStr = '<span class="' . $labelClasses . '">' . $label . ':</span> '
                . $argStr;
        }
        $this->logEntryAttribs['class'] = \str_replace('m_' . $method, 'm_group', $this->logEntryAttribs['class']);
        $str = '<li' . $this->debug->utilities->buildAttribString($this->logEntryAttribs) . '>' . "\n";
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
            $headerStr
        ) . "\n";
        /*
            Group open
        */
        $str .= '<ul' . $this->debug->utilities->buildAttribString(array(
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
                    . $this->table->build(
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
                    ) . "\n"
                : $this->buildArgString($args, $meta)
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
        if ($type == 'string') {
            // we do NOT wrap in <span>...  log('<a href="%s">link</a>', $url);
            $val = $this->dump($val, $opts, false);
        } elseif ($type == 'array') {
            $count = \count($val);
            $val = '<span class="t_keyword">array</span>'
                . '<span class="t_punct">(</span>' . $count . '<span class="t_punct">)</span>';
        } elseif ($type == 'object') {
            $toStr = AbstractObject::toString($val);
            if ($toStr) {
                $val = $this->dump($toStr, $opts, false);
            } else {
                $val = $this->markupIdentifier($val['className']);
            }
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
