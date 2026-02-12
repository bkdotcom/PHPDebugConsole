<?php

namespace bdk\Test\Table;

use bdk\Table\Element;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use ErrorException;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

/**
 * PHPUnit tests for bdk\Table\Element
 *
 * @covers \bdk\Table\Element
 */
class ElementTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    /**
     * Test basic constructor with tag name
     */
    public function testConstructorBasic()
    {
        $element = new Element('div');
        self::assertSame('div', $element->getTagName());
        self::assertSame([], $element->getChildren());
        self::assertSame('', $element->getHtml());
    }

    /**
     * Test constructor with HTML content
     */
    public function testConstructorWithHtml()
    {
        $element = new Element('span', 'Hello World');
        self::assertSame('span', $element->getTagName());
        self::assertSame('Hello World', $element->getHtml());
    }

    /**
     * Test constructor with children array
     */
    public function testConstructorWithChildren()
    {
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('span', 'Child 2');
        $element = new Element('div', [$child1, $child2]);

        self::assertSame('div', $element->getTagName());
        self::assertCount(2, $element->getChildren());
        self::assertSame($element, $child1->getParent());
        self::assertSame($element, $child2->getParent());
    }

    /**
     * Test constructor with associative array of properties
     */
    public function testConstructorWithProperties()
    {
        $element = new Element('div', [
            'attribs' => ['class' => 'test-class', 'id' => 'test-id'],
            'html' => 'Test content',
            'meta' => ['key' => 'value'],
        ]);

        self::assertSame('div', $element->getTagName());
        self::assertSame('Test content', $element->getHtml());
        self::assertSame(['class' => ['test-class'], 'id' => 'test-id'], $element->getAttribs());
        self::assertSame('value', $element->getMeta('key'));
    }

    /**
     * Test setTagName / getTagName
     */
    public function testTagName()
    {
        $element = new Element('div');
        self::assertSame('div', $element->getTagName());

        $element->setTagName('SPAN');
        self::assertSame('span', $element->getTagName());

        $element->setTagName('TD');
        self::assertSame('td', $element->getTagName());
    }

    /**
     * Test setHtml / getHtml
     */
    public function testHtml()
    {
        $element = new Element('div');
        self::assertSame('', $element->getHtml());

        $element->setHtml('<strong>Bold</strong>');
        self::assertSame('<strong>Bold</strong>', $element->getHtml());
    }

    /**
     * Test setText / getText
     */
    public function testText()
    {
        $element = new Element('div');
        $element->setText('Test & "quotes"');

        self::assertSame('Test &amp; &quot;quotes&quot;', $element->getHtml());
        self::assertSame('Test & "quotes"', $element->getText());
    }

    /**
     * Test getText with nested children
     */
    public function testGetTextWithChildren()
    {
        $child1 = new Element('span');
        $child1->setText('Hello ');

        $child2 = new Element('strong');
        $child2->setText('World');

        $parent = new Element('div', [$child1, $child2]);

        self::assertSame('Hello World', $parent->getText());
    }

    /**
     * Test setAttrib / getAttribs
     */
    public function testAttribs()
    {
        $element = new Element('div');

        $element->setAttrib('id', 'test-id');
        self::assertSame(['id' => 'test-id'], $element->getAttribs());

        $element->setAttrib('data-value', '123');
        self::assertSame([
            'data-value' => '123',
            'id' => 'test-id',
        ], $element->getAttribs());
    }

    /**
     * Test setAttribs (plural)
     */
    public function testSetAttribs()
    {
        $element = new Element('div');
        $element->setAttribs([
            'id' => 'test-id',
            'class' => 'test-class',
            'data-value' => 'abc',
        ]);

        self::assertSame([
            'class' => ['test-class'],
            'data-value' => 'abc',
            'id' => 'test-id',
        ], $element->getAttribs());
    }

    /**
     * Test addClass
     */
    public function testAddClass()
    {
        $element = new Element('div');

        $element->addClass('class1');
        self::assertSame(['class' => ['class1']], $element->getAttribs());

        $element->addClass('class2');
        self::assertSame(['class' => ['class1', 'class2']], $element->getAttribs());

        $element->addClass(['class3', 'class4']);
        self::assertSame(['class' => ['class1', 'class2', 'class3', 'class4']], $element->getAttribs());

        // Test duplicate class
        $element->addClass('class1');
        self::assertSame(['class' => ['class1', 'class2', 'class3', 'class4']], $element->getAttribs());
    }

    /**
     * Test addClass with space-separated string
     */
    public function testAddClassWithSpaces()
    {
        $element = new Element('div');
        $element->addClass('class1 class2 class3');

        self::assertSame(['class' => ['class1', 'class2', 'class3']], $element->getAttribs());
    }

    /**
     * Test addClass with array of classname => bool
     */
    public function testAddClassWithBooleanArray()
    {
        $element = new Element('div');

        // Add classes conditionally based on boolean values
        $element->addClass([
            'active' => true,
            'disabled' => false,
            'highlight' => true,
        ]);

        // Only classes with true values should be added
        self::assertSame(['class' => ['active', 'highlight']], $element->getAttribs());

        // Test with existing classes
        $element->addClass([
            'disabled' => true,
            'active' => false,  // Should remove active
        ]);

        // 'active' should be removed, 'disabled' added
        self::assertSame(['class' => ['disabled', 'highlight']], $element->getAttribs());
    }

    /**
     * Test addClass with mixed array (indexed and associative)
     */
    public function testAddClassWithMixedArray()
    {
        $element = new Element('div');
        $element->addClass('existing');

        // Mix of indexed (always add) and associative (conditional)
        $element->addClass([
            'always-added',
            'conditional-true' => true,
            'conditional-false' => false,
        ]);

        self::assertSame(['class' => ['always-added', 'conditional-true', 'existing']], $element->getAttribs());
    }

    /**
     * Test removeClass
     */
    public function testRemoveClass()
    {
        $element = new Element('div');
        $element->addClass(['class1', 'class2', 'class3']);

        $element->removeClass('class2');
        self::assertSame(['class' => ['class1', 'class3']], $element->getAttribs());

        $element->removeClass(['class1', 'class3']);
        self::assertSame([], $element->getAttribs());
    }

    /**
     * Test removeClass with space-separated string
     */
    public function testRemoveClassWithSpaces()
    {
        $element = new Element('div');
        $element->addClass('class1 class2 class3');
        $element->removeClass('class1 class3');

        self::assertSame(['class' => ['class2']], $element->getAttribs());
    }

    /**
     * Test appendChild
     */
    public function testAppendChild()
    {
        $parent = new Element('div');
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('span', 'Child 2');

        $parent->appendChild($child1);
        self::assertCount(1, $parent->getChildren());
        self::assertSame($parent, $child1->getParent());

        $parent->appendChild($child2);
        self::assertCount(2, $parent->getChildren());
        self::assertSame($parent, $child2->getParent());
    }

    /**
     * Test setChildren
     */
    public function testSetChildren()
    {
        $parent = new Element('div');
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('span', 'Child 2');

        $parent->setChildren([$child1, $child2]);

        self::assertCount(2, $parent->getChildren());
        self::assertSame($parent, $child1->getParent());
        self::assertSame($parent, $child2->getParent());
    }

    /**
     * Test setChildren with invalid child throws exception
     */
    public function testSetChildrenInvalid()
    {
        self::assertExceptionOrTypeError(function () {
            $parent = new Element('div');
            $parent->setChildren(['not an Element object']);
        });
    }

    /**
     * Test getChildren
     */
    public function testGetChildren()
    {
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('span', 'Child 2');
        $parent = new Element('div', [$child1, $child2]);

        $children = $parent->getChildren();
        self::assertCount(2, $children);
        self::assertSame($child1, $children[0]);
        self::assertSame($child2, $children[1]);
    }

    /**
     * Test getHtml with children
     */
    public function testGetHtmlWithChildren()
    {
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('strong', 'Child 2');
        $parent = new Element('div', [$child1, $child2]);

        $html = $parent->getHtml();
        self::assertStringContainsString('<span>Child 1</span>', $html);
        self::assertStringContainsString('<strong>Child 2</strong>', $html);
    }

    /**
     * Test setParent / getParent
     */
    public function testParent()
    {
        $parent = new Element('div');
        $child = new Element('span');

        self::assertNull($child->getParent());

        $child->setParent($parent);
        self::assertSame($parent, $child->getParent());

        $child->setParent(null);
        self::assertNull($child->getParent());
    }

    /**
     * Test setParent with invalid parent throws exception
     */
    public function testSetParentInvalid()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Parent must be instance of');

        $child = new Element('span');
        $child->setParent('not an Element object');
    }

    /**
     * Test getIndex
     */
    public function testGetIndex()
    {
        $parent = new Element('div');
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('span', 'Child 2');
        $child3 = new Element('span', 'Child 3');

        $parent->setChildren([$child1, $child2, $child3]);

        self::assertSame(0, $child1->getIndex());
        self::assertSame(1, $child2->getIndex());
        self::assertSame(2, $child3->getIndex());
    }

    /**
     * Test getIndex with no parent
     */
    public function testGetIndexNoParent()
    {
        $element = new Element('div');
        self::assertNull($element->getIndex());
    }

    /**
     * Test setMeta / getMeta
     */
    public function testMeta()
    {
        $element = new Element('div');

        $element->setMeta('key1', 'value1');
        self::assertSame('value1', $element->getMeta('key1'));

        $element->setMeta('key2', 'value2');
        self::assertSame('value2', $element->getMeta('key2'));

        // Test getting non-existent key
        self::assertNull($element->getMeta('nonexistent'));
        self::assertSame('default', $element->getMeta('nonexistent', 'default'));
    }

    /**
     * Test setMeta with array
     */
    public function testSetMetaArray()
    {
        $element = new Element('div');
        $element->setMeta([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        self::assertSame('value1', $element->getMeta('key1'));
        self::assertSame('value2', $element->getMeta('key2'));
    }

    /**
     * Test getMeta with no key returns all meta
     */
    public function testGetMetaAll()
    {
        $element = new Element('div');
        $element->setMeta([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        self::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $element->getMeta());
    }

    /**
     * Test setDefaults
     */
    public function testSetDefaults()
    {
        $element = new Element('div');
        $element->setDefaults([
            'tagName' => 'div',
            'attribs' => ['class' => ['default-class']],
        ]);

        // Defaults should be merged with current attribs
        $element->setAttrib('id', 'test-id');
        self::assertSame([
            'class' => ['default-class'],
            'id' => 'test-id',
        ], $element->getAttribs());
    }

    /**
     * Test getOuterHtml
     */
    public function testGetOuterHtml()
    {
        $element = new Element('div', 'Hello World');
        $element->setAttrib('id', 'test-id');
        $element->setAttrib('class', 'test-class');

        $html = $element->getOuterHtml();
        self::assertSame('<div class="test-class" id="test-id">Hello World</div>', $html);
    }

    /**
     * Test getOuterHtml with children
     */
    public function testGetOuterHtmlWithChildren()
    {
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('strong', 'Child 2');
        $parent = new Element('div', [$child1, $child2]);
        $parent->setAttrib('id', 'parent');

        $html = $parent->getOuterHtml();

        self::assertStringContainsString('<div id="parent">', $html);
        self::assertStringContainsString('<span>Child 1</span>', $html);
        self::assertStringContainsString('<strong>Child 2</strong>', $html);
        self::assertStringContainsString('</div>', $html);
    }

    /**
     * Test __serialize
     */
    public function testSerialize()
    {
        $element = new Element('span', 'Test content');
        $element->setAttrib('id', 'test-id');
        $element->setMeta('key', 'value');

        $data = $element->__serialize();

        // tagName should be in data since it's different from default 'div'
        self::assertArrayHasKey('tagName', $data);
        self::assertArrayHasKey('html', $data);
        self::assertArrayHasKey('attribs', $data);
        self::assertArrayHasKey('meta', $data);
        self::assertSame('span', $data['tagName']);
        self::assertSame('Test content', $data['html']);
        self::assertSame(['id' => 'test-id'], $data['attribs']);
        self::assertSame(['key' => 'value'], $data['meta']);
    }

    /**
     * Test __serialize with children
     */
    public function testSerializeWithChildren()
    {
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('strong', 'Child 2');
        $parent = new Element('div', [$child1, $child2]);

        $data = $parent->__serialize();

        self::assertArrayHasKey('children', $data);
        self::assertCount(2, $data['children']);
    }

    /**
     * Test __unserialize
     */
    public function testUnserialize()
    {
        $element = new Element('span');
        $element->__unserialize([
            'tagName' => 'div',
            'html' => 'Test content',
            'attribs' => ['id' => 'test-id'],
            'meta' => ['key' => 'value'],
        ]);

        self::assertSame('div', $element->getTagName());
        self::assertSame('Test content', $element->getHtml());
        self::assertSame(['id' => 'test-id'], $element->getAttribs());
        self::assertSame('value', $element->getMeta('key'));
    }

    /**
     * Test serialize / unserialize methods (Serializable interface)
     */
    public function testSerializableMethods()
    {
        $element = new Element('span', 'Test content');
        $element->setAttrib('id', 'test-id');
        $element->setMeta('key', 'value');

        $serialized = $element->serialize();
        self::assertIsString($serialized);

        $newElement = new Element('div');
        $newElement->unserialize($serialized);

        self::assertSame('span', $newElement->getTagName());
        self::assertSame('Test content', $newElement->getHtml());
        self::assertSame(['id' => 'test-id'], $newElement->getAttribs());
        self::assertSame('value', $newElement->getMeta('key'));
    }

    /**
     * Test jsonSerialize
     */
    public function testJsonSerialize()
    {
        $element = new Element('span', 'Test content');
        $element->setAttrib('id', 'test-id');

        $json = $element->jsonSerialize();

        self::assertIsArray($json);
        self::assertArrayHasKey('tagName', $json);
        self::assertArrayHasKey('html', $json);
        self::assertArrayHasKey('attribs', $json);
    }

    /**
     * Test jsonSerialize with only HTML returns string
     */
    public function testJsonSerializeHtmlOnly()
    {
        $element = new Element('div', 'Test content');

        $json = $element->jsonSerialize();

        self::assertSame('Test content', $json);
    }

    /**
     * Test jsonSerialize with only children returns array
     */
    public function testJsonSerializeChildrenOnly()
    {
        $child1 = new Element('span', 'Child 1');
        $child2 = new Element('strong', 'Child 2');
        $parent = new Element('div', [$child1, $child2]);

        $json = $parent->jsonSerialize();

        self::assertIsArray($json);
        self::assertCount(2, $json);
    }

    /**
     * Test that empty values are not serialized
     */
    public function testSerializeEmptyValues()
    {
        $element = new Element('span');

        $data = $element->__serialize();

        // Empty arrays and nulls should not be included, but tagName differs from default
        self::assertArrayHasKey('tagName', $data);
        self::assertSame('span', $data['tagName']);
        // html is empty string which gets filtered out
        self::assertArrayNotHasKey('attribs', $data);
        self::assertArrayNotHasKey('meta', $data);
        self::assertArrayNotHasKey('children', $data);
    }

    /**
     * Test that default values are not serialized
     */
    public function testSerializeDefaultValues()
    {
        $element = new Element('div');
        $element->setDefaults([
            'tagName' => 'div',
            'attribs' => ['class' => ['default-class']],
        ]);

        $data = $element->__serialize();

        // Default tagName should not be in serialized data
        self::assertArrayNotHasKey('tagName', $data);
    }

    /**
     * Test fluent interface (chaining)
     */
    public function testFluentInterface()
    {
        $element = new Element('div');

        $result = $element
            ->setTagName('span')
            ->setHtml('Test')
            ->setAttrib('id', 'test-id')
            ->addClass('test-class')
            ->setMeta('key', 'value');

        self::assertSame($element, $result);
        self::assertSame('span', $element->getTagName());
        self::assertSame('Test', $element->getHtml());
        self::assertSame([
            'class' => ['test-class'],
            'id' => 'test-id',
        ], $element->getAttribs());
        self::assertSame('value', $element->getMeta('key'));
    }

    /**
     * Test complex nested structure
     */
    public function testComplexNestedStructure()
    {
        $grandchild1 = new Element('em', 'Emphasized');
        $grandchild2 = new Element('strong', 'Bold');

        $child1 = new Element('span', [$grandchild1]);
        $child1->setAttrib('class', 'child-span');

        $child2 = new Element('div', [$grandchild2]);
        $child2->setAttrib('id', 'child-div');

        $parent = new Element('article', [$child1, $child2]);
        $parent->setAttrib('class', 'parent-article');

        // Test structure
        self::assertSame($parent, $child1->getParent());
        self::assertSame($parent, $child2->getParent());
        self::assertSame($child1, $grandchild1->getParent());
        self::assertSame($child2, $grandchild2->getParent());

        // Test HTML generation
        $html = $parent->getOuterHtml();
        self::assertStringContainsString('<article class="parent-article">', $html);
        self::assertStringContainsString('<span class="child-span">', $html);
        self::assertStringContainsString('<div id="child-div">', $html);
        self::assertStringContainsString('<em>Emphasized</em>', $html);
        self::assertStringContainsString('<strong>Bold</strong>', $html);
    }

    /**
     * Test setting and getting empty class attribute
     */
    public function testEmptyClassAttribute()
    {
        $element = new Element('div');
        $element->setAttrib('class', []);

        // Empty class should not be in attributes
        self::assertSame([], $element->getAttribs());
    }

    /**
     * Test class normalization with duplicates and empty strings
     */
    public function testClassNormalization()
    {
        $element = new Element('div');
        $element->addClass(['class1', '', 'class2', 'class1', 'class3', '']);

        // Should filter out empty strings and duplicates, and sort
        self::assertSame(['class' => ['class1', 'class2', 'class3']], $element->getAttribs());
    }

    /**
     * Test unserialize with invalid data
     */
    public function testUnserializeWithInvalidData()
    {
        $element = new Element('div', 'Original');

        // Unserialize with non-array should not change element
        $element->unserialize(\serialize('not an array'));

        self::assertSame('div', $element->getTagName());
        self::assertSame('Original', $element->getHtml());
    }

    /**
     * Test constructor with empty associative array
     */
    public function testConstructorWithEmptyAssociativeArray()
    {
        $element = new Element('div', []);

        self::assertSame('div', $element->getTagName());
        self::assertSame([], $element->getChildren());
    }

    /**
     * Test getAttribs with defaults
     */
    public function testGetAttribsWithDefaults()
    {
        $element = new Element('div');
        $element->setDefaults([
            'attribs' => [
                'class' => ['default-class'],
                'data-default' => 'value',
            ],
        ]);

        $element->setAttrib('id', 'test-id');
        $element->addClass('custom-class');

        $attribs = $element->getAttribs();

        // Defaults should be merged with custom attributes
        self::assertArrayHasKey('class', $attribs);
        self::assertContains('default-class', $attribs['class']);
        self::assertContains('custom-class', $attribs['class']);
        self::assertSame('value', $attribs['data-default']);
        self::assertSame('test-id', $attribs['id']);
    }

    /**
     * Test getText with HTML entities
     */
    public function testGetTextWithHtmlEntities()
    {
        $element = new Element('div');
        $element->setHtml('Test &lt;tag&gt; &amp; &quot;quotes&quot;');

        // getText should decode HTML entities
        self::assertSame('Test <tag> & "quotes"', $element->getText());
    }

    /**
     * Test getText with nested HTML tags
     */
    public function testGetTextWithNestedTags()
    {
        $element = new Element('div');
        $element->setHtml('<strong>Bold</strong> and <em>italic</em> text');

        // getText should strip all tags
        self::assertSame('Bold and italic text', $element->getText());
    }

    /**
     * Test serialization of nested children
     */
    public function testSerializeNestedChildren()
    {
        $grandchild = new Element('em', 'Nested');
        $child = new Element('span', [$grandchild]);
        $parent = new Element('div', [$child]);

        $data = $parent->__serialize();

        self::assertArrayHasKey('children', $data);
        self::assertIsArray($data['children']);
        self::assertCount(1, $data['children']);
    }

    /**
     * Test that addClass returns the element for chaining
     */
    public function testAddClassReturnsElement()
    {
        $element = new Element('div');
        $result = $element->addClass('test-class');

        self::assertSame($element, $result);
    }

    /**
     * Test that removeClass returns the element for chaining
     */
    public function testRemoveClassReturnsElement()
    {
        $element = new Element('div');
        $element->addClass('test-class');
        $result = $element->removeClass('test-class');

        self::assertSame($element, $result);
    }

    /**
     * Test that appendChild returns the element for chaining
     */
    public function testAppendChildReturnsElement()
    {
        $parent = new Element('div');
        $child = new Element('span');
        $result = $parent->appendChild($child);

        self::assertSame($parent, $result);
    }

    /**
     * Test empty HTML string
     */
    public function testEmptyHtmlString()
    {
        $element = new Element('div', '');

        self::assertSame('', $element->getHtml());
        self::assertSame('', $element->getText());
    }

    /**
     * Test getHtml with both children and HTML set
     */
    public function testGetHtmlPrioritizesChildren()
    {
        $child = new Element('span', 'Child');
        $parent = new Element('div', [$child]);

        // Setting HTML doesn't affect children
        $parent->setHtml('New HTML content');

        // Children still exist
        self::assertCount(1, $parent->getChildren());

        // getHtml returns children HTML when children exist, not the set HTML
        $html = $parent->getHtml();
        self::assertStringContainsString('<span>Child</span>', $html);
    }

    protected static function assertExceptionOrTypeError($callable)
    {
        try {
            $callable();
        } catch (ErrorException $e) {
            // self::assertSame('A non well formed numeric value encountered', $e->getMessage());
            self::assertTrue(true);
            return;
        } catch (RuntimeException $e) {
            // self::assertSame('A non well formed numeric value encountered', $e->getMessage());
            self::assertTrue(true);
            return;
        } catch (InvalidArgumentException $e) {
            self::assertTrue(true);
            return;
        } catch (TypeError $e) {
            self::assertSame(\get_class($e), 'TypeError');
            return;
        }
        throw new AssertionFailedError('Exception not thrown');
    }
}
