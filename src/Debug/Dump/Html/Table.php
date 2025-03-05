<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug\Dump\Html as Dumper;

/**
 * build a table
 */
class Table
{
    /** @var Dumper */
    protected $debug;

    /** @var Dumper */
    protected $dumper;

    /** @var array<string,mixed> */
    protected $options;

    /** @var array<string,mixed> */
    private $optionsDefault = array(
        'attribs' => array(),
        'caption' => '',
        'onBuildRow' => null,   // callable (or array of callables)
        'tableInfo' => array(
            'class' => null,  // class name of table object
            'columns' => array(),
            'commonRowInfo' => array(
                'attribs' => array(),
                'class' => null,
                'key' => null,
                'summary' => '',
            ),
            'haveObjRow' => false,
            'indexLabel' => null,
            'rows' => array(),
            'summary' => '', // title attr on class
        ),
    );


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
        $this->buildInitOptions($options);

        return $this->debug->html->buildTag(
            'table',
            $this->options['attribs'],
            "\n"
                . $this->buildCaption()
                . $this->buildHeader()
                . $this->buildBody($rows)
                . $this->buildFooter()
        );
    }

    /**
     * Initialize options
     *
     * @param array $options table options and info
     *
     * @return void
     */
    private function buildInitOptions(array $options)
    {
        $this->options = \array_replace_recursive($this->optionsDefault, $options);

        foreach ($this->options['tableInfo']['columns'] as $k => $colInfo) {
            $this->options['tableInfo']['columns'][$k] = \array_merge(array(
                'attribs' => array(),
                'class' => null,
                'falseAs' => null,
                'key' => '',
                'total' => null,
                'trueAs' => null,
            ), $colInfo);
        }
    }

    /**
     * Builds table's body
     *
     * @param array $rows array of arrays or Traversable
     *
     * @return string
     */
    protected function buildBody($rows)
    {
        $tBody = '';
        $this->options['onBuildRow'] = \is_callable($this->options['onBuildRow'])
            ? [$this->options['onBuildRow']]
            : (array) $this->options['onBuildRow'];
        foreach ($rows as $k => $row) {
            $rowInfo = \array_merge(
                $this->options['tableInfo']['commonRowInfo'],
                isset($this->options['tableInfo']['rows'][$k])
                    ? $this->options['tableInfo']['rows'][$k]
                    : array()
            );
            $tBody .= $this->buildRow($row, $rowInfo, $k);
        }
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
            'className',
            'span',
            array(
                'title' => $this->options['tableInfo']['summary'],
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
        $cells = \array_map(function ($colInfo) use (&$haveTotal) {
            if (isset($colInfo['total']) === false) {
                return '<td></td>';
            }
            $haveTotal = true;
            $totalVal = $colInfo['total'];
            if (\is_float($totalVal)) {
                $totalVal = \round($totalVal, 6);
            }
            return $this->dumper->valDumper->dump($totalVal, array(
                'attribs' => $colInfo['attribs'],
                'tagName' => 'td',
            ));
        }, $this->options['tableInfo']['columns']);
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
            if (!empty($colInfo['class'])) {
                $label .= ' ' . $this->dumper->valDumper->markupIdentifier($colInfo['class'], 'className');
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
    protected function buildRow($row, array $rowInfo, $rowKey)
    {
        $str = '';
        $rowKey = $rowInfo['key'] ?: $rowKey;
        $str .= '<tr' . $this->debug->html->buildAttribString($rowInfo['attribs']) . '>';
        $str .= $this->buildRowKey($rowKey);
        /*
            Output row's classname (if row is an object)
        */
        if ($this->options['tableInfo']['haveObjRow']) {
            $str .= $rowInfo['class']
                ? $this->dumper->valDumper->markupIdentifier($rowInfo['class'], 'className', 'td', array(
                    'title' => $rowInfo['summary'],
                ))
                : '<td class="t_undefined"></td>';
        }
        /*
            Output values
        */
        $str .= $this->buildRowCells($row);
        $str .= '</tr>';
        return $this->onBuildRow($str, $row, $rowInfo, $rowKey) . "\n";
    }

    /**
     * Build the row's value cells
     *
     * @param mixed $row should be array or object abstraction
     *
     * @return string
     */
    private function buildRowCells($row)
    {
        $cells = \array_map(function ($val, $i) {
            $colInfo = $this->options['tableInfo']['columns'][$i];
            $td = $this->dumper->valDumper->dump($val, array(
                'attribs' => $colInfo['attribs'],
                'tagName' => 'td',
            ));
            if ($val === true && $colInfo['trueAs'] !== null) {
                $td = \str_replace('>true<', '>' . $colInfo['trueAs'] . '<', $td);
            } elseif ($val === false && $colInfo['falseAs'] !== null) {
                $td = \str_replace('>false<', '>' . $colInfo['falseAs'] . '<', $td);
            }
            return $td;
        }, \array_values($row), \range(0, \count($row) - 1));
        return \implode('', $cells);
    }

    /**
     * Build row's key/index th tag
     *
     * @param string|int $rowKey Row's index
     *
     * @return string
     */
    private function buildRowKey($rowKey)
    {
        $rowKeyParsed = $this->debug->html->parseTag($this->dumper->valDumper->dump($rowKey));
        return $this->debug->html->buildTag(
            'th',
            $this->debug->arrayUtil->mergeDeep($rowKeyParsed['attribs'], array(
                'class' => ['t_key', 'text-right'],
                'scope' => 'row',
            )),
            $rowKeyParsed['innerhtml']
        );
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
