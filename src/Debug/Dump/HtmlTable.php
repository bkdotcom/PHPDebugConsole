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

use bdk\Debug;
use bdk\Debug\Dump\Html;
use bdk\Debug\MethodTable;

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
     *                           'columns' : array of columns to display (defaults to all)
     *                           'totalCols' : array of column keys that will get totaled
     *
     * @return string
     */
    public function build($rows, $options = array())
    {
        $options = \array_merge(array(
            'attribs' => array(),
            'caption' => null,
            'columns' => array(),
            'totalCols' => array(),
        ), $options);
        if (\is_string($options['attribs'])) {
            $options['attribs'] = array(
                'class' => $options['attribs'],
            );
        }
        if ($this->debug->abstracter->isAbstraction($rows, 'object')) {
            $classname = $this->html->markupIdentifier(
                $rows['className'],
                'span',
                array(
                    'title' => $rows['phpDoc']['summary'] ?: null,
                )
            );
            $options['caption'] = \strlen($options['caption'])
                ? $options['caption'] . ' (' . $classname . ')'
                : $classname;
            if ($rows['traverseValues']) {
                $rows = $rows['traverseValues'];
            } else {
                $rows = \array_map(
                    function ($info) {
                        return $info['value'];
                    },
                    \array_filter($rows['properties'], function ($info) {
                        return !\in_array($info['visibility'], array('private', 'protected'));
                    })
                );
            }
        }
        $keys = $options['columns'] ?: $this->debug->methodTable->colKeys($rows);
        $keyIndex = $index = \array_search('__key', $keys);
        if ($keyIndex !== false) {
            unset($keys[$index]);
        }
        $this->tableInfo = array(
            'colClasses' => \array_fill_keys($keys, null),
            'haveObjRow' => false,
            'totals' => \array_fill_keys($options['totalCols'], null),
        );
        $tBody = '';
        foreach ($rows as $k => $row) {
            // row may be array or Traversable
            $tBody .= $this->buildRow($row, $keys, $k);
        }
        $tBody = \str_replace(' title=""', '', $tBody);
        if (!$this->tableInfo['haveObjRow']) {
            $tBody = \str_replace('<td class="classname"></td>', '', $tBody);
        }
        return $this->debug->utilities->buildTag(
            'table',
            $options['attribs'],
            "\n"
                . ($options['caption'] ? '<caption>' . $options['caption'] . '</caption>' . "\n" : '')
                . $this->buildHeader($keys)
                . '<tbody>' . "\n" . $tBody . '</tbody>' . "\n"
                . $this->buildFooter($keys)
        );
    }

    /**
     * Returns table's tfoot
     *
     * @param array $keys column header values (keys of array or property names)
     *
     * @return string
     */
    protected function buildFooter($keys)
    {
        $haveTotal = false;
        $cells = array();
        foreach ($keys as $key) {
            $colHasTotal = isset($this->tableInfo['totals'][$key]);
            $cells[] = $colHasTotal
                ? $this->html->dump(\round($this->tableInfo['totals'][$key], 6), true, 'td')
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
     * @param array $keys column header values (keys of array or property names)
     *
     * @return string
     */
    protected function buildHeader($keys)
    {
        $headers = array();
        foreach ($keys as $key) {
            $headers[$key] = $key === MethodTable::SCALAR
                ? 'value'
                : \htmlspecialchars($key);
            if ($this->tableInfo['colClasses'][$key]) {
                $headers[$key] .= ' ' . $this->html->markupIdentifier($this->tableInfo['colClasses'][$key]);
            }
        }
        return '<thead>' . "\n"
            . '<tr><th>&nbsp;</th>'
                . ($this->tableInfo['haveObjRow'] ? '<th>&nbsp;</th>' : '')
                . '<th>' . \implode('</th><th scope="col">', $headers) . '</th>'
            . '</tr>' . "\n"
            . '</thead>' . "\n";
    }

    /**
     * Returns table row
     *
     * @param mixed $row    should be array or object abstraction
     * @param array $keys   column keys
     * @param array $rowKey row key
     *
     * @return string
     */
    protected function buildRow($row, $keys, $rowKey)
    {
        $str = '';
        if (\is_array($row) && isset($row['__key'])) {
            $rowKey = $row['__key'];
            unset($row['__key']);
        }
        $values = $this->debug->methodTable->keyValues($row, $keys, $objInfo);
        $rowKeyParsed = $this->debug->utilities->parseTag($this->html->dump($rowKey));
        $str .= '<tr>';
        /*
            Output key
        */
        $str .= $this->debug->utilities->buildTag(
            'th',
            array(
                'class' => 't_key text-right ' . $rowKeyParsed['attribs']['class'],
                'scope' => 'row',
            ),
            $rowKeyParsed['innerhtml']
        );
        /*
            Output row's classname (if row is an object)
        */
        if ($objInfo['row']) {
            $str .= $this->html->markupIdentifier($objInfo['row']['className'], 'td', array(
                'title' => $objInfo['row']['phpDoc']['summary'] ?: null,
            ));
            $this->tableInfo['haveObjRow'] = true;
        } else {
            $str .= '<td class="classname"></td>';
        }
        /*
            Output values
        */
        foreach ($values as $v) {
            $str .= $this->html->dump($v, true, 'td');
        }
        $str .= '</tr>' . "\n";
        foreach (\array_keys($this->tableInfo['totals']) as $k) {
            $this->tableInfo['totals'][$k] += $values[$k];
        }
        foreach ($objInfo['cols'] as $k2 => $classname) {
            if ($this->tableInfo['colClasses'][$k2] === false) {
                // column values not of the same type
                continue;
            }
            if ($this->tableInfo['colClasses'][$k2] === null) {
                $this->tableInfo['colClasses'][$k2] = $classname;
            }
            if ($this->tableInfo['colClasses'][$k2] !== $classname) {
                $this->tableInfo['colClasses'][$k2] = false;
            }
        }
        return $str;
    }
}
