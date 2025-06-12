<?php

$baseDir = \realpath(__DIR__ . '/..');
require $baseDir . '/vendor/autoload.php';

$fsHelper = new FsHelper($baseDir);

// move plugin files to root
$files = \glob($baseDir . '/src/Debug/FrameWork/WordPress/*');
foreach ($files as $filepath) {
    $new = $baseDir . '/' . \basename($filepath);
    $fsHelper->rename($filepath, $new);
}

// move src to vendor/bdk
$files = \glob($baseDir . '/src/*');
foreach ($files as $filepath) {
    $filepathNew = $baseDir . '/vendor/bdk/' . \basename($filepath);
    $fsHelper->rename($filepath, $filepathNew);
}
$fsHelper->unlink($baseDir . '/src');

// remove files we don't need for wordpress plugin
$files = [
    $baseDir . '/src/Debug/Framework',
];
foreach ($files as $filepath) {
    $fsHelper->unlink($filepath);
}

/**
 * Helper class
 */
class FsHelper
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
