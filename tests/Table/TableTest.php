<?php

namespace bdk\Test\Table;

use bdk\Table\Element;
use bdk\Table\Table;
use bdk\Table\TableCell;
use bdk\Table\TableRow;
// use bdk\Test\Debug\DebugTestFramework;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for bdk\Table\Table
 *
 * @covers \bdk\Table\Table
 */
class TableTest extends TestCase
{
    const CLASS_TABLE_ROW = 'bdk\\Table\\TableRow';
    const CLASS_ELEMENT = 'bdk\\Table\\Element';

    /**
     * Test basic constructor
     */
    public function testConstructorBasic()
    {
        $table = new Table();

        self::assertSame('table', $table->getTagName());
        self::assertSame([], $table->getRows());
        self::assertNull($table->getCaption());
        self::assertNull($table->getHeader());
        self::assertNull($table->getFooter());
    }

    /**
     * Test constructor with rows
     */
    public function testConstructorWithRows()
    {
        $row1 = new TableRow(['Cell 1', 'Cell 2']);
        $row2 = new TableRow(['Cell 3', 'Cell 4']);

        $table = new Table([$row1, $row2]);

        self::assertCount(2, $table->getRows());
        self::assertSame($row1, $table->getRows()[0]);
        self::assertSame($row2, $table->getRows()[1]);
    }

    /**
     * Test constructor with array rows (auto-conversion)
     */
    public function testConstructorWithArrayRows()
    {
        $table = new Table([
            ['A1', 'A2', 'A3'],
            ['B1', 'B2', 'B3'],
        ]);

        $rows = $table->getRows();
        self::assertCount(2, $rows);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $rows[0]);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $rows[1]);
    }

    /**
     * Test appendRow with TableRow object
     */
    public function testAppendRowWithTableRow()
    {
        $table = new Table();
        $row = new TableRow(['Cell 1', 'Cell 2']);

        $result = $table->appendRow($row);

        self::assertSame($table, $result);
        self::assertCount(1, $table->getRows());
        self::assertSame($row, $table->getRows()[0]);
    }

    /**
     * Test appendRow with array (auto-conversion)
     */
    public function testAppendRowWithArray()
    {
        $table = new Table();

        $table->appendRow(['A', 'B', 'C']);

        $rows = $table->getRows();
        self::assertCount(1, $rows);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $rows[0]);

        $cells = $rows[0]->getCells();
        self::assertCount(3, $cells);
    }

    /**
     * Test multiple appendRow calls
     */
    public function testAppendMultipleRows()
    {
        $table = new Table();

        $table->appendRow(['A', 'B']);
        $table->appendRow(['C', 'D']);
        $table->appendRow(['E', 'F']);

        self::assertCount(3, $table->getRows());
    }

    /**
     * Test setRows
     */
    public function testSetRows()
    {
        $table = new Table();
        $table->appendRow(['Old 1', 'Old 2']);

        $row1 = new TableRow(['New 1', 'New 2']);
        $row2 = new TableRow(['New 3', 'New 4']);

        $result = $table->setRows([$row1, $row2]);

        self::assertSame($table, $result);
        self::assertCount(2, $table->getRows());
        self::assertSame($row1, $table->getRows()[0]);
        self::assertSame($row2, $table->getRows()[1]);
    }

    /**
     * Test setRows with array data
     */
    public function testSetRowsWithArrays()
    {
        $table = new Table();

        $table->setRows([
            ['A', 'B', 'C'],
            ['D', 'E', 'F'],
        ]);

        $rows = $table->getRows();
        self::assertCount(2, $rows);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $rows[0]);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $rows[1]);
    }

    /**
     * Test getRows
     */
    public function testGetRows()
    {
        $row1 = new TableRow(['A', 'B']);
        $row2 = new TableRow(['C', 'D']);
        $table = new Table([$row1, $row2]);

        $rows = $table->getRows();

        self::assertIsArray($rows);
        self::assertCount(2, $rows);
        self::assertSame($row1, $rows[0]);
        self::assertSame($row2, $rows[1]);
    }

    /**
     * Test setCaption with string
     */
    public function testSetCaptionWithString()
    {
        $table = new Table();

        $result = $table->setCaption('Table Caption');

        self::assertSame($table, $result);
        $caption = $table->getCaption();
        self::assertInstanceOf(self::CLASS_ELEMENT, $caption);
        self::assertSame('caption', $caption->getTagName());
        self::assertSame('Table Caption', $caption->getHtml());
    }

    /**
     * Test setCaption with Element
     */
    public function testSetCaptionWithElement()
    {
        $table = new Table();
        $captionElement = new Element('caption', 'Custom Caption');

        $table->setCaption($captionElement);

        self::assertSame($captionElement, $table->getCaption());
        self::assertSame($table, $captionElement->getParent());
    }

    /**
     * Test getCaption
     */
    public function testGetCaption()
    {
        $table = new Table();

        self::assertNull($table->getCaption());

        $table->setCaption('Test Caption');

        $caption = $table->getCaption();
        self::assertInstanceOf(self::CLASS_ELEMENT, $caption);
        self::assertSame('Test Caption', $caption->getHtml());
    }

    /**
     * Test setHeader with array
     */
    public function testSetHeaderWithArray()
    {
        $table = new Table();

        $result = $table->setHeader(['Name', 'Age', 'Email']);

        self::assertSame($table, $result);
        $header = $table->getHeader();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header);

        $cells = $header->getCells();
        self::assertCount(3, $cells);

        // Check that cells are <th> elements
        foreach ($cells as $cell) {
            self::assertSame('th', $cell->getTagName());
        }
    }

    /**
     * Test setHeader with TableRow
     */
    public function testSetHeaderWithTableRow()
    {
        $table = new Table();
        $headerRow = new TableRow(['Col1', 'Col2', 'Col3']);

        $table->setHeader($headerRow);

        $header = $table->getHeader();
        self::assertSame($headerRow, $header);

        // Cells should be converted to <th>
        $cells = $header->getCells();
        foreach ($cells as $cell) {
            self::assertSame('th', $cell->getTagName());
        }
    }

    /**
     * Test getHeader
     */
    public function testGetHeader()
    {
        $table = new Table();

        self::assertNull($table->getHeader());

        $table->setHeader(['Header 1', 'Header 2']);

        $header = $table->getHeader();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header);
    }

    /**
     * Test setFooter with array
     */
    public function testSetFooterWithArray()
    {
        $table = new Table();

        $result = $table->setFooter(['Total', '100', '200']);

        self::assertSame($table, $result);
        $footer = $table->getFooter();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer);

        $cells = $footer->getCells();
        self::assertCount(3, $cells);
    }

    /**
     * Test setFooter with TableRow
     */
    public function testSetFooterWithTableRow()
    {
        $table = new Table();
        $footerRow = new TableRow(['Sum', '500']);

        $table->setFooter($footerRow);

        $footer = $table->getFooter();
        self::assertSame($footerRow, $footer);
    }

    /**
     * Test getFooter
     */
    public function testGetFooter()
    {
        $table = new Table();

        self::assertNull($table->getFooter());

        $table->setFooter(['Footer 1', 'Footer 2']);

        $footer = $table->getFooter();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer);
    }

    /**
     * Test getChildren
     */
    public function testGetChildren()
    {
        $table = new Table();

        // Initially only tbody exists
        $children = $table->getChildren();
        self::assertCount(1, $children);

        // Add caption, header, and footer
        $table->setCaption('Caption');
        $table->setHeader(['H1', 'H2']);
        $table->setFooter(['F1', 'F2']);

        $children = $table->getChildren();
        // Should have: caption, thead, tbody, tfoot
        self::assertCount(4, $children);
    }

    /**
     * Test getChildren returns elements in correct order
     */
    public function testGetChildrenOrder()
    {
        $table = new Table();
        $table->setCaption('Caption');
        $table->setHeader(['H1', 'H2']);
        $table->appendRow(['R1', 'R2']);
        $table->setFooter(['F1', 'F2']);

        $children = $table->getChildren();

        self::assertSame('caption', $children[0]->getTagName());
        self::assertSame('thead', $children[1]->getTagName());
        self::assertSame('tbody', $children[2]->getTagName());
        self::assertSame('tfoot', $children[3]->getTagName());
    }

    /**
     * Test setChildren with mixed elements
     */
    public function testSetChildrenWithMixedElements()
    {
        $table = new Table();

        $caption = new Element('caption', 'Test Caption');
        $thead = new Element('thead', [new TableRow(['H1', 'H2'])]);
        $tbody = new Element('tbody', [new TableRow(['B1', 'B2'])]);
        $tfoot = new Element('tfoot', [new TableRow(['F1', 'F2'])]);

        $table->setChildren([$caption, $thead, $tbody, $tfoot]);

        self::assertSame($caption, $table->getCaption());
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $table->getHeader());
        self::assertCount(1, $table->getRows());
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $table->getFooter());
    }

    /**
     * Test setChildren with row arrays
     */
    public function testSetChildrenWithRowArrays()
    {
        $table = new Table();

        $table->setChildren([
            ['A1', 'A2'],
            ['B1', 'B2'],
        ]);

        $rows = $table->getRows();
        self::assertCount(2, $rows);
    }

    /**
     * Test setChildren resets all parts
     */
    public function testSetChildrenResetsAllParts()
    {
        $table = new Table();
        $table->setCaption('Caption');
        $table->setHeader(['H1', 'H2']);
        $table->appendRow(['R1', 'R2']);
        $table->setFooter(['F1', 'F2']);

        // Set new children (just rows)
        $table->setChildren([
            ['New1', 'New2'],
        ]);

        self::assertNull($table->getCaption());
        self::assertNull($table->getHeader());
        self::assertNull($table->getFooter());
        self::assertCount(1, $table->getRows());
    }

    /**
     * Test __serialize
     */
    public function testSerialize()
    {
        $table = new Table();
        $table->setCaption('Test Caption');
        $table->setHeader(['Col1', 'Col2']);
        $table->appendRow(['A', 'B']);
        $table->appendRow(['C', 'D']);
        $table->setFooter(['Total', '100']);

        $data = $table->__serialize();

        self::assertIsArray($data);
        self::assertArrayHasKey('caption', $data);
        self::assertArrayHasKey('header', $data);
        self::assertArrayHasKey('rows', $data);
        self::assertArrayHasKey('footer', $data);

        self::assertInstanceOf(self::CLASS_ELEMENT, $data['caption']);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $data['header']);
        self::assertIsArray($data['rows']);
        self::assertCount(2, $data['rows']);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $data['footer']);
    }

    /**
     * Test serialize with empty table
     */
    public function testSerializeEmptyTable()
    {
        $table = new Table();

        $data = $table->__serialize();

        // Empty/null values should be filtered out
        self::assertArrayNotHasKey('caption', $data);
        self::assertArrayNotHasKey('header', $data);
        self::assertArrayNotHasKey('footer', $data);
    }

    /**
     * Test getOuterHtml
     */
    public function testGetOuterHtml()
    {
        $table = new Table();
        $table->setCaption('Test Table');
        $table->setHeader(['Name', 'Age']);
        $table->appendRow(['John', '30']);
        $table->appendRow(['Jane', '25']);

        $html = $table->getOuterHtml();

        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('</table>', $html);
        self::assertStringContainsString('<caption>Test Table</caption>', $html);
        self::assertStringContainsString('<thead>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringContainsString('<th', $html);
        self::assertStringContainsString('</th>', $html);
        self::assertStringContainsString('<tr>', $html);
        self::assertStringContainsString('<td', $html);
        self::assertStringContainsString('</td>', $html);
    }

    /**
     * Test fluent interface
     */
    public function testFluentInterface()
    {
        $table = new Table();

        $result = $table
            ->setCaption('Caption')
            ->setHeader(['H1', 'H2'])
            ->appendRow(['A', 'B'])
            ->setFooter(['F1', 'F2']);

        self::assertSame($table, $result);
        self::assertInstanceOf(self::CLASS_ELEMENT, $table->getCaption());
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $table->getHeader());
        self::assertCount(1, $table->getRows());
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $table->getFooter());
    }

    /**
     * Test table with attributes
     */
    public function testTableWithAttributes()
    {
        $table = new Table();
        $table->setAttrib('id', 'data-table');
        $table->setAttrib('class', 'table-striped');
        $table->appendRow(['A', 'B']);

        $html = $table->getOuterHtml();

        self::assertStringContainsString('id="data-table"', $html);
        self::assertStringContainsString('class="table-striped"', $html);
    }

    /**
     * Test complex table structure
     */
    public function testComplexTableStructure()
    {
        $table = new Table();

        // Caption
        $table->setCaption('Sales Report');

        // Header with attributes
        $header = new TableRow([
            new TableCell(['value' => 'Product', 'attribs' => ['class' => 'col-product']]),
            new TableCell(['value' => 'Price', 'attribs' => ['class' => 'col-price']]),
            new TableCell(['value' => 'Quantity', 'attribs' => ['class' => 'col-qty']]),
        ]);
        $table->setHeader($header);

        // Body rows
        $table->appendRow(['Widget A', '$10.00', '5']);
        $table->appendRow(['Widget B', '$15.00', '3']);

        // Footer
        $table->setFooter(['Total', '$75.00', '8']);

        $html = $table->getOuterHtml();

        self::assertStringContainsString('Sales Report', $html);
        self::assertStringContainsString('col-product', $html);
        self::assertStringContainsString('Widget A', $html);
        self::assertStringContainsString('Widget B', $html);
        self::assertStringContainsString('Total', $html);
    }

    /**
     * Test that tbody is always present
     */
    public function testTbodyAlwaysPresent()
    {
        $table = new Table();

        $children = $table->getChildren();

        // Even empty table should have tbody
        $hasTbody = false;
        foreach ($children as $child) {
            if ($child->getTagName() === 'tbody') {
                $hasTbody = true;
                break;
            }
        }

        self::assertTrue($hasTbody);
    }

    /**
     * Test parent relationship for header
     */
    public function testHeaderParentRelationship()
    {
        $table = new Table();
        $table->setHeader(['H1', 'H2']);

        $children = $table->getChildren();
        $thead = null;
        foreach ($children as $child) {
            if ($child->getTagName() === 'thead') {
                $thead = $child;
                break;
            }
        }

        self::assertNotNull($thead);
        self::assertSame($table, $thead->getParent());
    }

    /**
     * Test parent relationship for footer
     */
    public function testFooterParentRelationship()
    {
        $table = new Table();
        $table->setFooter(['F1', 'F2']);

        $children = $table->getChildren();
        $tfoot = null;
        foreach ($children as $child) {
            if ($child->getTagName() === 'tfoot') {
                $tfoot = $child;
                break;
            }
        }

        self::assertNotNull($tfoot);
        self::assertSame($table, $tfoot->getParent());
    }

    /**
     * Test parent relationship for caption
     */
    public function testCaptionParentRelationship()
    {
        $table = new Table();
        $table->setCaption('Test');

        $caption = $table->getCaption();
        self::assertSame($table, $caption->getParent());
    }

    /**
     * Test th scope attribute in header
     */
    public function testHeaderThScopeAttribute()
    {
        $table = new Table();
        $table->setHeader(['Column 1', 'Column 2']);

        $header = $table->getHeader();
        $cells = $header->getCells();

        foreach ($cells as $cell) {
            $attribs = $cell->getAttribs();
            self::assertSame('col', $attribs['scope']);
        }
    }

    /**
     * Test serialization keys are sorted
     */
    public function testSerializeKeysSorted()
    {
        $table = new Table();
        $table->setCaption('Caption');
        $table->setHeader(['H1', 'H2']);
        $table->appendRow(['R1', 'R2']);
        $table->setFooter(['F1', 'F2']);

        $data = $table->__serialize();
        $keys = \array_keys($data);
        $sortedKeys = $keys;
        \sort($sortedKeys);

        self::assertSame($sortedKeys, $keys);
    }

    /**
     * Test empty rows array
     */
    public function testEmptyRowsArray()
    {
        $table = new Table([]);

        self::assertSame([], $table->getRows());
    }

    /**
     * Test setRows replaces existing rows
     */
    public function testSetRowsReplacesExisting()
    {
        $table = new Table();
        $table->appendRow(['Old 1']);
        $table->appendRow(['Old 2']);
        $table->appendRow(['Old 3']);

        self::assertCount(3, $table->getRows());

        $table->setRows([
            ['New 1'],
            ['New 2'],
        ]);

        self::assertCount(2, $table->getRows());
    }

    /**
     * Test getHeader with multiple header rows
     */
    public function testGetHeaderMultipleRows()
    {
        $table = new Table();

        // Create thead with multiple rows manually
        $thead = new Element('thead', [
            new TableRow(['H1', 'H2']),
            new TableRow(['H3', 'H4']),
        ]);
        $table->setChildren([$thead]);

        $header = $table->getHeader();

        // Should return array of rows when multiple
        self::assertIsArray($header);
        self::assertCount(2, $header);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header[0]);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header[1]);
    }

    /**
     * Test getFooter with multiple footer rows
     */
    public function testGetFooterMultipleRows()
    {
        $table = new Table();

        // Create tfoot with multiple rows manually
        $tfoot = new Element('tfoot', [
            new TableRow(['F1', 'F2']),
            new TableRow(['F3', 'F4']),
        ]);
        $table->setChildren([$tfoot]);

        $footer = $table->getFooter();

        // Should return array of rows when multiple
        self::assertIsArray($footer);
        self::assertCount(2, $footer);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer[0]);
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer[1]);
    }

    /**
     * Test caption defaults
     */
    public function testCaptionDefaults()
    {
        $table = new Table();
        $table->setCaption('Test Caption');

        $caption = $table->getCaption();

        // Caption should have default tagName
        $serialized = $caption->__serialize();
        self::assertArrayNotHasKey('tagName', $serialized);
    }
}
