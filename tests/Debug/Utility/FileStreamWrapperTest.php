<?php

namespace bdk\Test\Debug\Utility;

use bdk\Debug;
use bdk\Debug\Utility\FileStreamWrapper;
use bdk\Test\PolyFill\AssertionTrait;
use bdk\Test\PolyFill\ExpectExceptionTrait;
use PHPUnit\Framework\TestCase;

/**
 * PHPUnit tests for Debug class
 *
 * @covers \bdk\Debug\Utility\FileStreamWrapper
 */
class FileStreamWrapperTest extends TestCase
{
    use AssertionTrait;
    use ExpectExceptionTrait;

    protected static $filepath = '';

    protected $stream;

    protected static $tmpdir = 'mkdirtest';
    protected static $tmpdir2 = 'mkdirtest2';

    public static function setUpBeforeClass(): void
    {
        $fileSource = TEST_DIR . '/assets/logo.png';
        static::$filepath = TEST_DIR . '/../tmp/streamTest.png';
        \copy($fileSource, static::$filepath);
        FileStreamWrapper::register();
    }

    public static function tearDownAfterClass(): void
    {
        self::rmdir(self::$tmpdir);
        self::rmdir(self::$tmpdir2);
    }

    public function testSetEventManager()
    {
        $pathsExcludeBack = FileStreamWrapper::getPathsExclude();
        $requirePath = __DIR__ . '/../Fixture/fileStreamWrapperRequireTest.php';

        $eventManager = \bdk\Debug::getInstance()->eventManager;
        FileStreamWrapper::setEventManager($eventManager);
        $modifyContent = static function (\bdk\PubSub\Event $event) {
            $event['content'] = \str_replace('return \'foo\'', 'return \'modified\'', $event['content']);
        };
        $eventManager->subscribe(Debug::EVENT_STREAM_WRAP, $modifyContent);

        $return = require $requirePath;
        // \bdk\Debug::varDump('return', $return);
        self::assertSame('modified', $return);

        // exclude specific file
        FileStreamWrapper::setPathsExclude(array(
            $requirePath,
        ));
        $return = require $requirePath;
        self::assertSame('foo', $return);
        self::assertSame(array(\realpath($requirePath)), FileStreamWrapper::getPathsExclude());

        // exclude dir
        FileStreamWrapper::setPathsExclude(array(
            __DIR__ . '/../Fixture',
        ));
        $return = require $requirePath;
        self::assertSame('foo', $return);

        FileStreamWrapper::setPathsExclude($pathsExcludeBack);
        $eventManager->unsubscribe(Debug::EVENT_STREAM_WRAP, $modifyContent);
    }

    public function testDirClosedir()
    {
        $resource = \opendir(__DIR__);
        self::assertIsResource($resource);
        \closedir($resource);
        self::assertFalse(is_resource($resource));

        $fsw = new FileStreamWrapper();
        self::assertFalse($fsw->dir_closedir());
    }

    public function testDirOpendir()
    {
        $resource = \opendir(__DIR__);
        self::assertIsResource($resource);
    }

    public function testDirReaddir()
    {
        $dh = \opendir(__DIR__);
        if (!$dh) {
            throw new \PHPUnit\Framework\Exception('opendir failed', 500);
        }
        $entry = \readdir($dh);
        self::assertNotFalse($entry);
        \closedir($dh);

        $fsw = new FileStreamWrapper();
        self::assertFalse($fsw->dir_readdir());
    }

    public function testDirRewinddir()
    {
        $dh = \opendir(__DIR__);
        if (!$dh) {
            throw new \PHPUnit\Framework\Exception('opendir failed', 500);
        }
        $entry = \readdir($dh);
        \rewinddir($dh);
        $entry2 = \readdir($dh);
        self::assertSame($entry, $entry2);
        \closedir($dh);

        $fsw = new FileStreamWrapper();
        self::assertFalse($fsw->dir_rewinddir());
    }

    public function testMkdir()
    {
        if (\is_dir(self::$tmpdir)) {
            self::rmdir(self::$tmpdir);
        }
        \mkdir(self::$tmpdir);
        self::assertTrue(\is_dir(self::$tmpdir));
    }

    public function testRegister()
    {
        // should already be registered... no harm in registering
        $return = FileStreamWrapper::register();
        self::assertNull($return);
    }

    public function testRename()
    {
        \rename(self::$tmpdir, self::$tmpdir2);
        self::assertTrue(\is_dir(self::$tmpdir2));
    }

    public function testRmdir()
    {
        \rmdir(self::$tmpdir2);
        self::assertFalse(\is_dir(self::$tmpdir2));
    }

    public function testStreamCast()
    {
        $fsw = new FileStreamWrapper();
        self::assertFalse($fsw->stream_cast(0));

        $resource = \fopen(__FILE__, 'r');

        // Debug::varDump('STREAM_CAST_AS_STREAM', STREAM_CAST_AS_STREAM, -1 & STREAM_CAST_AS_STREAM);

        \bdk\Debug\Utility\Reflection::propSet($fsw, 'resource', $resource);
        self::assertSame($resource, $fsw->stream_cast(42));

        self::assertSame($resource, $fsw->stream_cast(STREAM_CAST_AS_STREAM));
    }

    public function testStreamClose()
    {
        if (!\is_dir(self::$tmpdir)) {
            \mkdir(self::$tmpdir);
        }
        $fh = \fopen(self::$tmpdir . '/tempfile', 'w');
        \fclose($fh);
        self::assertFalse(\is_resource($fh));
    }

    public function testStreamEof()
    {
        if (!\is_dir(self::$tmpdir)) {
            \mkdir(self::$tmpdir);
        }
        $fh = \fopen(self::$tmpdir . '/tempfile', 'w+');
        \fwrite($fh, 'stringy goodness');
        \fseek($fh, 0);
        self::assertFalse(\feof($fh));
        $line = \fgets($fh);
        self::assertTrue(feof($fh));
        \fclose($fh);
    }

    public function testStreamFlush()
    {
        if (!\is_dir(self::$tmpdir)) {
            \mkdir(self::$tmpdir);
        }
        $file = self::$tmpdir . '/tempfile';
        $fh = \fopen($file, 'r+');
        \rewind($fh);
        \fwrite($fh, 'stringy goodness');
        $success = \fflush($fh);
        self::assertTrue($success);
        \ftruncate($fh, \ftell($fh));
        \fclose($fh);
        self::assertSame('stringy goodness', \file_get_contents($file));
    }

    public function testStreamLock()
    {
        if (!\is_dir(self::$tmpdir)) {
            \mkdir(self::$tmpdir);
        }
        $file = self::$tmpdir . '/tempfile';
        $fh = \fopen($file, 'r+');
        $success = \flock($fh, LOCK_EX);
        self::assertTrue($success);
        $fh2 = \fopen($file, 'r+');
        $success = \flock($fh2, LOCK_EX | LOCK_NB);
        self::assertFalse($success);
        \fclose($fh);

        $fsw = new FileStreamWrapper();
        self::assertFalse($fsw->stream_lock(LOCK_SH));
    }

    public function testStreamMetadata()
    {
        $filepath = TEST_DIR . '/../tmp/streamTest.png';
        $fileinfo = $this->getFileInfo($filepath);

        $fsw = new FileStreamWrapper();

        self::assertTrue($fsw->stream_metadata($filepath, STREAM_META_TOUCH, array()));
        self::assertTrue($fsw->stream_metadata($filepath, STREAM_META_OWNER_NAME, $fileinfo['user']['name']));
        self::assertTrue($fsw->stream_metadata($filepath, STREAM_META_OWNER, $fileinfo['user']['uid']));
        self::assertTrue($fsw->stream_metadata($filepath, STREAM_META_GROUP_NAME, $fileinfo['group']['name']));
        self::assertTrue($fsw->stream_metadata($filepath, STREAM_META_GROUP, $fileinfo['group']['gid']));
        self::assertTrue($fsw->stream_metadata($filepath, STREAM_META_ACCESS, $fileinfo['mode']));
    }

    public function testStreamOpen()
    {
        \set_error_handler(function () {});
        self::assertFalse(\fopen(__DIR__ . '/no_such_file.txt', 'r'));
        self::assertFalse(\fopen(static::$filepath, 'rx'));
        \restore_error_handler();
        self::assertIsResource(\fopen(static::$filepath, 'r'));
    }

    public function testStreamSeek()
    {
        $resource = \fopen(static::$filepath, 'rw');
        self::assertSame(0, \fseek($resource, 100));
    }

    public function testStreamSetOption()
    {
        $fsw = new FileStreamWrapper();
        $resource = \fopen(static::$filepath, 'rw');

        \set_error_handler(function () {});
        self::assertFalse($fsw->stream_set_option(STREAM_OPTION_WRITE_BUFFER, STREAM_BUFFER_NONE, 1024));
        \restore_error_handler();

        self::assertTrue(\stream_set_blocking($resource, false));

        self::assertFalse(\stream_set_timeout($resource, 88));

        \bdk\Debug\Utility\Reflection::propSet($fsw, 'resource', $resource);
        $fsw->stream_set_option(STREAM_OPTION_WRITE_BUFFER, STREAM_BUFFER_NONE, 1024);
    }

    public function testStreamTell()
    {
        $fsw = new FileStreamWrapper();
        $resource = \fopen(static::$filepath, 'r');
        self::assertFalse($fsw->stream_tell());
        \bdk\Debug\Utility\Reflection::propSet($fsw, 'resource', $resource);
        self::assertSame(0, $fsw->stream_tell());
    }

    public function testUnlink()
    {
        if (!\is_dir(self::$tmpdir)) {
            \mkdir(self::$tmpdir);
        }
        $file = self::$tmpdir . '/tempfile';
        \file_put_contents($file, 'string');
        $success = \unlink($file);
        self::assertTrue($success);
    }

    /*
    public function testStreamRead()
    {
        // $this->markTestSkipped(__METHOD__);
    }

    public function testStreamStat()
    {
        // $this->markTestSkipped(__METHOD__);
    }

    public function testStreamTruncate()
    {
        // $this->markTestSkipped(__METHOD__);
    }

    public function testStreamWrite()
    {
        // $this->markTestSkipped(__METHOD__);
    }

    public function testUrlStat()
    {
        $this->markTestSkipped('todo');
    }
    */

    protected static function rmdir($dirPath)
    {
        if (!\is_dir($dirPath)) {
            // throw new \InvalidArgumentException($dirPath.' must be a directory');
            return;
        }
        if (\substr($dirPath, \strlen($dirPath) - 1, 1) !== '/') {
            $dirPath .= '/';
        }
        $files = \glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            \is_dir($file)
                ? self::rmdir($file)
                : \unlink($file);
        }
        \rmdir($dirPath);
    }

    protected function getFileInfo($filepath)
    {
        $stats = \stat($filepath);
        return array(
            'group' => \posix_getgrgid($stats['gid']),
            'mode' => $stats['mode'] & 0777,
            'user' => \posix_getpwuid($stats['uid']),
        );
    }
}
