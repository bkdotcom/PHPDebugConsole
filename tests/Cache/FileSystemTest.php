<?php

namespace bdk\Test\Cache;

use bdk\Cache\FileSystem;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Cache\AbstractBase
 * @covers \bdk\Cache\FileSystem
 */
class FileSystemTest extends TestCase
{
    /** @var FileSystem */
    private $cache;

    /** @var string */
    private $cacheDir;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        $this->cacheDir = \sys_get_temp_dir() . '/bdk_cache_test_' . \uniqid();
        $this->cache = new FileSystem($this->cacheDir);
    }

    /**
     * Clean up test environment
     */
    public function tearDown(): void
    {
        if (\file_exists($this->cacheDir) && \is_dir($this->cacheDir)) {
            $this->cache->clear();
            \rmdir($this->cacheDir);
        }
        $GLOBALS['timeOffset'] = 0;
    }

    /**
     * Test constructor with default directory
     */
    public function testConstructorDefault()
    {
        $cache = new FileSystem();
        $this->assertInstanceOf('bdk\\Cache\\FileSystem', $cache);
    }

    /**
     * Test constructor with custom directory
     */
    public function testConstructorCustomDirectory()
    {
        $dir = \sys_get_temp_dir() . '/custom_cache_' . \uniqid();
        $cache = new FileSystem($dir);
        $this->assertInstanceOf('bdk\\Cache\\FileSystem', $cache);
        $this->assertTrue(\is_dir($dir));
        \rmdir($dir);
    }

    /**
     * Test basic set and get operations
     */
    public function testSetAndGet()
    {
        $result = $this->cache->set('test_key', 'test_value');
        $this->assertTrue($result);

        $value = $this->cache->get('test_key');
        $this->assertSame('test_value', $value);
    }

    /**
     * Test get with default value
     */
    public function testGetWithDefault()
    {
        $value = $this->cache->get('nonexistent_key', 'default_value');
        $this->assertSame('default_value', $value);
    }

    /**
     * Test get with null default
     */
    public function testGetWithNullDefault()
    {
        $value = $this->cache->get('nonexistent_key');
        $this->assertNull($value);
    }

    /**
     * Test set with various data types
     *
     * @dataProvider providerDataTypes
     */
    public function testSetWithVariousTypes($name, $value)
    {
        $this->cache->set($name, $value);
        $this->assertTrue($this->cache->has($name));
        $name === 'object'
            ? $this->assertEquals($value, $this->cache->get($name))
            : $this->assertSame($value, $this->cache->get($name));
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
     * Test set with TTL as integer (relative time)
     */
    public function testSetWithTtlInteger()
    {
        // Set with 2 second TTL
        $this->cache->set('ttl_key', 'value', 2);
        $this->assertSame('value', $this->cache->get('ttl_key'));

        // Wait for expiry
        $GLOBALS['timeOffset'] = 3;
        $this->assertNull($this->cache->get('ttl_key'));
    }

    /**
     * Test set with TTL as zero (no expiration)
     */
    public function testSetWithTtlZero()
    {
        $this->cache->set('no_expire', 'value', 0);
        $GLOBALS['timeOffset'] = 3600 * 24 * 365; // advance time by 1 year
        $this->assertSame('value', $this->cache->get('no_expire'));
    }

    /**
     * Test set with TTL as null (no expiration)
     */
    public function testSetWithTtlNull()
    {
        $this->cache->set('no_expire', 'value', null);
        $GLOBALS['timeOffset'] = 3600 * 24 * 365; // advance time by 1 year
        $this->assertSame('value', $this->cache->get('no_expire'));
    }

    /**
     * Test set with TTL as DateInterval
     */
    public function testSetWithTtlDateInterval()
    {
        $interval = new \DateInterval('PT2S'); // 2 seconds
        $this->cache->set('interval_key', 'value', $interval);
        $this->assertSame('value', $this->cache->get('interval_key'));

        $GLOBALS['timeOffset'] = 3;
        $this->assertNull($this->cache->get('interval_key'));
    }

    /**
     * Test set with TTL as DateTime
     */
    public function testSetWithTtlDateTime()
    {
        $datetime = new \DateTime('+2 seconds');
        $this->cache->set('datetime_key', 'value', $datetime);
        $this->assertSame('value', $this->cache->get('datetime_key'));

        $GLOBALS['timeOffset'] = 3;
        $this->assertNull($this->cache->get('datetime_key'));
    }

    /**
     * Test set with TTL as string (date format)
     */
    public function testSetWithTtlString()
    {
        $future = new \DateTime('+2 seconds', new \DateTimeZone('UTC'));
        $dateString = $future->format('Y-m-d H:i:s');

        $this->cache->set('string_date_key', 'value', $dateString);
        $this->assertSame('value', $this->cache->get('string_date_key'));

        $GLOBALS['timeOffset'] = 3;
        $this->assertNull($this->cache->get('string_date_key'));
    }

    /**
     * Test delete operation
     */
    public function testDelete()
    {
        $this->cache->set('delete_key', 'value');
        $this->assertSame('value', $this->cache->get('delete_key'));

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
     * Test clear operation
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
     * Test has operation
     */
    public function testHas()
    {
        $this->assertFalse($this->cache->has('has_key'));

        $this->cache->set('has_key', 'value');
        $this->assertTrue($this->cache->has('has_key'));

        $this->cache->delete('has_key');
        $this->assertFalse($this->cache->has('has_key'));
    }

    /**
     * Test has with expired key
     */
    public function testHasWithExpiredKey()
    {
        $this->cache->set('expire_key', 'value', 1);
        $this->assertTrue($this->cache->has('expire_key'));

        $GLOBALS['timeOffset'] = 2;
        $this->assertFalse($this->cache->has('expire_key'));
    }

    /**
     * Test getMultiple operation
     */
    public function testGetMultiple()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $values = $this->cache->getMultiple(['key1', 'key2', 'key3', 'key4']);

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
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
            'key1' => 'value1',
            'key2' => 'default',
        ];

        $this->assertSame($expected, $values);
    }

    /**
     * Test setMultiple operation
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

        $this->assertSame('multi_value1', $this->cache->get('multi_key1'));
        $this->assertSame('multi_value2', $this->cache->get('multi_key2'));
        $this->assertSame('multi_value3', $this->cache->get('multi_key3'));
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

        $this->assertSame('value1', $this->cache->get('ttl_multi1'));

        $GLOBALS['timeOffset'] = 3;
        $this->assertNull($this->cache->get('ttl_multi1'));
    }

    /**
     * Test deleteMultiple operation
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
        $this->assertSame('value3', $this->cache->get('del_multi3'));
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
     * Test with Traversable for getMultiple
     */
    public function testGetMultipleWithTraversable()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $iterator = new \ArrayIterator(['key1', 'key2', 'key3']);
        $values = $this->cache->getMultiple($iterator);

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => null,
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

        $this->assertSame('trav_value1', $this->cache->get('trav_key1'));
        $this->assertSame('trav_value2', $this->cache->get('trav_key2'));
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
     * Test large TTL value (absolute timestamp)
     */
    public function testSetWithLargeTtl()
    {
        // TTL > 30 days should be treated as absolute timestamp
        $future = \time() + 60 * 60 * 24 * 31; // 31 days from now
        $this->cache->set('large_ttl', 'value', $future);
        $this->assertSame('value', $this->cache->get('large_ttl'));
    }

    /**
     * Test overwriting existing value
     */
    public function testOverwriteValue()
    {
        $this->cache->set('overwrite_key', 'original_value');
        $this->assertSame('original_value', $this->cache->get('overwrite_key'));

        $this->cache->set('overwrite_key', 'new_value');
        $this->assertSame('new_value', $this->cache->get('overwrite_key'));
    }
}
