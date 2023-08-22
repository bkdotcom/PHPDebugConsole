<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Utility\Php;
use bdk\PubSub\Manager;

/**
 * Generic stream-wrapper which publishes Debug::EVENT_STREAM_WRAP when file is required/included
 *
 * Event subscriber is able to modify file on-the-fly to monkey-patch
 *  or, in the case of PHPDebugConsole, inject `declare(ticks=1);`
 *
 * @see http://php.net/manual/en/class.streamwrapper.php
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class FileStreamWrapper
{
    const OUTPUT_ACCESS_MODE = 'rb+';
    const OUTPUT_DESTINATION = 'php://memory';
    const STREAM_OPEN_FOR_INCLUDE = 128;

    public static $filesTransformed = array();

    /**
     * @var resource
     */
    public $context;

    protected static $isRegistered = false;

    /**
     * @var string[]
     */
    protected static $protocols = array('file', 'phar');

    private static $eventManager;

    /**
     * @var array paths to exclude from adding tick declaration
     */
    private static $pathsExclude = array();

    /**
     * @var resource|null
     */
    private $resource;

    /**
     * Register this stream wrapper
     *
     * @return void
     *
     * @throws \UnexpectedValueException
     */
    public static function register()
    {
        if (static::$isRegistered) {
            return;
        }
        foreach (static::$protocols as $protocol) {
            self::registerProtocol($protocol);
        }
        static::$isRegistered = true;
        /*
            Disable OPcache
                a) want to make sure we modify required files
                b) don't want to cache modified files
        */
        \ini_set('opcache.enable', '0');
    }

    /**
     * Restore previous wrapper
     *
     * @return void
     *
     * @throws \UnexpectedValueException
     */
    public static function unregister()
    {
        if (static::$isRegistered === false) {
            return;
        }
        foreach (static::$protocols as $protocol) {
            $result = \stream_wrapper_restore($protocol);
            if ($result === false) {
                throw new \UnexpectedValueException('Failed to restore stream wrapper for ' . $protocol);
            }
        }
        static::$isRegistered = false;
    }

    /**
     * Define EventManager
     *
     * @param Manager $eventManager Event manager
     *
     * @return void
     */
    public static function setEventManager(Manager $eventManager)
    {
        static::$eventManager = $eventManager;
    }

    /**
     * Set paths/directories to exclude
     *
     * @param string[] $pathsExclude paths/directories to exclude
     *
     * @return void
     */
    public static function setPathsExclude(array $pathsExclude)
    {
        static::$pathsExclude = \array_map('realpath', $pathsExclude);
    }

    /**
     * Get paths/directories to exclude
     *
     * @return string[]
     */
    public static function getPathsExclude()
    {
        return static::$pathsExclude;
    }

    /**
     * Close the directory
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.dir-closedir.php
     */
    public function dir_closedir()
    {
        if (!$this->resource) {
            return false;
        }
        \closedir($this->resource);
        $this->resource = null;
        return true;
    }

    /**
     * Opens a directory for reading
     *
     * @param string $path    Specifies the URL that was passed to opendir().
     * @param int    $options Whether or not to enforce safe_mode (0x04).
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.dir-opendir.php
     */
    public function dir_opendir($path, $options = 0)
    {
        // "use" our function params so things don't complain
        array($options);
        static::unregister();
        $args = $this->popNull(array($path, $this->context));
        $this->resource = \call_user_func_array('opendir', $args);
        static::register();
        return $this->resource !== false;
    }

    /**
     * Read a single filename of a directory
     *
     * @return string|false
     *
     * @see http://php.net/manual/en/streamwrapper.dir-readdir.php
     */
    public function dir_readdir()
    {
        return $this->resource
            ? \readdir($this->resource)
            : false;
    }

    /**
     * Reset directory name pointer
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.dir-rewinddir.php
     */
    public function dir_rewinddir()
    {
        if (!$this->resource) {
            return false;
        }
        \rewinddir($this->resource);
        return true;
    }

    /**
     * Create a directory
     *
     * @param string $path    Directory which should be created.
     * @param int    $mode    The value passed to mkdir().
     * @param int    $options A bitwise mask of values, such as STREAM_MKDIR_RECURSIVE.
     *
     * @return bool
     */
    public function mkdir($path, $mode, $options = 0)
    {
        static::unregister();
        $isRecursive = (bool) ($options & STREAM_MKDIR_RECURSIVE);
        $args = $this->popNull(array($path, $mode, $isRecursive, $this->context));
        $result = \call_user_func_array('mkdir', $args);
        static::register();
        return $result;
    }

    /**
     * Rename a file
     *
     * @param string $pathFrom existing path
     * @param string $pathTo   The URL which the path_from should be renamed to.
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.rename.php
     */
    public function rename($pathFrom, $pathTo)
    {
        static::unregister();
        $args = $this->popNull(array($pathFrom, $pathTo, $this->context));
        $result = \call_user_func_array('rename', $args);
        static::register();
        return $result;
    }

    /**
     * Remove a directory
     *
     * @param string $path    directory to remove
     * @param int    $options bitwise mask of values
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.rmdir.php
     */
    public function rmdir($path, $options)
    {
        // "use" our function params so things don't complain
        array($options);
        static::unregister();
        $args = $this->popNull(array($path, $this->context));
        $result = \call_user_func_array('rmdir', $args);
        static::register();
        return $result;
    }

    /**
     * Retrieve the underlying resource
     *
     * @param int $castAs STREAM_CAST_FOR_SELECT when stream_select() is calling stream_cast()
     *                      STREAM_CAST_AS_STREAM when stream_cast() is called for other uses
     *
     * @return resource|false
     *
     * @see http://php.net/manual/en/streamwrapper.stream-cast.php
     */
    public function stream_cast($castAs) // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        return $this->resource
            ? $this->resource
            : false;
    }

    /**
     * Close a file
     *
     * @see http://php.net/manual/en/streamwrapper.stream-close.php
     *
     * @return void
     */
    public function stream_close()
    {
        if (\is_resource($this->resource)) {
            // juggle var so psalm doesn't complain about assigning closed-resource
            $resource = $this->resource;
            \fclose($resource);
        }
        $this->resource = null;
    }

    /**
     * Tests for end-of-file on a file pointer
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.stream-eof.php
     */
    public function stream_eof()
    {
        return $this->resource
            ? \feof($this->resource)
            : false;
    }

    /**
     * Flush the output
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.stream-flush.php
     */
    public function stream_flush()
    {
        return $this->resource
            ? \fflush($this->resource)
            : false;
    }

    /**
     * Advisory file locking
     *
     * @param int $operation is one of the following:
     *       LOCK_SH to acquire a shared lock (reader).
     *       LOCK_EX to acquire an exclusive lock (writer).
     *       LOCK_UN to release a lock (shared or exclusive).
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.stream-lock.php
     */
    public function stream_lock($operation)
    {
        if (!$this->resource) {
            return false;
        }
        $validOperations = array(
            LOCK_SH,
            LOCK_EX,
            LOCK_UN,
            LOCK_SH | LOCK_NB,
            LOCK_EX | LOCK_NB,
            LOCK_UN | LOCK_NB,
        );
        if ($operation === 0) {
            // phpunit 9.5.5 issue ??
            $operation = LOCK_EX;
        }
        return \in_array($operation, $validOperations, true)
            ? \flock($this->resource, $operation)
            : false;
    }

    /**
     * Change file options
     *
     * @param string $path   filepath or URL
     * @param int    $option What meta value is being set
     * @param mixed  $value  Meta value
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.stream-metadata.php
     */
    public function stream_metadata($path, $option, $value)
    {
        static::unregister();
        $result = false;
        switch ($option) {
            case STREAM_META_TOUCH:
                $args = \array_merge(array($path), $value ?: array());
                $result = \call_user_func_array('touch', $this->popNull($args));
                break;
            case STREAM_META_OWNER_NAME:
                // Fall through
            case STREAM_META_OWNER:
                $result = \chown($path, $value);
                break;
            case STREAM_META_GROUP_NAME:
                // Fall through
            case STREAM_META_GROUP:
                $result = \chgrp($path, $value);
                break;
            case STREAM_META_ACCESS:
                $result = \chmod($path, $value);
                break;
        }
        static::register();
        return $result;
    }

    /**
     * Opens file or URL
     *
     * @param string $path       Specifies the file/URL that was passed to the original function.
     * @param string $mode       The mode used to open the file, as detailed for fopen().
     * @param int    $options    Holds additional flags set by the streams API.
     * @param string $openedPath the full path of the file/resource that was actually opened
     *
     * @return bool
     * @see    http://php.net/manual/en/streamwrapper.stream-open.php
     * @throws \UnexpectedValueException
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        if (\strpos($mode, 'r') !== false && \file_exists($path) === false) {
            return false;
        }
        if (\strpos($mode, 'x') !== false && \file_exists($path)) {
            return false;
        }
        static::unregister();
        $this->resource = static::shouldTransform($path, $options)
            ? static::getResourceTransformed($path, $options, $openedPath)
            : static::getResource($path, $mode, $options, $openedPath);
        static::register();
        return $this->resource !== false;
    }

    /**
     * Read from stream
     *
     * @param int $bytes How many bytes of data from the current position should be returned.
     *
     * @return string|false
     *
     * @see http://php.net/manual/en/streamwrapper.stream-read.php
     */
    public function stream_read($bytes)
    {
        return $this->resource
            ? \fread($this->resource, $bytes)
            : false;
    }

    /**
     * Seek to specific location in a stream
     *
     * This method is called in response to `fseek().`
     *
     * @param int $offset The stream offset to seek to
     * @param int $whence [SEEK_SET] | SEEK_CUR | SEEK_END
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.stream-seek.php
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return $this->resource
            ? \fseek($this->resource, $offset, $whence) !== -1
            : false;
    }

    /**
     * Change stream options
     *
     * @param int $option STREAM_OPTION_BLOCKING | STREAM_OPTION_READ_TIMEOUT | STREAM_OPTION_READ_BUFFER | STREAM_OPTION_WRITE_BUFFER
     * @param int $arg1   @see https://www.php.net/manual/en/streamwrapper.stream-set-option.php
     * @param int $arg2   @see https://www.php.net/manual/en/streamwrapper.stream-set-option.php
     *
     * @return bool
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        if (!$this->resource || \get_resource_type($this->resource) !== 'stream') {
            \trigger_error(\sprintf(
                'The "$resource" property of "%s" needs to be a stream.  Currently %s',
                __CLASS__,
                Php::getDebugType($this->resource)
            ), E_USER_WARNING);
            return false;
        }
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return \stream_set_blocking($this->resource, (bool) $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
                return \stream_set_timeout($this->resource, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
                // $arg1: STREAM_BUFFER_NONE or STREAM_BUFFER_FULL
                // $arg2: the requested buffer size
                return \stream_set_write_buffer($this->resource, $arg2) === 0;
            case STREAM_OPTION_READ_BUFFER:
                // $arg1: STREAM_BUFFER_NONE or STREAM_BUFFER_FULL
                // $arg2: the requested buffer size
                return \stream_set_read_buffer($this->resource, $arg2) === 0;
        }
        return false;
    }

    /**
     * Retrieve information about a file resource
     *
     * @return array|false
     *
     * @see http://php.net/manual/en/streamwrapper.stream-stat.php
     */
    public function stream_stat()
    {
        return $this->resource
            ? \fstat($this->resource)
            : false;
    }

    /**
     * Retrieve the current position of a stream
     *
     * This method is called in response to `fseek()` to determine the current position.
     *
     * @return int|false
     *
     * @see http://php.net/manual/en/streamwrapper.stream-tell.php
     */
    public function stream_tell()
    {
        return $this->resource
            ? \ftell($this->resource)
            : false;
    }

    /**
     * Truncates a file to the given size
     *
     * @param int $size Truncate to this size
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.stream-truncate.php
     */
    public function stream_truncate($size)
    {
        return $this->resource
            ? \ftruncate($this->resource, $size)
            : false;
    }

    /**
     * Write to stream
     *
     * @param string $data data to write
     *
     * @return int|false
     *
     * @see http://php.net/manual/en/streamwrapper.stream-write.php
     */
    public function stream_write($data)
    {
        return $this->resource
            ? \fwrite($this->resource, $data)
            : false;
    }

    /**
     * Unlink a file
     *
     * @param string $path filepath
     *
     * @return bool
     *
     * @see http://php.net/manual/en/streamwrapper.unlink.php
     */
    public function unlink($path)
    {
        static::unregister();
        $args = $this->popNull(array($path, $this->context));
        $result = \call_user_func_array('unlink', $args);
        static::register();
        return $result;
    }

    /**
     * Retrieve information about a file
     *
     * @param string $path  The file path or URL to stat
     * @param int    $flags Holds additional flags set by the streams API.
     *
     * @return array|false
     *
     * @see http://php.net/manual/en/streamwrapper.url-stat.php
     */
    public function url_stat($path, $flags)
    {
        static::unregister();
        if (\file_exists($path) === false) {
            static::register();
            return false;
        }
        if ($flags & STREAM_URL_STAT_QUIET) {
            // Temporary error handler to discard errors in silent mode
            \set_error_handler(static function () {});
            try {
                $result = $flags & STREAM_URL_STAT_LINK
                    ? \lstat($path)
                    : \stat($path);
            } catch (\Exception $e) {
                $result = false;
            }
            \restore_error_handler();
            static::register();
            return $result;
        }
        $result = $flags & STREAM_URL_STAT_LINK
            ? \lstat($path)
            : \stat($path);
        static::register();
        return $result;
    }

    /**
     * Get file resource
     *
     * @param string $file       File path
     * @param string $mode       The mode used to open the file, as detailed for fopen().
     * @param int    $options    Holds additional flags set by the streams API.
     * @param string $openedPath the full path of the file/resource that was actually opened
     *
     * @return resource
     * @throws \UnexpectedValueException
     */
    private function getResource($file, $mode, $options, &$openedPath)
    {
        $useIncludePath = (bool) ($options & STREAM_USE_PATH);
        $args = $this->popNull(array($file, $mode, $useIncludePath, $this->context));
        $resource = \call_user_func_array('fopen', $args);
        /*
            Determine opened path
        */
        $meta = \stream_get_meta_data($resource);
        if (!isset($meta['uri'])) {
            throw new \UnexpectedValueException('Uri not in meta data');
        }
        $openedPath = $meta['uri'];
        return $resource;
    }

    /**
     * Return a resource with modified content
     *
     * @param string $file       File path
     * @param int    $options    Holds additional flags set by the streams API.
     * @param string $openedPath the full path of the file/resource that was actually opened
     *
     * @return resource
     */
    private function getResourceTransformed($file, $options, &$openedPath)
    {
        $resource = \fopen(static::OUTPUT_DESTINATION, static::OUTPUT_ACCESS_MODE);
        $useIncludePath = (bool) ($options & STREAM_USE_PATH);
        $args = $this->popNull(array($file, $useIncludePath, $this->context));
        $content = \call_user_func_array('file_get_contents', $args);
        $openedPath = $useIncludePath
            ? \stream_resolve_include_path($file)
            : $file;
        if (static::$eventManager) {
            $event = static::$eventManager->publish(Debug::EVENT_STREAM_WRAP, $resource, array(
                'content' => $content,
                'filepath' => $file,
            ));
            if ($event['content'] !== $content) {
                self::$filesTransformed[] = $openedPath;
            }
            $content = $event['content'];
        }
        \fwrite($resource, $content);
        \rewind($resource);
        return $resource;
    }

    /**
     * Check whether this file should be transformed
     *
     * @param string $file file path
     *
     * @return bool
     */
    private static function isTargeted($file)
    {
        foreach (static::$pathsExclude as $excludePath) {
            if ($file === $excludePath) {
                return false;
            }
            if (\strpos($file, $excludePath . DIRECTORY_SEPARATOR) === 0) {
                return false;
            }
        }
        return true;
    }

    private function popNull($values)
    {
        $count = \count($values);
        for ($i = $count; $i > 0; $i--) {
            if ($values[$i - 1] !== null) {
                break;
            }
        }
        return \array_slice($values, 0, $i);
    }

    /**
     * Register stream wrapper for the specified protocol
     *
     * First unregisters current protocol
     *
     * @param string $protocol Protocol such as "file" or "phar"
     *
     * @return void
     *
     * @throws \UnexpectedValueException
     */
    private static function registerProtocol($protocol)
    {
        $result = \stream_wrapper_unregister($protocol);
        if ($result === false) {
            throw new \UnexpectedValueException('Failed to unregister stream wrapper for ' . $protocol);
        }
        $result = \stream_wrapper_register($protocol, \get_called_class());
        if ($result === false) {
            throw new \UnexpectedValueException('Failed to register stream wrapper for ' . $protocol);
        }
    }

    /**
     * Test if file should be transformed
     *
     * @param string $file    Specifies the file/URL that was passed to the original function.
     * @param int    $options Holds additional flags set by the streams API.
     *
     * @return bool
     */
    private static function shouldTransform($file, $options)
    {
        $including = (bool) ($options & static::STREAM_OPEN_FOR_INCLUDE);
        return static::isTargeted($file) && $including;
    }
}
