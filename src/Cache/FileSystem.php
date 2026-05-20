<?php

namespace bdk\Cache;

/**
 * File system based cache implementation
 */
class FileSystem extends AbstractBase
{
    /** @var string $directory Directory where cache files are stored */
    private $directory;

    /**
     * Constructor
     *
     * @param string $directory Base directory.  Defaults to system temp directory
     */
    public function __construct($directory = null)
    {
        $directory = $directory
            ? $directory
            : \sys_get_temp_dir() . DIRECTORY_SEPARATOR . \preg_replace('/[\/\\\\]/', '_', __CLASS__);
        $directory = \rtrim($directory, '/\\');
        if (!\file_exists($directory)) {
            \mkdir($directory, 0777, true);
        }
        $this->directory = $directory;
    }

    /**
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        $filePath = $this->getFilePath($key);

        if (!\file_exists($filePath)) {
            return $default;
        }

        $data = \unserialize(\file_get_contents($filePath));

        if ($this->isExpired($data)) {
            \unlink($filePath);
            return $default;
        }

        return $data['value'];
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $filePath = $this->getFilePath($key);

        $data = array(
            'expiry' => $this->expiry($ttl),
            'value' => $value,
        );

        $result = \file_put_contents($filePath, \serialize($data), LOCK_EX);

        return $result !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
        $filePath = $this->getFilePath($key);
        return \file_exists($filePath)
            ? \unlink($filePath)
            : true;
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $files = \glob($this->directory . DIRECTORY_SEPARATOR . '*');

        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (\is_file($file)) {
                $success = \unlink($file) && $success;
            }
        }

        return $success;
    }

    // getMultiple, setMultiple, deleteMultiple implemented in AbstractBase

    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        return $this->get($key, '__cache_miss__') !== '__cache_miss__';
    }

    /**
     * Get the file path for a cache key
     *
     * @param string $key Cache key
     *
     * @return string File path
     */
    protected function getFilePath($key)
    {
        $this->assertValidKey($key);
        return $this->directory . DIRECTORY_SEPARATOR . $key;
    }
}
