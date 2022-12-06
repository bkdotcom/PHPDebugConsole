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

use bdk\HttpMessage\Stream;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Value object representing a file uploaded through an HTTP request.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 */
class UploadedFile implements UploadedFileInterface
{
    /** @var string|null */
    private $clientFilename;

    /** @var string|null */
    private $clientFullPath;

    /** @var string|null */
    private $clientMediaType;

    /** @var int */
    private $error;

    private $errors = array(
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
    );

    /** @var string|null */
    private $file;

    /** @var bool */
    private $isMoved = false;

    /** @var int|null */
    private $size;

    /** @var StreamInterface|null */
    private $stream;

    /** @var string */
    private $sapi = PHP_SAPI;

    /**
     * Constructor
     *
     *    __construct(array $fileInfo)
     *    __construct($streamOrFile, $size = null, $error = UPLOAD_ERR_OK, $clientFilename = null, $clientMediaType = null, $clientFullPath = null)
     *
     * @param array $values Uploaded file values as populated in $_FILES array
     *    null|string|resource|StreamInterface tmp_name  filepath, resource, or StreamInterface
     *    int    size      Size in bytes
     *    int    error     one of the UPLOAD_ERR_* constants
     *    string name      client file name
     *    string type      client mime type
     *    string full_path client full path (as of php 8.1)
     *
     * @throws InvalidArgumentException
     *
     * @see https://www.php.net/manual/en/features.file-upload.post-method.php
     */
    public function __construct($values = array())
    {
        $defaultValues = array(
            'tmp_name' => null,
            'size' => null,
            'error' => UPLOAD_ERR_OK,
            'name' => null,
            'type' => null,
            'full_path' => null,
        );
        if (\is_array($values) === false) {
            $values = \array_combine(\array_slice(\array_keys($defaultValues), 0, \func_num_args()), \func_get_args());
        }
        $values = \array_merge($defaultValues, $values);

        $this->assertSize($values['size']);
        $this->assertError($values['error']);
        $this->assertClientFilename($values['name']);
        $this->assertClientMediaType($values['type']);
        $this->assertStringOrNull($values['full_path'], 'clientFullPath');

        $this->size = $values['size'];
        $this->error = $values['error'];
        $this->clientFilename = $values['name'];
        $this->clientFullPath = $values['full_path'];
        $this->clientMediaType = $values['type'];

        if ($this->isOk()) {
            $this->setStream($values['tmp_name']);
        }
    }

    /**
     * Retrieve a stream representing the uploaded file.
     *
     * If the moveTo() method has been called previously, this method will raise
     * an exception.
     *
     * @return StreamInterface representation of the uploaded file.
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
            throw new RuntimeException('The stream has been moved.');
        }
        if (!$this->stream && $this->file) {
            $this->stream = $this->getStreamFromFile();
        }
        if (!$this->stream) {
            // ie error is not UPLOAD_ERR_OK
            throw new RuntimeException('No stream is available or can be created.');
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
        $this->validateCanMove();
        $this->assertTargetPath($targetPath);
        if ($this->file !== null) {
            $this->isMoved = $this->moveFile($targetPath);
            return;
        }
        $stream = $this->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        // Copy the contents of a stream into another stream until end-of-file.
        $dest = new Stream(\fopen($targetPath, 'w'));
        $bufferSize = (int) \pow(1024, 2); // 1 MB
        while (!$stream->eof()) {
            $dest->write($stream->read($bufferSize));
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
        return $this->errors[$this->error];
    }

    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * @return string|null The filename sent by the client
     *   or null if none was provided.
     */
    public function getClientFilename()
    {
        return $this->clientFilename ?: null;
    }

    /**
     * Retrieve the full_path sent by the client.
     *
     * NOT DEFINED IN INTERFACE
     *
     * full_path is new as of PHP 8.1 and passed by client when uploading a directory
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * @return string|null The full-path sent by the client
     *   or null if none was provided.
     */
    public function getClientFullPath()
    {
        return $this->clientFullPath ?: null;
    }

    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type with the intention to corrupt or hack your
     * application.
     *
     * @return string|null The media type sent by the client
     *   or null if none was provided.
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType ?: null;
    }

    /**
     * Assert valid client filename
     *
     * @param string|null $filename filename to validate
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertClientFilename($filename)
    {
        $this->assertStringOrNull($filename, 'clientFilename');
        if ($filename === null) {
            return;
        }
        if (\preg_match('#[/\r\n\x00]#', $filename)) {
            throw new InvalidArgumentException(\sprintf('Invalid client file name provided: "%s"', $filename));
        }
    }

    /**
     * Assert valid client filename
     *
     * @param string|null $type Content/Media type to validate
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertClientMediaType($type)
    {
        $this->assertStringOrNull($type, 'clientMediaType');
        if ($type === null || $type === '') {
            return;
        }
        // https://stackoverflow.com/a/48046041/1646086
        // https://en.wikipedia.org/wiki/Media_type#Standards_tree
        $regex = '#^'
            . '(?P<type>\w+)'
            . '/'
            . '(?P<subtype>[-.\w]+)'
            . '(?P<suffix>\+[-.\w]+)?'
            . '(\s*;\s*(?P<param>\w+)\s*=\s*(?P<val>\S+))*'
            . '$#' ;
        if (\preg_match($regex, $type) !== 1) {
            throw new InvalidArgumentException(\sprintf('Invalid media type specified: "%s"', $type));
        }
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
        if (\is_int($error) === false || \array_key_exists($error, $this->errors) === false) {
            throw new InvalidArgumentException('Upload file error status must be an integer value and one of the "UPLOAD_ERR_*" constants.');
        }
    }

    /**
     * Validate reported filesize
     *
     * @param int|null $size Reported filesize
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertSize($size)
    {
        if ($size === null || \is_int($size) && $size > -1) {
            return;
        }
        throw new InvalidArgumentException('Upload file size must be a positive integer');
    }

    /**
     * Validate client filename / filepath / mediaType
     *
     * @param string|null $value Reported filesize
     * @param string      $key   nNme of param being tested
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertStringOrNull($value, $key)
    {
        if ($value === null || \is_string($value)) {
            return;
        }
        throw new InvalidArgumentException('Upload file ' . $key . ' must be a string or null');
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
        if (\is_string($targetPath) === false || $targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        if (\is_writable(\dirname($targetPath)) === false) {
            // Throw exception if the $targetPath specified is invalid.
            throw new RuntimeException(\sprintf(
                'The target path "%s" is not writable.',
                $targetPath
            ));
        }
    }

    /**
     * Create Stream from filepath
     *
     * @return Stream
     *
     * @throws RuntimeException
     */
    private function getStreamFromFile()
    {
        $errMsg = '';
        \set_error_handler(static function ($type, $msg) use (&$errMsg) {
            $errMsg = '(' . $type . ') ' . $msg;
        });
        $resource = \fopen($this->file, 'r');
        \restore_error_handler();
        if (\is_resource($resource)) {
            return new Stream($resource);
        }
        throw new RuntimeException(
            $errMsg ?: 'Unable to open ' . $this->file
        );
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
        \set_error_handler(static function ($type, $msg) use (&$errMsg) {
            $errMsg = $msg;
        });
        $success = $this->sapi === 'cli'
            ? \rename($this->file, $targetPath)
            : \move_uploaded_file($this->file, $targetPath);
        \restore_error_handler();
        if ($success === false) {
            throw new RuntimeException(\rtrim(\sprintf(
                'Unable to move the file to %s (%s)',
                $targetPath,
                $errMsg
            ), ' ()'));
        }
        return $success;
    }

    /**
     * Set the file's stream
     *
     * @param string|resource|StreamInterface $streamOrFile filepath, resource, or StreamInterface
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function setStream($streamOrFile)
    {
        if (\is_string($streamOrFile)) {
            $this->file = $streamOrFile;
            if (\file_exists($this->file)) {
                $this->size = \filesize($this->file);
            }
            return;
        }
        if (\is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
            $this->size = $this->stream->getSize();
            return;
        }
        if ($streamOrFile instanceof StreamInterface) {
            $this->stream = $streamOrFile;
            $this->size = $this->stream->getSize();
            return;
        }
        throw new InvalidArgumentException(
            'Invalid file, resource, or StreamInterface provided for UploadedFile'
        );
    }

    /**
     * @return void
     * @throws \RuntimeException if is moved or not ok
     */
    private function validateCanMove()
    {
        if ($this->isOk() === false) {
            throw new RuntimeException('Cannot move upload due to upload error: ' . $this->getErrorMessage());
        }
        if ($this->isMoved) {
            throw new RuntimeException('Cannot move upload after it has already been moved (#reasons)');
        }
    }
}
