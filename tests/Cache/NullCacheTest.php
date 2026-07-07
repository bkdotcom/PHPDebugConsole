<?php

namespace bdk\Test\Cache;

use bdk\Cache\NullCache;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Cache\AbstractBase
 * @covers \bdk\Cache\NullCache
 */
class NullCacheTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    /** @var NullCache */
    private $cache;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $this->cache = new NullCache();
    }

    /**
     * Test constructor
     */
    public function testConstructor()
    {
        $cache = new NullCache();
        $this->assertInstanceOf('bdk\\Cache\\NullCache', $cache);
    }

    /**
     * Test get always returns default value
     */
    public function testGet()
    {
        $value = $this->cache->get('test_key', 'default_value');
        $this->assertSame('default_value', $value);
    }

    /**
     * Test get with null default
     */
    public function testGetWithNullDefault()
    {
        $value = $this->cache->get('test_key');
        $this->assertNull($value);
    }

    /**
     * Test get after set returns default (not the set value)
     */
    public function testGetAfterSet()
    {
        $this->cache->set('test_key', 'test_value');
        $value = $this->cache->get('test_key', 'default_value');
        $this->assertSame('default_value', $value);
    }

    /**
     * Test set always returns true
     */
    public function testSet()
    {
        $result = $this->cache->set('test_key', 'test_value');
        $this->assertTrue($result);
    }

    /**
     * Test set with various data types
     *
     * @dataProvider providerDataTypes
     */
    public function testSetWithVariousTypes($name, $value)
    {
        $result = $this->cache->set($name, $value);
        $this->assertTrue($result);

        // Verify nothing was actually stored
        $this->assertNull($this->cache->get($name));
    }

    public function providerDataTypes()
    {
        return array(
            'array' => ['array', ['a', 'b', 'c' => 'sea']],
            'float' => ['float', 3.14],
            'int' => ['int', 42],
            'object' => ['object', (object) ['prop' => 'value'] ],
            'string' => ['string', 'value'],
            'bool_true' => ['bool_true', true],
            'bool_false' => ['bool_false', false],
            'null' => ['null', null],
        );
    }

    /**
     * Test set with TTL as integer (ignored by NullCache)
     */
    public function testSetWithTtlInteger()
    {
        $result = $this->cache->set('ttl_key', 'value', 2);
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('ttl_key'));
    }

    /**
     * Test set with TTL as zero
     */
    public function testSetWithTtlZero()
    {
        $result = $this->cache->set('no_expire', 'value', 0);
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('no_expire'));
    }

    /**
     * Test set with TTL as null
     */
    public function testSetWithTtlNull()
    {
        $result = $this->cache->set('no_expire', 'value', null);
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('no_expire'));
    }

    /**
     * Test set with TTL as DateInterval
     */
    public function testSetWithTtlDateInterval()
    {
        $interval = new \DateInterval('PT2S'); // 2 seconds
        $result = $this->cache->set('interval_key', 'value', $interval);
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('interval_key'));
    }

    /**
     * Test set with TTL as DateTime
     */
    public function testSetWithTtlDateTime()
    {
        $datetime = new \DateTime('+2 seconds');
        $result = $this->cache->set('datetime_key', 'value', $datetime);
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('datetime_key'));
    }

    /**
     * Test set with TTL as string (date format)
     */
    public function testSetWithTtlString()
    {
        $future = new \DateTime('+2 seconds', new \DateTimeZone('UTC'));
        $dateString = $future->format('Y-m-d H:i:s');

        $result = $this->cache->set('string_date_key', 'value', $dateString);
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('string_date_key'));
    }

    /**
     * Test delete always returns true
     */
    public function testDelete()
    {
        $result = $this->cache->delete('delete_key');
        $this->assertTrue($result);
    }

    /**
     * Test delete after set
     */
    public function testDeleteAfterSet()
    {
        $this->cache->set('delete_key', 'value');
        $result = $this->cache->delete('delete_key');
        $this->assertTrue($result);
        $this->assertNull($this->cache->get('delete_key'));
    }

    /**
     * Test delete non-existent key
     */
    public function testDeleteNonExistent()
    {
        $result = $this->cache->delete('nonexistent_key');
        $this->assertTrue($result);
    }

    /**
     * Test clear always returns true
     */
    public function testClear()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $result = $this->cache->clear();
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    /**
     * Test has always returns false
     */
    public function testHas()
    {
        $this->assertFalse($this->cache->has('has_key'));

        $this->cache->set('has_key', 'value');
        $this->assertFalse($this->cache->has('has_key'));

        $this->cache->delete('has_key');
        $this->assertFalse($this->cache->has('has_key'));
    }

    /**
     * Test getMultiple returns all default values
     */
    public function testGetMultiple()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $values = $this->cache->getMultiple(['key1', 'key2', 'key3', 'key4']);

        $expected = [
            'key1' => null,
            'key2' => null,
            'key3' => null,
            'key4' => null,
        ];

        $this->assertSame($expected, $values);
    }

    /**
     * Test getMultiple with default value
     */
    public function testGetMultipleWithDefault()
    {
        $this->cache->set('key1', 'value1');

        $values = $this->cache->getMultiple(['key1', 'key2'], 'default');

        $expected = [
            'key1' => 'default',
            'key2' => 'default',
        ];

        $this->assertSame($expected, $values);
    }

    /**
     * Test getMultiple with invalid argument
     */
    public function testGetMultipleWithInvalidArgument()
    {
        $this->expectException('bdk\Cache\InvalidArgumentException');
        $this->expectExceptionMessage('Value must be an array or implement Traversable');
        $this->cache->getMultiple('not_an_array');
    }

    /**
     * Test setMultiple always returns true
     */
    public function testSetMultiple()
    {
        $values = [
            'multi_key1' => 'multi_value1',
            'multi_key2' => 'multi_value2',
            'multi_key3' => 'multi_value3',
        ];

        $result = $this->cache->setMultiple($values);
        $this->assertTrue($result);

        // Verify nothing was actually stored
        $this->assertNull($this->cache->get('multi_key1'));
        $this->assertNull($this->cache->get('multi_key2'));
        $this->assertNull($this->cache->get('multi_key3'));
    }

    /**
     * Test setMultiple with TTL
     */
    public function testSetMultipleWithTtl()
    {
        $values = [
            'ttl_multi1' => 'value1',
            'ttl_multi2' => 'value2',
        ];

        $result = $this->cache->setMultiple($values, 2);
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('ttl_multi1'));
    }

    /**
     * Test setMultiple with invalid argument
     */
    public function testSetMultipleWithInvalidArgument()
    {
        $this->expectException('bdk\Cache\InvalidArgumentException');
        $this->expectExceptionMessage('Value must be an array or implement Traversable');
        $this->cache->setMultiple('not_an_array');
    }

    /**
     * Test deleteMultiple always returns true
     */
    public function testDeleteMultiple()
    {
        $this->cache->set('del_multi1', 'value1');
        $this->cache->set('del_multi2', 'value2');
        $this->cache->set('del_multi3', 'value3');

        $result = $this->cache->deleteMultiple(['del_multi1', 'del_multi2']);
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('del_multi1'));
        $this->assertNull($this->cache->get('del_multi2'));
        $this->assertNull($this->cache->get('del_multi3'));
    }

    /**
     * Test deleteMultiple with non-existent keys
     */
    public function testDeleteMultipleNonExistent()
    {
        $result = $this->cache->deleteMultiple(['nonexistent1', 'nonexistent2']);
        $this->assertTrue($result);
    }

    /**
     * Test deleteMultiple with invalid argument
     */
    public function testDeleteMultipleWithInvalidArgument()
    {
        $this->expectException('bdk\Cache\InvalidArgumentException');
        $this->expectExceptionMessage('Value must be an array or implement Traversable');
        $this->cache->deleteMultiple('not_an_array');
    }

    /**
     * Test with Traversable for getMultiple
     */
    public function testGetMultipleWithTraversable()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $iterator = new \ArrayIterator(['key1', 'key2', 'key3']);
        $values = $this->cache->getMultiple($iterator);

        $expected = [
            'key1' => null,
            'key2' => null,
            'key3' => null,
        ];

        $this->assertSame($expected, $values);
    }

    /**
     * Test with Traversable for getMultiple with default
     */
    public function testGetMultipleWithTraversableAndDefault()
    {
        $iterator = new \ArrayIterator(['key1', 'key2']);
        $values = $this->cache->getMultiple($iterator, 'custom_default');

        $expected = [
            'key1' => 'custom_default',
            'key2' => 'custom_default',
        ];

        $this->assertSame($expected, $values);
    }

    /**
     * Test with Traversable for setMultiple
     */
    public function testSetMultipleWithTraversable()
    {
        $iterator = new \ArrayIterator([
            'trav_key1' => 'trav_value1',
            'trav_key2' => 'trav_value2',
        ]);

        $result = $this->cache->setMultiple($iterator);
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('trav_key1'));
        $this->assertNull($this->cache->get('trav_key2'));
    }

    /**
     * Test with Traversable for deleteMultiple
     */
    public function testDeleteMultipleWithTraversable()
    {
        $this->cache->set('trav_del1', 'value1');
        $this->cache->set('trav_del2', 'value2');

        $iterator = new \ArrayIterator(['trav_del1', 'trav_del2']);
        $result = $this->cache->deleteMultiple($iterator);
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('trav_del1'));
        $this->assertNull($this->cache->get('trav_del2'));
    }

    /**
     * Test overwriting attempt (nothing to overwrite with NullCache)
     */
    public function testOverwriteValue()
    {
        $this->cache->set('overwrite_key', 'original_value');
        $this->assertNull($this->cache->get('overwrite_key'));

        $this->cache->set('overwrite_key', 'new_value');
        $this->assertNull($this->cache->get('overwrite_key'));
    }
}
