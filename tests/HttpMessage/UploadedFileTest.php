<?php

namespace bdk\DebugTests\HttpMessage;

use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

/**
 *
 */
class UploadedFileTest extends TestCase
{
    use \bdk\DebugTests\PolyFill\ExpectExceptionTrait;

    public function testConstruct()
    {
        // Test 1

        $uploadedFile = new UploadedFile(
            '/tmp/php1234.tmp', // source
            100000,             // size
            UPLOAD_ERR_OK,      // error
            'example1.jpg',     // name
            'image/jpeg'        // type
        );

        $this->assertTrue($uploadedFile instanceof UploadedFile);

        // Test 2
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);
        $uploadedFile = new UploadedFile($stream);
        $this->assertEquals($stream, $uploadedFile->getStream());
    }

    public function testMoveToSapiCli()
    {
        $sourceFile = TEST_DIR . '/assets/logo.png';
        $cloneFile = self::getTestFilepath('logo_clone.png');
        $targetPath = self::getTestFilepath('logo_moved.png');

        // Clone a sample file for testing MoveTo method.
        $this->assertTrue(\copy($sourceFile, $cloneFile));

        $uploadedFile = new UploadedFile(
            $cloneFile,
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $uploadedFile->moveTo($targetPath);

        $this->assertTrue(\file_exists($targetPath));

        \unlink($targetPath);
    }

    public function testGetPrefixMethods()
    {
        $sourceFile = TEST_DIR . '/assets/logo.png';
        $cloneFile = self::getTestFilepath('logo_clone.png');

        // Clone a sample file for testing MoveTo method.
        $this->assertTrue(\copy($sourceFile, $cloneFile));

        $uploadedFile = new UploadedFile(
            $cloneFile,
            126,    // actual file size will be used
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $this->assertSame(\filesize(TEST_DIR . '/assets/logo.png'), $uploadedFile->getSize());
        $this->assertSame(0, $uploadedFile->getError());
        $this->assertSame('logo.png', $uploadedFile->getClientFilename());
        $this->assertSame('image/png', $uploadedFile->getClientMediaType());
        $this->assertSame('', $uploadedFile->getErrorMessage());

        $stream = $uploadedFile->getStream();

        $this->assertTrue($stream instanceof Stream);
    }

    function testGetErrorMessage()
    {
        $uploadedFile = new UploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $this->assertSame('', $uploadedFile->getErrorMessage());

        $reflection = new ReflectionObject($uploadedFile);
        $error = $reflection->getProperty('error');
        $error->setAccessible(true);

        $error->setValue($uploadedFile, UPLOAD_ERR_INI_SIZE);
        $this->assertSame(
            'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, UPLOAD_ERR_FORM_SIZE);
        $this->assertSame(
            'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, UPLOAD_ERR_PARTIAL);
        $this->assertSame(
            'The uploaded file was only partially uploaded.',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, UPLOAD_ERR_NO_FILE);
        $this->assertSame(
            'No file was uploaded.',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, UPLOAD_ERR_NO_TMP_DIR);
        $this->assertSame(
            'Missing a temporary folder.',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, UPLOAD_ERR_CANT_WRITE);
        $this->assertSame(
            'Failed to write file to disk.',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, UPLOAD_ERR_EXTENSION);
        $this->assertSame(
            'File upload stopped by extension.',
            $uploadedFile->getErrorMessage()
        );

        $error->setValue($uploadedFile, 19890604);
        $this->assertSame(
            'Unknown upload error.',
            $uploadedFile->getErrorMessage()
        );
    }

    /*
        Exceptions
    */

    public function testExceptionArgumentIsInvalidSource()
    {
        $this->expectException('InvalidArgumentException');
        // Exception => First argument accepts only a string or StreamInterface instance.
        new UploadedFile([]);
    }

    public function testExceptionGetStreamStreamIsNotAvailable()
    {
        $this->expectException('RuntimeException');

        // Test 1: Source is not a stream.

        $uploadedFile = new UploadedFile(
            '/tmp/php1234.tmp', // source
            100000,             // size
            UPLOAD_ERR_OK,      // error
            'example1.jpg',     // name
            'image/jpeg'        // type
        );

        // Exception => No stream is available or can be created.
        $uploadedFile->getStream();
    }

    public function testExceptionGetStreamStreamIsMoved()
    {
        $this->expectException('RuntimeException');

        // Test 2: Stream has been moved, so can't find it using getStream().

        $sourceFile = TEST_DIR . '/assets/logo.png';
        $cloneFile = self::getTestFilepath('logo_clone.png');

        // Clone a sample file for testing MoveTo method.
        $this->assertTrue(\copy($sourceFile, $cloneFile));

        $resource = \fopen($cloneFile, 'r+');
        $stream = new Stream($resource);
        $uploadedFile = new UploadedFile($stream);

        $targetPath = self::getTestFilepath('logo_moved.png');
        $uploadedFile->moveTo($targetPath);

        $this->assertTrue(\file_exists($targetPath));

        \unlink($targetPath);

        // Exception => The stream has been moved
        $uploadedFile->getStream();
    }

    public function testExceptionMoveToFileIsMoved()
    {
        $this->expectException('RuntimeException');

        $uploadedFile = new UploadedFile(
            '/tmp/php1234.tmp',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $reflection = new ReflectionObject($uploadedFile);
        $isMoved = $reflection->getProperty('isMoved');
        $isMoved->setAccessible(true);
        $isMoved->setValue($uploadedFile, true);

        $targetPath = self::getTestFilepath('logo_moved.png');

        // Exception => The uploaded file has been moved.
        $uploadedFile->moveTo($targetPath);
    }

    public function testExceptionMoveToTargetIsNotWritable()
    {
        $this->expectException('RuntimeException');

        $uploadedFile = new UploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        // Exception => The target path "/tmp/folder-not-exists/test.png" is not writable.
        $uploadedFile->moveTo(TEST_DIR . '/tmp/folder-not-exists/test.png');
    }

    public function testExceptionMoveToFileNotUploaded()
    {
        $this->expectException('RuntimeException');

        $uploadedFile = new UploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
            // 'mock-is-uploaded-file-false'
        );

        $reflection = new ReflectionObject($uploadedFile);
        $sapi = $reflection->getProperty('sapi');
        $sapi->setAccessible(true);
        $sapi->setValue($uploadedFile, 'apache');

        $targetPath = self::getTestFilepath('logo_moved.png');
        // Exception => not an uploaded file
        $uploadedFile->moveTo($targetPath);
    }

    /**
     * Create a writable directrory for unit testing.
     *
     * @param string $filename File name.
     * @param string $dir      (optional) specify sub dir
     *
     * @return string The file's path.
     */
    private static function getTestFilepath($filename, $dir = '')
    {
        $dir = $dir === ''
            ? TEST_DIR . '/../tmp'
            : TEST_DIR . '/tmp/' . $dir;
        if (!\is_dir($dir)) {
            $originalUmask = \umask(0);
            $result = @mkdir($dir, 0777, true);
            \umask($originalUmask);
        }
        return $dir . '/' . $filename;
    }
}
