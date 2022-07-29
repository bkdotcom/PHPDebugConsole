<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Table;
use bdk\Debug\Dump\Html\Value;
use bdk\Debug\LogEntry;

/**
 * Dump val as HTML
 *
 * @property HtmlTable $table lazy-loaded HtmlTable... only loaded if outputing a table
 */
class Html extends Base
{
    /** @var HtmlHelper helper class */
    public $helper;

    /** @var Debug[] Logged channels (channelName => Debug) */
    protected $channels = array();

    /** @var array LogEntry meta attribs */
    protected $logEntryAttribs = array();

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

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
        $this->helper = new Helper($this);
        $this->html = $debug->html;
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
        $this->valDumper->string->detectFiles = $meta['detectFiles'];
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
     * Coerce value to string
     *
     * Extends Base
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    public function substitutionAsString($val, $opts)
    {
        // function array dereferencing = php 5.4
        $type = $this->debug->abstracter->getType($val)[0];
        if ($type === Abstracter::TYPE_STRING) {
            return $this->valDumper->string->dumpAsSubstitution($val, $opts);
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
                ? $this->valDumper->markupIdentifier($toStr)
                : $this->valDumper->dump($toStr, $opts);
        }
        return $this->valDumper->dump($val);
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
     * Getter for this->table
     *
     * @return HtmlTable
     */
    protected function getTable()
    {
        if (!$this->lazyTable) {
            $this->lazyTable = new Table($this);
        }
        return $this->lazyTable;
    }

    /**
     * Get value dumper
     *
     * @return \bdk\Debug\Dump\BaseValue
     */
    protected function getValDumper()
    {
        if (!$this->valDumper) {
            $this->valDumper = new Value($this);
        }
        return $this->valDumper;
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
            $args[0] = $this->valDumper->dump($args[0], array(
                'sanitize' => $meta['sanitizeFirst'],
                'tagName' => null,
            ));
            $args = $this->substitution->process($args, array(
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
        $html = $this->valDumper->dump($args[0], array(
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
        $label = $meta['isFuncName']
            ? $this->valDumper->markupIdentifier($label, true)
            : \preg_replace('#^<span class="t_string">(.+)</span>$#s', '$1', $this->valDumper->dump($label));
        $labelClasses = \implode(' ', \array_keys(\array_filter(array(
            'font-weight-bold' => $meta['boldLabel'],
            'group-label' => true,
        ))));

        $headerAppend = '';

        if ($args) {
            foreach ($args as $k => $v) {
                $args[$k] = $this->valDumper->dump($v);
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
}
