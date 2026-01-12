<?php

namespace bdk\Test\Table;

use bdk\Table\Element;
use bdk\Table\TableCell;
use bdk\Table\TableRow;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for bdk\Table\TableRow
 *
 * @covers \bdk\Table\Element
 * @covers \bdk\Table\TableRow
 */
class TableRowTest extends TestCase
{
    const CLASS_ELEMENT = 'bdk\\Table\\Element';
    const CLASS_TABLE_CELL = 'bdk\\Table\\TableCell';

    /**
     * Test basic constructor
     */
    public function testConstructorBasic()
    {
        $row = new TableRow();

        self::assertSame('tr', $row->getTagName());
        self::assertSame([], $row->getCells());
    }

    /**
     * Test constructor with array of values
     */
    public function testConstructorWithValues()
    {
        $row = new TableRow(['A', 'B', 'C']);

        $cells = $row->getCells();
        self::assertCount(3, $cells);

        foreach ($cells as $cell) {
            self::assertInstanceOf(self::CLASS_TABLE_CELL, $cell);
        }
    }

    /**
     * Test constructor with TableCell objects
     */
    public function testConstructorWithTableCells()
    {
        $cell1 = new TableCell('Cell 1');
        $cell2 = new TableCell('Cell 2');
        $cell3 = new TableCell('Cell 3');

        $row = new TableRow([$cell1, $cell2, $cell3]);

        $cells = $row->getCells();
        self::assertCount(3, $cells);
        self::assertSame($cell1, $cells[0]);
        self::assertSame($cell2, $cells[1]);
        self::assertSame($cell3, $cells[2]);
    }

    /**
     * Test constructor with associative array (properties)
     */
    public function testConstructorWithProperties()
    {
        $row = new TableRow([
            'cells' => ['A', 'B', 'C'],
            'attribs' => ['class' => 'highlight'],
        ]);

        self::assertCount(3, $row->getCells());
        self::assertSame(['class' => ['highlight']], $row->getAttribs());
    }

    /**
     * Test constructor with empty array
     */
    public function testConstructorWithEmptyArray()
    {
        $row = new TableRow([]);

        self::assertSame([], $row->getCells());
    }

    /**
     * Test getCells
     */
    public function testGetCells()
    {
        $row = new TableRow(['X', 'Y', 'Z']);

        $cells = $row->getCells();

        self::assertIsArray($cells);
        self::assertCount(3, $cells);

        foreach ($cells as $cell) {
            self::assertInstanceOf(self::CLASS_TABLE_CELL, $cell);
        }
    }

    /**
     * Test setCells with array values
     */
    public function testSetCellsWithValues()
    {
        $row = new TableRow();

        $result = $row->setCells(['One', 'Two', 'Three']);

        self::assertSame($row, $result);
        self::assertCount(3, $row->getCells());
    }

    /**
     * Test setCells with TableCell objects
     */
    public function testSetCellsWithTableCells()
    {
        $row = new TableRow();

        $cell1 = new TableCell('Cell 1');
        $cell2 = new TableCell('Cell 2');

        $row->setCells([$cell1, $cell2]);

        $cells = $row->getCells();
        self::assertCount(2, $cells);
        self::assertSame($cell1, $cells[0]);
        self::assertSame($cell2, $cells[1]);
    }

    /**
     * Test setCells replaces existing cells
     */
    public function testSetCellsReplacesExisting()
    {
        $row = new TableRow(['Old 1', 'Old 2', 'Old 3']);

        self::assertCount(3, $row->getCells());

        $row->setCells(['New 1', 'New 2']);

        self::assertCount(2, $row->getCells());
    }

    /**
     * Test appendCell with value
     */
    public function testAppendCellWithValue()
    {
        $row = new TableRow(['A', 'B']);

        self::assertCount(2, $row->getCells());

        $result = $row->appendCell('C');

        self::assertSame($row, $result);
        self::assertCount(3, $row->getCells());

        $cells = $row->getCells();
        self::assertInstanceOf(self::CLASS_TABLE_CELL, $cells[2]);
    }

    /**
     * Test appendCell with TableCell object
     */
    public function testAppendCellWithTableCell()
    {
        $row = new TableRow(['A', 'B']);

        $cell = new TableCell('C');
        $result = $row->appendCell($cell);

        self::assertSame($row, $result);
        self::assertCount(3, $row->getCells());

        $cells = $row->getCells();
        self::assertSame($cell, $cells[2]);
        self::assertSame($row, $cell->getParent());
    }

    /**
     * Test appendCell to empty row
     */
    public function testAppendCellToEmptyRow()
    {
        $row = new TableRow();

        self::assertCount(0, $row->getCells());

        $row->appendCell('First Cell');

        self::assertCount(1, $row->getCells());
    }

    /**
     * Test appendCell multiple times
     */
    public function testAppendCellMultipleTimes()
    {
        $row = new TableRow();

        $row->appendCell('A')
            ->appendCell('B')
            ->appendCell('C')
            ->appendCell('D');

        self::assertCount(4, $row->getCells());
    }

    /**
     * Test setChildren with array values
     */
    public function testSetChildrenWithValues()
    {
        $row = new TableRow();

        $result = $row->setChildren(['Alpha', 'Beta', 'Gamma']);

        self::assertSame($row, $result);

        $cells = $row->getCells();
        self::assertCount(3, $cells);

        foreach ($cells as $cell) {
            self::assertInstanceOf(self::CLASS_TABLE_CELL, $cell);
        }
    }

    /**
     * Test setChildren with TableCell objects
     */
    public function testSetChildrenWithTableCells()
    {
        $row = new TableRow();

        $cell1 = new TableCell('A');
        $cell2 = new TableCell('B');

        $row->setChildren([$cell1, $cell2]);

        $cells = $row->getCells();
        self::assertSame($cell1, $cells[0]);
        self::assertSame($cell2, $cells[1]);
    }

    /**
     * Test setChildren with mixed Element types (non-TableCell)
     */
    public function testSetChildrenWithGenericElements()
    {
        $row = new TableRow();

        $elem1 = new Element('td', 'Element 1');
        $elem2 = new Element('td', 'Element 2');

        $row->setChildren([$elem1, $elem2]);

        $cells = $row->getCells();
        self::assertCount(2, $cells);
        self::assertSame($elem1, $cells[0]);
        self::assertSame($elem2, $cells[1]);
    }

    /**
     * Test setChildren sets parent relationship
     */
    public function testSetChildrenSetsParent()
    {
        $row = new TableRow();

        $cell1 = new TableCell('Cell 1');
        $cell2 = new TableCell('Cell 2');

        $row->setChildren([$cell1, $cell2]);

        self::assertSame($row, $cell1->getParent());
        self::assertSame($row, $cell2->getParent());
    }

    /**
     * Test setChildren re-indexes array
     */
    public function testSetChildrenReindexesArray()
    {
        $row = new TableRow();

        // Associative array with non-sequential keys
        $cells = [
            5 => 'Cell A',
            10 => 'Cell B',
            15 => 'Cell C',
        ];

        $row->setChildren($cells);

        $resultCells = $row->getCells();
        $keys = \array_keys($resultCells);

        // Should be re-indexed to 0, 1, 2
        self::assertSame([0, 1, 2], $keys);
    }

    /**
     * Test getChildren returns cells
     */
    public function testGetChildren()
    {
        $row = new TableRow(['A', 'B', 'C']);

        $children = $row->getChildren();
        $cells = $row->getCells();

        self::assertSame($cells, $children);
    }

    /**
     * Test fluent interface
     */
    public function testFluentInterface()
    {
        $row = new TableRow();

        $result = $row
            ->setCells(['A', 'B', 'C'])
            ->setAttrib('class', 'data-row')
            ->setAttrib('id', 'row-1');

        self::assertSame($row, $result);
        self::assertCount(3, $row->getCells());
        self::assertSame(['class' => ['data-row'], 'id' => 'row-1'], $row->getAttribs());
    }

    /**
     * Test getOuterHtml
     */
    public function testGetOuterHtml()
    {
        $row = new TableRow(['Cell 1', 'Cell 2', 'Cell 3']);
        $row->setAttrib('class', 'highlight');

        $html = $row->getOuterHtml();

        self::assertStringContainsString('<tr', $html);
        self::assertStringContainsString('</tr>', $html);
        self::assertStringContainsString('class="highlight"', $html);
        self::assertStringContainsString('<td', $html);
        self::assertStringContainsString('</td>', $html);
    }

    /**
     * Test serialization
     */
    public function testSerialize()
    {
        $row = new TableRow(['A', 'B', 'C']);
        $row->setAttrib('id', 'test-row');

        $data = $row->__serialize();

        self::assertIsArray($data);
        self::assertArrayHasKey('children', $data);
        self::assertArrayHasKey('attribs', $data);
    }

    /**
     * Test cells with various data types
     */
    public function testCellsWithVariousDataTypes()
    {
        $row = new TableRow([
            'String',
            123,
            45.67,
            true,
            null,
            ['nested', 'array'],
        ]);

        $cells = $row->getCells();

        self::assertCount(6, $cells);

        foreach ($cells as $cell) {
            self::assertInstanceOf(self::CLASS_TABLE_CELL, $cell);
        }
    }

    /**
     * Test row with attributes
     */
    public function testRowWithAttributes()
    {
        $row = new TableRow(['A', 'B']);
        $row->setAttrib('data-id', '123');
        $row->setAttrib('class', 'even');

        $attribs = $row->getAttribs();

        self::assertSame('123', $attribs['data-id']);
        self::assertSame(['even'], $attribs['class']);
    }

    /**
     * Test row with mixed cell types
     */
    public function testRowWithMixedCellTypes()
    {
        $cell1 = new TableCell('TableCell');
        $cell2 = new Element('td', 'Element');
        $value3 = 'Plain Value';

        $row = new TableRow([$cell1, $cell2, $value3]);

        $cells = $row->getCells();

        self::assertCount(3, $cells);
        self::assertSame($cell1, $cells[0]);
        self::assertSame($cell2, $cells[1]);
        self::assertInstanceOf(self::CLASS_TABLE_CELL, $cells[2]);
    }

    /**
     * Test empty cells array
     */
    public function testEmptyCellsArray()
    {
        $row = new TableRow();
        $row->setCells([]);

        self::assertSame([], $row->getCells());

        $html = $row->getOuterHtml();
        self::assertSame('<tr></tr>', $html);
    }

    /**
     * Test constructor with properties and attribs
     */
    public function testConstructorWithPropertiesAndAttribs()
    {
        $row = new TableRow([
            'cells' => ['A', 'B'],
            'attribs' => [
                'id' => 'my-row',
                'class' => 'striped',
            ],
            'meta' => ['key' => 'value'],
        ]);

        self::assertCount(2, $row->getCells());
        self::assertSame(['class' => ['striped'], 'id' => 'my-row'], $row->getAttribs());
        self::assertSame('value', $row->getMeta('key'));
    }

    /**
     * Test single cell row
     */
    public function testSingleCellRow()
    {
        $row = new TableRow(['Single Cell']);

        self::assertCount(1, $row->getCells());
    }

    /**
     * Test large number of cells
     */
    public function testLargeNumberOfCells()
    {
        $values = \range(1, 50);
        $row = new TableRow($values);

        self::assertCount(50, $row->getCells());
    }

    /**
     * Test cell parent updates when setting new cells
     */
    public function testCellParentUpdates()
    {
        $row = new TableRow();

        $cell1 = new TableCell('Cell 1');
        $cell2 = new TableCell('Cell 2');

        $row->setCells([$cell1, $cell2]);

        self::assertSame($row, $cell1->getParent());
        self::assertSame($row, $cell2->getParent());

        // Now set new cells
        $cell3 = new TableCell('Cell 3');
        $row->setCells([$cell3]);

        self::assertSame($row, $cell3->getParent());
    }

    /**
     * Test setChildren with Element subclass
     */
    public function testSetChildrenWithElementSubclass()
    {
        $row = new TableRow();

        // TableCell is a subclass of Element
        $cell = new TableCell('Test');
        $row->setChildren([$cell]);

        $cells = $row->getCells();
        self::assertSame($cell, $cells[0]);
        self::assertInstanceOf(self::CLASS_ELEMENT, $cells[0]);
        self::assertInstanceOf(self::CLASS_TABLE_CELL, $cells[0]);
    }

    /**
     * Test getIndex
     */
    public function testGetIndex()
    {
        $cell1 = new TableCell('A');
        $cell2 = new TableCell('B');
        $cell3 = new TableCell('C');

        $row = new TableRow([$cell1, $cell2, $cell3]);

        // TableRow's children are the cells
        self::assertSame(0, $cell1->getIndex());
        self::assertSame(1, $cell2->getIndex());
        self::assertSame(2, $cell3->getIndex());
    }

    /**
     * Test row defaults
     */
    public function testRowDefaults()
    {
        $row = new TableRow(['A', 'B']);

        // tagName should be 'tr' which is the default
        self::assertSame('tr', $row->getTagName());

        // When serialized, default tagName should not appear
        $data = $row->__serialize();
        self::assertArrayNotHasKey('tagName', $data);
    }

    /**
     * Test constructor determines between list and associative array
     */
    public function testConstructorArrayTypeDetection()
    {
        // List array - should be treated as cells
        $row1 = new TableRow(['A', 'B', 'C']);
        self::assertCount(3, $row1->getCells());

        // Associative array - should be treated as properties
        $row2 = new TableRow([
            'cells' => ['X', 'Y'],
            'attribs' => ['class' => 'test'],
        ]);
        self::assertCount(2, $row2->getCells());
        self::assertArrayHasKey('class', $row2->getAttribs());

        // Array starting with Element - should be treated as cells
        $cell = new TableCell('Z');
        $row3 = new TableRow([$cell]);
        self::assertCount(1, $row3->getCells());
        self::assertSame($cell, $row3->getCells()[0]);
    }

    /**
     * Test JSON serialization
     */
    public function testJsonSerialize()
    {
        $row = new TableRow(['A', 'B', 'C']);

        $json = $row->jsonSerialize();

        self::assertIsArray($json);
    }

    /**
     * Test row with classes
     */
    public function testRowWithClasses()
    {
        $row = new TableRow(['A', 'B']);
        $row->addClass('row-even');
        $row->addClass('row-highlight');

        $attribs = $row->getAttribs();

        self::assertContains('row-even', $attribs['class']);
        self::assertContains('row-highlight', $attribs['class']);
    }

    /**
     * Test row with meta data
     */
    public function testRowWithMetaData()
    {
        $row = new TableRow(['A', 'B']);
        $row->setMeta('rowType', 'data');
        $row->setMeta('position', 5);

        self::assertSame('data', $row->getMeta('rowType'));
        self::assertSame(5, $row->getMeta('position'));
    }
}
