<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2024 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Group;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Table;
use bdk\Debug\Dump\Html\Value;
use bdk\Debug\LogEntry;

/**
 * Dump val as HTML
 *
 * @property HtmlTable $table lazy-loaded HtmlTable... only loaded if outputting a table
 */
class Html extends Base
{
    /** @var HtmlHelper helper class */
    public $helper;

    /** @var Debug[] Logged channels (channelName => Debug) */
    protected $channels = array();

    /** @var Group */
    protected $group;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /** @var HtmlTable */
    protected $lazyTable;

    /** @var array LogEntry meta attribs */
    protected $logEntryAttribs = array();

    /**
     * Constructor
     *
     * @param Debug $debug Debug instance
     */
    public function __construct(Debug $debug)
    {
        parent::__construct($debug);
        $this->group = new Group($this);
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
            'class' => ['m_' . $logEntry['method']],
            'data-channel' => $channelName !== $this->channelNameRoot
                ? $channelName
                : null,
            'data-detect-files' => $meta['detectFiles'],
            'data-icon' => $meta['icon'],
        ), $meta['attribs']);
        $html = parent::processLogEntry($logEntry);
        $html = \preg_replace('/ data-[-\w]+="null"/', '', $html);
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
        $type = $this->debug->abstracter->type->getType($val)[0];
        if ($type === Type::TYPE_STRING) {
            return $this->valDumper->string->dumpAsSubstitution($val, $opts);
        }
        if ($type === Type::TYPE_ARRAY) {
            $count = \count($val);
            return '<span class="t_keyword">array</span>'
                . '<span class="t_punct">(</span>' . $count . '<span class="t_punct">)</span>';
        }
        if ($type === Type::TYPE_OBJECT) {
            $opts['tagName'] = null;
            $toStr = (string) $val; // objects __toString or its classname
            return $toStr === $val['className']
                ? $this->valDumper->markupIdentifier($toStr, 'classname')
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
        return [$args, $meta];
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
            'class' => [],
            'role' => 'alert',
        ), $this->logEntryAttribs);
        $attribs['class'][] = 'alert-' . $meta['level'];
        $html = \count($args) === 1
            ? $this->valDumper->dump($args[0], array(
                'sanitize' => $meta['sanitizeFirst'],
                'tagName' => null, // don't wrap value span
                'visualWhiteSpace' => false,
            ))
            : $this->helper->buildArgString($args, $meta);
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
            $meta = \array_merge(array('evalLine' => null), $meta);
            $attribs = \array_merge(array(
                'data-evalLine' => $meta['evalLine'],
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
        return $this->group->build($logEntry, $this->logEntryAttribs);
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
                    'sortable' => $logEntry->getMeta('sortable'),
                    'table-bordered' => true,
                    'trace-context' => $logEntry->getMeta('inclContext'),
                ))),
            ),
            'caption' => null,
            'onBuildRow' => array(),
            'tableInfo' => array(),
        ), $logEntry['meta']);
        if ($logEntry['method'] === 'trace') {
            $meta['onBuildRow'][] = [$this->helper, 'tableMarkupFunction'];
        }
        if ($logEntry->getMeta('inclContext')) {
            $meta['onBuildRow'][] = [$this->helper, 'tableAddContextRow'];
        }
        return $this->html->buildTag(
            'li',
            $this->logEntryAttribs,
            "\n" . $this->table->build($logEntry['args'][0], $meta) . "\n"
        );
    }

    /**
     * Set default meta values on log entry
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
            'sanitize' => true,         // apply htmlspecialchars to args?
            'sanitizeFirst' => null,    // apply htmlspecialchars to first arg?  (defaults to sanitize value)
            'uncollapse' => null,
        ), $logEntry['meta']);
        if ($meta['sanitizeFirst'] === null) {
            $meta['sanitizeFirst'] = $meta['sanitize'];
        }
        $logEntry->setMeta($meta);
        return $meta;
    }
}
