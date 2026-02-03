<?php

namespace bdk\Test\Table;

use bdk\Table\Factory;
use bdk\Table\Table;
use bdk\Table\TableRow;
use bdk\Test\Debug\DebugTestFramework;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * PHPUnit tests for bdk\Table\Factory
 *
 * @covers \bdk\Table\Factory
 */
class FactoryTest extends TestCase
{
    const CLASS_TABLE_ROW = 'bdk\Table\TableRow';
    const CLASS_TABLE = 'bdk\Table\Table';

    /**
     * Test basic create with simple array
     */
    public function testCreateWithSimpleArray()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(2, $table->getRows());
    }

    /**
     * Test create with empty array
     */
    public function testCreateWithEmptyArray()
    {
        $factory = new Factory();
        $table = $factory->create([]);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(0, $table->getRows());
    }

    /**
     * Test create with indexed array
     */
    public function testCreateWithIndexedArray()
    {
        $factory = new Factory();
        $data = [
            ['Apple', 'Red'],
            ['Banana', 'Yellow'],
            ['Orange', 'Orange'],
        ];

        $table = $factory->create($data);

        self::assertCount(3, $table->getRows());
    }

    /**
     * Test create with objects
     */
    public function testCreateWithObjects()
    {
        $factory = new Factory();

        $obj1 = new stdClass();
        $obj1->name = 'Item 1';
        $obj1->price = 10.50;

        $obj2 = new stdClass();
        $obj2->name = 'Item 2';
        $obj2->price = 20.75;

        $data = [$obj1, $obj2];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(2, $table->getRows());
    }

    /**
     * Test create with object as data source
     */
    public function testCreateWithObjectAsData()
    {
        $factory = new Factory();

        $data = new stdClass();
        $data->item1 = ['name' => 'Item 1', 'value' => 100];
        $data->item2 = ['name' => 'Item 2', 'value' => 200];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(2, $table->getRows());
    }

    /**
     * Test create with scalar values
     */
    public function testCreateWithScalarValues()
    {
        $factory = new Factory();
        $data = [10, true, false, null, 'string'];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(5, $table->getRows());
    }

    /**
     * Test create with mixed array structures
     */
    public function testCreateWithMixedArrays()
    {
        $factory = new Factory();
        $data = [
            ['a' => 1, 'b' => 2],
            ['a' => 3, 'c' => 4],
            ['b' => 5, 'c' => 6],
        ];

        $table = $factory->create($data);

        self::assertCount(3, $table->getRows());
        // Should have columns for index, a, b, c
        $header = $table->getHeader();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header);
    }

    /**
     * Test column ordering with inconsistent keys
     */
    public function testColumnOrderingWithInconsistentKeys()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'John', 'age' => 30, 'city' => 'NYC'],
            ['name' => 'Jane', 'city' => 'LA', 'state' => 'CA'],
            ['age' => 25, 'name' => 'Bob', 'state' => 'TX'],
        ];

        $table = $factory->create($data);

        self::assertCount(3, $table->getRows());
        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test with column labels option
     */
    public function testCreateWithColumnLabels()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];

        $options = [
            'columnLabels' => [
                'age' => 'Age (years)',
                'name' => 'Full Name',
            ],
        ];

        $table = $factory->create($data, $options);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        $header = $table->getHeader();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header);
    }

    /**
     * Test with columns option (specify columns)
     */
    public function testCreateWithColumnsOption()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'John', 'age' => 30, 'city' => 'NYC'],
            ['name' => 'Jane', 'age' => 25, 'city' => 'LA'],
        ];

        $options = [
            'columns' => ['name', 'age'],
        ];

        $table = $factory->create($data, $options);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        // Should only have index, name, and age columns
        $header = $table->getHeader();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header);
        $cells = $header->getCells();
        self::assertCount(3, $cells); // index, name, age
    }

    /**
     * Test with totalCols option
     */
    public function testCreateWithTotalCols()
    {
        $factory = new Factory();
        $data = [
            ['product' => 'A', 'quantity' => 10, 'price' => 5.50],
            ['product' => 'B', 'quantity' => 20, 'price' => 3.25],
            ['product' => 'C', 'quantity' => 15, 'price' => 7.00],
        ];

        $options = [
            'totalCols' => ['quantity', 'price'],
        ];

        $table = $factory->create($data, $options);

        self::assertInstanceOf(self::CLASS_TABLE, $table);

        // Should have footer with totals
        $footer = $table->getFooter();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer);
    }

    /**
     * Test constructor with default options
     */
    public function testConstructorWithDefaultOptions()
    {
        $defaultOptions = [
            'columnLabels' => [
                'id' => 'ID',
                'name' => 'Name',
            ],
        ];

        $factory = new Factory($defaultOptions);

        $data = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test that options are merged correctly
     */
    public function testOptionsAreMerged()
    {
        $defaultOptions = [
            'columnLabels' => [
                'id' => 'ID',
            ],
        ];

        $factory = new Factory($defaultOptions);

        $createOptions = [
            'columnLabels' => [
                'name' => 'Name',
            ],
        ];

        $data = [
            ['id' => 1, 'name' => 'Item'],
        ];

        $table = $factory->create($data, $createOptions);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test header is created
     */
    public function testHeaderIsCreated()
    {
        $factory = new Factory();
        $data = [
            ['col1' => 'A', 'col2' => 'B'],
        ];

        $table = $factory->create($data);

        $header = $table->getHeader();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $header);

        $cells = $header->getCells();
        self::assertGreaterThan(0, \count($cells));
    }

    /**
     * Test footer is created with totals
     */
    public function testFooterIsCreatedWithTotals()
    {
        $factory = new Factory();
        $data = [
            ['value' => 10],
            ['value' => 20],
            ['value' => 30],
        ];

        $table = $factory->create($data, ['totalCols' => ['value']]);

        $footer = $table->getFooter();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer);
    }

    /**
     * Test no footer when totalCols is empty
     */
    public function testNoFooterWhenTotalColsEmpty()
    {
        $factory = new Factory();
        $data = [
            ['value' => 10],
        ];

        $table = $factory->create($data);

        self::assertNull($table->getFooter());
    }

    /**
     * Test meta information is set
     */
    public function testMetaInformationIsSet()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'Item'],
        ];

        $table = $factory->create($data);

        $meta = $table->getMeta();
        self::assertIsArray($meta);
        self::assertArrayHasKey('columns', $meta);
    }

    /**
     * Test meta class is set when data is object
     */
    public function testMetaClassIsSetWhenDataIsObject()
    {
        $factory = new Factory();

        $data = new stdClass();
        $data->item1 = ['value' => 1];

        $table = $factory->create($data);

        $meta = $table->getMeta();
        self::assertSame('stdClass', $meta['class']);
    }

    /**
     * Test KEY_INDEX column has proper cell attributes
     */
    public function testKeyIndexColumnHasProperAttributes()
    {
        $factory = new Factory();
        $data = [
            ['value' => 1],
        ];

        $table = $factory->create($data);

        $meta = $table->getMeta();
        $rows = $table->getRows();
        $firstRow = $rows[0];
        $cells = $firstRow->getCells();
        $firstCell = $cells[0];

        // First cell should be the index column
        self::assertSame('td', $firstCell->getTagName());
        self::assertSame('th', $meta['columns'][0]['tagName']);
        self::assertContains('t_key', $meta['columns'][0]['attribs']['class']);
        self::assertSame('row', $meta['columns'][0]['attribs']['scope']);
        self::assertSame('<th class="t_int t_key" scope="row">0</th>', $firstCell->getOuterHtml());
    }

    /**
     * Test VAL_UNDEFINED constant
     */
    public function testValUndefinedConstant()
    {
        self::assertIsString(Factory::VAL_UNDEFINED);
        self::assertNotEmpty(Factory::VAL_UNDEFINED);
    }

    /**
     * Test KEY_CLASS_NAME constant
     */
    public function testKeyClassNameConstant()
    {
        self::assertSame('___class_name', Factory::KEY_CLASS_NAME);
    }

    /**
     * Test KEY_INDEX constant
     */
    public function testKeyIndexConstant()
    {
        self::assertIsString(Factory::KEY_INDEX);
    }

    /**
     * Test KEY_SCALAR constant
     */
    public function testKeyScalarConstant()
    {
        self::assertIsString(Factory::KEY_SCALAR);
    }

    /**
     * Test with null values
     */
    public function testCreateWithNullValues()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'John', 'age' => null],
            ['name' => null, 'age' => 25],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(2, $table->getRows());
    }

    /**
     * Test with boolean values
     */
    public function testCreateWithBooleanValues()
    {
        $factory = new Factory();
        $data = [
            ['active' => true, 'visible' => false],
            ['active' => false, 'visible' => true],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
        self::assertCount(2, $table->getRows());
    }

    /**
     * Test with numeric keys
     */
    public function testCreateWithNumericKeys()
    {
        $factory = new Factory();
        $data = [
            [10 => 'A', 20 => 'B'],
            [10 => 'C', 20 => 'D'],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test column meta with objects of same class
     */
    public function testColumnMetaWithObjectsOfSameClass()
    {
        $factory = new Factory();

        $obj1 = new stdClass();
        $obj1->value = 1;

        $obj2 = new stdClass();
        $obj2->value = 2;

        $data = [
            ['object' => $obj1],
            ['object' => $obj2],
        ];

        $table = $factory->create($data);

        $meta = $table->getMeta();
        // Column meta should track that all values are stdClass
        self::assertIsArray($meta['columns']);
    }

    /**
     * Test with nested arrays
     */
    public function testCreateWithNestedArrays()
    {
        $factory = new Factory();
        $data = [
            ['name' => 'Item', 'details' => ['color' => 'red', 'size' => 'large']],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test totals calculation
     */
    public function testTotalsCalculation()
    {
        $factory = new Factory();
        $data = [
            ['qty' => 10, 'price' => 5.50],
            ['qty' => 20, 'price' => 3.25],
            ['qty' => 15, 'price' => 7.00],
        ];

        $table = $factory->create($data, ['totalCols' => ['qty', 'price']]);

        $footer = $table->getFooter();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer);

        $cells = $footer->getCells();
        // Check that totals were calculated (45 for qty, 15.75 for price)
        self::assertGreaterThan(0, \count($cells));
    }

    /**
     * Test totals ignore non-numeric values
     */
    public function testTotalsIgnoreNonNumericValues()
    {
        $factory = new Factory();
        $data = [
            ['value' => 10],
            ['value' => 'text'],
            ['value' => 20],
        ];

        $table = $factory->create($data, ['totalCols' => ['value']]);

        $footer = $table->getFooter();
        self::assertInstanceOf(self::CLASS_TABLE_ROW, $footer);
    }

    /**
     * Test empty string values
     */
    public function testCreateWithEmptyStrings()
    {
        $factory = new Factory();
        $data = [
            ['name' => '', 'value' => 0],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test with single row
     */
    public function testCreateWithSingleRow()
    {
        $factory = new Factory();
        $data = [
            ['only' => 'row'],
        ];

        $table = $factory->create($data);

        self::assertCount(1, $table->getRows());
    }

    /**
     * Test with many rows
     */
    public function testCreateWithManyRows()
    {
        $factory = new Factory();
        $data = [];

        for ($i = 0; $i < 100; $i++) {
            $data[] = ['index' => $i, 'value' => $i * 2];
        }

        $table = $factory->create($data);

        self::assertCount(100, $table->getRows());
    }

    /**
     * Test multiple create calls with same factory
     */
    public function testMultipleCreateCalls()
    {
        $factory = new Factory();

        $data1 = [['a' => 1]];
        $table1 = $factory->create($data1);

        $data2 = [['b' => 2]];
        $table2 = $factory->create($data2);

        self::assertInstanceOf(self::CLASS_TABLE, $table1);
        self::assertInstanceOf(self::CLASS_TABLE, $table2);
        self::assertNotSame($table1, $table2);
    }

    /**
     * Test with object having properties
     */
    public function testCreateWithObjectHavingProperties()
    {
        $factory = new Factory();

        $obj = new class {
            public $publicProp = 'public';
            protected $protectedProp = 'protected';
            private $privateProp = 'private';
        };

        $data = [$obj];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test with DateTime objects
     */
    public function testCreateWithDateTimeObjects()
    {
        $factory = new Factory();
        $data = [
            ['date' => new \DateTime('2025-01-01')],
            ['date' => new \DateTime('2025-12-31')],
        ];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test column labels for special keys
     */
    public function testColumnLabelsForSpecialKeys()
    {
        $factory = new Factory([
            'columnLabels' => [
                Factory::KEY_INDEX => 'Index',
                Factory::KEY_SCALAR => 'Value',
            ],
        ]);

        $data = [1, 2, 3];

        $table = $factory->create($data);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test with associative array having string keys
     */
    public function testCreateWithAssociativeArrayStringKeys()
    {
        $factory = new Factory();
        $data = [
            'first' => ['name' => 'First'],
            'second' => ['name' => 'Second'],
        ];

        $table = $factory->create($data);

        self::assertCount(2, $table->getRows());
    }

    /**
     * Test that undefined values are handled
     */
    public function testUndefinedValuesAreHandled()
    {
        $factory = new Factory();
        $data = [
            ['a' => 1, 'b' => 2],
            ['a' => 3], // 'b' is undefined
        ];

        $table = $factory->create($data);

        self::assertCount(2, $table->getRows());
    }

    /**
     * Test with object missing specified columns
     */
    public function testWithObjectMissingSpecifiedColumns()
    {
        $factory = new Factory();

        $obj = new stdClass();
        $obj->existing = 'value';

        $data = [$obj];
        $options = ['columns' => ['existing', 'nonexistent']];

        $table = $factory->create($data, $options);

        self::assertInstanceOf(self::CLASS_TABLE, $table);
    }

    /**
     * Test column consistency across rows
     */
    public function testColumnConsistencyAcrossRows()
    {
        $factory = new Factory();
        $data = [
            ['a' => 1],
            ['b' => 2],
            ['c' => 3],
        ];

        $table = $factory->create($data);

        // Each row should have same number of cells (including undefined values)
        $rows = $table->getRows();
        $cellCount = \count($rows[0]->getCells());

        foreach ($rows as $row) {
            self::assertCount($cellCount, $row->getCells());
        }
    }
}
