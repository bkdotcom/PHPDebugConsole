<?php

/**
 * This file is part of PHPDebugConsole
 *
 * @package   PHPDebugConsole
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2020 Brad Kent
 * @version   v3.0
 */

namespace bdk\Debug\Psr7lite;

use Psr\Http\Message\StreamInterface;

/**
 * Psr\Http\Message\StreamInterface implementation
 *
 * for internal use... doesn't actually implement StreamInterface to avoid dependency
 *
 * @psalm-consistent-constructor
 */
class Stream
{
    /**
     * Resource modes.
     *
     * @var string
     *
     * @see http://php.net/manual/function.fopen.php
     * @see http://php.net/manual/en/function.gzopen.php
     */
    const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';
    const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    private $stream;
    private $size;
    private $seekable;
    private $readable;
    private $writable;
    private $uri;
    private $customMetadata;

    /**
     * This constructor accepts an associative array of options.
     *
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknowledge, then you can
     *   provide that size, in bytes.
     *
     * @param resource $stream  Stream resource to wrap.
     * @param array    $options Associative array of options.
     *
     * @throws \InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct($stream, $options = array())
    {
        if (\is_resource($stream) === false) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        if (isset($options['size'])) {
            $this->size = $options['size'];
        }

        $this->customMetadata = isset($options['metadata'])
            ? $options['metadata']
            : array();

        $this->stream = $stream;
        $meta = \stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = (bool) \preg_match(self::READABLE_MODES, $meta['mode']);
        $this->writable = (bool) \preg_match(self::WRITABLE_MODES, $meta['mode']);
        $this->uri = $this->getMetadata('uri');
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * @return string
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     */
    public function __toString()
    {
        try {
            $this->seek(0);
            return (string) \stream_get_contents($this->stream);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Create a new stream based on the input type.
     *
     * Options is an associative array that can contain the following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * @param mixed $resource Entity body data
     * @param array $options  Additional options
     *
     * @return StreamInterface|static
     * @throws \InvalidArgumentException if the $resource arg is not valid.
     */
    public static function factory($resource = '', $options = array())
    {
        if ($resource === null || $resource === '') {
            return new static(\fopen('php://temp', 'r+'), $options);
        }
        if (\is_scalar($resource)) {
            $stream = \fopen('php://temp', 'r+');
            \fwrite($stream, (string) $resource);
            \fseek($stream, 0);
            if (\class_exists('Psr\\Http\\Message\\StreamInterface')) {
                return new StreamInterface($stream, $options);
            }
            return new static($stream, $options);
        }
        if (\is_resource($resource)) {
            return new static($resource, $options);
        }
        if (\is_object($resource)) {
            $stream = self::factoryFromObject($resource, $options);
            if ($stream !== false) {
                return $stream;
            }
        }
        throw new \InvalidArgumentException('Invalid resource type: ' . \gettype($resource));
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        if (isset($this->stream)) {
            if (\is_resource($this->stream)) {
                \fclose($this->stream);
            }
            $this->detach();
        }
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $result = $this->stream;
        unset($this->stream);
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->seekable = false;
        $this->writable = false;

        return $result;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     * @throws \RuntimeException
     */
    public function eof()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        return \feof($this->stream);
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $contents = \stream_get_contents($this->stream);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @param string $key Specific metadata to retrieve.
     *
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     * @link   http://php.net/manual/en/function.stream-get-meta-data.php
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return $key ? null : array();
        } elseif (!$key) {
            return $this->customMetadata + \stream_get_meta_data($this->stream);
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }
        $meta = \stream_get_meta_data($this->stream);
        return isset($meta[$key]) ? $meta[$key] : null;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize()
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!isset($this->stream)) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            \clearstatcache(true, $this->uri);
        }

        $stats = \fstat($this->stream);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable()
    {
        return $this->seekable;
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     *
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->readable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new \RuntimeException('Length parameter cannot be negative');
        }

        if ($length === 0) {
            return '';
        }

        $string = \fread($this->stream, $length);
        if ($string === false) {
            throw new \RuntimeException('Unable to read from stream');
        }
        return $string;
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @link   http://www.php.net/manual/en/function.fseek.php
     * @see    seek()
     * @throws \RuntimeException on failure.
     *
     * @return void
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Seek to a position in the stream.
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     *
     * @link   http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     *
     * @return void
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $whence = (int) $whence;

        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->seekable) {
            throw new \RuntimeException('Stream is not seekable');
        }
        if (\fseek($this->stream, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position '
                . $offset . ' with whence ' . \var_export($whence, true));
        }
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell()
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }

        $result = \ftell($this->stream);
        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }
        return $result;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     *
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string)
    {
        if (!isset($this->stream)) {
            throw new \RuntimeException('Stream is detached');
        }
        if (!$this->writable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result = \fwrite($this->stream, $string);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream');
        }
        return $result;
    }

    /**
     * Create stream from object (StreamInterface, static, or object with __toString)
     *
     * @param object $obj     StreamInterface, static, or object with __toString()
     * @param array  $options Additional options
     *
     * @return StreamInterface|static|false
     */
    private static function factoryFromObject($obj, $options)
    {
        if ($obj instanceof StreamInterface) {
            return $obj;
        }
        if (\get_class($obj) === __CLASS__) {
            /** @var static */
            return $obj;
        }
        if (\method_exists($obj, '__toString')) {
            return self::factory((string) $obj, $options);
        }
        return false;
    }
}
