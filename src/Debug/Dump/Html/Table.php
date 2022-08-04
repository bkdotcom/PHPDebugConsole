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

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Dump\Html as Dumper;

/**
 * build a table
 */
class Table
{
    protected $debug;
    protected $dumper;
    protected $options;

    /**
     * Constructor
     *
     * @param Dumper $dumper html dumper
     */
    public function __construct(Dumper $dumper)
    {
        $this->debug = $dumper->debug;
        $this->dumper = $dumper;
    }

    /**
     * Formats an array as a table
     *
     * @param mixed $rows    array of \Traversable or Abstraction
     * @param array $options options
     *                           'attribs' : key/val array (or string - interpreted as class value)
     *                           'caption' : optional caption
     *                           'tableInfo':
     *                               'columns' : list of columns info
     *
     * @return string
     */
    public function build($rows, $options = array())
    {
        $this->options = \array_merge(array(
            'attribs' => array(),
            'caption' => '',
            'onBuildRow' => null,   // callable (or array of callables)
            'tableInfo' => array(),
        ), $options);
        return $this->debug->html->buildTag(
            'table',
            $this->options['attribs'],
            "\n"
                . $this->buildCaption()
                . $this->buildHeader()
                . $this->buildbody($rows)
                . $this->buildFooter()
        );
    }

    /**
     * Builds table's body
     *
     * @param array $rows array of arrays or Traverssable
     *
     * @return string
     */
    protected function buildBody($rows)
    {
        $tBody = '';
        $this->options['onBuildRow'] = \is_callable($this->options['onBuildRow'])
            ? array( $this->options['onBuildRow'] )
            : (array) $this->options['onBuildRow'];
        foreach ($rows as $k => $row) {
            $rowInfo = \array_merge(
                array(
                    'class' => null,
                    'key' => null,
                    'summary' => null,
                ),
                isset($this->options['tableInfo']['rows'][$k])
                    ? $this->options['tableInfo']['rows'][$k]
                    : array()
            );
            $html = $this->buildRow($row, $rowInfo, $k);
            // $html = $this->onBuildRow($html, $row, $rowInfo, $k);
            $tBody .= $html;
        }
        $tBody = \str_replace(' title=""', '', $tBody);
        return '<tbody>' . "\n" . $tBody . '</tbody>' . "\n";
    }

    /**
     * Build table caption
     *
     * @return string
     */
    private function buildCaption()
    {
        $caption = \htmlspecialchars((string) $this->options['caption']);
        if (!$this->options['tableInfo']['class']) {
            return $caption
                ? '<caption>' . $caption . '</caption>' . "\n"
                : '';
        }
        $class = $this->dumper->valDumper->markupIdentifier(
            $this->options['tableInfo']['class'],
            false,
            'span',
            array(
                'title' => $this->options['tableInfo']['summary'] ?: null,
            )
        );
        $caption = $caption
            ? $caption . ' (' . $class . ')'
            : $class;
        return '<caption>' . $caption . '</caption>' . "\n";
    }

    /**
     * Builds table's tfoot
     *
     * @return string
     */
    protected function buildFooter()
    {
        $haveTotal = false;
        $cells = array();
        foreach ($this->options['tableInfo']['columns'] as $colInfo) {
            if (isset($colInfo['total']) === false) {
                $cells[] = '<td></td>';
                continue;
            }
            $totalVal = $colInfo['total'];
            if (\is_float($totalVal)) {
                $totalVal = \round($totalVal, 6);
            }
            $cells[] = $this->dumper->valDumper->dump($totalVal, array('tagName' => 'td'));
            $haveTotal = true;
        }
        if (!$haveTotal) {
            return '';
        }
        return '<tfoot>' . "\n"
            . '<tr><td>&nbsp;</td>'
                . ($this->options['tableInfo']['haveObjRow'] ? '<td>&nbsp;</td>' : '')
                . \implode('', $cells)
            . '</tr>' . "\n"
            . '</tfoot>' . "\n";
    }

    /**
     * Returns table's thead
     *
     * @return string
     */
    protected function buildHeader()
    {
        $labels = array();
        foreach ($this->options['tableInfo']['columns'] as $colInfo) {
            $label = $colInfo['key'];
            if (isset($colInfo['class'])) {
                $label .= ' ' . $this->dumper->valDumper->markupIdentifier($colInfo['class']);
            }
            $labels[] = $label;
        }
        return '<thead>' . "\n"
            . '<tr>'
                . ($this->options['tableInfo']['indexLabel']
                    ? '<th class="text-right">' . $this->options['tableInfo']['indexLabel'] . '</th>'
                    : '<th>&nbsp;</th>')
                . ($this->options['tableInfo']['haveObjRow']
                    ? '<th>&nbsp;</th>'
                    : '')
                . '<th scope="col">' . \implode('</th><th scope="col">', $labels) . '</th>'
            . '</tr>' . "\n"
            . '</thead>' . "\n";
    }

    /**
     * Returns table row
     *
     * @param mixed      $row     should be array or object abstraction
     * @param array      $rowInfo row info / meta
     * @param string|int $rowKey  row key
     *
     * @return string
     */
    protected function buildRow($row, $rowInfo, $rowKey)
    {
        $str = '';
        $rowKey = $rowInfo['key'] ?: $rowKey;
        $rowKeyParsed = $this->debug->html->parseTag($this->dumper->valDumper->dump($rowKey));
        $str .= '<tr>';
        /*
            Output key
        */
        $str .= $this->debug->html->buildTag(
            'th',
            $this->debug->arrayUtil->mergeDeep($rowKeyParsed['attribs'], array(
                'class' => array('t_key', 'text-right'),
                'scope' => 'row',
            )),
            $rowKeyParsed['innerhtml']
        );
        /*
            Output row's classname
        */
        if ($this->options['tableInfo']['haveObjRow']) {
            $str .= $rowInfo['class']
                ? $this->dumper->valDumper->markupIdentifier($rowInfo['class'], false, 'td', array(
                    'title' => $rowInfo['summary'] ?: null,
                ))
                : '<td class="t_undefined"></td>';
        }
        /*
            Output values
        */
        foreach ($row as $v) {
            $str .= $this->dumper->valDumper->dump($v, array('tagName' => 'td'));
        }
        $str .= '</tr>' . "\n";
        return $this->onBuildRow($str, $row, $rowInfo, $rowKey);
    }

    /**
     * Call any onBuildRow callbacks
     *
     * @param string     $html    built row
     * @param mixed      $row     should be array or object abstraction
     * @param array      $rowInfo row info / meta
     * @param string|int $rowKey  row key
     *
     * @return string html fragment
     */
    private function onBuildRow($html, $row, $rowInfo, $rowKey)
    {
        foreach ($this->options['onBuildRow'] as $callable) {
            if (\is_callable($callable)) {
                $html = $callable($html, $row, $rowInfo, $rowKey);
            }
        }
        return $html;
    }
}
