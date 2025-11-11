<?php

namespace bdk\Test\I18n;

use bdk\I18n\FileLoader;
use PHPUnit\Framework\TestCase;

/**
 * @covers bdk\I18n\FileLoader
 */
class FileLoaderTest extends TestCase
{
    public function testRegisterExtParser()
    {
        $fileLoader = new FileLoader();
        $fileLoader->registerExtParser('bob', static function ($filepath) {
            return \unserialize(\file_get_contents($filepath));
        });
        $data = $fileLoader->load(__DIR__ . '/trans/t/en.bob');
        self::assertSame('serialized seems dumb', $data['idea.bad']);
    }

    public function testLoadErrorIsFile()
    {
        $fileLoader = new FileLoader();
        $file = __DIR__ . '/bogusFile.csv';
        $data = $fileLoader->load($file);
        self::assertSame(array(), $data);
        self::assertSame($file . ' is not a file', $fileLoader->lastError);
    }

    public function testLoadErrorNoParser()
    {
        $fileLoader = new FileLoader();
        $file = __DIR__ . '/trans/t/readme.md';
        $data = $fileLoader->load($file);
        self::assertSame(array(), $data);
        self::assertSame('No parser defined for md files', $fileLoader->lastError);
    }

    public function testLoadErrorError()
    {
        $fileLoader = new FileLoader();
        $fileLoader->registerExtParser('md', static function ($filepath) {
            \trigger_error('some error', E_USER_DEPRECATED);
        });
        $file = __DIR__ . '/trans/t/readme.md';
        $data = $fileLoader->load($file);
        self::assertSame(array(), $data);
        self::assertSame('some error', $fileLoader->lastError);
    }

    public function testLoadErrorException()
    {
        $fileLoader = new FileLoader();
        $fileLoader->registerExtParser('md', static function ($filepath) {
            throw new \Exception('oh dear');
        });
        $file = __DIR__ . '/trans/t/readme.md';
        $data = $fileLoader->load($file);
        self::assertSame(array(), $data);
        self::assertSame('oh dear', $fileLoader->lastError);
    }

    public function testLoadErrorNotArray()
    {
        $fileLoader = new FileLoader();
        $fileLoader->registerExtParser('md', static function ($filepath) {
            return (object) array('foo' => 'bar');
        });
        $file = __DIR__ . '/trans/t/readme.md';
        $data = $fileLoader->load($file);
        self::assertSame(array(), $data);
        self::assertSame('Load(' . $file . ') did not return an array', $fileLoader->lastError);
    }

    public function testParseCsvNoHandle()
    {
        $method = new \ReflectionMethod('bdk\I18n\FileLoader', 'parseExtCsv');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        try {
            // under some circumstances, this will throw an exception (fileStreamWrapper??)
            $return = $method->invoke(null, __DIR__ . '/bogusFile.csv');
        } catch (\Exception $e) {
            $return = array();
        }
        self::assertSame(array(), $return);
    }

    public function testParseCsv()
    {
        $fileLoader = new FileLoader();
        $file = __DIR__ . '/trans/messages/en.csv';
        $data = $fileLoader->load($file);
        self::assertSame('{name} likes {what}', $data['user.likes']);
    }

    public function testParseIni()
    {
        $fileLoader = new FileLoader();
        $file = __DIR__ . '/trans/messages/en.ini';
        $data = $fileLoader->load($file);
        self::assertSame('{name} likes {what}', $data['user.likes']);
    }

    public function testParseJson()
    {
        $fileLoader = new FileLoader();
        $file = __DIR__ . '/trans/messages/en.json';
        $data = $fileLoader->load($file);
        self::assertSame('{name} likes {what}', $data['user.likes']);
    }

    public function testParsePhp()
    {
        $fileLoader = new FileLoader();
        $file = __DIR__ . '/trans/messages/en.php';
        $data = $fileLoader->load($file);
        self::assertSame('{name} likes {what}', $data['user.likes']);
    }
}
