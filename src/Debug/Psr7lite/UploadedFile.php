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

use bdk\Debug\Psr7lite\Stream;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Value object representing a file uploaded through an HTTP request.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 */
class UploadedFile
{

    const ERRORS = array(
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
    );

    /** @var string */
    private $clientFilename;

    /** @var string */
    private $clientMediaType;

    /** @var int */
    private $error;

    /** @var string|null */
    private $file;

    /** @var bool */
    private $isMoved = false;

    /** @var int */
    private $size;

    /** @var StreamInterface|null */
    private $stream;

    /** @var string */
    private $sapi = PHP_SAPI;

    /**
     * Constructor
     *
     * @param mixed  $streamOrFile    Stream, Resouce, or filepath
     * @param int    $size            Size in bytes
     * @param int    $error           one of the UPLOAD_ERR_* constants
     * @param string $clientFilename  client file name
     * @param string $clientMediaType client mime type
     *
     * @throws InvalidArgumentException
     */
    public function __construct($streamOrFile, $size = null, $error = UPLOAD_ERR_OK, $clientFilename = null, $clientMediaType = null)
    {
        if ($size !== null && !\is_int($size)) {
            throw new InvalidArgumentException('Upload file size must be an integer');
        }
        $this->assertError($error);
        if ($clientFilename !== null && !\is_string($clientFilename)) {
            throw new InvalidArgumentException('Upload file client filename must be a string or null');
        }
        if ($clientMediaType !== null && !\is_string($clientMediaType)) {
            throw new InvalidArgumentException('Upload file client media type must be a string or null');
        }

        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if ($this->isOk()) {
            $this->setStreamOrFile($streamOrFile);
        }
    }

    /**
     * Retrieve a stream representing the uploaded file.
     *
     * If the moveTo() method has been called previously, this method will raise
     * an exception.
     *
     * @return Stream Stream representation of the uploaded file.
     *
     * @throws RuntimeException in cases when no stream is available or can be
     *     created.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function getStream()
    {
        if ($this->isMoved) {
            throw new RuntimeException(
                'The stream has been moved.'
            );
        }

        if (!$this->stream && $this->file) {
            $errMsg = '';
            \set_error_handler(function ($type, $msg) use (&$errMsg) {
                $errMsg = $msg;
            });
            $resource = \fopen($this->file, 'r');
            \restore_error_handler();
            if (\is_resource($resource)) {
                $this->stream = new Stream($resource);
                return $this->stream;
            }
            throw new RuntimeException(
                $errMsg ?: 'Unable to open ' . $this->file
            );
        }
        if (!$this->stream) {
            throw new RuntimeException(
                'No stream is available or can be created.'
            );
        }
        return $this->stream;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Use this method as an alternative to move_uploaded_file().
     * This method is guaranteed to work in both SAPI and non-SAPI environments.
     *
     * $targetPath may be an absolute path, or a relative path. If it is a
     * relative path, resolution will be the same as used by PHP's rename()
     * function.
     *
     * The original file or stream will be removed on completion.
     *
     * If this method is called more than once, any subsequent calls will raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @param string $targetPath Path to which to move the uploaded file.
     *
     * @return void
     *
     * @throws InvalidArgumentException if the $targetPath specified is invalid.
     * @throws RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     */
    public function moveTo($targetPath)
    {
        $this->validateActive();
        $this->assertTargetPath($targetPath);
        if ($this->file !== null) {
            $this->moveFile($targetPath);
            return;
        }
        $stream = $this->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        // Copy the contents of a stream into another stream until end-of-file.
        $dest = new Stream(\fopen($targetPath, 'w'));
        $bufferSize = \pow(1024, 2); // 1 MB
        while (!$stream->eof()) {
            if (!$dest->write($stream->read($bufferSize))) {
                break;
            }
        }
        $this->isMoved = true;
    }

    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * Returns one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, will return
     * UPLOAD_ERR_OK.
     *
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get uploaded file's error message
     *
     * This is a non PSR-7 method
     *
     * @return string
     */
    public function getErrorMessage()
    {
        if ($this->error === UPLOAD_ERR_OK) {
            return '';
        }
        return \array_key_exists($this->error, self::ERRORS)
            ? self::ERRORS[$this->error]
            : 'Unknown upload error.';
    }

    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename()
    {
        return $this->clientFilename;
    }

    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type with the intention to corrupt or hack your
     * application.
     *
     * @return string|null The media type sent by the client or null if none
     *     was provided.
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }

    /**
     * Validate uploaded file error
     *
     * @param int $error one of the UPLOAD_ERR_* constants
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertError($error)
    {
        if (\is_int($error) === false || !\array_key_exists($error, self::ERRORS)) {
            throw new InvalidArgumentException('Upload file error status must be an integer value and one of the "UPLOAD_ERR_*" constants.');
        }
    }

    /**
     * Validate target path
     *
     * @param string $targetPath Path to which to move the uploaded file.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function assertTargetPath($targetPath)
    {
        if (!\is_string($targetPath) || $targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        if (!\is_writable(\dirname($targetPath))) {
            // Throw exception if the $targetPath specified is invalid.
            throw new RuntimeException(\sprintf(
                'The target path "%s" is not writable.',
                $targetPath
            ));
        }
    }

    /**
     * Test if no upload error
     *
     * @return bool
     */
    private function isOk()
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * Move file
     *
     * @param string $targetPath Path to which to move the uploaded file.
     *
     * @return bool
     *
     * @throws RuntimeException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    private function moveFile($targetPath)
    {
        $errMsg = '';
        \set_error_handler(function ($type, $msg) use (&$errMsg) {
            $errMsg = $msg;
        });
        $this->isMoved = $this->sapi === 'cli'
            ? \rename($this->file, $targetPath)
            : \move_uploaded_file($this->file, $targetPath);
        \restore_error_handler();
        if ($this->isMoved === false) {
            $msg = \sprintf(
                'Unable to move the file to %s',
                $targetPath
            );
            if ($errMsg) {
                $msg .= '(' . $errMsg . ')';
            }
            throw new RuntimeException($errMsg);
        }
        return $this->isMoved;
    }

    /**
     * Depending on the value set file or stream variable
     *
     * @param mixed $streamOrFile filepath, resource, or Stream
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function setStreamOrFile($streamOrFile)
    {
        if (\is_string($streamOrFile)) {
            $this->file = $streamOrFile;
            if (\file_exists($streamOrFile)) {
                $this->size = \filesize($streamOrFile);
            }
            return;
        }
        if (\is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
            $stats = \fstat($this->resource);
            if (isset($stats['size'])) {
                $this->size = $stats['size'];
            }
            return;
        }
        if ($streamOrFile instanceof Stream) {
            $this->stream = $streamOrFile;
            $this->size = $this->stream->getSize();
            return;
        }
        if ($streamOrFile instanceof StreamInterface) {
            $this->stream = $streamOrFile;
            $this->size = $this->stream->getSize();
            return;
        }
        throw new InvalidArgumentException(
            'Invalid stream or file provided for UploadedFile'
        );
    }

    /**
     * @return void
     * @throws \RuntimeException if is moved or not ok
     */
    private function validateActive()
    {
        if ($this->isOk() === false) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }
        if ($this->isMoved) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }
}
