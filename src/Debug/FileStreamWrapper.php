<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2019 Brad Kent
 * @version   v2.3
 */

namespace bdk\Debug;

/**
 * Streamwrapper which injects `declare(ticks=1)`
 *
 * @see http://php.net/manual/en/class.streamwrapper.php
 *
 * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 * phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps
 */
class FileStreamWrapper
{
    /**
     * @var resource
     */
    public $context;

    private $bufferPrepend = '';

    private $declaredTicks = false;

    private $filepath;

    private $sizeAdjust = null;

    /**
     * @var resource
     */
    private $handle;

    /**
     * @var array paths to exclude from adding tick declaration
     */
    private static $pathsExclude = array();

    public static $filesModified = array();

    /**
     * @var string
     */
    const PROTOCOL = 'file';

    /**
     * Register this stream wrapper
     *
     * @param array $pathsExclude paths/directories to exclude
     *
     * @return void
     *
     * @throws \UnexpectedValueException
     */
    public static function register($pathsExclude = array())
    {
        $result = \stream_wrapper_unregister(static::PROTOCOL);
        if ($result === false) {
            throw new \UnexpectedValueException('Failed to unregister');
        }
        if ($pathsExclude) {
            self::$pathsExclude = $pathsExclude;
        }
        \stream_wrapper_register(static::PROTOCOL, \get_called_class());
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
    private static function restorePrev()
    {
        $result = \stream_wrapper_restore(static::PROTOCOL);
        if ($result === false) {
            throw new \UnexpectedValueException('Failed to restore');
        }
    }

    /**
     * Close the directory
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.dir-closedir.php
     */
    public function dir_closedir()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        \closedir($this->handle);
        self::register();
        $this->handle = null;
        return true;
    }

    /**
     * Opens a directory for reading
     *
     * @param string  $path    Specifies the URL that was passed to opendir().
     * @param integer $options Whether or not to enforce safe_mode (0x04).
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.dir-opendir.php
     */
    public function dir_opendir($path, $options = 0)
    {
        if ($this->handle) {
            return false;
        }
        // "use" our function params so things don't complain
        array($options);
        self::restorePrev();
        $this->handle = \opendir($path);
        self::register();
        return $this->handle !== false;
    }

    /**
     * Read a single filename of a directory
     *
     * @return string|boolean
     *
     * @see http://php.net/manual/en/streamwrapper.dir-readdir.php
     */
    public function dir_readdir()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $success = \readdir($this->handle);
        self::register();
        return $success;
    }

    /**
     * Reset directory name pointer
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.dir-rewinddir.php
     */
    public function dir_rewinddir()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        \rewinddir($this->handle);
        self::register();
        return true;
    }

    /**
     * Create a directory
     *
     * @param string  $path    Directory which should be created.
     * @param integer $mode    The value passed to mkdir().
     * @param integer $options A bitwise mask of values, such as STREAM_MKDIR_RECURSIVE.
     *
     * @return boolean
     */
    public function mkdir($path, $mode, $options = 0)
    {
        self::restorePrev();
        $success = \mkdir($path, $mode, (bool) ($options & STREAM_MKDIR_RECURSIVE));
        self::register();
        return $success;
    }

    /**
     * Rename a file
     *
     * @param string $pathFrom existing path
     * @param string $pathTo   The URL which the path_from should be renamed to.
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.rename.php
     */
    public function rename($pathFrom, $pathTo)
    {
        self::restorePrev();
        $success = \rename($pathFrom, $pathTo);
        self::register();
        return $success;
    }

    /**
     * Remove a directory
     *
     * @param string  $path    directory to remove
     * @param integer $options bitwise mask of values
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.rmdir.php
     */
    public function rmdir($path, $options)
    {
        // "use" our function params so things don't complain
        array($options);
        self::restorePrev();
        $success = \rmdir($path);
        self::register();
        return $success;
    }

    /**
     * Retrieve the underlying resource
     *
     * @param integer $castAs STREAM_CAST_FOR_SELECT when stream_select() is calling stream_cast()
     *                        STREAM_CAST_AS_STREAM when stream_cast() is called for other uses
     *
     * @return resource|boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-cast.php
     */
    public function stream_cast($castAs)
    {
        if ($this->handle && $castAs & STREAM_CAST_AS_STREAM) {
            return $this->handle;
        }
        return false;
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
        if (!$this->handle) {
            return;
        }
        self::restorePrev();
        \fclose($this->handle);
        $this->handle = null;
        self::register();
    }

    /**
     * Tests for end-of-file on a file pointer
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-eof.php
     */
    public function stream_eof()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $result = \feof($this->handle);
        self::register();
        return $result;
    }

    /**
     * Flush the output
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-flush.php
     */
    public function stream_flush()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $success = \fflush($this->handle);
        self::register();
        return $success;
    }

    /**
     * Advisory file locking
     *
     * @param integer $operation is one of the following:
     *       LOCK_SH to acquire a shared lock (reader).
     *       LOCK_EX to acquire an exclusive lock (writer).
     *       LOCK_UN to release a lock (shared or exclusive).
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-lock.php
     */
    public function stream_lock($operation)
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $success = \flock($this->handle, $operation);
        self::register();
        return $success;
    }

    /**
     * Change file options
     *
     * @param string  $path   filepath or URL
     * @param integer $option What meta value is being set
     * @param mixed   $value  Meta value
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-metadata.php
     */
    public function stream_metadata($path, $option, $value)
    {
        self::restorePrev();
        switch ($option) {
            case STREAM_META_TOUCH:
                if (!empty($value)) {
                    $success = \touch($path, $value[0], $value[1]);
                } else {
                    $success = \touch($path);
                }
                break;
            case STREAM_META_OWNER_NAME:
                // Fall through
            case STREAM_META_OWNER:
                $success = \chown($path, $value);
                break;
            case STREAM_META_GROUP_NAME:
                // Fall through
            case STREAM_META_GROUP:
                $success = \chgrp($path, $value);
                break;
            case STREAM_META_ACCESS:
                $success = \chmod($path, $value);
                break;
            default:
                $success = false;
        }
        self::register();
        return $success;
    }

    /**
     * Opens file or URL
     *
     * @param string   $path       Specifies the file/URL that was passed to the original function.
     * @param string   $mode       The mode used to open the file, as detailed for fopen().
     * @param integers $options    Holds additional flags set by the streams API. I
     * @param string   $openedPath the full path of the file/resource that was actually opened
     *
     * @return boolean
     *
     * @see    http://php.net/manual/en/streamwrapper.stream-open.php
     * @throws \UnexpectedValueException
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        if ($this->handle) {
            return false;
        }
        $useIncludePath = (bool) $options & STREAM_USE_PATH;
        $context = $this->context;
        if ($context === null) {
            $context = \stream_context_get_default();
        }
        self::restorePrev();
        if (\strpos($mode, 'r') !== false && !\file_exists($path)) {
            return false;
        } elseif (\strpos($mode, 'x') !== false && \file_exists($path)) {
            return false;
        }
        $handle = \fopen($path, $mode, $useIncludePath, $context);
        self::register();
        if ($handle === false) {
            return false;
        }
        /*
            Determine opened path
        */
        $meta = \stream_get_meta_data($handle);
        if (!isset($meta['uri'])) {
            throw new \UnexpectedValueException('Uri not in meta data');
        }
        $this->filepath = $openedPath = $meta['uri'];
        $this->handle = $handle;
        return true;
    }

    /**
     * Read from stream
     *
     * @param integer $bytes How many bytes of data from the current position should be returned.
     *
     * @return string
     *
     * @see http://php.net/manual/en/streamwrapper.stream-read.php
     */
    public function stream_read($bytes)
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $buffer = \fread($this->handle, $bytes);
        $bufferLen = \strlen($buffer);
        if (!$this->declaredTicks && $this->isTargeted()) {
            // insert declare(ticks=1);  without adding/removing any lines
            $declare = 'declare(ticks=1);';
            $buffer = \preg_replace(
                '/^(<\?php)\s*$/m',
                '$0 ' . $declare,
                $buffer,
                1,
                $count
            );
            if ($count) {
                $this->declaredTicks = true;
                self::$filesModified[] = $this->filepath;
                $this->sizeAdjust = \strlen($buffer) - $bufferLen;
            }
        }
        $buffer = $this->bufferPrepend . $buffer;
        $bufferLenAfter = \strlen($buffer);
        $diff = $bufferLenAfter - $bufferLen;
        $this->bufferPrepend = '';
        if ($diff) {
            $this->bufferPrepend = \substr($buffer, $bytes);
            $buffer = \substr($buffer, 0, $bytes);
        }
        self::register();
        return $buffer;
    }

    /**
     * Seek to specific location in a stream
     *
     * @param integer $offset The stream offset to seek to
     * @param integer $whence [SEEK_SET] | SEEK_CUR | SEEK_END
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-seek.php
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $result = \fseek($this->handle, $offset, $whence);
        $success = $result !== -1;
        self::register();
        return $success;
    }

    /**
     * Change stream options
     *
     * @param integer $option [description]
     * @param integer $arg1   [description]
     * @param integer $arg2   [description]
     *
     * @return boolean
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        if (!$this->handle || \get_resource_type($this->handle) !== 'stream') {
            \trigger_error(\sprintf('The "$handle" property of "%s" need to be a stream.', __CLASS__), E_USER_WARNING);
            return false;
        }
        self::restorePrev();
        $return = false;
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                $return = \stream_set_blocking($this->handle, $arg1);
                break;
            case STREAM_OPTION_READ_TIMEOUT:
                $return = \stream_set_timeout($this->handle, $arg1, $arg2);
                break;
            case STREAM_OPTION_READ_BUFFER:
                // poorly documented / unsure how to implement / return false to not implement
                break;
            case STREAM_OPTION_WRITE_BUFFER:
                // poorly documented / unsure how to implement / return false to not implement
                break;
        }
        self::register();
        return $return;
    }

    /**
     * Retrieve information about a file resource
     *
     * @return array
     *
     * @see http://php.net/manual/en/streamwrapper.stream-stat.php
     */
    public function stream_stat()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $stats = \fstat($this->handle);
        /*
            PHP 7.4 seems to require adjusted size to be returned or we get
              parse error, unexpected EOL..
              (ie only pre-modifed length used even though stream_read returning entire file)
              perhaps STREAM_OPTION_READ_BUFFER related
        */
        $sizeAdjust = 0;
        if ($this->isTargeted()) {
            $sizeAdjust = $this->sizeAdjust !== null
                ? $this->sizeAdjust
                : 50;
        }
        $stats[7] += $sizeAdjust;
        $stats['size'] += $sizeAdjust;
        self::register();
        return $stats;
    }

    /**
     * Retrieve the current position of a stream
     *
     * @return integer
     *
     * @see http://php.net/manual/en/streamwrapper.stream-tell.php
     */
    public function stream_tell()
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $position = \ftell($this->handle);
        self::register();
        return $position;
    }

    /**
     * Truncates a file to the given size
     *
     * @param integer $size Truncate to this size
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.stream-truncate.php
     */
    public function stream_truncate($size)
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $success = \ftruncate($this->handle, $size);
        self::register();
        return $success;
    }

    /**
     * Write to stream
     *
     * @param string $data data to write
     *
     * @return integer
     *
     * @see http://php.net/manual/en/streamwrapper.stream-write.php
     */
    public function stream_write($data)
    {
        if (!$this->handle) {
            return false;
        }
        self::restorePrev();
        $length = \fwrite($this->handle, $data);
        self::register();
        return $length;
    }

    /**
     * Unlink a file
     *
     * @param string $path filepath
     *
     * @return boolean
     *
     * @see http://php.net/manual/en/streamwrapper.unlink.php
     */
    public function unlink($path)
    {
        self::restorePrev();
        $success = \unlink($path);
        self::register();
        return $success;
    }

    /**
     * Retrieve information about a file
     *
     * @param string  $path  The file path or URL to stat
     * @param integer $flags Holds additional flags set by the streams API.
     *
     * @return array
     *
     * @see http://php.net/manual/en/streamwrapper.url-stat.php
     */
    public function url_stat($path, $flags)
    {
        self::restorePrev();
        if (!\file_exists($path)) {
            $info = false;
        } elseif ($flags & STREAM_URL_STAT_LINK) {
            $info = $flags & STREAM_URL_STAT_QUIET
                ? @\lstat($path)
                : \lstat($path);
        } else {
            $info = $flags & STREAM_URL_STAT_QUIET
                ? @\stat($path)
                : \stat($path);
        }
        self::register();
        return $info;
    }

    /**
     * Check whether this file has been, or should beinjected with declare ticks
     *
     * @return boolean
     */
    public function isTargeted()
    {
        if ($this->declaredTicks) {
            return true;
        }
        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $isRequire = !\in_array($backtrace[2]['function'], array('file_get_contents'));
        if (!$isRequire) {
            return false;
        }
        foreach (self::$pathsExclude as $excludePath) {
            if (\strpos($this->filepath, $excludePath . DIRECTORY_SEPARATOR) === 0) {
                return false;
            }
        }
        return true;
    }
}
