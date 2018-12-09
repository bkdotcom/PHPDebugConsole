<?php

use \bdk\Debug\FileStreamWrapper;

/**
 * PHPUnit tests for Debug class
 */
class FileStreamWrapperTest extends \PHPUnit\Framework\TestCase
{

    protected $stream;

    protected static $tmpdir = 'mkdirtest';
    protected static $tmpdir2 = 'mkdirtest2';

    public static function setUpBeforeClass()
    {
        FileStreamWrapper::register();
    }

    public static function tearDownAfterClass()
    {
        self::rmdir(self::$tmpdir);
        self::rmdir(self::$tmpdir2);
    }

    public function testDirClosedir()
    {
        $resource = opendir(__DIR__);
        $this->assertInternalType(\PHPUnit\Framework\Constraint\IsType::TYPE_RESOURCE, $resource);
        closedir($resource);
        $this->assertFalse(is_resource($resource));
    }

    public function testDirOpendir()
    {
        $resource = opendir(__DIR__);
        $this->assertInternalType(\PHPUnit\Framework\Constraint\IsType::TYPE_RESOURCE, $resource);
    }

    public function testDirReaddir()
    {
        if ($dh = opendir(__DIR__)) {
            $entry = readdir($dh);
            $this->assertNotFalse($entry);
            closedir($dh);
        } else {
            throw new \PHPUnit\Framework\Exception('opendir failed', 500);
        }
    }

    public function testDirRewinddir()
    {
        if ($dh = opendir(__DIR__)) {
            $entry = readdir($dh);
            rewinddir($dh);
            $entry2 = readdir($dh);
            $this->assertSame($entry, $entry2);
            closedir($dh);
        } else {
            throw new \PHPUnit\Framework\Exception('opendir failed', 500);
        }
    }

    public function testMkdir()
    {
        if (is_dir(self::$tmpdir)) {
            self::rmdir(self::$tmpdir);
        }
        mkdir(self::$tmpdir);
        $this->assertTrue(is_dir(self::$tmpdir));
    }

    public function testRename()
    {
        rename(self::$tmpdir, self::$tmpdir2);
        $this->assertTrue(is_dir(self::$tmpdir2));
    }

    public function testRmdir()
    {
        rmdir(self::$tmpdir2);
        $this->assertFalse(is_dir(self::$tmpdir2));
    }

    public function testStreamCast()
    {
        /*
        if (!is_dir(self::$tmpdir)) {
            mkdir(self::$tmpdir);
        }
        $fh = fopen(self::$tmpdir.'/tempfile', 'r+');
        var_dump($fh);
        $read   = array($fh);
        $write  = null;
        $except = null;
        $numChangedStreams = stream_select($read, $write, $except, 0);
        $this->assertTrue($numChangedStreams !== false);
        fclose($fh);
        */
    }

    public function testStreamClose()
    {
        if (!is_dir(self::$tmpdir)) {
            mkdir(self::$tmpdir);
        }
        $fh = fopen(self::$tmpdir.'/tempfile', 'w');
        fclose($fh);
        $this->assertFalse(is_resource($fh));
    }

    public function testStreamEof()
    {
        if (!is_dir(self::$tmpdir)) {
            mkdir(self::$tmpdir);
        }
        $fh = fopen(self::$tmpdir.'/tempfile', 'w+');
        fwrite($fh, 'stringy goodness');
        fseek($fh, 0);
        $this->assertFalse(feof($fh));
        $line = fgets($fh);
        $this->assertTrue(feof($fh));
        fclose($fh);
    }

    public function testStreamFlush()
    {
        if (!is_dir(self::$tmpdir)) {
            mkdir(self::$tmpdir);
        }
        $file = self::$tmpdir.'/tempfile';
        $fh = fopen($file, 'r+');
        rewind($fh);
        fwrite($fh, 'stringy goodness');
        $success = fflush($fh);
        $this->assertTrue($success);
        ftruncate($fh, ftell($fh));
        fclose($fh);
        $this->assertSame('stringy goodness', file_get_contents($file));
    }

    public function testStreamLock()
    {
        if (!is_dir(self::$tmpdir)) {
            mkdir(self::$tmpdir);
        }
        $file = self::$tmpdir.'/tempfile';
        $fh = fopen($file, 'r+');
        $success = flock($fh, LOCK_EX);
        $this->assertTrue($success);
        $fh2 = fopen($file, 'r+');
        $success = flock($fh2, LOCK_EX | LOCK_NB);
        $this->assertFalse($success);
        fclose($fh);
    }

    public function testStreamMetadata()
    {
        /*
        touch()
        chmod()
        chown()
        chgrp()
        */

    }

    public function testStreamOpen()
    {

    }

    public function testStreamRead()
    {

    }

    public function testStreamSeek()
    {

    }

    public function testStreamSetOption()
    {

    }

    public function testStreamStat()
    {

    }

    public function testStreamTell()
    {

    }

    public function testStreamTruncate()
    {

    }

    public function testStreamWrite()
    {

    }

    public function testUnlink()
    {
        if (!is_dir(self::$tmpdir)) {
            mkdir(self::$tmpdir);
        }
        $file = self::$tmpdir.'/tempfile';
        file_put_contents($file, 'string');
        $success = unlink($file);
        $this->assertTrue($success);
    }

    public function testUrlStat()
    {

    }

    protected static function rmdir($dirPath)
    {
        if (!is_dir($dirPath)) {
            //throw new InvalidArgumentException($dirPath.' must be a directory');
            return;
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::rmdir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }
}
