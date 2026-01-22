<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.0
 */

namespace bdk\Debug\Dump;

use bdk\Debug;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html\Group;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value;
use bdk\Debug\LogEntry;

/**
 * Dump val as HTML
 *
 * @property Value $valDumper HTML value dumper
 */
class Html extends Base
{
    /** @var Helper helper class */
    public $helper;

    /** @var Debug[] Logged channels (channelName => Debug) */
    protected $channels = array();

    /** @var Group */
    protected $group;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

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
        $meta = $this->mergeMetaDefaults($logEntry);
        $channelKey = $logEntry->getChannelKey();
        // phpError channel is handled separately
        if (!isset($this->channels[$channelKey]) && $channelKey !== $this->channelKeyRoot . '.phpError') {
            $this->channels[$channelKey] = $logEntry->getSubject();
        }
        $this->logEntryAttribs = $this->debug->arrayUtil->mergeDeep(array(
            'class' => ['m_' . $logEntry['method']],
            'data-channel' => $channelKey !== $this->channelKeyRoot
                ? $channelKey
                : null,
            'data-icon' => $meta['icon'],
        ), $meta['attribs']);
        $html = parent::processLogEntry($logEntry);
        $html = \preg_replace('/ data-[-\w]+="null"/', '', $html);
        return $html . "\n";
    }

    /**
     * Coerce value to string
     *
     * @param mixed $val  value
     * @param array $opts $options passed to dump
     *
     * @return string
     */
    #[\Override]
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
                ? $this->valDumper->markupIdentifier($toStr, Type::TYPE_IDENTIFIER_CLASSNAME)
                : $this->valDumper->dump($toStr, $opts);
        }
        return $this->valDumper->dump($val);
    }

    /**
     * Get & reset logged channels
     *
     * Note:  may return empty array of nothing (or only errors) logged
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
     * Get value dumper
     *
     * @return Value
     */
    protected function initValDumper()
    {
        return new Value($this);
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
    #[\Override]
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
            $html = '<button type="button" class="close" data-dismiss="alert" aria-label="' . $this->debug->i18n->trans('word.close') . '">'
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
    #[\Override]
    protected function methodDefault(LogEntry $logEntry)
    {
        list($args, $meta) = $this->handleSubstitutions($logEntry);
        $append = !empty($meta['context'])
            ? $this->helper->buildContext($meta['context'], $meta['line'])
            : '';
        $argString = $this->helper->buildArgString($args, $meta);
        if (\in_array($logEntry['method'], ['profileEnd', 'table', 'trace'], true)) {
            $argString = "\n" . $argString . "\n";
        }
        return $this->html->buildTag(
            'li',
            $this->methodDefaultAttribs($logEntry),
            $argString . $append
        );
    }

    /**
     * Get method html attributes
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array
     */
    private function methodDefaultAttribs(LogEntry $logEntry)
    {
        $meta = $logEntry['meta'];
        $attribs = $this->logEntryAttribs;
        if (isset($meta['file']) && $logEntry->getChannelKey() !== $this->channelKeyRoot . '.phpError') {
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
        if ($meta['level']) {
            $attribs['class'][] = 'level-' . $meta['level'];
        }
        if ($meta['uncollapse'] === false) {
            $attribs['data-uncollapse'] = false;
        }
        return $attribs;
    }

    /**
     * Handle html output of group, groupCollapsed, & groupEnd
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    #[\Override]
    protected function methodGroup(LogEntry $logEntry)
    {
        return $this->group->build($logEntry, $this->logEntryAttribs);
    }

    /**
     * Handle profile(End), table, & trace methods
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return string
     */
    #[\Override]
    protected function methodTabular(LogEntry $logEntry)
    {
        return $this->methodDefault($logEntry);
    }

    /**
     * Set default meta values on log entry
     *
     * @param LogEntry $logEntry LogEntry instance
     *
     * @return array all meta values
     */
    private function mergeMetaDefaults(LogEntry $logEntry)
    {
        $meta = \array_merge(array(
            'attribs' => array(),
            'errorCat' => null,         // should only be applicable for error & warn methods
            'glue' => null,
            'icon' => null,
            'level' => null,
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
