<?php

namespace bdk\Test\HttpMessage;

use bdk\HttpMessage\Stream;
use bdk\HttpMessage\UploadedFile;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \bdk\HttpMessage\UploadedFile
 */
class UploadedFileTest extends TestCase
{
    use ExpectExceptionTrait;
    use DataProviderTrait;
    use FactoryTrait;

    public function testConstruct()
    {
        // Test 1 (filepath)
        $uploadedFile = new UploadedFile(array(
            'tmp_name' => '/tmp/php1234.tmp', // source
            'size' => 100000,             // size
            'error' => UPLOAD_ERR_OK,     // error
            'name' => 'example1.jpg',     // name
            'type' => 'image/jpeg'        // type
        ));

        $this->assertTrue($uploadedFile instanceof UploadedFile);

        // Test 2 (Stream)
        $resource = \fopen(TEST_DIR . '/assets/logo.png', 'r+');
        $stream = new Stream($resource);
        $uploadedFile = new UploadedFile($stream);
        $this->assertEquals($stream, $uploadedFile->getStream());

        // Test 3 (resource)
        $sourceFile = TEST_DIR . '/assets/logo.png';
        $uploadedFile = new UploadedFile(
            \fopen($sourceFile, 'r+'), // source
            100000,             // size
            UPLOAD_ERR_OK,      // error
            'example1.jpg',     // name
            'image/jpeg'        // type
        );
        $this->assertSame(\filesize($sourceFile), $uploadedFile->getSize());
        $this->assertSame(\file_get_contents($sourceFile), $uploadedFile->getStream()->getContents());
    }

    public function testMoveToSapiCli()
    {
        $sourceFile = TEST_DIR . '/assets/logo.png';
        $cloneFile = self::getTestFilepath('logo_clone.png');
        $targetPath = self::getTestFilepath('logo_moved.png');

        // Clone a sample file for testing MoveTo method.
        $this->assertTrue(\copy($sourceFile, $cloneFile));

        $uploadedFile = $this->createUploadedFile(
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

        $uploadedFile = $this->createUploadedFile(
            $cloneFile,
            126,    // actual file size will be used
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png',
            '/foo/bar/logo.png'
        );

        $this->assertSame(\filesize(TEST_DIR . '/assets/logo.png'), $uploadedFile->getSize());
        $this->assertSame(0, $uploadedFile->getError());
        $this->assertSame('logo.png', $uploadedFile->getClientFilename());
        $this->assertSame('/foo/bar/logo.png', $uploadedFile->getClientFullPath());
        $this->assertSame('image/png', $uploadedFile->getClientMediaType());
        $this->assertSame('', $uploadedFile->getErrorMessage());

        $stream = $uploadedFile->getStream();

        $this->assertTrue($stream instanceof Stream);
    }

    public function testGetErrorMessage()
    {
        $uploadedFile = $this->createUploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $this->assertSame('', $uploadedFile->getErrorMessage());

        $reflection = new ReflectionProperty($uploadedFile, 'error');
        $reflection->setAccessible(true);

        $reflection->setValue($uploadedFile, UPLOAD_ERR_INI_SIZE);
        $this->assertSame(
            'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, UPLOAD_ERR_FORM_SIZE);
        $this->assertSame(
            'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, UPLOAD_ERR_PARTIAL);
        $this->assertSame(
            'The uploaded file was only partially uploaded.',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, UPLOAD_ERR_NO_FILE);
        $this->assertSame(
            'No file was uploaded.',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, UPLOAD_ERR_NO_TMP_DIR);
        $this->assertSame(
            'Missing a temporary folder.',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, UPLOAD_ERR_CANT_WRITE);
        $this->assertSame(
            'Failed to write file to disk.',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, UPLOAD_ERR_EXTENSION);
        $this->assertSame(
            'File upload stopped by extension.',
            $uploadedFile->getErrorMessage()
        );

        $reflection->setValue($uploadedFile, 19890604);
        $this->assertSame(
            'Unknown upload error.',
            $uploadedFile->getErrorMessage()
        );
    }

    public function testExceptionArgumentIsInvalidSource()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid stream or file provided for UploadedFile');
        new UploadedFile();
    }

    public function testExceptionUnableToOpen()
    {
        $sourceFile = '/tmp/php1234.tmp';

        $this->expectException('RuntimeException');
        // $this->expectExceptionMessage('Unable to open ' . $sourceFile);

        $uploadedFile = $this->createUploadedFile(
            $sourceFile,
            100000,             // size
            UPLOAD_ERR_OK,      // error
            'example1.jpg',     // name
            'image/jpeg'        // type
        );
        $uploadedFile->getStream();
    }

    public function testExceptionGetStreamIsMoved()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('The stream has been moved.');

        $sourceFile = TEST_DIR . '/assets/logo.png';
        $cloneFile = self::getTestFilepath('logo_clone.png');

        // Clone a sample file for testing MoveTo method.
        $this->assertTrue(\copy($sourceFile, $cloneFile));

        $resource = \fopen($cloneFile, 'r+');
        $stream = new Stream($resource);
        $uploadedFile = $this->createUploadedFile($stream);

        $targetPath = self::getTestFilepath('logo_moved.png');
        $uploadedFile->moveTo($targetPath);
        $this->assertTrue(\file_exists($targetPath));
        \unlink($targetPath);

        $uploadedFile->getStream();
    }

    public function testExceptionGetStreamNotAvailable()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No stream is available or can be created.');
        $uploadedFile = $this->createUploadedFile(
            '/tmp/php1234.tmp',
            100000,
            UPLOAD_ERR_FORM_SIZE,
            'logo.png',
            'image/png'
        );
        $uploadedFile->getStream();
    }

    public function testExceptionMovePath1()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid path provided for move operation; must be a non-empty string');
        $uploadedFile = $this->createUploadedFile(
            '/tmp/php1234.tmp',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );
        $uploadedFile->moveTo(true);
    }

    public function testExceptionMovePath2()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('The target path "some/bogus/path" is not writable.');
        $uploadedFile = $this->createUploadedFile(
            '/tmp/php1234.tmp',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );
        $uploadedFile->moveTo('some/bogus/path');
    }

    public function testExceptionMoveToFileIsMoved()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Cannot retrieve stream after it has already been moved');

        $uploadedFile = $this->createUploadedFile(
            '/tmp/php1234.tmp',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $reflection = new ReflectionProperty($uploadedFile, 'isMoved');
        $reflection->setAccessible(true);
        $reflection->setValue($uploadedFile, true);

        $targetPath = self::getTestFilepath('logo_moved.png');

        // Exception => The uploaded file has been moved.
        $uploadedFile->moveTo($targetPath);
    }

    public function testExceptionMoveToErrorUploaded()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Cannot retrieve stream due to upload error');

        $uploadedFile = $this->createUploadedFile(
            '/tmp/php1234.tmp',
            100000,
            UPLOAD_ERR_FORM_SIZE,
            'logo.png',
            'image/png'
        );

        $targetPath = self::getTestFilepath('logo_moved.png');
        $uploadedFile->moveTo($targetPath);
    }

    public function testExceptionMoveToTargetIsNotWritable()
    {
        $targetPath = TEST_DIR . '/tmp/folder-not-exists/test.png';

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('The target path "' . $targetPath . '" is not writable.');

        $uploadedFile = $this->createUploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        // Exception => The target path "/tmp/folder-not-exists/test.png" is not writable.
        $uploadedFile->moveTo($targetPath);
    }

    public function testExceptionMoveError()
    {
        $uploadedFile = $this->createUploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
        );

        $this->expectException('RuntimeException');
        $reflection = new ReflectionMethod($uploadedFile, 'moveFile');
        $reflection->setAccessible(true);
        // Exception => The target path "/tmp/folder-not-exists/test.png" is not writable.
        $reflection->invoke($uploadedFile, 'some/bogus/path');
    }

    public function testExceptionMoveToFileNotUploaded()
    {
        $targetPath = self::getTestFilepath('logo_moved.png');
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('Unable to move the file to ' . $targetPath);

        $uploadedFile = $this->createUploadedFile(
            TEST_DIR . '/assets/logo.png',
            100000,
            UPLOAD_ERR_OK,
            'logo.png',
            'image/png'
            // 'mock-is-uploaded-file-false'
        );

        $reflection = new ReflectionProperty($uploadedFile, 'sapi');
        $reflection->setAccessible(true);
        $reflection->setValue($uploadedFile, 'apache');
        // Exception => not an uploaded file
        $uploadedFile->moveTo($targetPath);
    }

    /**
     * @param $status
     *
     * @dataProvider invalidFileUploadErrorStatuses
     */
    public function testSettingInvalidErrorRaisesException($error)
    {
        $this->expectException('InvalidArgumentException');
        $stream = $this->createStream();
        $this->createUploadedFile($stream, 0, $error);
    }

    /**
     * @param int $code
     *
     * @dataProvider fileUploadErrorCodes
     */
    public function testNonOkErrorCodeRaisesExceptionOnGetStream($code)
    {
        $this->expectException('RuntimeException');
        $filepath = \tempnam(\sys_get_temp_dir(), 'source');
        $stream = $this->createStream($filepath);
        $uploadedFile = $this->createUploadedFile($stream, 100, $code)
            ->getStream();
    }

    /**
     * @param int $code
     *
     * @dataProvider fileUploadErrorCodes
     */
    public function testNonOkErrorCodeRaisesExceptionOnMoveTo($code)
    {
        $this->expectException('RuntimeException');
        $filepath = \tempnam(\sys_get_temp_dir(), 'source');
        $stream = $this->createStream($filepath);
        $uploadedFile = $this->createUploadedFile($stream, 100, $code)
            ->moveTo('/tmp/foo');
    }

    /**
     * @param $size
     *
     * @dataProvider invalidFileSizes
     */
    public function testSettingInvalidFileSizeThrowException($size)
    {
        $this->expectException('InvalidArgumentException');
        $stream = $this->createStream();
        $this->createUploadedFile($stream, $size, UPLOAD_ERR_OK);
    }

    /**
     * @param $fileName
     *
     * @dataProvider invalidFileNames
     */
    public function testInvalidClientFileNamesThrowException($fileName)
    {
        $this->expectException('InvalidArgumentException');
        $stream = $this->createStream();
        $this->createUploadedFile($stream, 0, UPLOAD_ERR_OK, $fileName);
    }

    /**
     * @param $mediaType
     *
     * @dataProvider validMediaTypes
     */
    public function testValidMediaTypesAreAccepted($mediaType)
    {
        $stream = $this->createStream();
        $file = $this->createUploadedFile($stream, 0, UPLOAD_ERR_OK, 'foobar.baz', $mediaType);
        $this->assertSame($mediaType, $file->getClientMediaType());
    }

    /**
     * @param $mediaType
     *
     * @dataProvider invalidMediaTypes
     */
    public function testInvalidClientMediaTypeRaiseAnException($mediaType)
    {
        $this->expectException('InvalidArgumentException');
        $stream = $this->createStream();
        $this->createUploadedFile($stream, 0, UPLOAD_ERR_OK, 'foobar.baz', $mediaType);
    }

    /**
     * @param $path
     *
     * @dataProvider invalidTargetPaths
     */
    public function testMovingToInvalidPathThrowsException($path)
    {
        $this->expectException('InvalidArgumentException');

        $stream = $this->createStream();
        $stream->write('Foo bar!');

        $upload = $this->createUploadedFile($stream, 0, UPLOAD_ERR_OK);
        $upload->moveTo($path);
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
