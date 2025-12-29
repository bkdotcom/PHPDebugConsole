<?php

namespace bdk\Table;

use bdk\Table\Table;
use bdk\Table\TableCell;

/**
 * Output table data as html
 */
class OutputHtml
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
}
