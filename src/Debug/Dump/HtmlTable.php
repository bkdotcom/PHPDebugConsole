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

use bdk\Debug\Dump\Html;

/**
 * build a table
 */
class HtmlTable
{

    protected $debug;
    protected $html;
    protected $tableInfo;

    /**
     * Constructor
     *
     * @param Html $html html dumper
     */
    public function __construct(Html $html)
    {
        $this->debug = $html->debug;
        $this->html = $html;
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
        $options = \array_merge(array(
            'attribs' => array(),
            'caption' => '',
            'onBuildRow' => null,   // callable (or array of callables)
            'tableInfo' => array(),
        ), $options);
        $this->tableInfo = $options['tableInfo'];
        $caption = \htmlspecialchars($options['caption']);
        if ($this->tableInfo['class']) {
            $class = $this->html->markupIdentifier(
                $this->tableInfo['class'],
                'span',
                array(
                    'title' => $this->tableInfo['summary'] ?: null,
                )
            );
            $caption = $caption
                ? $caption . ' (' . $class . ')'
                : $class;
        }
        return $this->debug->html->buildTag(
            'table',
            $options['attribs'],
            "\n"
                . ($caption
                    ? '<caption>' . $caption . '</caption>' . "\n"
                    : '')
                . $this->buildHeader($this->tableInfo['columns'])
                . $this->buildbody($rows, $options)
                . $this->buildFooter($this->tableInfo['columns'])
        );
    }

    /**
     * Builds table's body
     *
     * @param array $rows    array of arrays or Traverssable
     * @param array $options options
     *
     * @return string
     */
    protected function buildBody($rows, $options)
    {
        $tBody = '';
        $options['onBuildRow'] = \is_callable($options['onBuildRow'])
            ? array( $options['onBuildRow'] )
            : (array) $options['onBuildRow'];
        foreach ($rows as $k => $row) {
            $rowInfo = \array_merge(
                array(
                    'class' => null,
                    'key' => null,
                    'summary' => null,
                ),
                isset($this->tableInfo['rows'][$k])
                    ? $this->tableInfo['rows'][$k]
                    : array()
            );
            $html = $this->buildRow($row, $rowInfo, $k);
            foreach ($options['onBuildRow'] as $callable) {
                if (\is_callable($callable)) {
                    $html = $callable($html, $row, $rowInfo, $k);
                }
            }
            $tBody .= $html;
        }
        $tBody = \str_replace(' title=""', '', $tBody);
        return '<tbody>' . "\n" . $tBody . '</tbody>' . "\n";
    }

    /**
     * Builds table's tfoot
     *
     * @param array $columns column info
     *
     * @return string
     */
    protected function buildFooter($columns)
    {
        $haveTotal = false;
        $cells = array();
        foreach ($columns as $info) {
            $colHasTotal = isset($info['total']);
            $totalVal = $colHasTotal
                ? $info['total']
                : null;
            if (\is_float($totalVal)) {
                $totalVal = \round($totalVal, 6);
            }
            $cells[] = $colHasTotal
                ? $this->html->dump($totalVal, array(), 'td')
                : '<td></td>';
            $haveTotal = $haveTotal || $colHasTotal;
        }
        if (!$haveTotal) {
            return '';
        }
        return '<tfoot>' . "\n"
            . '<tr><td>&nbsp;</td>'
                . ($this->tableInfo['haveObjRow'] ? '<td>&nbsp;</td>' : '')
                . \implode('', $cells)
            . '</tr>' . "\n"
            . '</tfoot>' . "\n";
    }

    /**
     * Returns table's thead
     *
     * @param array $columns column info
     *
     * @return string
     */
    protected function buildHeader($columns)
    {
        $labels = array();
        foreach ($columns as $colInfo) {
            $label = $colInfo['key'];
            if (isset($colInfo['class'])) {
                $label .= ' ' . $this->html->markupIdentifier($colInfo['class']);
            }
            $labels[] = $label;
        }
        return '<thead>' . "\n"
            . '<tr>'
                . ($this->tableInfo['indexLabel'] ? '<th class="text-right">' . $this->tableInfo['indexLabel'] . '</th>' : '<th>&nbsp;</th>')
                . ($this->tableInfo['haveObjRow'] ? '<th>&nbsp;</th>' : '')
                . '<th>' . \implode('</th><th scope="col">', $labels) . '</th>'
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
        $rowKeyParsed = $this->debug->html->parseTag($this->html->dump($rowKey));
        $str .= '<tr>';
        /*
            Output key
        */
        $str .= $this->debug->html->buildTag(
            'th',
            $this->debug->utility->arrayMergeDeep($rowKeyParsed['attribs'], array(
                'class' => array('t_key', 'text-right'),
                'scope' => 'row',
            )),
            $rowKeyParsed['innerhtml']
        );
        /*
            Output row's classname
        */
        if ($this->tableInfo['haveObjRow']) {
            $str .= $rowInfo['class']
                ? $this->html->markupIdentifier($rowInfo['class'], 'td', array(
                    'title' => $rowInfo['summary'] ?: null,
                ))
                : '<td class="t_undefined"></td>';
        }
        /*
            Output values
        */
        foreach ($row as $v) {
            $str .= $this->html->dump($v, array(), 'td');
        }
        $str .= '</tr>' . "\n";
        return $str;
    }
}
