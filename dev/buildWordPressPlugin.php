<?php

$baseDir = \realpath(__DIR__ . '/..');
require $baseDir . '/vendor/autoload.php';

$helper = new WpBuildHelper($baseDir);

$buildDir = $baseDir . '/wordpress-build';
$version = \bdk\Debug::VERSION;

$exportIgnore = $helper->parseGitAttributes($baseDir . '/.gitattributes');
$exportIgnore = \array_filter($exportIgnore, static function ($item) {
    return !empty($item['attributes']['export-ignore']);
});
$exportIgnore = \array_map(static function ($item) {
    return $item['pattern'];
}, $exportIgnore);
$gitIgnore = \array_merge(
    $helper->parseGitIgnore($baseDir . '/.gitignore'),
    $helper->parseGitIgnore($baseDir . '/.git/info/exclude')
);
$exclude = \array_merge($exportIgnore, $gitIgnore);
$exclude = \array_filter($exclude, static function ($pattern) {
    return \strpos($pattern, '/src/') === 0;
});
$exclude[] = '/*/.DS_Store';
$exclude = \array_map(static function ($pattern) use ($baseDir) {
    return $baseDir . $pattern;
}, $exclude);
\sort($exclude);

//
// Build WordPress Plugin package
//

$helper->output('Copy src/* to vendor/bdk');
$helper->copy($baseDir . '/src', $buildDir . '/vendor/bdk', $exclude);

$helper->output('Copy select vendor dirs to vendor');
$files = [
    '/vendor/bdk/http-message/src',
    '/vendor/bdk/http-message/LICENSE',
    '/vendor/bdk/http-message/README.md',
    '/vendor/jdorn/sql-formatter/lib',
    '/vendor/jdorn/sql-formatter/LICENSE.txt',
    '/vendor/jdorn/sql-formatter/README.txt',
    '/vendor/psr/http-message/src',
    '/vendor/psr/http-message/LICENSE',
    '/vendor/psr/http-message/README.md',
];
foreach ($files as $file) {
    $helper->copy($baseDir . $file, $buildDir . $file);
}

$helper->output('Copy Debug\'s LICENSE and README.md to vendor/bdk/Debug');
$files = [
    $baseDir . '/LICENSE',
    $baseDir . '/README.md',
];
foreach ($files as $filepath) {
    $filepathNew = $buildDir . '/vendor/bdk/Debug/' . \basename($filepath);
    $helper->copy($filepath, $filepathNew);
}

$helper->output('Move WordPress framework files to src/');
$helper->rename($buildDir . '/vendor/bdk/Debug/Framework/WordPress', $buildDir . '/src');
$helper->unlink($buildDir . '/src/assets');

$helper->output('Remove other Framework plugins');
$helper->unlink($buildDir . '/vendor/bdk/Debug/Framework');

$helper->output('Move WordPress plugin file to /');
$files = [
    $buildDir . '/src/debug-console-php.php',
    $buildDir . '/src/readme.txt',
];
foreach ($files as $filepath) {
    $filepathNew = $buildDir . '/' . \basename($filepath);
    $helper->rename($filepath, $filepathNew);
}

$helper->output('update debug-console-php.php');
$filepath = $buildDir . '/debug-console-php.php';
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
     * Copy file or directory
     *
     * @param string $src     Source path
     * @param string $dest    Destination path
     * @param array  $exclude array of fnmatch patterns to exclude
     *
     * @return void
     */
    public function copy($src, $dest, $exclude = [])
    {
        if (\is_dir($dest) && \file_exists($dest)) {
            $this->unlink($dest);
        }
        if (\is_dir($src)) {
            $fileperms = \fileperms($src);
            \mkdir($dest, $fileperms, true);
            $files = \scandir($src);
            foreach ($files as $file) {
                if (\in_array($file, ['.', '..'], true)) {
                    continue;
                }
                if ($this->testExclude($src . '/' . $file, $exclude)) {
                    continue;
                }
                $this->copy($src . '/' . $file, $dest . '/' . $file, $exclude);
            }
        } elseif (\file_exists($src) && $this->testExclude($src, $exclude) === false) {
            \set_error_handler(function ($errNo, $errStr) {
                $this->output($errStr, 'error');
            });
            \copy($src, $dest);
            \restore_error_handler();
        }
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
        $this->output(\sprintf('%s: %d edit(s) made', $filepathDebug, \count($replacements)));
        \file_put_contents($filepath, $contents);
    }

    /**
     * rename/move file or directory
     *
     * @param string $src  filepath
     * @param string $dest new filepath
     *
     * @return void
     */
    public function rename($src, $dest)
    {
        if (\is_dir($dest) && \file_exists($dest)) {
            $this->unlink($dest, false);
        }
        \set_error_handler(function ($errNo, $errStr) {
            $this->output($errStr, 'error');
        });
        \rename($src, $dest);
        \restore_error_handler();
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
        \is_dir($filepath)
            ? self::emptyAndRmDir($filepath)
            : \unlink($filepath);
    }

    /**
     * Parse .gitattributes file
     *
     * @param string $filepath Path to .gitattributes file
     *
     * @return array
     */
    public function parseGitAttributes($filepath)
    {
        if (\is_file($filepath) === false) {
            return [];
        }

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $attributes = [];

        foreach ($lines as $line) {
            // Ignore comments
            if (\strpos(\trim($line), '#') === 0) {
                continue;
            }

            // Split the line into parts by whitespace
            $parts = \preg_split('/\s+/', \trim($line));
            $pattern = \array_shift($parts);
            $fileAttributes = [];

            foreach ($parts as $attr) {
                if (\strpos($attr, '-') === 0) {
                    $fileAttributes[\substr($attr, 1)] = false; // Unset
                } elseif (\strpos($attr, '=') !== false) {
                    list($name, $value) = \explode('=', $attr, 2);
                    $fileAttributes[$name] = $value; // Set to a value
                } else {
                    $fileAttributes[$attr] = true; // Set
                }
            }

            $attributes[] = [
                'attributes' => $fileAttributes,
                'pattern' => $pattern,
            ];
        }
        return $attributes;
    }

    /**
     * Parse .gitignore file
     *
     * @param string $filepath Path to .gitignore file
     *
     * @return array
     */
    public function parseGitIgnore($filepath)
    {
        if (\is_file($filepath) === false) {
            return [];
        }
        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $ignorePatterns = [];
        foreach ($lines as $line) {
            $line = \trim($line);
            if (\strpos($line, '#') === 0) {
                // Ignore comments
                continue;
            }
            if ($line === '') {
                continue;
            }
            $ignorePatterns[] = $line;
        }
        return $ignorePatterns;
    }

    /**
     * Output message to console
     *
     * @param string $message message to output
     * @param string $level   log level
     *
     * @return void
     */
    public static function output($message, $level = 'info')
    {
        echo self::ansiColor($message, $level) . "\n";
    }

    /**
     * @param string $text  text to color
     * @param string $color color name
     *
     * @return string
     */
    private static function ansiColor($text, $color)
    {
        $colors = array(
            'emergency' => "\e[38;5;11;1;4m",
            'alert' => "\e[38;5;226m",
            'critical' => "\e[38;5;220;1m",
            'error' => "\e[38;5;220m",
            'warning' => "\e[38;5;214;40m",
            'notice' => "\e[38;5;208m",
            'info' => "\e[38;5;51m",
            'muted' => "\e[38;5;247m",
        );
        $colorReset = "\e[0m";
        if (!isset($colors[$color])) {
            return $text;
        }
        return $colors[$color] . $text . $colorReset;
    }

    /**
     * Remove all files in directory and remove directory
     *
     * @param string $dirPath directory path
     *
     * @return void
     */
    private function emptyAndRmDir($dirPath)
    {
        if (!\is_dir($dirPath)) {
            return;
        }
        if (\substr($dirPath, \strlen($dirPath) - 1, 1) !== '/') {
            $dirPath .= '/';
        }
        $files = \scandir($dirPath);
        foreach ($files as $file) {
            if (\in_array($file, ['.', '..'], true)) {
                continue;
            }
            $file = $dirPath . $file;
            if (\is_dir($file)) {
                self::emptyAndRmDir($file);
                continue;
            }
            \set_error_handler(function ($errNo, $errStr) {
                $this->output($errStr, 'error');
            });
            \unlink($file);
            \restore_error_handler();
        }
        \set_error_handler(function ($errNo, $errStr) {
            $this->output($errStr, 'error');
        });
        \rmdir($dirPath);
        \restore_error_handler();
    }

    /**
     * Test if file matches exclude pattern
     *
     * @param string   $filepath        filepath to test
     * @param string[] $excludePatterns array of fnmatch patterns
     *
     * @return bool
     */
    private function testExclude($filepath, $excludePatterns)
    {
        foreach ($excludePatterns as $pattern) {
            if (\fnmatch($pattern, $filepath)) {
                return true;
            }
        }
        return false;
    }
}
