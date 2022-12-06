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

use InvalidArgumentException;
use RuntimeException;

/**
 * Extended by Stream
 */
abstract class AbstractStream
{
    protected $strings = array(
        'detached' => 'Stream is detached',
        'fopenFail' => 'The file %s cannot be opened.',
        'posUnknown' => 'Unable to determine stream position',
        'readFail' => 'Unable to read from stream',
        'readFailNonReadable' => 'Unable to read from non-readable stream',
        'readLengthNegative' => 'Length parameter cannot be negative',
        'resourceInvalidType' => 'Expected resource, filename, or string. %s provided',
        'seekFail' => 'Unable to seek to stream position %s with whence %s',
        'seekNonSeekable' => 'Stream is not seekable',
        'writeFail' => 'Unable to write to stream',
        'writeFailNonWritable' => 'Unable to write to a non-writable stream',
    );

    /** @var resource|closed-resource|null A resource reference */
    protected $resource;

    /**
     * Return object class or value type
     *
     * @param mixed $value The value being type checked
     *
     * @return string
     */
    protected static function getTypeDebug($value)
    {
        return \is_object($value)
            ? \get_class($value)
            : \gettype($value);
    }

    /**
     * Safely test if value is a file
     *
     * @param string $value The value to check
     *
     * @return bool
     */
    protected static function isFile($value)
    {
        return \is_string($value)
            && \preg_match('#(://|[\r\n\x00])#', $value) !== 1
            && \is_file($value);
    }

    /**
     * Is resource open?
     *
     * @return bool
     */
    protected function isResourceOpen()
    {
        return isset($this->resource) && \is_resource($this->resource);
    }

    /**
     * Set resource
     *
     * @param mixed $value Resource, filepath, or string content to wrap.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected function setResource($value)
    {
        if ($value === null) {
            $this->resource = \fopen('php://temp', 'wb+');
            return;
        }
        if (\is_resource($value)) {
            $this->resource = $value;
            return;
        }
        if ($this->isFile($value)) {
            $this->setResourceFile($value);
            return;
        }
        if (\is_string($value)) {
            $this->resource = \fopen('php://temp', 'wb+');
            \fwrite($this->resource, $value);
            \rewind($this->resource);
            return;
        }
        throw new InvalidArgumentException(\sprintf(
            $this->strings['resourceInvalidType'],
            $this->getTypeDebug($value)
        ));
    }

    /**
     * Set resource to the specified file
     *
     * @param string $file filepath
     *
     * @return void
     *
     * @throws RuntimeException
     */
    protected function setResourceFile($file)
    {
        \set_error_handler(static function () {
        });
        $this->resource = \fopen($file, 'r');
        \restore_error_handler();
        if ($this->resource === false) {
            throw new RuntimeException(\sprintf(
                $this->strings['fopenFail'],
                $file
            ));
        }
    }
}
