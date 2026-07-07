<?php

namespace bdk\Test\Cache;

use bdk\Cache\FileSystem;
use bdk\Cache\NullCache;
use bdk\PhpUnitPolyfill\AssertionTrait;
use bdk\PhpUnitPolyfill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \bdk\Cache\FileSystem
 * @covers \bdk\Cache\NullCache
 */
class ExceptionTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    /** @var string */
    private $cacheDir;

    protected $classes = array(
        'invalidArgumentException' => 'Psr\SimpleCache\InvalidArgumentException',
    );

    /**
     * Clean up test environment
     */
    protected function tearDown(): void
    {
        if (isset($this->cacheDir) && \file_exists($this->cacheDir) && \is_dir($this->cacheDir)) {
            \array_map('unlink', \glob($this->cacheDir . '/*'));
            \rmdir($this->cacheDir);
        }
    }

    /**
     * Create a cache instance for testing
     *
     * @param string $cacheType The cache type to create
     *
     * @return FileSystem|NullCache
     */
    private function createCache($cacheType)
    {
        if ($cacheType === 'FileSystem') {
            $this->cacheDir = \sys_get_temp_dir() . '/bdk_cache_test_' . \uniqid();
            return new FileSystem($this->cacheDir);
        }
        return new NullCache();
    }

    /**
     * Test invalid key - empty string
     *
     * @dataProvider providerCacheTypes
     */
    public function testSetWithInvalidKeyEmpty($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Cache key cannot be empty');
        $cache->set('', 'value');
    }

    /**
     * Test invalid key - not a string
     *
     * @dataProvider providerCacheTypes
     */
    public function testSetWithInvalidKeyNotString($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Cache key must be a string');
        $cache->set(123, 'value');
    }

    /**
     * Test invalid key - reserved characters
     * This demonstrates combining two data providers into one
     *
     * @dataProvider providerCacheTypesAndReservedCharacters
     */
    public function testSetWithInvalidKeyReservedCharacter($cacheType, $key)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Cache key contains reserved characters');
        $cache->set($key, 'value');
    }

    /**
     * Test set with invalid TTL
     *
     * @dataProvider providerCacheTypes
     */
    public function testSetWithInvalidTtl($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Invalid expiry/ttl value');
        $cache->set('key', 'value', 'invalid');
    }

    /**
     * Test setMultiple with invalid TTL
     *
     * @dataProvider providerCacheTypes
     */
    public function testSetMultipleWithInvalidTtl($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Invalid expiry/ttl value');
        $cache->setMultiple(['key1' => 'value1', 'key2' => 'value2'], 'invalid');
    }

    /**
     * Test getMultiple with invalid argument
     *
     * @dataProvider providerCacheTypes
     */
    public function testGetMultipleWithInvalidValues($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Value must be an array or implement Traversable');
        $cache->getMultiple('not_an_array');
    }

    /**
     * Test setMultiple with invalid argument
     *
     * @dataProvider providerCacheTypes
     */
    public function testSetMultipleWithInvalidValues($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Value must be an array or implement Traversable');
        $cache->setMultiple('not_an_array');
    }

    /**
     * Test deleteMultiple with invalid argument
     *
     * @dataProvider providerCacheTypes
     */
    public function testDeleteMultipleWithInvalidValues($cacheType)
    {
        $cache = $this->createCache($cacheType);
        $this->expectException($this->classes['invalidArgumentException']);
        $this->expectExceptionMessage('Value must be an array or implement Traversable');
        $cache->deleteMultiple('not_an_array');
    }

    /**
     * Data provider for cache types
     */
    public function providerCacheTypes()
    {
        return [
            'FileSystem' => ['FileSystem'],
            'NullCache' => ['NullCache'],
        ];
    }

    /**
     * Data provider for reserved characters
     */
    public function providerReservedCharacters()
    {
        return [
            ['{key}'],
            ['key{test'],
            ['key}test'],
            ['key(test'],
            ['key)test'],
            ['key/test'],
            ['key\\test'],
            ['key@test'],
            ['key:test'],
        ];
    }

    /**
     * Data provider combining cache types and reserved characters
     * This creates a Cartesian product of both datasets
     */
    public function providerCacheTypesAndReservedCharacters()
    {
        $cacheTypes = ['FileSystem', 'NullCache'];
        $reservedChars = \array_column($this->providerReservedCharacters(), 0);

        $combined = [];
        foreach ($cacheTypes as $cacheType) {
            foreach ($reservedChars as $char) {
                $combined[$cacheType . ' with ' . $char] = [$cacheType, $char];
            }
        }

        return $combined;
    }
}
