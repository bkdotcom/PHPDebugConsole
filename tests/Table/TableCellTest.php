<?php

namespace bdk\Test\Table;

use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\Table\Element;
use bdk\Table\Factory;
use bdk\Table\TableCell;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for bdk\Table\TableCell
 *
 * @covers \bdk\Table\TableCell
 */
class TableCellTest extends TestCase
{
    use AssertionTrait;

    const CLASS_TABLE_CELL = 'bdk\\Table\\TableCell';

    /**
     * Store original val dumper to restore after tests
     */
    private static $originalValDumper;

    /**
     * Set up before class
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TableCell::setValDumper(['bdk\Table\TableCell', 'valDumper']);
        // Store original dumper
        $reflection = new \ReflectionClass(self::CLASS_TABLE_CELL);
        $property = $reflection->getProperty('valDumper');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        self::$originalValDumper = $property->getValue();
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        parent::tearDown();
        // Restore original dumper
        TableCell::setValDumper(self::$originalValDumper);
    }

    /**
     * Test basic constructor
     */
    public function testConstructorBasic()
    {
        $cell = new TableCell();

        self::assertSame('td', $cell->getTagName());
        self::assertNull($cell->getValue());
    }

    /**
     * Test constructor with string value
     */
    public function testConstructorWithString()
    {
        $cell = new TableCell('Test Value');

        self::assertSame('Test Value', $cell->getValue());
        self::assertSame('td', $cell->getTagName());
    }

    /**
     * Test constructor with integer value
     */
    public function testConstructorWithInteger()
    {
        $cell = new TableCell(42);

        self::assertSame(42, $cell->getValue());
    }

    /**
     * Test constructor with float value
     */
    public function testConstructorWithFloat()
    {
        $cell = new TableCell(3.14);

        self::assertSame(3.14, $cell->getValue());
    }

    /**
     * Test constructor with boolean value
     */
    public function testConstructorWithBoolean()
    {
        $cell = new TableCell(true);

        self::assertTrue($cell->getValue());
    }

    /**
     * Test constructor with null value
     */
    public function testConstructorWithNull()
    {
        $cell = new TableCell(null);

        self::assertNull($cell->getValue());
    }

    /**
     * Test constructor with array value
     */
    public function testConstructorWithArrayValue()
    {
        $cell = new TableCell(['a', 'b', 'c']);

        self::assertSame(['a', 'b', 'c'], $cell->getValue());
    }

    /**
     * Test constructor with associative array (properties)
     */
    public function testConstructorWithProperties()
    {
        $cell = new TableCell([
            'attribs' => ['class' => 'highlight'],
            'tagName' => 'th',
            'value' => 'Test',
        ]);

        self::assertSame('Test', $cell->getValue());
        self::assertSame(['class' => ['highlight']], $cell->getAttribs());
        self::assertSame('th', $cell->getTagName());
    }

    /**
     * Test getValue
     */
    public function testGetValue()
    {
        $cell = new TableCell('My Value');

        self::assertSame('My Value', $cell->getValue());
    }

    /**
     * Test setValue
     */
    public function testSetValue()
    {
        $cell = new TableCell();

        $result = $cell->setValue('New Value');

        self::assertSame($cell, $result);
        self::assertSame('New Value', $cell->getValue());
    }

    /**
     * Test setValue with different types
     */
    public function testSetValueDifferentTypes()
    {
        $cell = new TableCell();

        $cell->setValue(100);
        self::assertSame(100, $cell->getValue());

        $cell->setValue(true);
        self::assertTrue($cell->getValue());

        $cell->setValue(['array']);
        self::assertSame(['array'], $cell->getValue());
    }

    /**
     * Test getHtml with explicit HTML set
     */
    public function testGetHtmlWithExplicitHtml()
    {
        $cell = new TableCell('value');
        $cell->setHtml('<strong>Bold</strong>');

        $html = $cell->getHtml();

        self::assertSame('<strong>Bold</strong>', $html);
    }

    /**
     * Test getHtml with value dumper (default)
     */
    public function testGetHtmlWithValueDumper()
    {
        $cell = new TableCell('Test String');

        $html = $cell->getHtml();

        self::assertStringContainsString('Test String', $html);
        // Default dumper adds class
        self::assertArrayHasKey('class', $cell->getAttribs());
    }

    /**
     * Test getHtml with string value
     */
    public function testGetHtmlStringValue()
    {
        $cell = new TableCell('Hello World');

        $html = $cell->getHtml();

        self::assertSame('Hello World', $html);
        self::assertContains('t_string', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with integer value
     */
    public function testGetHtmlIntegerValue()
    {
        $cell = new TableCell(123);

        $html = $cell->getHtml();

        self::assertSame('123', $html);
        self::assertContains('t_int', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with float value
     */
    public function testGetHtmlFloatValue()
    {
        $cell = new TableCell(45.67);

        $html = $cell->getHtml();

        self::assertSame('45.67', $html);
        self::assertContains('t_float', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with true value
     */
    public function testGetHtmlTrueValue()
    {
        $cell = new TableCell(true);

        $html = $cell->getHtml();

        self::assertSame('true', $html);
        self::assertContains('t_true', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with false value
     */
    public function testGetHtmlFalseValue()
    {
        $cell = new TableCell(false);

        $html = $cell->getHtml();

        self::assertSame('false', $html);
        self::assertContains('t_false', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with null value
     */
    public function testGetHtmlNullValue()
    {
        $cell = new TableCell(null);

        $html = $cell->getHtml();

        self::assertSame('null', $html);
        self::assertContains('t_null', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with array value
     */
    public function testGetHtmlArrayValue()
    {
        $cell = new TableCell(['a', 'b', 'c']);

        $html = $cell->getHtml();

        self::assertStringContainsString('Array', $html);
        self::assertContains('t_array', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with object having __toString
     */
    public function testGetHtmlObjectWithToString()
    {
        $obj = new \bdk\Test\Table\Fixture\Stringable('String Representation');

        $cell = new TableCell($obj);
        $html = $cell->getHtml();

        self::assertSame('String Representation', $html);
        self::assertContains('t_object', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with DateTime object
     */
    public function testGetHtmlDateTimeObject()
    {
        $date = new DateTime('2025-12-29 10:30:00');

        $cell = new TableCell($date);
        $html = $cell->getHtml();

        self::assertStringContainsString('2025-12-29', $html);
        self::assertStringContainsString('10:30:00', $html);
        self::assertContains('t_object', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with DateTimeImmutable object
     */
    /*
    public function testGetHtmlDateTimeImmutableObject()
    {
        $date = new DateTimeImmutable('2025-01-15 14:45:30');

        $cell = new TableCell($date);
        $html = $cell->getHtml();

        self::assertStringContainsString('2025-01-15', $html);
        self::assertStringContainsString('14:45:30', $html);
        self::assertContains('t_object', $cell->getAttribs()['class']);
    }
    */

    /**
     * Test getHtml with object without __toString
     */
    public function testGetHtmlObjectWithoutToString()
    {
        $obj = new \stdClass();

        $cell = new TableCell($obj);
        $html = $cell->getHtml();

        self::assertSame('stdClass', $html);
        self::assertContains('t_object', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with undefined value
     */
    public function testGetHtmlUndefinedValue()
    {
        $cell = new TableCell(Factory::VAL_UNDEFINED);

        $html = $cell->getHtml();

        self::assertSame('', $html);
        self::assertContains('t_undefined', $cell->getAttribs()['class']);
    }

    /**
     * Test getHtml with special characters
     */
    public function testGetHtmlSpecialCharacters()
    {
        $cell = new TableCell('<script>alert("XSS")</script>');

        $html = $cell->getHtml();

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    /**
     * Test setValDumper
     */
    public function testSetValDumper()
    {
        $customDumper = static function ($cell) {
            return 'CUSTOM: ' . $cell->getValue();
        };

        TableCell::setValDumper($customDumper);

        $cell = new TableCell('test');
        $html = $cell->getHtml();

        self::assertSame('CUSTOM: test', $html);
    }

    /**
     * Test custom val dumper with modification
     */
    public function testCustomValDumperModifiesCell()
    {
        $customDumper = static function ($cell) {
            $cell->setAttrib('data-custom', 'true');
            return 'Modified';
        };

        TableCell::setValDumper($customDumper);

        $cell = new TableCell('value');
        $html = $cell->getHtml();

        self::assertSame('Modified', $html);
        self::assertSame('true', $cell->getAttribs()['data-custom']);
    }

    /**
     * Test __serialize
     */
    public function testSerialize()
    {
        $cell = new TableCell('Test Value');
        $cell->setAttrib('id', 'cell-1');

        $data = $cell->__serialize();

        self::assertIsArray($data);
        self::assertArrayHasKey('value', $data);
        self::assertSame('Test Value', $data['value']);
        self::assertArrayHasKey('attribs', $data);
    }

    /**
     * Test serialize with complex value
     */
    public function testSerializeComplexValue()
    {
        $cell = new TableCell(['nested' => ['array' => 'value']]);

        $data = $cell->__serialize();

        self::assertSame(['nested' => ['array' => 'value']], $data['value']);
    }

    /**
     * Test getOuterHtml
     */
    public function testGetOuterHtml()
    {
        $cell = new TableCell('Content');
        $cell->setAttrib('class', 'highlight');

        $html = $cell->getOuterHtml();

        self::assertStringContainsString('<td', $html);
        self::assertStringContainsString('</td>', $html);
        self::assertStringContainsString('Content', $html);
    }

    /**
     * Test th cell
     */
    public function testThCell()
    {
        $cell = new TableCell('Header');
        $cell->setTagName('th');

        $html = $cell->getOuterHtml();

        self::assertStringContainsString('<th', $html);
        self::assertStringContainsString('</th>', $html);
    }

    /**
     * Test fluent interface
     */
    public function testFluentInterface()
    {
        $cell = new TableCell();

        $result = $cell
            ->setValue('Value')
            ->setAttrib('class', 'test')
            ->setTagName('th');

        self::assertSame($cell, $result);
        self::assertSame('Value', $cell->getValue());
        self::assertSame(['class' => ['test']], $cell->getAttribs());
        self::assertSame('th', $cell->getTagName());
    }

    /**
     * Test cell with attributes
     */
    public function testCellWithAttributes()
    {
        $cell = new TableCell('Data');
        $cell->setAttrib('colspan', '2');
        $cell->setAttrib('rowspan', '3');
        $cell->setAttrib('data-value', '123');

        $attribs = $cell->getAttribs();

        self::assertSame('2', $attribs['colspan']);
        self::assertSame('3', $attribs['rowspan']);
        self::assertSame('123', $attribs['data-value']);
    }

    /**
     * Test cell parent relationship
     */
    public function testCellParentRelationship()
    {
        $parent = new Element('tr');
        $cell = new TableCell('Value');

        $cell->setParent($parent);

        self::assertSame($parent, $cell->getParent());
    }

    /**
     * Test cell defaults
     */
    public function testCellDefaults()
    {
        $cell = new TableCell('Value');

        // tagName should be 'td' which is the default
        self::assertSame('td', $cell->getTagName());

        // When serialized, default tagName should not appear
        $data = $cell->__serialize();
        self::assertArrayNotHasKey('tagName', $data);
    }

    /**
     * Test cell with meta data
     */
    public function testCellWithMetaData()
    {
        $cell = new TableCell('Value');
        $cell->setMeta('sortValue', 100);
        $cell->setMeta('dataType', 'number');

        self::assertSame(100, $cell->getMeta('sortValue'));
        self::assertSame('number', $cell->getMeta('dataType'));
    }

    /**
     * Test constructor property detection
     */
    public function testConstructorPropertyDetection()
    {
        // Non-array value
        $cell1 = new TableCell('simple');
        self::assertSame('simple', $cell1->getValue());

        // Array without property names
        $cell2 = new TableCell(['a', 'b', 'c']);
        self::assertSame(['a', 'b', 'c'], $cell2->getValue());

        // Array with property names
        $cell3 = new TableCell([
            'attribs' => ['id' => 'my-cell'],
            'value' => 'test',
        ]);
        self::assertSame('test', $cell3->getValue());
        self::assertSame(['id' => 'my-cell'], $cell3->getAttribs());
    }

    /**
     * Test getHtml is called only once (caching via buildingHtml flag)
     */
    public function testGetHtmlCaching()
    {
        $callCount = 0;
        $customDumper = static function ($cell) use (&$callCount) {
            $callCount++;
            return 'dumped';
        };

        TableCell::setValDumper($customDumper);

        $cell = new TableCell('value');

        // First call
        $html1 = $cell->getHtml();
        self::assertSame(1, $callCount);

        // Setting explicit HTML should not call dumper
        $cell->setHtml('explicit');
        $html2 = $cell->getHtml();
        self::assertSame(1, $callCount);
        self::assertSame('explicit', $html2);
    }

    /**
     * Test empty string value
     */
    public function testEmptyStringValue()
    {
        $cell = new TableCell('');

        self::assertSame('', $cell->getValue());

        $html = $cell->getHtml();
        self::assertSame('', $html);
    }

    /**
     * Test zero values
     */
    public function testZeroValues()
    {
        $cell1 = new TableCell(0);
        self::assertSame(0, $cell1->getValue());
        self::assertSame('0', $cell1->getHtml());

        $cell2 = new TableCell(0.0);
        self::assertSame(0.0, $cell2->getValue());
        self::assertSame('0', $cell2->getHtml());

        $cell3 = new TableCell('0');
        self::assertSame('0', $cell3->getValue());
        self::assertSame('0', $cell3->getHtml());
    }

    /**
     * Test negative numbers
     */
    public function testNegativeNumbers()
    {
        $cell1 = new TableCell(-42);
        self::assertSame('-42', $cell1->getHtml());

        $cell2 = new TableCell(-3.14);
        self::assertSame('-3.14', $cell2->getHtml());
    }

    /**
     * Test scientific notation
     */
    public function testScientificNotation()
    {
        $cell = new TableCell(1.23e-10);

        $html = $cell->getHtml();
        self::assertIsString($html);
    }

    /**
     * Test JSON serialization
     */
    public function testJsonSerialize()
    {
        $cell = new TableCell('Test');

        $json = $cell->jsonSerialize();

        self::assertIsArray($json);
        self::assertArrayHasKey('value', $json);
    }

    /**
     * Test cell with classes
     */
    public function testCellWithClasses()
    {
        $cell = new TableCell('Value');
        $cell->addClass('cell-primary');
        $cell->addClass('cell-center');

        $attribs = $cell->getAttribs();

        self::assertContains('cell-primary', $attribs['class']);
        self::assertContains('cell-center', $attribs['class']);
    }

    /**
     * Test valDumper adds type class
     */
    public function testValDumperAddsTypeClass()
    {
        $testCases = [
            ['value' => 'string', 'expectedClass' => 't_string'],
            ['value' => 123, 'expectedClass' => 't_int'],
            ['value' => 45.67, 'expectedClass' => 't_float'],
            ['value' => true, 'expectedClass' => 't_true'],
            ['value' => false, 'expectedClass' => 't_false'],
            ['value' => null, 'expectedClass' => 't_null'],
            ['value' => ['array'], 'expectedClass' => 't_array'],
        ];

        foreach ($testCases as $testCase) {
            $cell = new TableCell($testCase['value']);
            $cell->getHtml(); // Trigger dumper

            $attribs = $cell->getAttribs();
            self::assertContains(
                $testCase['expectedClass'],
                $attribs['class'],
                'Failed for value type: ' . \gettype($testCase['value'])
            );
        }
    }

    /**
     * Test resource type (edge case)
     */
    public function testResourceType()
    {
        $resource = \fopen('php://memory', 'r');

        $cell = new TableCell($resource);
        $html = $cell->getHtml();

        self::assertIsString($html);

        \fclose($resource);
    }
}
