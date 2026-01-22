<?php

/**
 * @package   bdk/debug
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2025 Brad Kent
 * @since     2.3
 */

namespace bdk\Debug\Dump\Html;

use bdk\Debug;
use bdk\Debug\Abstraction\Abstracter;
use bdk\Debug\Abstraction\Abstraction;
use bdk\Debug\Abstraction\Type;
use bdk\Debug\Dump\Html as Dumper;
use bdk\Debug\Dump\Html\Helper;
use bdk\Debug\Dump\Html\Value as ValDumper;
use bdk\Table\Table as BdkTable;
use bdk\Table\TableCell;
use bdk\Table\TableRow;

/**
 * build a table
 */
class Table
{
    /** @var \bdk\Debug */
    protected $debug;

    /** @var Dumper */
    protected $dumper;

    /** @var Helper helper class */
    protected $helper;

    /** @var \bdk\Debug\Utility\Html */
    protected $html;

    /** @var ValDumper */
    protected $valDumper;

    /** @var int|null */
    private $classColumnIndex;

    /** @var BdkTable|null */
    private $table;

    /**
     * Constructor
     *
     * @param Dumper $dumper Html dumper
     * @param Helper $helper Html dump helpers
     */
    public function __construct(Dumper $dumper, Helper $helper)
    {
        $this->debug = $dumper->debug;
        $this->dumper = $dumper;
        $this->helper = $helper;
        $this->html = $this->debug->html;
        $this->valDumper = $dumper->valDumper;
    }

    /**
     * Dump table structure
     *
     * @param Abstraction $abs Table abstraction
     *
     * @return string
     */
    public function dump(Abstraction $abs)
    {
        $data = $abs->getValues();
        $table = new BdkTable($data);
        $classes = \array_keys(\array_filter(array(
            'sortable' => $table->getMeta('sortable'),
            'table-bordered' => true,
            'trace-context' => $table->getMeta('inclContext'), // only applies for trace tables
        )));
        $table->addClass($classes);
        $this->table = $table;

        $this->updateCaption();
        $this->setClassColumnIndex();

        if ($table->getMeta('inclContext')) {
            $this->addContextRows($table);
        }

        TableCell::setValDumper(function (TableCell $tableCell) {
            return $this->valDumper($tableCell);
        });
        return $table->getOuterHtml();
    }

    /**
     * Insert new table rows fot context
     *
     * @param BdkTable $table Table instance
     *
     * @return void
     */
    private function addContextRows(BdkTable $table)
    {
        $rows = $table->getRows();
        $table->setRows([]);
        foreach ($rows as $i => $row) {
            $table->appendRow($row);
            if ($row->getMeta('context') === null) {
                continue;
            }
            $contextRow = $this->buildContextRow($row, $i === 0);
            $table->appendRow($contextRow);
        }
    }

    /**
     * Dump context arguments
     *
     * @param string|array $args Arguments from backtrace
     *
     * @return string
     */
    private function buildContextArguments($args)
    {
        if (\is_array($args) === false || \count($args) === 0) {
            return '';
        }
        $crateRawWas = $this->dumper->crateRaw;
        $this->dumper->crateRaw = true;
        // set maxDepth for args
        $maxDepthBak = $this->debug->getCfg('maxDepth');
        if ($maxDepthBak > 0) {
            $this->debug->setCfg('maxDepth', $maxDepthBak + 1, Debug::CONFIG_NO_PUBLISH);
        }
        $args = '<hr />Arguments = ' . $this->valDumper->dump($args);
        $this->debug->setCfg('maxDepth', $maxDepthBak, Debug::CONFIG_NO_PUBLISH | Debug::CONFIG_NO_RETURN);
        $this->dumper->crateRaw = $crateRawWas;
        return $args;
    }

    /**
     * Create new TableRow containing trace context
     *
     * @param TableRow $row      TableRow instance
     * @param bool     $expanded Whether row should be initially expanded
     *
     * @return TableRow
     */
    private function buildContextRow(TableRow $row, $expanded)
    {
        if ($expanded) {
            $row->addClass('expanded', $expanded);
        }
        $row->setAttrib('data-toggle', 'next');

        $contextHtml = $this->helper->buildContext($row->getMeta('context'), $row->getCells()[2]->getValue())
            . $this->buildContextArguments($row->getMeta('args'));
        $contextHtml = $this->debug->abstracter->crateWithVals($contextHtml, array(
            'dumpType' => false, // don't add t_string css class
            'sanitize' => false,
            'visualWhiteSpace' => false,
        ));
        $tableCell = new TableCell($contextHtml);
        $tableCell->setAttrib('colspan', \count($row->getCells()));

        $tableRow = new TableRow();
        $tableRow->setAttribs(array(
            'class' => ['context'],
            'style' => $expanded ? 'display:table-row;' : null,
        ));

        $tableRow->appendCell($tableCell);
        return $tableRow;
    }

    /**
     * Determine & set the column index of ___class_name column
     *
     * @return void
     */
    private function setClassColumnIndex()
    {
        $this->classColumnIndex = null;
        foreach ($this->table->getMeta('columns', []) as $i => $colMeta) {
            if ($colMeta['key'] === '___class_name') {
                $this->classColumnIndex = $i;
                break;
            }
        }
    }

    /**
     * Dump a TableCell value
     *
     * @param TableCell $tableCell TableCell instance
     *
     * @return string
     */
    private function valDumper(TableCell $tableCell)
    {
        $index = $tableCell->getIndex();
        $value = $tableCell->getValue();
        $rowType = $tableCell->getParent()->getParent()->getTagName();

        if ($index === $this->classColumnIndex && $rowType !== 'thead' && $value !== Abstracter::UNDEFINED) {
            $dumped = $this->valDumper->markupIdentifier($value, Type::TYPE_IDENTIFIER_CLASSNAME);
            $parsed = $this->html->parseTag($dumped);
            $tableCell->setAttribs($parsed['attribs']);
            return $parsed['innerhtml'];
        }

        $dumped = $this->valDumper->dump($value, array(
            'tagName' => null,
        ));
        $optionsPrev = $this->valDumper->optionGet('previous');

        $columnMeta = \array_merge(array(
            'class' => null,
            'falseAs' => null,
            'trueAs' => null,
        ), $this->table->getMeta('columns', [])[$index]);

        if ($value === true && $columnMeta['trueAs'] !== null) {
            $dumped = $columnMeta['trueAs'];
        } elseif ($value === false && $columnMeta['falseAs'] !== null) {
            $dumped = $columnMeta['falseAs'];
        }

        $columnClass = $rowType === 'thead'
            ? $columnMeta['class']
            : null;
        if ($columnClass) {
            $dumped .= ' ' . $this->valDumper->markupIdentifier($columnClass, Type::TYPE_IDENTIFIER_CLASSNAME);
        }

        if ($optionsPrev['attribs']) {
            $attribs = $this->debug->arrayUtil->mergeDeep($tableCell->getAttribs(), $optionsPrev['attribs']);
            $tableCell->setAttribs($attribs);
        }
        return $dumped;
    }

    /**
     * Sanitize the caption and with classname (if applicable)
     *
     * @return void
     */
    private function updateCaption()
    {
        $caption = '';
        $captionElement = $this->table->getCaption();
        if ($captionElement) {
            $caption = $captionElement->getHtml();
            $caption = $this->valDumper->dump($caption, array(
                'tagName' => null,
                'type' => Type::TYPE_STRING, // pass so dumper doesn't need to infer
            ));
        }
        $class = $this->table->getMeta('class');
        if ($class) {
            $caption = \trim(\sprintf(
                '%s (%s)',
                $caption,
                $this->valDumper->markupIdentifier($class, Type::TYPE_IDENTIFIER_CLASSNAME)
            ));
        }
        $this->table->setCaption($caption ?: null);
    }
}
