<?php

namespace bdk\Table;

use bdk\Table\Factory;
use bdk\Table\Table;
use bdk\Table\TableCell;
use bdk\Table\TableRow;

/**
 * Output table data as html
 */
class Utility
{
    private static $options = array();

    private static $tempData = array();

    /**
     * Export table as array
     *
     * @param Table $table   Table instance
     * @param array $options (optional) options
     *
     * @return array
     */
    public static function asArray(Table $table, array $options = array())
    {
        self::$options = \array_merge(array(
            'forceArray' => false,
            'undefinedAs' => 'unset', // 'unset', Factory::VAL_UNDEFINED, null
        ), $options);

        self::$tempData = array(
            'columnsMeta' => $table->getMeta('columns'),
            'headerVals' => \array_map(static function ($cell) {
                return $cell->getValue();
            }, $table->getHeader()->getChildren()),
            'rowKey' => 0,
        );

        $rows = array();
        foreach ($table->getRows() as $iRow => $tableRow) {
            $rowAsArray = self::rowAsArray($tableRow, $iRow);
            $rows[self::$tempData['rowKey']] = $rowAsArray;
        }
        return $rows;
    }

    /**
     * Convert TableRow to array
     *
     * @param TableRow $tableRow TableRow instance
     * @param int      $iRow     Row index
     *
     * @return array
     */
    private static function rowAsArray(TableRow $tableRow, $iRow)
    {
        $row = array();
        self::$tempData['rowKey'] = $iRow;
        foreach ($tableRow->getCells() as $iCell => $tableCell) {
            $cellInfo = self::getCellKeyValue($tableCell, $iCell);
            $key = $cellInfo['key'];
            $value = $cellInfo['value'];
            /*
            if ($key === Factory::KEY_INDEX) {
                $this->tempData['rowKey'] = $value;
                continue;
            }
            */
            if ($key === null) {
                continue;
            }
            $row[$key] = $value;
        }
        if (\count($row) === 1 && $cellInfo['isScalar'] && self::$options['forceArray'] === false) {
            // this scalar column is the only column other than the index
            $row = $row[$key];
        }
        return $row;
    }

    /**
     * Get cell key and value
     *
     * @param TableCell $tableCell TableCell instance
     * @param int       $iCell     Cell index
     *
     * @return array
     */
    private static function getCellKeyValue(TableCell $tableCell, $iCell)
    {
        $key = self::$tempData['columnsMeta'][$iCell]['key'];
        $value = $tableCell->getValue();
        $isScalar = $key === Factory::KEY_SCALAR;
        if ($key === Factory::KEY_INDEX) {
            self::$tempData['rowKey'] = (string) $value; // value may be string Abstraction
            $key = null;
        }
        if ($value === Factory::VAL_UNDEFINED) {
            $value = self::$options['undefinedAs'];
            if ($value === 'unset') {
                $key = null;
            }
        }
        return array(
            'isScalar' => $isScalar,
            'key' => $isScalar
                ? self::$tempData['headerVals'][$iCell]
                : $key,
            'value' => $value,
        );
    }
}
