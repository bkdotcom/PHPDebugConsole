<?php

$baseDir = \realpath(__DIR__ . '/..');
require $baseDir . '/vendor/autoload.php';

$helper = new WpBuildHelper($baseDir);
$version = \bdk\Debug::VERSION;

// move src/* to vendor/bdk
$files = \glob($baseDir . '/src/*');
foreach ($files as $filepath) {
    $filepathNew = $baseDir . '/vendor/bdk/' . \basename($filepath);
    $helper->rename($filepath, $filepathNew);
}

// move LICENSE and README.md to vendor/bdk/Debug
$files = [
    $baseDir . '/LICENSE',
    $baseDir . '/README.md',
];
foreach ($files as $filepath) {
    $filepathNew = $baseDir . '/vendor/bdk/Debug/' . \basename($filepath);
    $helper->rename($filepath, $filepathNew);
}

// move wordpress plugin files to root/src
$files = \glob($baseDir . '/vendor/bdk/Debug/FrameWork/WordPress/*');
foreach ($files as $filepath) {
    $filepathNew = $baseDir . '/src/' . \basename($filepath);
    $helper->rename($filepath, $filepathNew);
}

// move main plugin files to root
$files = [
    $baseDir . '/src/debug-console-php.php',
    $baseDir . '/src/readme.txt',
];
foreach ($files as $filepath) {
    $filepathNew = $baseDir . '/' . \basename($filepath);
    $helper->rename($filepath, $filepathNew);
}

// remove files we don't need for wordpress plugin
$files = [
    $baseDir . '/vendor/bdk/Debug/Framework',
];
foreach ($files as $filepath) {
    $helper->unlink($filepath);
}

// update debug-console-php.php
$filepath = $baseDir . '/debug-console-php.php';
$helper->edit($filepath, [
    ['/^(\s*\* Version: ).*$/m', '${1}' . $version],
    // ['/^(\$pathBase = ).*$/m', '$1__DIR__;'],
    // ['#\'/src/Debug/Autoloader.php\'#', '\'/vendor/bdk/Debug/Autoloader.php\''],
    // ['/^(\$autoloader->addPsr4\(.*?, )__DIR__(\);)/m', '$1\$pathBase . \'/src\'$2'],
    // ['#__DIR__ . \'/lang#', '$pathBase . \'/src/lang'],
]);

/**
 * Helper class
 */
class WpBuildHelper
{
    /** @var string */
    private $baseDir;

    /** @var bool */
    private $touchFileSystem = true;

    /**
     * Constructor
     *
     * @param string $baseDir base directory (removed from output paths)
     */
    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    /**
     * Search and replace in file
     *
     * @param string $filepath     Path to file
     * @param array  $replacements search => replace pairs
     *
     * @return void
     */
    public function edit($filepath, array $replacements)
    {
        $filepathDebug = \strpos($filepath, $this->baseDir) === 0
            ? './' . \substr($filepath, \strlen($this->baseDir) + 1)
            : $filepath;
        $contents = \file_get_contents($filepath);
        foreach ($replacements as $replacement) {
            list($search, $replace) = $replacement;
            $contents = \is_callable($replace)
                ? \preg_replace_callback($search, $replace, $contents)
                : \preg_replace($search, $replace, $contents);
        }
        echo \sprintf('edit %s - %d edits made', $filepathDebug, \count($replacements)) . "\n";
        if ($this->touchFileSystem) {
            \file_put_contents($filepath, $contents);
        }
    }

    /**
     * rename/move file or directory
     *
     * @param string $old filepath
     * @param string $new new filepath
     *
     * @return void
     */
    public function rename($old, $new)
    {
        $paths = \array_map(function ($path) {
            return \strpos($path, $this->baseDir) === 0
                ? './' . \substr($path, \strlen($this->baseDir) + 1)
                : $path;
        }, array(
            $old,
            $new,
        ));
        echo \sprintf('rename %s -> %s', $paths[0], $paths[1]) . "\n";
        if ($this->touchFileSystem) {
            \rename($old, $new);
        }
    }

    /**
     * Remove file or directory
     *
     * @param string $filepath filepath
     *
     * @return void
     */
    public function unlink($filepath)
    {
        $filepathDebug = \strpos($filepath, $this->baseDir) === 0
            ? './' . \substr($filepath, \strlen($this->baseDir) + 1)
            : $filepath;
        echo \sprintf('remove %s %s', \is_dir($filepath) ? 'folder' : 'file', $filepathDebug) . "\n";
        if ($this->touchFileSystem) {
            \is_dir($filepath)
                ? self::emptyAndRmDir($filepath)
                : \unlink($filepath);
        }
    }

    /**
     * Remove all files in directory and remove directory
     *
     * @param string $dirPath directory path
     *
     * @return void
     */
    private static function emptyAndRmDir($dirPath)
    {
        if (!\is_dir($dirPath)) {
            return;
        }
        if (\substr($dirPath, \strlen($dirPath) - 1, 1) !== '/') {
            $dirPath .= '/';
        }
        $files = \glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            \is_dir($file)
                ? self::emptyAndRmDir($file)
                : \unlink($file);
        }
        \rmdir($dirPath);
    }
}
