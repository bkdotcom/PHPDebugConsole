<?php

/**
 * This file is part of HttpMessage
 *
 * @package   bdk/http-message
 * @author    Brad Kent <bkfake-github@yahoo.com>
 * @license   http://opensource.org/licenses/MIT MIT
 * @copyright 2014-2022 Brad Kent
 * @version   v1.0
 */

namespace bdk\HttpMessage;

use bdk\HttpMessage\AbstractStream;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Psr\Http\Message\Stream implementation
 */
class Stream extends AbstractStream implements StreamInterface
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
     * @param mixed $resource Resource, file, or string content to wrap.
     * @param array $options  Associative array of options.
     *
     * @throws InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct($resource = null, $options = array())
    {
        $this->setResource($resource);
        if (isset($options['size'])) {
            $this->size = $options['size'];
        }
        $this->customMetadata = isset($options['metadata'])
            ? $options['metadata']
            : array();
        /** @psalm-suppress PossiblyInvalidArgument */
        $meta = \stream_get_meta_data($this->resource);
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
        if ($this->isResourceOpen() === false) {
            return '';
        }
        try {
            $this->seek(0);
            /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
            return (string) \stream_get_contents($this->resource);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        if (isset($this->resource)) {
            if ($this->isResourceOpen() === true) {
                /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
                \fclose($this->resource);
            }
            $this->detach();
        }
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|closed-resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if (!isset($this->resource)) {
            return null;
        }
        $resource = $this->resource;
        unset($this->resource);
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->seekable = false;
        $this->writable = false;
        return $resource;
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

        if ($this->isResourceOpen() === false) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            \clearstatcache(true, $this->uri);
        }

        /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
        $stats = \fstat($this->resource);
        if (isset($stats['size'])) {
            $this->size = $stats['size'];
        }

        return $this->size;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws RuntimeException on error.
     */
    public function tell()
    {
        if ($this->isResourceOpen() === false) {
            throw new RuntimeException($this->strings['detached']);
        }
        /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
        $result = \ftell($this->resource);
        if ($result === false) {
            throw new RuntimeException($this->strings['posUnknown']);
        }
        return $result;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     * @throws RuntimeException
     */
    public function eof()
    {
        if ($this->isResourceOpen() === false) {
            throw new RuntimeException($this->strings['detached']);
        }
        /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
        return \feof($this->resource);
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
     * Seek to a position in the stream.
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.
     *     SEEK_SET: Set position equal to offset bytes
     *     SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     *
     * @link   http://www.php.net/manual/en/function.fseek.php
     * @throws RuntimeException on failure.
     *
     * @return void
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $whence = (int) $whence;
        if ($this->isResourceOpen() === false) {
            throw new RuntimeException($this->strings['detached']);
        }
        if (!$this->seekable) {
            throw new RuntimeException($this->strings['seekNonSeekable']);
        }
        /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
        if (\fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException(\sprintf(
                $this->strings['seekFail'],
                $offset,
                \var_export($whence, true)
            ));
        }
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @link   http://www.php.net/manual/en/function.fseek.php
     * @see    seek()
     * @throws RuntimeException on failure.
     *
     * @return void
     */
    public function rewind()
    {
        $this->seek(0);
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
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     *
     * @return int Returns the number of bytes written to the stream.
     * @throws RuntimeException on failure.
     */
    public function write($string)
    {
        if ($this->isResourceOpen() === false) {
            throw new RuntimeException($this->strings['detached']);
        }
        if (!$this->writable) {
            throw new RuntimeException($this->strings['writeFailNonWritable']);
        }

        // We can't know the size after writing anything
        $this->size = null;
        /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
        $result = \fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException($this->strings['writeFail']);
        }
        return $result;
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
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     *
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws RuntimeException if an error occurs.
     * @throws InvalidArgumentException if negative length specified
     */
    public function read($length)
    {
        if ($this->isResourceOpen() === false) {
            throw new RuntimeException($this->strings['detached']);
        }
        if (!$this->readable) {
            throw new RuntimeException($this->strings['readFailNonReadable']);
        }
        if ($length < 0) {
            throw new InvalidArgumentException($this->strings['readLengthNegative']);
        }
        if ($length === 0) {
            return '';
        }

        /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
        $string = \fread($this->resource, $length);
        if ($string === false) {
            throw new RuntimeException($this->strings['readFail']);
        }
        return $string;
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents()
    {
        if ($this->isResourceOpen() === false) {
            throw new RuntimeException($this->strings['detached']);
        }

        $contents = false;
        if ($this->readable) {
            /** @psalm-suppress PossiblyInvalidArgument we know resource is open */
            $contents = \stream_get_contents($this->resource);
        }
        if ($contents === false) {
            throw new RuntimeException($this->strings['readFail']);
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
     *
     * @psalm-suppress PossiblyInvalidArgument we know resource is open
     */
    public function getMetadata($key = null)
    {
        if ($this->isResourceOpen() === false) {
            return $key !== null
                ? null
                : array();
        }
        $meta = $this->customMetadata + \stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }
        return isset($meta[$key])
            ? $meta[$key]
            : null;
    }
}
