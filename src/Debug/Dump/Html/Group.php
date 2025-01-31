<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     3.3
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Dump\Html as Dumper;
use bdk\Debug\LogEntry;

/**
 * Build output for group, groupCollapsed, & groupEnd
 */
class Group
{
	/** @var Dumper  */
    protected $dumper;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /** @var array LogEntry meta attribs */
    protected $logEntryAttribs = array();

    /**
     * Constructor
     *
     * @param Dumper $dumper html dumper
     */
    public function __construct(Dumper $dumper)
    {
        $this->dumper = $dumper;
        $this->html = $dumper->debug->html;
    }

    /**
     * Build output for group, groupCollapsed, & groupEnd
     *
     * @param LogEntry $logEntry        LogEntry instance
     * @param array    $logEntryAttribs tag attributes
     *
     * @return string
     */
	public function build(LogEntry $logEntry, array $logEntryAttribs)
	{
		$this->logEntryAttribs = $logEntryAttribs;
        $method = $logEntry['method'];
        if ($method === 'groupEnd') {
            return '</ul>' . "\n" . '</li>';
        }
        $meta = $this->prep($logEntry);

        $str = '<li' . $this->html->buildAttribString($this->logEntryAttribs) . '>' . "\n";
        $str .= $this->html->buildTag(
            'div',
            array(
                'class' => 'group-header',
            ),
            $this->header($logEntry['args'], $meta)
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
    private function prep(LogEntry $logEntry)
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
    private function header(array $args, array $meta)
    {
        $label = \array_shift($args);
        $label = $meta['isFuncName']
            ? $this->dumper->valDumper->markupIdentifier($label, 'method')
            : \preg_replace('#^<span class="t_string">(.+)</span>$#s', '$1', $this->dumper->valDumper->dump($label));

        $labelClasses = \implode(' ', \array_keys(\array_filter(array(
            'font-weight-bold' => $meta['boldLabel'],
            'group-label' => true,
        ))));

        if (!$args) {
			return '<span class="' . $labelClasses . '">' . $label . '</span>';
		}

		foreach ($args as $k => $v) {
			$args[$k] = $this->dumper->valDumper->dump($v);
		}
		$argStr = \implode(', ', $args);
        return $meta['argsAsParams']
        	? '<span class="' . $labelClasses . '">' . $label . '(</span>'
        		. $argStr
        		. '<span class="' . $labelClasses . '">)</span>'
        	: '<span class="' . $labelClasses . '">' . $label . ':</span> '
            	. $argStr;
    }
}
