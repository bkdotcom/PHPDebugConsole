<?php

namespace bdk\Table;

use bdk\Table\Factory;
use bdk\Table\Table;
use bdk\Table\TableCell;

/**
 * Output table data as html
 */
class Utility
{
    /** @var callable|null */
    protected $valDumper;

    /**
     * Set value / content dumper
     *
     * @param callable $valDumper Callable that returns the cell content
     *                             Signature: function (TableCell $cell, array &$attribs) : string
     *
     * @return void
     */
    public function setValDumper(callable $valDumper)
    {
        $this->valDumper = $valDumper;
    }

    /**
     * Output Table as HTML
     *
     * @param Table $table Table instance
     *
     * @return string html fragment
     */
    public function output(Table $table)
    {
        if ($this->valDumper) {
            TableCell::setValDumper($this->valDumper);
        }
        return $table->getOuterHtml();
    }

    /**
     * Export table as array
     *
     * @param Table $table   Table instance
     * @param array $options (optional) options
     *
     * @return array
     *
     * @todo header vs meta.column.i.key
     * @todo skip colspan cols?
     */
    public static function asArray(Table $table, array $options = array())
    {
        $options = \array_merge(array(
            'forceArray' => false,
            'undefinedAs' => 'unset', // 'unset', Factory::VAL_UNDEFINED, null
        ), $options);

        $columnMeta = $table->getMeta('columns');
        $headerVals = \array_map(static function ($cell) {
            return $cell->getValue();
        }, $table->getHeader()->getChildren());
        $rows = array();
        $colCount = \count($columnMeta);
        foreach ($table->getRows() as $iRow => $tableRow) {
            $row = array();
            $cells = $tableRow->getCells();
            $rowKey = $iRow;
            $iCell = 0;
            if ($columnMeta[0]['key'] === Factory::KEY_INDEX) {
                $iCell = 1;
                $rowKey = (string) $cells[0]->getValue();
            }
            $cellCountOut = 0;
            for (; $iCell < $colCount; $iCell++) {
                $tableCell = $cells[$iCell];
                $key = $columnMeta[$iCell]['key'];
                $value = $tableCell->getValue();
                $isScalar = $key === Factory::KEY_SCALAR;
                if ($value === Factory::VAL_UNDEFINED) {
                    $value = $options['undefinedAs'];
                    if ($value === 'unset') {
                        continue;
                    }
                }
                $cellCountOut++;
                if ($isScalar) {
                    $key = $headerVals[$iCell];
                }
                $row[$key] = $value;
                if ($isScalar && $cellCountOut === 1 && $options['forceArray'] === false) {
                    // this scalar column is the only column other than the index
                    $row = $row[$key];
                }
            }
            $rows[$rowKey] = $row;
        }
        return $rows;
    }
}
